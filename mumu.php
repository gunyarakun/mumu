<?php
// mumu (c) Brazil, Inc.
// originally developed by Tasuku SUENAGA a.k.a. gunyarakun
/*
Copyright (c) 2005, the Lawrence Journal-World
Copyright (c) 2007, Brazil, Inc.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:

  1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

  2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

  3. Neither the name of Django nor the names of its contributors may be used
     to endorse or promote products derived from this software without
     specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
*/

// ●記法

// 変数
// {{ 変数名 }}             : 変数名で置換

// ブロック
// {% include "filename" %} : テンプレートのインクルード
// {% extends "filename" %} : テンプレートの拡張
// {% block blockname %}    : ブロックの始まり
// {% endblock %}           : ブロックの終わり
// {% for item in items %}  : 変数itemsからitemを取り出す
// {% endfor %}             : forの終わり
// {% cycle val1,val2 %}    : forループの中でval1,val2を交互に出す(表で行ごと背景色とか)
// {% if cond %} {% else %} : cond条件が満たされたところだけを出力
// {% endif %}              : ifの終わり
// {% debug %}              : テンプレートに渡された情報をダンプする
// {% now "format" %}       : 現在の日付を指定フォーマットで出力します
// {% filter fil1|fil2 %}   : ブロック内コンテンツをフィルタにかけます
// {% endfilter %}          : filterの終わり
// {# comment #}            : コメント

// パイプ
// {{ 変数名|パイプ1|パイプ2 }} : 変数をフィルタして出力する
// |addslashes                  : \を\\に。JavaScriptに値渡したりとかに便利かな
// |length                      : 配列の長さ
// |escape                      : %<>"'のエスケープ
// |stringformat:"format"       : 指定したフォーマットで値をフォーマット
// |urlencode                   : urlエンコード
// |linebreaksbr                : 改行を<br />に変換

// 特殊な変数
// forloop.counter     : 現在のループ回数番号 (1 から数えたもの)
// forloop.counter0    : 現在のループ回数番号 (0 から数えたもの)
// forloop.revcounter  : 末尾から数えたループ回数番号 (1 から数えたもの)
// forloop.revcounter0 : 末尾から数えたループ回数番号 (0 から数えたもの)
// forloop.first       : 最初のループであれば true になります
// forloop.last        : 最後のループであれば true になります
// forloop.parentloop  : 入れ子のループの場合、一つ上のループを表します
// block.super         : 親テンプレートのblockの中身を取り出す。内容を追加する場合に便利。

// 実装メモ
// find_endtagsは見つけ次第parseもして、$spos動かしてもいいんじゃね！？
// FIXMEを全部直すように。
// キャッシュ機構とか欲しいね。パース済みの構造をシリアライズする？

class MuMuContext {
  // テンプレートに当てはめる値の情報を保持するクラス
  private $dicts;
  const VARIABLE_ATTRIBUTE_SEPARATOR = '.';
  function __construct($dict = array()) {
    $this->dicts = array($dict);
  }
  // ドット連結表現から値を取り出す
  function resolve($expr) {
    $bits = explode(self::VARIABLE_ATTRIBUTE_SEPARATOR, $expr);
    $current = $this->get($bits[0]);
    array_shift($bits);
    while ($bits) {
      if (is_array($current) && array_key_exists($bits[0], $current)) {
        // arrayからの辞書引き(配列キーもコレと一緒)
        $current = $current[$bits[0]];
      } elseif (method_exists($current, $bits[0])) {
        // メソッドコール
        if (($current = call_user_func(array($current, $bits[0]))) === FALSE) {
          return 'method call error';
        }
      } else {
        return 'resolve error';
      }
      array_shift($bits);
    }
    return $current;
  }
  function has_key($key) {
    foreach ($this->dicts as $dict) {
      if (array_key_exists($key, $dict)) {
        return True;
      }
    }
    return False;
  }
  function get($key) {
    foreach ($this->dicts as $dict) {
      if (array_key_exists($key, $dict)) {
        return $dict[$key];
      }
    }
    return null;
  }
  function set($key, $value) {
    $this->dicts[0][$key] = $value;
  }
  function push() {
    array_unshift($this->dicts, array());
  }
  function pop() {
    array_shift($this->dicts);
  }
  function update($other_array) {
    array_unshift($this->dicts, $other_array);
  }
}

