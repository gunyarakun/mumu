<?php
// mumu the template engine (c) 2007- Brazil, Inc.
// originally developed by Tasuku SUENAGA a.k.a. gunyarakun
/*
Copyright (c) 2005, the Lawrence Journal-World
Copyright (c) 2007-, Brazil, Inc.
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
// |date:"format"               : 指定したフォーマットで日付をフォーマット
// |join:"str"                  : strをはさんで配列を連結

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
// FIXMEを全部直すように。
// キャッシュ機構とか欲しいね。パース済みの構造をシリアライズする？

class MuUtil {
  public static function getpath($basepath, $path) {
    $o = getcwd();
    chdir(dirname($basepath));
    $r = realpath($path);
    chdir($o);
    return $r;
  }
}

class MuContext {
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

class MuNode {
  public function _render() {
    return '';
  }
}

class MuNodeList {
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

class MuErrorNode extends MuNode {
  private $errorCode;
  private $filename;
  private $linenumber;
  // TODO: php5 don't support array as const
  private static $errorMsg = array(
    'without_closetag_tag' => 'Cannot find %} !', // TODO: remove const
    'without_closetag_variable' => 'Cannot find }} !',
    'without_closetag_comment' => 'Cannot find #} !',
    'invalidfilename_extends_tag' => 'Invalid filename specified with extends tag',
    'invalidfilename_include_tag' => 'Invalid filename specified with include tag',
    'multiple_extends_tag' => 'Only 1 extends tag are allowed to be specified',
    'numofparam_extends_tag' => 'Number of parameters are invalid to extends tag',
    'invalidparam_extends_tag' => 'Invalid parameter(s) specified to extends tag',
    'numofparam_include_tag' => 'Number of parameters are invalid to include tag',
    'invalidparam_include_tag' => 'Invalid parameter(s) specified to include tag',
    'multiple_block_tag' => 'Only 1 block tag can exists as the same name',
    'numofparam_for_tag' => 'Number of parameters are invalid to for tag',
    'invalidparam_for_tag' => 'Invalid parameter(s) specified to for tag',
    'numofparam_cycle_tag' => 'Number of parameters are invalid to cycle tag',
    'invalidparam_cycle_tag' => 'Invalid parameter(s) specified to cycle tag',
    'numofparam_if_tag' => 'Number of parameters are invalid to if tag',
    'andormixed_if_tag' => 'In if condition and/or is not allowed to be mixed',
    'invalidparam_if_tag' => 'Invalid parameter(s) specified to if tag',
    'numofparam_now_tag' => 'Number of parameters are invalid to now tag',
    'invalidparam_now_tag' => 'Invalid parameter(s) specified to now tag',
    'numofparam_filter_tag' => 'Number of parameters are invalid to filter tag',
    'invalidparam_filter_tag' => 'Invalid parameter(s) specified to filter tag',
    'invalidparam_filter_variable' => 'Invalid filter name',
    'unknown_tag' => 'Unknown tag is specified',
    'unknown' => 'Unknown error. Maybe bugs in MuMu'
  );
  function __construct($errorCode, $filename, $linenumber) {
    if (array_key_exists($errorCode, self::$errorMsg)) {
      $this->errorCode = $errorCode;
    } else {
      $this->errorCode = 'unknown';
    }
    $this->filename = $filename;
    $this->linenumber = $linenumber;
  }
  public function _render($context) {
    return 'file: '. $this->filename .' line: '. $this->linenumber .' '.
           self::$errorMsg[$this->errorCode];
  }
}

// 1つのテンプレートをパースしたもの。
class MuFile extends MuNode {
  public $nodelist;        // ファイルをパースしたNodeList
  private $block_dict;      // nodelistの中にあるblock名 => MuBlockNode(の参照)
  private $parent_tfile;    // extendsがある場合の親テンプレート
  function __construct($nodelist, $block_dict, $parentPath = null, $path = null) {
    $this->nodelist = $nodelist;
    $this->block_dict = $block_dict;
    if ($parentPath && $path) {
      $epath = MuUtil::getpath($path, $parentPath);
      if (($this->parent_tfile = MuParser::parse_from_file($epath)) === FALSE) {
        // TODO: エラー起こしたテンプレート名を安全に教えてあげる
      }
    }
  }
  public function render($raw_context) {
    return $this->_render(new MuContext($raw_context));
  }
  public function _render($context) {
    if ($this->parent_tfile) {
      foreach ($this->block_dict as $blockname => $blocknode) {
        if (($parent_block = $this->parent_tfile->get_block($blockname)) === FALSE) {
          if ($this->parent_tfile->is_child()) {
            $this->parent_tfile->append_block($blocknode);
          }
        } else {
          $parent_block->parent = $blocknode->parent;
          $parent_block->add_parent($parent_block->nodelist);
          $parent_block->nodelist = $blocknode->nodelist;
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
    $this->block_dict[$blocknode->name] = $blocknode;
  }
  public function is_child() {
    return ($parent_tfile !== FALSE);
  }
}

class MuTextNode extends MuNode {
  private $text;
  function __construct($text) {
    $this->text = $text;
  }
  public function _render($context) {
    return $this->text;
  }
}

class MuVariableNode extends MuNode {
  private $filter_expression;
  function __construct($filter_expression) {
    $this->filter_expression = $filter_expression;
  }
  public function _render($context) {
    return $this->filter_expression->resolve($context);
  }
}

class MuIncludeNode extends MuNode {
  private $tplfile;
  function __construct($includePath) {
    // FIXME: セキュリティチェック、無限ループチェック
    if (($this->tplfile = MuParser::parse_from_file($includePath)) === FALSE) {
      // TODO: エラー起こしたテンプレート名を安全に教えてあげる
      $this->tplfile = $this->make_errornode('invalidfilename_include');
    }
  }
  public function _render($context) {
    return $this->tplfile->_render($context);
  }
}

class MuBlockNode extends MuNode {
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
    $this->context = $context;
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
      $this->parent = new MuBlockNode($this->name, $this->nodelist);
    }
  }
}

class MuCycleNode extends MuNode {
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

class MuDebugNode extends MuNode {
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

class MuFilterNode extends MuNode {
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

class MuForNode extends MuNode {
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
      $parentloop = new MuContext();
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

class MuIfNode extends MuNode {
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

class MuNowNode extends MuNode {
  private $format_string;
  function __construct($format_string) {
    $this->format_string = $format_string;
  }
  public function _render($context) {
    return date($this->format_string);
  }
}

class MuFilterExpression {
  private $var;
  private $filters;

  // TODO: php5 don't support array as const...
  private static $valid_filternames = array (
    'addslashes',
    'length',
    'escape',
    'stringformat',
    'urlencode',
    'linebreaksbr',
    'date',
    'join',
  );

  function __construct($token) {
    // $token = 'variable|default:"Default value"|date:"Y-m-d"'
    // ってのがあったら、
    // $this->var = 'variable'
    // $this->filters = 'array(array('default, 'Default value'), array('date', 'Y-m-d'))'
    // ってする。
    // Djangoのは_で始まったらいけないらしい。

    $fils = MuParser::smart_split(trim($token), '|', False, True);
    $this->var = array_shift($fils);
    $this->filters = array();
    foreach ($fils as $fil) {
      $f = MuParser::smart_split($fil, ':', True, False);
      if (in_array($f[0], self::$valid_filternames)) {
        array_push($this->filters, $f);
      }
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
        case 'date':
          $val = $val instanceof DateTime ? $val : new DateTime($val);
          $val = $val->format($fil[1]);
          break;
        case 'join':
          if (is_array($val)) {
            $val = implode($fil[1], $val);
          }
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

class MuParser {
  private $template;             // パース前のテンプレート文字列
  private $template_len;         // テンプレート文字列の長さ
  public $templatePath;         // テンプレートのパス(あれば)
  private $block_dict = array(); // blockの名前 => blockへの参照
  private $extends = false;      // extendsの場合のファイル名
  private $spos = 0;             // 現在パース中の位置

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
    $buf = '';
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
  private function find_closetag($closetag) {
    if (($fpos = strpos($this->template, $closetag, $this->spos)) === FALSE) {
      return FALSE;
    }
    return $fpos;
  }

  private function make_errornode($errorCode) {
    // from $spos to linenumber
    // TODO: count on parsing
    $c = substr($this->template, 0, $this->spos);
    $ln = 1;
    for ($i = 0; $i < $this->spos; $i++) {
      switch ($c[$i]) {
        case "\r":
          if ($c[$i + 1] == "\n") {
            $i++;
          }
          $ln++;
          break;
        case "\n":
          $ln++;
          break;
      }
    }
    return new MuErrorNode($errorCode, $this->templatePath, $ln);
  }

  // {% %}の中身をパースして、MuNodeを返す。
  // extendsは頭に書かないといけない
  private function parse_block() {
    $this->spos += 2;
    if (($lpos = $this->find_closetag(self::BLOCK_TAG_END)) === FALSE) {
      return $this->make_errornode('without_closetag_tag');
    }
    $in = $this->smart_split(substr($this->template, $this->spos, $lpos - $this->spos));
    switch ($in[0]) {
      // TODO: 引数の数チェックを全般
      case 'extends':
        if ($this->extends !== FALSE) {
          return $this->make_errornode('multiple_extends_tag');
        }
        if (count($in) != 2) {
          return $this->make_errornode('numofparam_extends_tag');
        }
        $param = explode('"', $in[1]);
        if (count($param) != 3) {
          // Djangoは変数もOKだけどね
          return $this->make_errornode('invalidparam_extends_tag');
        }
        $this->extends = $param[1];
        $this->spos = $lpos + 2;
        break;
      case 'include':
        if (count($in) != 2) {
          return $this->make_errornode('numofparam_include_tag');
        }
        $param = explode('"', $in[1]);
        if (count($param) != 3) {
          // Djangoは変数もOKだけどね
          return $this->make_errornode('invalidparam_include_tag');
        }
        $node = new MuIncludeNode($param[1]);
        $this->spos = $lpos + 2;
        break;
      case 'block': // endblock
        // TODO: check params
        // TODO: filter block name
        $blockname = $in[1];
        if (array_key_exists($blockname, $this->block_dict)) {
          return $this->make_errornode('multiple_block_tag');
        }
        $this->spos = $lpos + 2;
        list($nodelist) = $this->_parse(array('endblock'));
        $node = new MuBlockNode($blockname, $nodelist);
        $this->block_dict[$blockname] = $node;
        break;
      case 'for': // endfor
        // $in[1] = $loopvar, $in[2] = 'in', $in[3] = $sequence, $in[4] = 'reversed'
        if ((count($in) != 4 && count($in) != 5) || $in[2] != 'in') {
          return $this->make_errornode('numofparam_for_tag');
        }
        if (count($in) == 5) {
          if ($in[4] == 'reversed') {
            $reversed = True;
          } else {
            return $this->make_errornode('invalidparam_for_tag');
          }
        } else {
          $reversed = False;
        }
        $this->spos = $lpos + 2;
        list($nodelist) = $this->_parse(array('endfor'));
        $node = new MuForNode($in[1], $in[3], $reversed, $nodelist);
        break;
      case 'cycle':
        // TODO: implement namedCycleNodes
        if (count($in) != 2) {
          return $this->make_errornode('numofparam_cycle_tag');
        }
        $cyclevars = explode(',', $in[1]);
        if (count($cyclevars) == 0) {
          return $this->make_errornode('invalidparam_cycle_tag');
        }
        $node = new MuCycleNode($cyclevars);
        $this->spos = $lpos + 2;
        break;
      case 'if': // else, endif
        array_shift($in);
        if (count($in) < 1) {
          return $this->make_errornode('numofparam_if_tag');
        }
        $bitstr = implode(' ', $in);
        $boolpairs = explode(' and ', $bitstr);
        $boolvars = array();
        if (count($boolpairs) == 1) {
          $link_type = MuIfNode::LINKTYPE_OR;
          $boolpairs = explode(' or ', $bitstr);
        } else {
          $link_type = MuIfNode::LINKTYPE_AND;
          if (in_array(' or ', $bitstr)) {
            return $this->make_errornode('andormixed_if_tag');
          }
        }
        foreach ($boolpairs as $boolpair) {
          // handle 'not'
          if (strpos($boolpair, ' ') !== FALSE) {
            // TODO: error handling
            list($not, $boolvar) = explode(' ', $boolpair);
            if ($not != 'not') {
              return $this->make_errornode('invalidparam_if_tag');
            }
            array_push($boolvars, array(True, $boolvar));
          } else {
            array_push($boolvars, array(False, $boolpair));
          }
        }
        $this->spos = $lpos + 2;
        list($nodelist_true, $nexttag) =
          $this->_parse(array('else', 'endif'));
        if ($nexttag == 'else') {
          list($nodelist_false) = $this->_parse(array('endif'));
        } else {
          $nodelist_false = new MuNodeList();
        }
        $node = new MuIfNode($boolvars, $nodelist_true, $nodelist_false, $link_type);
        break;
      case 'debug':
        $node = new MuDebugNode();
        $this->spos = $lpos + 2;
        break;
      case 'now':
        if (count($in) != 2) {
          return $this->make_errornode('numofparam_now_tag');
        }
        $param = explode('"', $in[1]);
        if (count($param) != 3) {
          return $this->make_errornode('invalidparam_now_tag');
        }
        $node = new MuNowNode($param[1]);
        $this->spos = $lpos + 2;
        break;
      case 'filter': // endfilter
        if (count($in) != 2) {
          return $this->make_errornode('numofparam_filter_tag');
        }
        $this->spos = $lpos + 2;
        if (($filter_expr = new MuFilterExpression('var|'. $in[1])) === FALSE) {
          return $this->make_errornode('invalidparam_filter_tag');
        }
        list($nodelist) = $this->_parse(array('endfilter'));
        $node = new MuFilterNode($filter_expr, $nodelist);
        break;
      case 'endblock':
      case 'else':
      case 'endif':
      case 'endfor':
      case 'endfilter':
        $node = $in[0]; // raw string
        $this->spos = $lpos + 2;
        break;
      default:
        $node = $this->make_errornode('unknown_tag');
        $this->spos = $lpos + 2;
        break;
    }
    return $node;
  }

  private function parse_variable() {
    $this->spos += 2;
    if (($lpos = $this->find_closetag(self::VARIABLE_TAG_END)) === FALSE) {
      return FALSE;
    }
    // TODO: handle empty {{ }}
    if (($fil = new MuFilterExpression(
                  substr($this->template, $this->spos, $lpos - $this->spos))) === FALSE) {
      return $this->make_errornode('invalidparam_filter_variable');
    }
    $node = new MuVariableNode($fil);
    $this->spos = $lpos + 2;
    return $node;
  }

  private function parse_comment() {
    $this->spos += 2;
    if (($lpos = $this->find_closetag(self::COMMENT_TAG_END)) === FALSE) {
      return FALSE;
    }
    $this->spos = $lpos + 2;
  }

  static public function parse_from_file($templatePath) {
    if (($t = file_get_contents($templatePath)) === FALSE) {
      return FALSE;
    }
    $p = new MuParser($t);
    $p->templatePath = $templatePath;
    list($nl) = $p->_parse(array());
    return new MuFile($nl, $p->block_dict, $p->extends, $templatePath);
  }

  static public function parse($templateStr) {
    $p = new MuParser($templateStr);
    list($nl) = $p->_parse(array());
    return new MuFile($nl, $p->block_dict);
  }

  private function add_textnode($nodelist, $tspos, $epos) {
    if ($tspos < $epos) {
      $nodelist->push(new MuTextNode(
        substr($this->template, $tspos, $epos - $tspos)));
    }
  }

  private function _parse($parse_until) {
    $nl = new MuNodeList();
    $tspos = $this->spos;
    while (true) {
      $this->spos = strpos($this->template, self::SINGLE_BRACE_START, $this->spos);
      if ($this->spos === FALSE) {
        $this->spos = $this->template_len;
        $this->add_textnode($nl, $tspos, $this->template_len);
        return array($nl, null);
      }
      switch (substr($this->template, $this->spos, 2)) {
        case self::BLOCK_TAG_START:
          $this->add_textnode($nl, $tspos, $this->spos);
          if (($node = $this->parse_block()) === FALSE) {
            return FALSE;
          }
          $tspos = $this->spos;
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
          $this->add_textnode($nl, $tspos, $this->spos);
          if (($node = $this->parse_variable()) === FALSE) {
            return FALSE;
          }
          $nl->push($node);
          $tspos = $this->spos;
          break;
        case self::COMMENT_TAG_START:
          $this->add_textnode($nl, $tspos, $this->spos);
          if (($node = $this->parse_comment()) === FALSE) {
            return FALSE;
          }
          $nl->push($node);
          $tspos = $this->spos;
          break;
        default:
          // { only
          $this->spos += 1;
      }
    }
  }
}

class MuErrorHandler {

  private static $errorType = array (
    E_ERROR             => 'ERROR',
    E_WARNING           => 'WARNING',
    E_PARSE             => 'PARSING ERROR',
    E_NOTICE            => 'NOTICE',
    E_CORE_ERROR        => 'CORE ERROR',
    E_CORE_WARNING      => 'CORE WARNING',
    E_COMPILE_ERROR     => 'COMPILE ERROR',
    E_COMPILE_WARNING   => 'COMPILE WARNING',
    E_USER_ERROR        => 'USER ERROR',
    E_USER_WARNING      => 'USER WARNING',
    E_USER_NOTICE       => 'USER NOTICE',
    E_STRICT            => 'STRICT NOTICE',
    E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR'
  );

  // error page html
  const BACKTRACE_HTML = '
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8" />
  <meta name="robots" content="NONE,NOARCHIVE" />
  <title>{{ error_type }}</title>
  <style type="text/css">
    html * { padding:0; margin:0; }
    body * { padding:10px 20px; }
    body * * { padding:0; }
    body { font:small sans-serif; }
    body>div { border-bottom:1px solid #ddd; }
    h1 { font-weight:normal; }
    h2 { margin-bottom:.8em; }
    h2 span { font-size:80%; color:#666; font-weight:normal; }
    h3 { margin:1em 0 .5em 0; }
    h4 { margin:0 0 .5em 0; font-weight: normal; }
    table { border:1px solid #ccc; border-collapse: collapse; width:100%; background:white; }
    tbody td, tbody th { vertical-align:top; padding:2px 3px; }
    thead th { padding:1px 6px 1px 3px; background:#fefefe; text-align:left; font-weight:normal; font-size:11px; border:1px solid #ddd; }
    tbody th { width:12em; text-align:right; color:#666; padding-right:.5em; }
    table.vars { margin:5px 0 2px 40px; }
    table.vars td, table.req td { font-family:monospace; }
    table td.code { width:100%; }
    table td.code div { overflow:hidden; }
    table.source th { color:#666; }
    table.source td { font-family:monospace; white-space:pre; border-bottom:1px solid #eee; }
    ul.traceback { list-style-type:none; }
    ul.traceback li.frame { margin-bottom:1em; }
    div.context { margin: 10px 0; }
    div.context ol { padding-left:30px; margin:0 10px; list-style-position: inside; }
    div.context ol li { font-family:monospace; white-space:pre; color:#666; cursor:pointer; }
    div.context ol.context-line li { color:black; background-color:#ccc; }
    div.context ol.context-line li span { float: right; }
    div.commands { margin-left: 40px; }
    div.commands a { color:black; text-decoration:none; }
    #summary { background: #ffc; }
    #summary h2 { font-weight: normal; color: #666; }
    #explanation { background:#eee; }
    #template, #template-not-exist { background:#f6f6f6; }
    #template-not-exist ul { margin: 0 0 0 20px; }
    #traceback { background:#eee; }
    #requestinfo { background:#f6f6f6; padding-left:120px; }
    #summary table { border:none; background:transparent; }
    #requestinfo h2, #requestinfo h3 { position:relative; margin-left:-100px; }
    #requestinfo h3 { margin-bottom:-1em; }
    .error { background: #ffc; }
    .specific { color:#cc3300; font-weight:bold; }
  </style>
  <script type="text/javascript">
  //<!--
    function getElementsByClassName(oElm, strTagName, strClassName){
        // Written by Jonathan Snook, http://www.snook.ca/jon; Add-ons by Robert Nyman, http://www.robertnyman.com
        var arrElements = (strTagName == "*" && document.all)? document.all :
        oElm.getElementsByTagName(strTagName);
        var arrReturnElements = new Array();
        strClassName = strClassName.replace(/\-/g, "\-");
        var oRegExp = new RegExp("(^|\s)" + strClassName + "(\s|$)");
        var oElement;
        for(var i=0; i<arrElements.length; i++){
            oElement = arrElements[i];
            if(oRegExp.test(oElement.className)){
                arrReturnElements.push(oElement);
            }
        }
        return (arrReturnElements)
    }
    function hideAll(elems) {
      for (var e = 0; e < elems.length; e++) {
        elems[e].style.display = \'none\';
      }
    }
    window.onload = function() {
      hideAll(getElementsByClassName(document, \'table\', \'vars\'));
      hideAll(getElementsByClassName(document, \'ol\', \'pre-context\'));
      hideAll(getElementsByClassName(document, \'ol\', \'post-context\'));
      hideAll(getElementsByClassName(document, \'div\', \'pastebin\'));
    }
    function toggle() {
      for (var i = 0; i < arguments.length; i++) {
        var e = document.getElementById(arguments[i]);
        if (e) {
          e.style.display = e.style.display == \'none\' ? \'block\' : \'none\';
        }
      }
      return false;
    }
    function varToggle(link, id) {
      toggle(\'v\' + id);
      var s = link.getElementsByTagName(\'span\')[0];
      var uarr = String.fromCharCode(0x25b6);
      var darr = String.fromCharCode(0x25bc);
      s.innerHTML = s.innerHTML == uarr ? darr : uarr;
      return false;
    }
    function switchPastebinFriendly(link) {
      s1 = "Switch to copy-and-paste view";
      s2 = "Switch back to interactive view";
      link.innerHTML = link.innerHTML == s1 ? s2 : s1;
      toggle(\'browserTraceback\', \'pastebinTraceback\');
      return false;
    }
    //-->
  </script>
</head>
<body>
<div id="summary">
  <h1>{{ error_type|escape }}</h1>
  <h2>{{ error_str|escape }}</h2>
  <table class="meta">
    <!--tr>
      <th>Request Method:</th>
      <td>{{ request.method|escape }}</td>
    </tr>
    <tr>
      <th>Request URL:</th>
      <td>{{ request.url|escape }}</td>
    </tr>
    <tr>
      <th>Exception Type:</th>
      <td>{{ exception.type }}</td>
    </tr-->
    <tr>
      <th>Exception Value:</th>
      <td>{{ error_str }}</td>
    </tr>
    <tr>
      <th>Exception Location:</th>
      <td>{{ error_file|escape }}, line {{ error_line }}</td>
      <!-- TODO: get function name -->
    </tr>
  </table>
</div>


</body>
';

  static public function getArguments(&$args)
  {
    $pargs = array();
    foreach($args as $arg) {
      array_push($pargs, self::getArgument($arg));
    }
    return $pargs;
  }

  static public function getArgument($arg, $recursion = true)
  {
    switch (strtolower(gettype($arg))) {
      case 'string':
        return '"'.$arg.'"';
      case 'boolean':
        return (bool)$arg;
      case 'object':
        return 'object('. get_class($arg) . ')';
      case 'array':
        $pargs = array();
        foreach ($arg as $key => $value) {
          if (!$recursion) {
            array_push($pargs, self::getArgument($key, false). ' => '. self::getArgument($value, false));
          }
        }
        return 'array('. implode(', ', $pargs) . ')';
      case 'resource':
        return 'resource('. get_resource_type($arg). ')';
      default:
        return var_export($arg, true);
    }
  }

  static public function handler($errno, $errstr = '', $errfile = '', $errline = '', $errcontext = null)
  {
    $p = array('backtrace' => array());
    if (error_reporting() == 0) {
      return;
    }
    if (func_num_args() == 5) {
      // error
      list($p['errno'], $p['error_str'], $p['error_file'], $p['error_line']) = func_get_args();
      $backtrace = array_reverse(debug_backtrace());
    } else {
      // exception
      $exc = func_get_arg(0);
      $p['errno'] = $exc->getCode();
      $p['error_str'] = $exc->getMessage();
      $p['error_file'] = $exc->getFile();
      $p['error_line'] = $exc->getLine();
      $backtrace = $exc->getTrace();
    }
    array_pop($backtrace); // remove handler self
    if (array_key_exists($errno, self::$errorType)) {
      $p['error_type'] = self::$errorType[$p['errno']];
    } else {
      $p['error_type'] = 'CAUGHT EXCEPTION';
    }
    foreach ($backtrace as $bt) {
      if (isset($bt['class'])) {
        $trace = 'in class '. $bt['class'].'::'.$v['function'].'(';
        if (isset($bt['args'])) {
          $trace .= implode(', ', self::getArguments($bt['args']));
        }
        $trace .= ')';
        array_push($p['backtrace'], $trace);
      } elseif (isset($bt['function'])) {
        $trace = 'in function '.$bt['function'].'(';
        if (isset($bt['args'])) {
          $trace .= implode(', ', self::getArguments($bt['args']));
        }
        $trace .= ')';
        array_push($p['backtrace'], $trace);
      } else {
        array_push($p['backtrace'], 'unknown');
      }
    }
    switch ($p['errno']) {
      case E_NOTICE:
      case E_USER_NOTICE:
      case E_STRICT:
        return;
        break;
      default:
        echo MuParser::parse(self::BACKTRACE_HTML)->render($p);
    }
    exit(1);
  }
}
?>
