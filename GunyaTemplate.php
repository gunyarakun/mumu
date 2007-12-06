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
// - PHP4以上で動くように、と思ったけどあきらめた

// 使い方の概要
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
// |truncatewords:"100"         : 指定した文字数まで切り詰める
// |urlencode                   : urlエンコード

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

class GTTextNode extends GTNode {
  private $text;
  function __construct($text) {
    $this->text = $text;
  }
  function render($context) {
    return $text;
  }
}

class GTVariableNode extends GTNode {
}

class GTExtendsNode extends GTNode {
}

class GTIncludeNode extends GTNode {
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
  function render($context) {
    return date($this->format_string);
  }
}

class GTUnknownNode extends GTNode {
  function render($context) {
    return 'unknown...';
  }
}

class GTParser {
  private $template; // パース前のテンプレート文字列
  private $errorStr; // エラー文字列
  private $ptemplate; // パース後のテンプレート(Nodeのarray)

  // パース用
  private $blockmode; // 'f': for, 'i': if, 'e': else
  private $outmode;   // 'a': 追加 'i': 無視 'b': ブロックに追加

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
  private function smart_split($text) {
    $epos = strlen($text);
    $ret = array();
    $mode = 'n';  // 'n': not quoted, 'd': in ", 'q': in '
    for ($spos = 0; $spos < $epos; $spos++) {
      $a = $text[$spos];
      switch ($a) {
        case '\\':
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
            default:
              $buf .= '\\';
          }
          break;
        case "'":
          switch ($mode) {
            case 'd':
              $buf .= "'";
              break;
            case 'q':
              array_push($ret, $buf);
              $mode = 'n';
              $buf = '';
              break;
            default:
              if ($buf != '') {
                array_push($ret, $buf);
              }
              $mode = 'q';
              $buf = '';
              break;
          }
          break;
        case '"':
          switch ($mode) {
            case 'd':
              array_push($ret, $buf);
              $mode = 'n';
              $buf = '';
              break;
            case 'q':
              $buf .= '"';
              break;
            default:
              if ($buf != '') {
                array_push($ret, $buf);
              }
              $mode = 'd';
              $buf = '';
              break;
          }
          break;
        case ' ':
          switch ($mode) {
            case 'd':
            case 'q':
              $buf .= ' ';
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
  private function find_closetag($t, &$spos, $closetag) {
    if (($epos = strpos($t, $closetag, $spos)) === FALSE) {
      $this->errorStr = "タグが閉じられてないようです($closetagが見つかりません)。";
      return FALSE;
    }
    return $epos;
  }

  // {% %}の中身をパースして、GTNodeを返す。
  // extendsは頭に書かないといけない
  private function parse_block(&$spos) {
    $spos += 2;
    if (($epos = $this->find_closetag($this->template, $spos, self::BLOCK_TAG_END))
        === FALSE) {
      return FALSE;
    }
    $in = $this->smart_split(substr($this->template, $spos, $epos - 2));
    echo 'inblock: '. $in[0] .'\n';
    switch ($in[0]) {
      case 'extends':
        // FIXME: ループチェック
        $node = new GTExtendsNode(nodelist, parent_name, parent_name_expr);
        break;
      case 'include':
        // FIXME: ループチェック
        $node = new GTIncludeNode(template_name);
        break;
      case 'block': // endblock
        $node = new GTBlockNode(name, nodelist, parent);
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
        var_dump($in);
        if (count($in) != 2) {
          $this->errorStr = 'nowは書式文字列が必要です';
          return FALSE;
        }
        $node = new GTNowNode($in[1]);
        break;
      default:
        $node = new GTUnknownNode();
        break;
    }
    $spos = $epos + 2;
    return $node;
  }

  // こんな感じに加工
  // 前: variable|filter1|filter2:"test"|filter3
  // 後: array('variable', array('filter1'), array(filter2, 'test'), array('filter3'))
  // したあとに、

  // {{ }}の中身をパース
  private function parse_variable(&$spos) {
    $spos += 2;
    if (($epos = find_closetag($this->template, $spos, self::VARIABLE_TAG_END)) === FALSE) {
      return FALSE;
    }
    // TODO: use limit for explode
    $in = explode(' ', trim(substr($this->template, $spos, $epos)));

    $spos = $epos + 2;
  }

  // {# #}の中身をパース
  private function parse_comment(&$spos) {
    $spos += 2;
    if (($epos = find_closetag($this->template, $spos, self::COMMENT_TAG_END)) === FALSE) {
      return FALSE;
    }
    $spos = $epos + 2; // #}のあと
  }

  public function parse_from_file($templatePath) {
    if (($t = file_get_contents($templatePath)) === FALSE) {
      $this->errorStr = 'ファイルが開けません。';
      return FALSE;
    }
    $this->template = $t;
    return $this->_parse(0, strlen($t));
  }

  public function parse($templateStr) {
    $this->template = $templateStr;
    return $this->_parse(0, strlen($templateStr));
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
          if (($node = $this->parse_block($nspos)) === FALSE) {
            unset($this->template);
            return FALSE;
          }
          $nl->push($node);
          break;
        case self::VARIABLE_TAG_START:
          if (($node = $this->parse_variable($nspos)) === FALSE) {
            unset($this->template);
            return FALSE;
          }
          $nl->push($node);
          break;
        case self::COMMENT_TAG_START:
          if (($node = $this->parse_comment($nspos)) === FALSE) {
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
?>