class MuMuNode {
  public function _render() {
    return '';
  }
}

class MuMuNodeList {
  private $nodes;
  function __construct() {
    $this->nodes = array();
  }
  public function _render($context) {
    $bits = array();
    foreach ($this->nodes as $node) {
      array_push($bits, $node->_render($context));
    }
    return implode('', $bits);
  }
  public function push($node) {
    array_push($this->nodes, $node);
  }
}

// 1つのテンプレートをパースしたもの。
class MuMuFile extends GTNode {
  public $nodelist;        // ファイルをパースしたNodeList
  private $block_dict;      // nodelistの中にあるblock名 => GTBlockNode(の参照)
  private $parent_tfile;    // extendsがある場合の親テンプレート
  function __construct($nodelist, $block_dict, $parentPath = false) {
    $this->nodelist = $nodelist;
    $this->block_dict = $block_dict;
    if ($parentPath) {
      if (($this->parent_tfile = GTParser::parse_from_file($parentPath)) === FALSE) {
        // TODO: エラー起こしたテンプレート名を安全に教えてあげる
      }
    }
  }
  public function render($raw_context) {
    return $this->_render(new MuMuContext($raw_context));
  }
  public function _render($context) {
    if ($this->parent_tfile) {
      foreach ($this->block_dict as $blockname => $blocknode) {
        if (($parent_block = $this->parent_tfile->get_block($blockname)) === FALSE) {
          if ($this->parent_tfile->is_child()) {
            $this->parent_tfile->append_block(&$blocknode);
          }
        } else {
          $parent_block->parent = &$blocknode->parent;
          $parent_block->add_parent(&$parent_block->nodelist);
          $parent_block->nodelist = &$blocknode->nodelist;
        }
      }
      return $this->parent_tfile->_render($context);
    } else {
      return $this->nodelist->_render($context);
    }
  }
  public function get_block($blockname) {
    if (array_key_exists($blockname, $this->block_dict)) {
      return $this->block_dict[$blockname];
    } else {
      return false;
    }
  }
  public function append_block($blocknode) {
    // 孫で定義されたブロック名で、親には定義されていないが、
    // 親の親には定義されている場合のため、
    // 孫から親にブロックを移す
    $this->nodelist->push($blocknode);
    $this->block_dict[$blocknode->name] = &$blocknode;
  }
  public function is_child() {
    return ($parent_tfile !== FALSE);
  }
}

class MuMuTextNode extends GTNode {
  private $text;
  function __construct($text) {
    $this->text = $text;
  }
  public function _render($context) {
    return $this->text;
  }
}

class MuMuVariableNode extends GTNode {
  private $filter_expression;
  function __construct($filter_expression) {
    $this->filter_expression = $filter_expression;
  }
  public function _render($context) {
    return $this->filter_expression->resolve($context);
  }
}

class MuMuIncludeNode extends GTNode {
  private $tplfile;
  function __construct($includePath) {
    // FIXME: セキュリティチェック、無限ループチェック
    if (($this->tplfile = GTParser::parse_from_file($includePath)) === FALSE) {
      // TODO: エラー起こしたテンプレート名を安全に教えてあげる
      $this->tplfile = new MuMuTextNode('include error');
    }
  }
  public function _render($context) {
    return $this->tplfile->_render($context);
  }
}

class MuMuBlockNode extends GTNode {
  public $name;
  public $nodelist;
  public $parent;

  private $context; // for block.super

