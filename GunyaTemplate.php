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

// ノードたち。パースされたテンプレートはGTNodeListで表される
// GTNodeは$nodelistというGTNodeListのメンバを持つ場合がある。
// GTNodeListは$nodesというGTNodeのarrayを持つ。
// GTNodeListのrenderを呼べば、テンプレートに値があてはめられる。

class GTNode {
  public function render() {
    return '';
  }
}

class GTNodeList {
  private $nodes;
  function __construct() {
    $this->nodes = array();
  }
  public function render($context) {
    $bits = array();
    foreach ($this->nodes as $node) {
      array_push($bits, $node->render($context));
    }
    return implode('', $bits);
  }
  public function push($node) {
    array_push($this->nodes, $node);
  }
}

// 1テンプレートファイル。
class GTFile {
  private $nodelist;        // ファイルをパースしたNodeList
  private $extends_index;   // nodelistの中でextendsのものの位置
  private $block_dict;      // nodelistの中にあるblock名 => nodelist内位置
  function __construct($nodelist, $extends_index, $block_dict) {
    $this->nodelist = $nodelist;
    $this->extends_index = $extends_index;
    $this->block_dict = $block_dict;
  }
  function render($context) {
    return $this->nodelist->render($context);
  }
  function get_block($blockname) {
    if (array_key_exists($blockname, $block_dict)) {
      return $nodelist[$block_dict[$blockname]];
    } else {
      return false;
    }
  }
  function append_block($blocknode) {
    // 孫で定義されたブロック名で、親には定義されていないが、
    // 親の親には定義されている場合のため、
    // 孫から親にブロックを移す

    // こいつの型はGTExtendsNode
    $nodelist[$extends_index]->append_block($blocknode);
  }
  function is_child() {
    return is_numeric($extends_index);
  }
}

class GTTextNode extends GTNode {
  private $text;
  function __construct($text) {
    $this->text = $text;
  }
  public function render($context) {
    return $this->text;
  }
}

class GTVariableNode extends GTNode {
  private $filter_expression;
  function __construct($filter_expression) {
    $this->filter_expression = $filter_expression;
  }
  public function render($context) {
    return $this->filter_expression->resolve($context);
  }
}

class GTExtendsNode extends GTNode {
  private $nodelist;
  private $block_dict;
  private $parent_tplfile;
  function __construct($nodelist, $block_dict, $parentPath) {
    // FIXME: セキュリティチェック、無限ループチェック
    $this->nodelist = $nodelist;
    $this->block_dict = $block_dict;
    $p = new GTParser();
    if (($this->parent_tplfile = $p->parse_from_file($parentPath)) === FALSE) {
      // TODO: エラー起こしたテンプレート名を安全に教えてあげる
    }
  }

  function render($context) {
    if ($parent_nodelist) {
      $parent_is_child = $parent_tplfile->is_child();
      $bidxs = $parent_tplfile->get_block_indexes();
      foreach ($bidxs as $bidx) {
      }
    } else {
      return 'extends error';
    }
  }

  function append_block($blocknode) {
    array_push($this->nodelist, $blocknode);
  }
}

class GTIncludeNode extends GTNode {
  private $tplfile;
  function __construct($includePath) {
    // FIXME: セキュリティチェック、無限ループチェック
    $p = new GTParser();
    if (($this->tplfile = $p->parse_from_file($includePath)) === FALSE) {
      // TODO: エラー起こしたテンプレート名を安全に教えてあげる
      $this->tplfile = array(new GTTextNode('include error'));
    }
  }
  public function render($context) {
    return $this->tplfile>render($context);
  }
}

class GTBlockNode extends GTNode {
}

class GTCycleNode extends GTNode {
}

class GTDebugNode extends GTNode {
}

class GTFilterNode extends GTNode {
}

class GTForNode extends GTNode {
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
  function render($context) {
    if ($this->link_type == self::LINKTYPE_OR) {
      // TODO: 条件判断(resolve)
      return $nodelist_false->render($context);
    } else { // self::LINKTYPE_AND
      // TODO: 条件判断(resolve)
      return $nodelist_true>render($context);
    }
  }
}

