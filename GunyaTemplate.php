<?php
// GunyaTemplate (c) Brazil, Inc.
// originally developed by Tasuku SUENAGA a.k.a. gunyarakun
// developed from 2007/09/04

// 設計目的
// - このファイルだけあればOK
// - 高速
// - キャッシュ機能
// - ヘッダ・フッタのinclude
// - テンプレート内でループが扱える
// - テンプレートファイルを直にhtmlとして閲覧可能
// - urlエンコードやhtmlspecialcharsを簡単に指定できる
// - PHP5は要求するよ

// 使い方の概要(の目標)
// - すべてのデータをハッシュに入れる（事前に全データを準備しないといけない）
// - テンプレートファイルを指定する
// - 処理する(キャッシュの有無も指定できる)
// - 処理された内容が返ってくる
// - echoなりなんなりお好きに

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

class GTContext {
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
      } elseif (is_callable($current)) {
        // 関数コール
        $current = $current();
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
}

class GTNode {
  public function _render() {
    return '';
  }
}

class GTNodeList {
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

// 1つのテンプレートファイルをパースしたもの。
// 外の世界に出るのがこいつ
class GTFile extends GTNode {
  public $nodelist;        // ファイルをパースしたNodeList
  private $block_dict;      // nodelistの中にあるblock名 => GTBlockNode(の参照)
  private $parent_tfile;    // extendsがある場合の親テンプレート
  function __construct($nodelist, $block_dict, $parentPath = false) {
    $this->nodelist = $nodelist;
    $this->block_dict = $block_dict;
    if ($parentPath) {
      $p = new GTParser();
      if (($this->parent_tfile = $p->parse_from_file($parentPath)) === FALSE) {
        // TODO: エラー起こしたテンプレート名を安全に教えてあげる
      }
    }
  }
  public function render($raw_context) {
    return $this->_render(new GTContext($raw_context));
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

class GTTextNode extends GTNode {
  private $text;
  function __construct($text) {
    $this->text = $text;
  }
  public function _render($context) {
    return $this->text;
  }
}

class GTVariableNode extends GTNode {
  private $filter_expression;
  function __construct($filter_expression) {
    $this->filter_expression = $filter_expression;
  }
  public function _render($context) {
    return $this->filter_expression->resolve($context);
  }
}

class GTIncludeNode extends GTNode {
  private $tplfile;
  function __construct($includePath) {
    // FIXME: セキュリティチェック、無限ループチェック
    $p = new GTParser();
    if (($this->tplfile = $p->parse_from_file($includePath)) === FALSE) {
      // TODO: エラー起こしたテンプレート名を安全に教えてあげる
      $this->tplfile = new GTTextNode('include error');
    }
  }
  public function _render($context) {
    return $this->tplfile->_render($context);
  }
}

class GTBlockNode extends GTNode {
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
      $this->parent = new GTBlockNode($this->name, $this->nodelist);
    }
  }
}

class GTCycleNode extends GTNode {
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

class GTDebugNode extends GTNode {
}

class GTFilterNode extends GTNode {
}

class GTForNode extends GTNode {
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
      $parentloop = new GTContext();
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
    var_dump($rnodelist);
    return implode('', $rnodelist);
  }
}

class GTIfNode extends GTNode {
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

class GTNowNode extends GTNode {
  private $format_string;
  function __construct($format_string) {
    $this->format_string = $format_string;
  }
  public function _render($context) {
    return date($this->format_string);
  }
}

class GTUnknownNode extends GTNode {
  public function _render($context) {
    return 'unknown...';
  }
}

class GTFilterExpression {
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

class GTParser {
  private $template;             // パース前のテンプレート文字列
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
  private function find_closetag(&$spos, &$epos, $closetag) {
    if (($fpos = strpos($this->template, $closetag, $spos)) === FALSE || $fpos >= $epos) {
      $this->errorStr = "タグが閉じられてないようです($closetagが見つかりません)。";
      return FALSE;
    }
    return $fpos;
  }

  // 直近のelseやendblockやendifやendforを探す
  private function find_endtags($spos, &$epos, $endtags) {
    // まず、直近のブロック開始タグを探して
    while (($spos = strpos($this->template, self::BLOCK_TAG_START, $spos)) !== FALSE
           && $spos < $epos) {
      $spos += 2;
      if (($lpos = $this->find_closetag($spos, $epos, self::BLOCK_TAG_END)) !== FALSE
          && $lpos < $epos) {
        // その中身をtrimしてチェック
        $c = trim(substr($this->template, $spos, $lpos - $spos));
        if (in_array($c, $endtags)) {
          return array($spos - 2, $lpos + 2, $c);
        }
        $spos = $lpos + 2;
      }
    }
    $this->errorStr = "block/if/forが閉じられていないようです。";
    return FALSE;
  }