  function __construct($name, $nodelist, $parent = Null) {
    $this->name = $name;
    $this->nodelist = $nodelist;
    $this->parent = $parent;
  }
  public function _render($context) {
    $context->push();
    $this->context = &$context;
    $context->set('block', $this); // block.super用
    $res = $this->nodelist->_render($context);
    $context->pop();
    return $res;
  }
  public function super() {
    if ($this->parent) {
      return $this->parent->_render($this->context);
    }
    return '';
  }
  public function add_parent($nodelist) {
    if ($this->parent) {
      $this->parent->add_parent($nodelist);
    } else {
      $this->parent = new MuMuBlockNode($this->name, $this->nodelist);
    }
  }
}

class MuMuCycleNode extends GTNode {
  private $cyclevars;
  private $cyclevars_len;
  private $variable_name;

  function __construct($cyclevars, $variable_name = null) {
    $this->cyclevars = $cyclevars;
    $this->cyclevars_len = count($cyclevars);
    $this->counter = -1;
    $this->variable_name = $variable_name;
  }

  function _render($context) {
    $this->counter++;
    $value = $this->cyclevars[$this->counter % $this->cyclevars_len];
    if ($this->variable_name) {
      $context.set($this->variable_name, $value);
    }
    return $value;
  }
}

class MuMuDebugNode extends GTNode {
  function _render($context) {
    ob_start();
    echo "Context\n";
    var_dump($context);
    echo "\$_SERVER\n";
    var_dump($_SERVER);
    echo "\$_GET\n";
    var_dump($_GET);
    echo "\$_POST\n";
    var_dump($_POST);
    echo "\$_COOKIE\n";
    var_dump($_COOKIE);
    $output = ob_get_contents();
    ob_end_clean();
    return $output;
  }
}

class MuMuFilterNode extends GTNode {
  private $filter_expr;
  private $nodelist;
  function __construct($filter_expr, $nodelist) {
    $this->filter_expr = $filter_expr;
    $this->nodelist = $nodelist;
  }
  function _render($context) {
    $output = $this->nodelist->_render($context);
    $context->update(array('var' => $output));
    $filtered = $this->filter_expr->resolve($context);
    $context->pop();
    return $filtered;
  }
}

class MuMuForNode extends GTNode {
  private $loopvar;
  private $sequence;
  private $reversed;
  private $nodelist_loop;

  function __construct($loopvar, $sequence, $reversed, $nodelist_loop) {
    $this->loopvar = $loopvar;
    $this->sequence = $sequence;
    $this->reversed = $reversed;
    $this->nodelist_loop = $nodelist_loop;
  }
  function _render($context) {
    if ($context->has_key('forloop')) {
      $parentloop = $context->get('forloop');
    } else {
      $parentloop = new MuMuContext();
    }
    $context->push();
    if (!($values = $context->resolve($this->sequence))) {
      $values = array();
    }
    if (!is_array($values)) {
      $values = array($value);
    }
    $len_values = count($values);
    // FIXME: $this->reversed
    $rnodelist = array();
    for ($i = 0; $i < $len_values; $i++) {
      $context->set('forloop', array(
        'counter0' => $i,
        'counter' => $i + 1,
        'revcounter' => $len_values - $i,
        'revcounter0' => $len_values - $i - 1,
        'first' => ($i == 0),
        'last' => ($i == ($len_values - 1)),
        'parentloop' => $parentloop
      ));
      $context->set($this->loopvar, $values[$i]);
      array_push($rnodelist, $this->nodelist_loop->_render($context));
    }
    $context->pop();
    return implode('', $rnodelist);
  }
}

class MuMuIfNode extends GTNode {
  private $bool_exprs;
  private $nodelist_true;
  private $nodelist_false;
  private $link_type;

  const LINKTYPE_AND = 0;
  const LINKTYPE_OR  = 1;

