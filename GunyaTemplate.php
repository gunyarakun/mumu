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
  public function render($context) {
    $bits = array();
    foreach ($nodes as $node) {
      array_push($bits, $node->render($context));
    }
    return implode('', $bits);
  }
}

class GTTextNode extends GTNode {
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
}

class GTNowNode extends GTNode {
}

class GunyaTemplate {
  private $template; // パース前のテンプレート文字列
  private $errorStr; // エラー文字列
  private $ptemplate; // パース後のテンプレート(Nodeのarray)

  // パース用
  private $blockmode; // 'f': for, 'i': if, 'e': else
  private $outmode;   // 'a': 追加 'i': 無視 'b': ブロックに追加

  # template syntax constants
  FILTER_SEPARATOR = '|'
  FILTER_ARGUMENT_SEPARATOR = ':'
  VARIABLE_ATTRIBUTE_SEPARATOR = '.'
  BLOCK_TAG_START = '{%'
  BLOCK_TAG_END = '%}'
  VARIABLE_TAG_START = '{{'
  VARIABLE_TAG_END = '}}'
  COMMENT_TAG_START = '{#'
  COMMENT_TAG_END = '#}'
  SINGLE_BRACE_START = '{'
  SINGLE_BRACE_END = '}'

  function GunyaTemplate($templatePath) {
  }

  // 終了タグを探して、その位置を返す
  private function find_closetag($t, &$spos, $closetag) {
    if (($epos = strpos($t, $closetag, $spos)) === FALSE) {
      $this->errorStr = "タグが閉じられてないようです($closetagが見つかりません)。"
      return FALSE;
    }
    return $epos;
  }

  // {% %}の中身をパースして、GTNodeを返す。
  // extendsは頭に書かないといけない
  private function parse_block(&$spos) {
    $spos += 2
    if (($epos = find_closetag($this->template, $spos, self::BLOCK_TAG_END)) === FALSE) {
      return FALSE;
    }
    $in = trim(substr($this->template, $spos, $epos));
    switch ($in[0]) {
      case 'extends':
        // FIXME: ループチェック
        $node = new GTExtendsNode(nodelist, parent_name, parent_name_expr);
      case 'include':
        // FIXME: ループチェック
        $node = new GTIncludeNode(template_name);
      case 'block': // endblock
        $node = new GTBlockNode(name, nodelist, parent=None);
      case 'for': // endfor
        $node = new GTForNode(loopvar, sequence, reversed, nodelist_loop);
      case 'cycle':
        $node = new GTCycleNode(cyclevars);
        // TODO: implement
      case 'if': // else, endif
        $node = new GTIfNode(bool_exprs, nodelist_true, nodelist_false, link_type);
      case 'debug':
        $node = new GTDebugNode();
      case 'now':
        $node = new GTNowNode(format_string);
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
    $spos += 2
    if (($epos = find_closetag($this->template, $spos, self::VARIABLE_TAG_END)) === FALSE) {
      return FALSE;
    }
    // TODO: use limit for explode
    $in = explode(' ', trim(substr($this->template, $spos, $epos)));

    $spos = $epos + 2;
  }

  // {# #}の中身をパース
  private function parse_comment(&$spos) {
    $spos += 2
    if (($epos = find_closetag($this->template, $spos, self::COMMENT_TAG_END)) === FALSE) {
      return FALSE;
    }
    $spos = $epos + 2; // #}のあと
  }

  public function parse($templatePath) {
    if (($t = $file_get_contents($templatePath)) === FALSE) {
      $this->errorStr = 'ファイルが開けません。';
      return FALSE;
    }
    $pos = 0;

    $this->template = $t;

    // タグの開始部分を見つける
    while (($pos = strpos($t, self::SINGLE_BRACE_START, $pos)) !== FALSE) {
      switch (substr($t, pos, 2) {
        case BLOCK_TAG_START:
          if (parse_block($pos) === FALSE) {
            unset($this->template);
            return FALSE;
          }
          break;
        case VARIABLE_TAG_START:
          if (parse_variable($pos) === FALSE) {
            unset($this->template);
            return FALSE;
          }
          break;
        case COMMENT_TAG_START:
          if (parse_comment($pos) === FALSE) {
            unset($this->template);
            return FALSE;
          }
          break;
        default:
          // 単なる{は読みとばす
          $pos += 1;
      }
    }
  }
}
?>