class GTNowNode extends GTNode {
  private $format_string;
  function __construct($format_string) {
    $this->format_string = $format_string;
  }
  public function render($context) {
    return date($this->format_string);
  }
}

class GTUnknownNode extends GTNode {
  public function render($context) {
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
    var_dump($this->filters);
  }

  // TODO: support ignore_failures
  public function resolve($context) {
    // evalとかcall_user_func_arrayせずにswitch-caseでdispatch、めんどいから

    // TODO: resolve_variable
    $val = $context[$this->var];
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
  private $template;        // パース前のテンプレート文字列
  private $errorStr;        // エラー文字列
  private $block_dict;      // blockの名前 => blockへの参照
  private $extends = false; // extendsの場合のファイル名

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

  // 終了タグを探して、その位置を返す
  private function find_closetag($t, &$spos, &$epos, $closetag) {
    if (($fpos = strpos($t, $closetag, $spos)) === FALSE || $fpos >= $epos) {
      $this->errorStr = "タグが閉じられてないようです($closetagが見つかりません)。";
      return FALSE;
    }
    return $fpos;
  }

  // endblockやendifやendforを探す
  private function find_endtag($t, &$spos, &$epos) {
  }

  // {% %}の中身をパースして、GTNodeを返す。
  // extendsは頭に書かないといけない
  private function parse_block(&$spos, &$epos) {
    $spos += 2;
    if (($lpos = $this->find_closetag($this->template, $spos, $epos, self::BLOCK_TAG_END))
        === FALSE) {
      return FALSE;
    }
    $in = $this->smart_split(substr($this->template, $spos, $lpos - $spos));
    echo 'inblock: '. $in[0] .'\n';
    switch ($in[0]) {
      case 'extends':
        if ($this->extends !== FALSE) {
          $this->errorStr = 'extendsは１つだけしか指定できません。';
          return FALSE;
        }
        $this->extends = $in[1];
        break;
      case 'include':
        $node = new GTIncludeNode(template_name);
        break;
      case 'block': // endblock
        // TODO: filter block name
        $blockname = $in[1];
        if (array_key_exists($blockname, $this->block_dict)) {
          // TODO: filtered block name print
          $this->errorStr = '同じ名前のblockは１つだけしか指定できません。';
          return FALSE;
        }
        // blockタグはネストがないので、そのまんまendblock検索
        // FIXME FIXME koko
        $nodelist = $this->_parse($lpos + 2, $epos);
        $node = new GTBlockNode($blockname, $nodelist);
        $this->block_dict[$blockname] = &$node; // reference
        break;
      case 'for': // endfor
        $node = new GTForNode(loopvar, sequence, reversed, nodelist_loop);
        break;
      case 'cycle':
        $node = new GTCycleNode(cyclevars);
        // TODO: implement
        break;
      case 'if': // else, endif
        $node = new GTIfNode(bool_exprs, nodelist_true, nodelist_false, link_type);
        break;
      case 'debug':
        $node = new GTDebugNode();
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
        break;
      default:
        $node = new GTUnknownNode();
        break;
    }
    $spos = $lpos + 2;
    return $node;
  }

  // {{ }}の中身をパース
  private function parse_variable(&$spos, &$epos) {
    $spos += 2;
    if (($lpos = $this->find_closetag($this->template, $spos, $epos, self::VARIABLE_TAG_END))
        === FALSE) {
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
    if (($lpos = $this->find_closetag($this->template, $spos, $epos, self::COMMENT_TAG_END))
        === FALSE) {
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
    if ($this->extends !== FALSE) {
      $nl = new GTExtendsNode($nl);
    }
    unset($this->template);
    return new GTFile($nl, $this->extends);
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
      $spos = $nspos;
    }
    return $nl;
  }
}

class GunyaTemplate {
}
?>