  function __construct($bool_exprs, $nodelist_true, $nodelist_false, $link_type) {
    $this->bool_exprs = $bool_exprs;
    $this->nodelist_true = $nodelist_true;
    $this->nodelist_false = $nodelist_false;
    $this->link_type = $link_type;
  }
  function _render($context) {
    if ($this->link_type == self::LINKTYPE_OR) {
      foreach ($this->bool_exprs as $be) {
        list($ifnot, $bool_expr) = $be;
        $value = $context->resolve($bool_expr);
        if (($value && !$ifnot) || ($ifnot && !$value)) {
          return $this->nodelist_true->_render($context);
        }
      }
      return $this->nodelist_false->_render($context);
    } else { // self::LINKTYPE_AND
      foreach ($this->bool_exprs as $be) {
        list($ifnot, $bool_expr) = $be;
        $value = $context->resolve($bool_expr);
        if (!(($value && !$ifnot) || ($ifnot && !$value))) {
          return $this->nodelist_false->_render($context);
        }
      }
      return $this->nodelist_true->_render($context);
    }
  }
}

class MuMuNowNode extends GTNode {
  private $format_string;
  function __construct($format_string) {
    $this->format_string = $format_string;
  }
  public function _render($context) {
    return date($this->format_string);
  }
}

class MuMuUnknownNode extends GTNode {
  public function _render($context) {
    return 'unknown...';
  }
}

class MuMuFilterExpression {
  private $var;
  private $filters;

  function __construct($token) {
    // $token = 'variable|default:"Default value"|date:"Y-m-d"'
    // ってのがあったら、
    // $this->var = 'variable'
    // $this->filters = 'array(array('default, 'Default value'), array('date', 'Y-m-d'))'
    // ってする。
    // Djangoのは_で始まったらいけないらしい。

    $fils = GTParser::smart_split(trim($token), '|', False, True);
    $this->var = array_shift($fils);
    $this->filters = array();
    foreach ($fils as $fil) {
      $f = GTParser::smart_split($fil, ':', True, False);
      array_push($this->filters, $f);
    }
  }

  // TODO: support ignore_failures
  public function resolve($context) {
    // evalとかcall_user_func_arrayせずにswitch-caseでdispatch、めんどいから

    $val = $context->resolve($this->var);
    foreach ($this->filters as $fil) {
      // TODO: 引数チェック
      switch ($fil[0]) {
        case 'addslashes':
          $val = addslashes($val);
          break;
        case 'length':
          # arrayはcount、stringはstrlen
          if (is_array($val)) {
            $val = count($val);
          } else if (is_string($val)) {
            $val = strlen($val);
          }
          break;
        case 'escape':
          $val = htmlspecialchars($val);
          break;
        case 'stringformat':
          // $fil[1]にヤバい文字入らないように気をつけるんだよ
          $val = sprintf($fil[1], $val);
          break;
        case 'urlencode':
          $val = urlencode($val);
          break;
        case 'linebreaksbr':
          $val = nl2br($val);
          break;
        default:
          // どんなフィルタ名がマズかったか教えてあげたいけど、
          // 出力先で意味持った文字列入ってるとマズいから不親切に

          // TODO: フィルタ名をアルファベットと_とかのみにフィルタしておく
          $val = 'unknown filter specified';
      }
    }
    return $val;
  }
}

class MuMuParser {
  private $template;             // パース前のテンプレート文字列
  private $template_len;         // テンプレート文字列の長さ
  private $errorStr;             // エラー文字列
  private $block_dict = array(); // blockの名前 => blockへの参照
  private $extends = false;      // extendsの場合のファイル名

  # template syntax constants
  const FILTER_SEPARATOR = '|';
  const FILTER_ARGUMENT_SEPARATOR = ':';
  const VARIABLE_ATTRIBUTE_SEPARATOR = '.';
  const BLOCK_TAG_START = '{%';
  const BLOCK_TAG_END = '%}';
  const VARIABLE_TAG_START = '{{';
  const VARIABLE_TAG_END = '}}';
  const COMMENT_TAG_START = '{#';
  const COMMENT_TAG_END = '#}';
  const SINGLE_BRACE_START = '{';
  const SINGLE_BRACE_END = '}';
  // FIXME: タグの長さである定数2がパーサの中に散らばってます

  function __construct($template) {
    $this->template = $template;
    $this->template_len = strlen($template);
  }