  // {% %}の中身をパースして、GTNodeを返す。
  // extendsは頭に書かないといけない
  private function parse_block(&$spos, &$epos) {
    $spos += 2;
    if (($lpos = $this->find_closetag($spos, $epos, self::BLOCK_TAG_END)) === FALSE) {
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
        $node = new GTIncludeNode($param[1]);
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
        $lpos += 2;
        if ((list($bepos, $blpos) = $this->find_endtags($lpos, $epos, array('endblock'))) === FALSE) {
          return FALSE;
        }
        $nodelist = $this->_parse($lpos, $bepos);
        $node = new GTBlockNode($blockname, $nodelist);
        $this->block_dict[$blockname] = &$node; // reference
        $spos = $blpos;
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
        $lpos += 2;
        if ((list($bepos, $blpos) = $this->find_endtags($lpos, $epos, array('endfor'))) === FALSE) {
          return FALSE;
        }
        $nodelist = $this->_parse($lpos, $bepos);
        $node = new GTForNode($in[1], $in[3], $reversed, $nodelist);
        $spos = $blpos;
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
        $node = new GTCycleNode($cyclevars);
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
          // notの処理
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
        $lpos += 2;
        if ((list($bepos, $eblpos, $nexttag) =
               $this->find_endtags($lpos, $epos, array('else', 'endif'))) === FALSE) {
          return FALSE;
        }
        $nodelist_true = $this->_parse($lpos, $bepos);
        if ($nexttag == 'else') {
          echo 'elsessu****';
          if ((list($bepos, $blpos, $nexttag) =
                    $this->find_endtags($eblpos, $epos, array('endif'))) === FALSE) {
            return FALSE;
          }
          $nodelist_false = $this->_parse($eblpos, $bepos);
          $spos = $blpos;
        } else {
          $nodelist_false = new GTNodeList();
          $spos = $eblpos;
        }
        $node = new GTIfNode($boolvars, $nodelist_true, $nodelist_false, $link_type);
        break;
      case 'debug':
        $node = new GTDebugNode();
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
        $node = new GTNowNode($param[1]);
        $spos = $lpos + 2;
        break;
      case 'filter':
        // TODO: implement
        $spos = $lpos + 2;
        break;
      default:
        $node = new GTUnknownNode();
        $spos = $lpos + 2;
        break;
    }
    return $node;
  }

  // {{ }}の中身をパース
  private function parse_variable(&$spos, &$epos) {
    $spos += 2;
    if (($lpos = $this->find_closetag($spos, $epos, self::VARIABLE_TAG_END)) === FALSE) {
      return FALSE;
    }
    // TODO: handle empty {{ }}
    $fil = new GTFilterExpression(substr($this->template, $spos, $lpos - $spos));
    $node = new GTVariableNode($fil);
    $spos = $lpos + 2;
    return $node;
  }

  // {# #}の中身をパース
  private function parse_comment(&$spos, &$epos) {
    $spos += 2;
    if (($lpos = $this->find_closetag($spos, $epos, self::COMMENT_TAG_END)) === FALSE) {
      return FALSE;
    }
    $spos = $lpos + 2; // #}のあと
  }

  public function parse_from_file($templatePath) {
    if (($t = file_get_contents($templatePath)) === FALSE) {
      $this->errorStr = 'ファイルが開けません。';
      return FALSE;
    }
    $this->template = $t;
    $nl = $this->_parse(0, strlen($t));
    unset($this->template);
    return new GTFile($nl, $this->block_dict, $this->extends);
  }

  public function parse($templateStr) {
    $this->template = $templateStr;
    $nl = $this->_parse(0, strlen($templateStr));
    unset($this->template);
    return $nl;
  }

  private function _parse($spos, $epos) {
    $nl = new GTNodeList();
    while ($spos < $epos) {
      // タグの開始部分を見つける
      $nspos = strpos($this->template, self::SINGLE_BRACE_START, $spos);
      if ($nspos === FALSE) {
        $nspos = $epos;
      }
      if ($spos < $nspos) {
        // タグ以外の部分をGTTextNodeとして保存
        $nl->push(new GTTextNode(substr($this->template, $spos, $nspos - $spos)));
      }
      if ($nspos < $epos) {
        switch (substr($this->template, $nspos, 2)) {
          case self::BLOCK_TAG_START:
            if (($node = $this->parse_block($nspos, $epos)) === FALSE) {
              unset($this->template);
              return FALSE;
            }
            $nl->push($node);
            break;
          case self::VARIABLE_TAG_START:
            if (($node = $this->parse_variable($nspos, $epos)) === FALSE) {
              unset($this->template);
              return FALSE;
            }
            $nl->push($node);
            break;
          case self::COMMENT_TAG_START:
            if (($node = $this->parse_comment($nspos, $epos)) === FALSE) {
              unset($this->template);
              return FALSE;
            }
            $nl->push($node);
            break;
          default:
            // 単なる{は読みとばす
            $nspos += 1;
        }
      }
      $spos = $nspos;
    }
    return $nl;
  }
}
?>