  // "や'でクオートされたものを除いてスペースで分割
  // "や'内で"'を使う場合には、\"\'とする。
  // 素直に正規表現で書けばよかったか、まあいっか。
  // マルチバイトセーフなデリミタを使うように気をつけるんだよ
  // $decode : quote中の\でのエスケープを解釈して展開するかどうか
  // $quote  : quote文字そのものも出力するかどうか
  static public function smart_split($text, $delimiter = ' ', $decode = True, $quote = True) {
    $epos = strlen($text);
    $ret = array();
    $mode = 'n';  // 'n': not quoted, 'd': in ", 'q': in '
    for ($spos = 0; $spos < $epos; $spos++) {
      $a = $text[$spos];
      switch ($a) {
        case '\\':
          // 何度もsmart_splitする場合は$decodeをFalseにしておく(ex. filter)
          if (!$decode && $mode != 'n') {
            $buf .= '\\';
          }
          switch ($mode) {
            case 'd':
              if ($text[$spos + 1] == '"') {
                $buf .= '"';
                $spos += 1;
              } else if ($text[$spos + 1] == '\\') {
                $buf .= '\\';
                $spos += 1;
              } else {
                $buf .= $a;
              }
              break;
            case 'q':
              if ($text[$spos + 1] == "'") {
                $buf .= "'";
                $spos += 1;
              } else if ($text[$spos + 1] == '\\') {
                $buf .= '\\';
                $spos += 1;
              } else {
                $buf .= $a;
              }
              break;
            default: // 'n'
              $buf.= '\\';
          }
          break;
        case "'":
          if ($quote) {
            $buf .= "'";
          }
          switch ($mode) {
            case 'd':
              break;
            case 'q':
              $mode = 'n';
              break;
            default:
              $mode = 'q';
              break;
          }
          break;
        case '"':
          if ($quote) {
            $buf .= '"';
          }
          switch ($mode) {
            case 'd':
              $mode = 'n';
              break;
            case 'q':
              break;
            default:
              $mode = 'd';
              break;
          }
          break;
        case $delimiter:
          switch ($mode) {
            case 'd':
            case 'q':
              $buf .= $delimiter;
              break;
            default:
              if ($buf != '') {
                array_push($ret, $buf);
                $buf = '';
              }
              break;
          }
          break;
        default:
          $buf .= $a;
          break;
      }
    }
    if ($mode == 'n' && $buf != '') {
      array_push($ret, $buf);
    }
    return $ret;
  }

  // 終了タグ(#}とか)を探して、その位置を返す
  private function find_closetag(&$spos, $closetag) {
    if (($fpos = strpos($this->template, $closetag, $spos)) === FALSE) {
      $this->errorStr = "タグが閉じられてないようです($closetagが見つかりません)。";
      return FALSE;
    }
    return $fpos;
  }

  // {% %}の中身をパースして、GTNodeを返す。
  // extendsは頭に書かないといけない
  private function parse_block(&$spos) {
    $spos += 2;
    if (($lpos = $this->find_closetag($spos, self::BLOCK_TAG_END)) === FALSE) {
      return FALSE;
    }
    $in = $this->smart_split(substr($this->template, $spos, $lpos - $spos));
    switch ($in[0]) {
      // TODO: 引数の数チェックを全般
      case 'extends':
        if ($this->extends !== FALSE) {
          $this->errorStr = 'extendsは１つだけしか指定できません。';
          return FALSE;
        }
        if (count($in) != 2) {
          $this->errorStr = 'extendsのパラメータを指定してください';
          return FALSE;
        }
        $param = explode('"', $in[1]);
        if (count($param) != 3) {
          // Djangoは変数もOKだけどね
          $this->errorStr = 'extendsのパラメータはファイル名のみです';
          return FALSE;
        }
        $this->extends = $param[1];
        $spos = $lpos + 2;
        break;
      case 'include':
        if (count($in) != 2) {
          $this->errorStr = 'includeのパラメータを指定してください';
          return FALSE;
        }
        $param = explode('"', $in[1]);
        if (count($param) != 3) {
          // Djangoは変数もOKだけどね
          $this->errorStr = 'includeのパラメータはファイル名のみです';
          return FALSE;
        }
        $node = new MuMuIncludeNode($param[1]);
        $spos = $lpos + 2;
        break;
      case 'block': // endblock
        // TODO: check params
        // TODO: filter block name
        $blockname = $in[1];
        if (array_key_exists($blockname, $this->block_dict)) {
          // TODO: filtered block name print
          $this->errorStr = '同じ名前のblockは１つだけしか指定できません。';
          return FALSE;
        }
        $spos = $lpos + 2;
        list($nodelist) = $this->_parse($spos, array('endblock'));
        $node = new MuMuBlockNode($blockname, $nodelist);
        $this->block_dict[$blockname] = &$node; // reference
        break;
      case 'for': // endfor
        // $in[1] = $loopvar, $in[2] = 'in', $in[3] = $sequence, $in[4] = 'reversed'
        if ((count($in) != 4 && count($in) != 5) || $in[2] != 'in') {
          $this->errorStr = 'forのパラメータの数または書式が不正です';
          return FALSE;
        }
        if (count($in) == 5) {
          if ($in[4] == 'reversed') {
            $reversed = True;
          } else {
            $this->errorStr = 'forの5つめのパラメータはreversedのみ指定できます。';
            return FALSE;
          }
        } else {
          $reversed = False;
        }
        $spos = $lpos + 2;
        list($nodelist) = $this->_parse($spos, array('endfor'));
        $node = new MuMuForNode($in[1], $in[3], $reversed, $nodelist);
        break;
      case 'cycle':
        // TODO: implement namedCycleNodes
        if (count($in) != 2) {
          $this->errorStr = 'cycleにはパラメータが１つ必要です。';
          return FALSE;
        }
        $cyclevars = explode(',', $in[1]);
        if (count($cyclevars) == 0) {
          $this->errorStr = 'cycleには,で区切られた文字列が必要です。';
          return FALSE;
        }
        $node = new MuMuCycleNode($cyclevars);
        $spos = $lpos + 2;
        break;
      case 'if': // else, endif
        array_shift($in);
        if (count($in) < 1) {
          $this->errorStr = 'ifのパラメータがありません。';
          return FALSE;
        }
        $bitstr = implode(' ', $in);
        $boolpairs = explode(' and ', $bitstr);
        $boolvars = array();
        if (count($boolpairs) == 1) {
          $link_type = GTIfNode::LINKTYPE_OR;
          $boolpairs = explode(' or ', $bitstr);
        } else {
          $link_type = GTIfNode::LINKTYPE_AND;
          if (in_array(' or ', $bitstr)) {
            $this->errorStr = 'ifでandとorを混ぜることができません。';
            return FALSE;
          }
        }
        foreach ($boolpairs as $boolpair) {
          // handle 'not'
          if (strpos($boolpair, ' ') !== FALSE) {
            // TODO: error handling
            list($not, $boolvar) = explode(' ', $boolpair);
            if ($not != 'not') {
              $this->errorStr = 'ifでnotが入るべきところに別のものが入っています。';
              return FALSE;
            }
            array_push($boolvars, array(True, $boolvar));
          } else {
            array_push($boolvars, array(False, $boolpair));
          }
        }
        $spos = $lpos + 2;
        list($nodelist_true, $nexttag) =
          $this->_parse($spos, array('else', 'endif'));
        if ($nexttag == 'else') {
          list($nodelist_false) = $this->_parse($spos, array('endif'));
        } else {
          $nodelist_false = new MuMuNodeList();
        }
        $node = new MuMuIfNode($boolvars, $nodelist_true, $nodelist_false, $link_type);
        break;
      case 'debug':
        $node = new MuMuDebugNode();
        $spos = $lpos + 2;
        break;
      case 'now':
        if (count($in) != 2) {
          $this->errorStr = 'nowは書式文字列が必要です';
          return FALSE;
        }
        $param = explode('"', $in[1]);
        if (count($param) != 3) {
          $this->errorStr = 'nowの書式文字列は"でくくってください';
          return FALSE;
        }
        $node = new MuMuNowNode($param[1]);
        $spos = $lpos + 2;
        break;
      case 'filter': // endfilter
        if (count($in) != 2) {
          $this->errorStr = 'filterのパラメータがありません。';
          return FALSE;
        }
        $spos = $lpos + 2;
        $filter_expr = new MuMuFilterExpression('var|'. $in[1]);
        list($nodelist) = $this->_parse($spos, array('endfilter'));
        $node = new MuMuFilterNode($filter_expr, $nodelist);
        break;
      case 'endblock':
      case 'else':
      case 'endif':
      case 'endfor':
      case 'endfilter':
        $node = $in[0]; // raw string
        $spos = $lpos + 2;
        break;
      default:
        $node = new MuMuUnknownNode();
        $spos = $lpos + 2;
        break;
    }
    return $node;
  }

  private function parse_variable(&$spos) {
    $spos += 2;
    if (($lpos = $this->find_closetag($spos, self::VARIABLE_TAG_END)) === FALSE) {
      return FALSE;
    }
    // TODO: handle empty {{ }}
    $fil = new MuMuFilterExpression(substr($this->template, $spos, $lpos - $spos));
    $node = new MuMuVariableNode($fil);
    $spos = $lpos + 2;
    return $node;
  }

  private function parse_comment(&$spos) {
    $spos += 2;
    if (($lpos = $this->find_closetag($spos, self::COMMENT_TAG_END)) === FALSE) {
      return FALSE;
    }
    $spos = $lpos + 2;
  }

  static public function parse_from_file($templatePath) {
    if (($t = file_get_contents($templatePath)) === FALSE) {
      $this->errorStr = 'ファイルが開けません。';
      return FALSE;
    }
    $p = new MuMuParser($t);
    $spos = 0;
    list($nl) = $p->_parse($spos, array());
    return new MuMuFile($nl, $p->block_dict, $p->extends);
  }

  static public function parse($templateStr) {
    $p = new MuMuParser($templateStr);
    $spos = 0;
    list($nl) = $p->_parse($spos, array());
    return new MuMuFile($nl, $p->block_dict);
  }

  private function add_textnode(&$nodelist, &$tspos, &$epos) {
    if ($tspos < $epos) {
      $nodelist->push(new MuMuTextNode(
        substr($this->template, $tspos, $epos - $tspos)));
    }
  }

  private function _parse(&$spos, $parse_until) {
    $nl = new MuMuNodeList();
    $tspos = $spos;
    while (true) {
      $spos = strpos($this->template, self::SINGLE_BRACE_START, $spos);
      if ($spos === FALSE) {
        $this->add_textnode($nl, $tspos, $this->template_len);
        return array($nl, null);
      }
      switch (substr($this->template, $spos, 2)) {
        case self::BLOCK_TAG_START:
          $this->add_textnode($nl, $tspos, $spos);
          if (($node = $this->parse_block($spos)) === FALSE) {
            return FALSE;
          }
          $tspos = $spos;
          if (is_string($node)) {
            // close tag
            if (in_array($node, $parse_until)) {
              return array($nl, $node);
            } else {
              // invalid close tag
            }
          } else {
            $nl->push($node);
          }
          break;
        case self::VARIABLE_TAG_START:
          $this->add_textnode($nl, $tspos, $spos);
          if (($node = $this->parse_variable($spos)) === FALSE) {
            return FALSE;
          }
          $nl->push($node);
          $tspos = $spos;
          break;
        case self::COMMENT_TAG_START:
          $this->add_textnode($nl, $tspos, $spos);
          if (($node = $this->parse_comment($spos)) === FALSE) {
            return FALSE;
          }
          $nl->push($node);
          $tspos = $spos;
          break;
        default:
          // { only
          $spos += 1;
      }
    }
  }
}
?>
