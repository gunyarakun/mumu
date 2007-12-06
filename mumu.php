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
// {% include "filename" %}     : テンプレートのインクルード
// {% extends "filename" %}     : テンプレートの拡張
// {% block blockname %}        : ブロックの始まり
// {% endblock %}               : ブロックの終わり
// {% for item in items %}      : 変数itemsからitemを取り出す
// {% endfor %}                 : forの終わり
// {% cycle val1,val2 %}        : forループの中でval1,val2を交互に出す(表で行ごと背景色とか)
// {% if cond %} {% else %}     : cond条件が満たされたところだけを出力
// {% endif %}                  : ifの終わり
// {% debug %}                  : テンプレートに渡された情報をダンプする
// {% now "format" %}           : 現在の日付を指定フォーマットで出力します
// {% filter fil1|fil2 %}       : ブロック内コンテンツをフィルタにかけます
// {% endfilter %}              : filterの終わり
// {% firstof var1 var2 %}      : 渡された変数のうち、falseでない最初の変数
// {% ifequal var1 var2 %}      : var1 == var2の条件が満たされたら出力
// {% endifequal %}             : ifequalの終わり
// {% ifnotequal var1 var2 %}   : var1 == var2の条件が満たされたら出力
// {% endifnotequal %}          : ifnotequalの終わり
// {% spaceless %}              : タグの間のスペースを詰めます
// {% endspaceless %}           : spacelessの終わり
// {% templatetag tagname %}    : templateの構成に使われる文字字体を出力します
// {% comment %}                : コメントです。出力されません。
// {% endcomment %}             : コメントの終わりです。
// {% widthratio val max mul %} : (val / max) * mulを出力します
// {# comment  #}               : コメント

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
// |add                         : 
// |capfirst                    : 
// |center                      : 
// |cut                         : 
// |default                     : 
// |default_if_none             : 
// |divisibleby                 : 
// |filesizeformat              : 
// |first                       : 
// |fix_ampersands              : 
// |floatformat                 : 
// |get_digit                   : 
// |length_is                   : 
// |linebreaks                  : 
// |linenumbers                 : 
// |ljust                       : 
// |lower                       : 
// |make_list                   : 
// |pprint                      : 
// |random                      : 
// |removetags                  : 
// |rjust                       : 
// |slice                       : 
// |striptags                   : 
// |title                       : 
// |truncatewords               : 
// |upper                       : 
// |wordcount                   : 
// |wordwrap                    : 
// |yesno                       : 

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
// キャッシュ機構とか欲しいね。パース済みの構           : コメントの終わりです。造をシリアライズする？

class MuUtil {
  public static function getpath($basepath, $path) {
    $basepath = realpath($basepath);
    $o = getcwd();
    chdir(dirname($basepath));
    $r = realpath($path);
    if ($basepath == $realpath) {
      // avoid include/extends loop
      return false;
    }
    chdir($o);
    return $r;
  }
  public static function serialize_to_file($obj, $path) {
    if ($f = @fopen($path, "w")) {
      if (@fwrite($f, serialize($obj))) {
        @fclose($f);
        return true;
      }
      @fclose($f);
    }
    return false;
  }
  public static function unserialize_from_file($path) {
    if (($c = file_get_contents($path)) !== false) {
      if (($obj = unserialize($c)) !== false) {
        return $obj;
      }
    }
    return false;
  }
}

class MuValueDoesNotExistException extends Exception
{
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
    if (is_numeric($expr)) {
      $current = (strpos($expr, '.') === false) ? intval($expr) : floatval($expr);
    } elseif (($expr{0} == "'" || $expr{0} ==  '"') &&
              $expr{0} == $expr{-1}) {
      $current = substr($expr, 1, strlen($expr) - 2);
    } else {
      $bits = explode(self::VARIABLE_ATTRIBUTE_SEPARATOR, $expr);
      $current = $this->get($bits[0]);
      array_shift($bits);
      while ($bits) {
        if (is_array($current) && array_key_exists($bits[0], $current)) {
          // arrayからの辞書引き(配列キーもコレと一緒)
          $current = $current[$bits[0]];
        } elseif (method_exists($current, $bits[0])) {
          // メソッドコール
          if (($current = call_user_func(array($current, $bits[0]))) === false) {
            return 'method call error';
          }
        } else {
          throw new MuValueDoesNotExistException("Failed lookup for key [{$bits[0]}]");
        }
        array_shift($bits);
      }
    }
    return $current;
  }
  function has_key($key) {
    foreach ($this->dicts as $dict) {
      if (array_key_exists($key, $dict)) {
        return true;
      }
    }
    return false;
  }
  function get($key) {
    foreach ($this->dicts as $dict) {
      if (array_key_exists($key, $dict)) {
        return $dict[$key];
      }
    }
    throw new MuValueDoesNotExistException("Failed lookup for key [$key]");
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

interface MuNode {
  public function _render($context);
}

class MuNodeList {
  private $nodes;
  function __construct() {
    $this->nodes = array();
  }
  public function _render($context) {
    $r = '';
    foreach ($this->nodes as $node) {
      $r .= $node->_render($context);
    }
    return $r;
  }
  public function push($node) {
    $this->nodes[] = $node;
  }
}

class MuErrorNode implements MuNode {
  private $errorMsg;
  private $filename;
  private $linenumber;
  // TODO: php5 don't support array as const
  private static $errorMsgs = array(
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
    'numofparam_firstof_tag' => 'Number of parameters are invalid to firstof tag',
    'numofparam_ifequal_tag' => 'Number of parameters are invalid to ifequal/ifnotequal tag',
    'numofparam_templatetag_tag' => 'Number of parameters are invalid to templatetag tag',
    'invalidparam_templatetag_tag' => 'Invalid parameter(s) specified to templatetag tag',
    'numofparam_widthratio_tag' => 'Number of parameters are invalid to widthratio tag',
    'invalidparam_widthratio_tag' => 'Invalid parameter(s) specified to widthratio tag',
    'invalidparam_filter_variable' => 'Invalid filter name',
    'unknown_tag' => 'Unknown tag is specified',
  );
  function __construct($errorCode, $filename, $linenumber) {
    if (array_key_exists($errorCode, self::$errorMsgs)) {
      $this->errorMsg = self::$errorMsgs[$errorCode];
    } else {
      $this->errorMsg = $errorCode;
    }
    $this->filename = $filename;
    $this->linenumber = $linenumber;
  }
  public function _render($context) {
    return 'file: '. $this->filename .' line: '. $this->linenumber .' '. $this->errorMsg;
  }
}

// 1つのテンプレートをパースしたもの。
class MuFile implements MuNode {
  public $nodelist;        // ファイルをパースしたNodeList
  private $block_dict;     // nodelistの中にあるblock名 => MuBlockNode(の参照)
  public $include_paths;   // includeしたファイル名一覧（キャッシュの確認で使う）
  public $path;            // 自分のファイル名
  private $parent_tfile;   // extendsがある場合の親テンプレート
  function __construct($nodelist, $block_dict, $include_paths,
                       $path = null, $parent_path = null) {
    $this->nodelist = $nodelist;
    $this->block_dict = $block_dict;
    $this->include_paths = $include_paths;
    $this->path = $path;
    if ($parent_path && $path) {
      if (($epath = MuUtil::getpath($path, $parent_path)) === false
          || ($this->parent_tfile = MuParser::parse_from_file($epath)) === false) {
        throw new MuParserException('invalid filename specified on extends');
      }
    }
  }
  public function render($raw_context) {
    return $this->_render(new MuContext($raw_context));
  }
  public function _render($context) {
    if ($this->parent_tfile) {
      foreach ($this->block_dict as $blockname => $blocknode) {
        if (($parent_block = $this->parent_tfile->get_block($blockname)) === false) {
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
    return (isset($this->parent_tfile));
  }
  // extendsやincludeしているファイルがキャッシュ生成よりあとに更新されているか
  public function check_cache_mtime($cache_mtime) {
    foreach ($this->include_paths as $inc_path) {
      if ($cache_mtime < filemtime($inc_path)) {
        return false;
      }
    }
    if (isset($this->parent_tfile)) {
      if ($cache_mtime < filemtime($this->parent_tfile->path)) {
        return false;
      }
      // check parent
      return $this->parent_tfile->check_cache_mtime($cache_mtime);
    }
    return true;
  }
}

class MuTextNode implements MuNode {
  private $text;
  function __construct($text) {
    $this->text = $text;
  }
  public function _render($context) {
    return $this->text;
  }
}

class MuVariableNode implements MuNode {
  private $filter_expression;
  function __construct($filter_expression) {
    $this->filter_expression = $filter_expression;
  }
  public function _render($context) {
    try {
      return $this->filter_expression->resolve($context);
    } catch (MuValueDoesNotExistException $e) {
      return '';
      // return $e->getMessage();
    }
  }
}

class MuIncludeNode implements MuNode {
  private $tplfile;
  function __construct($include_path, $path) {
    if (($epath = MuUtil::getpath($path, $include_path)) === false
        || ($this->tplfile = MuParser::parse_from_file($epath)) === false) {
      throw new MuParserException('include filename is invalid');
    }
  }
  public function _render($context) {
    return $this->tplfile->_render($context);
  }
  public function get_tplfile() {
    return $this->tplfile;
  }
}

class MuBlockNode implements MuNode {
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

class MuCycleNode implements MuNode {
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

class MuDebugNode implements MuNode {
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

class MuFilterNode implements MuNode {
  private $filter_expr;
  private $nodelist;
  function __construct($filter_expr, $nodelist) {
    $this->filter_expr = $filter_expr;
    $this->nodelist = $nodelist;
  }
  function _render($context) {
    $output = $this->nodelist->_render($context);
    $context->update(array('var' => $output));
    try {
      $filtered = $this->filter_expr->resolve($context);
    } catch (MuValueDoesNotExistException $e) {
      $filtered = '';
    }
    $context->pop();
    return $filtered;
  }
}

class MuForNode implements MuNode {
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
    try {
      $values = $context->resolve($this->sequence);
    } catch (MuValueDoesNotExistException $e) {
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
      $rnodelist[] = $this->nodelist_loop->_render($context);
    }
    $context->pop();
    return implode('', $rnodelist);
  }
}

class MuIfNode implements MuNode {
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
        try {
          $value = $context->resolve($bool_expr);
        } catch (MuValueDoesNotExistException $e) {
          $value = false;
        }
        if (($value && !$ifnot) || ($ifnot && !$value)) {
          return $this->nodelist_true->_render($context);
        }
      }
      return $this->nodelist_false->_render($context);
    } else { // self::LINKTYPE_AND
      foreach ($this->bool_exprs as $be) {
        list($ifnot, $bool_expr) = $be;
        try {
          $value = $context->resolve($bool_expr);
        } catch (MuValueDoesNotExistException $e) {
          $value = false;
        }
        if (!(($value && !$ifnot) || ($ifnot && !$value))) {
          return $this->nodelist_false->_render($context);
        }
      }
      return $this->nodelist_true->_render($context);
    }
  }
}

class MuIfEqualNode implements MuNode {
  private $var1;
  private $var2;
  private $nodelist_true;
  private $nodelist_falst;
  private $negate;
  function __construct($var1, $var2, $nodelist_true, $nodelist_false, $negate) {
    $this->var1 = $var1;
    $this->var2 = $var2;
    $this->nodelist_true = $nodelist_true;
    $this->nodelist_false = $nodelist_false;
    $this->negate = $negate;
  }

  public function _render($context) {
    try {
      $val1 = $context->resolve($this->var1);
    } catch (MuValueDoesNotExistException $e) {
    }
    try {
      $val2 = $context->resolve($this->var2);
    } catch (MuValueDoesNotExistException $e) {
    }
    if (($this->negate && $val1 != $val2) || (!$this->negate && $val1 == $val2)) {
      return $this->nodelist_true->_render($context);
    }
    return $this->nodelist_false->_render($context);
  }
}

class MuSpacelessNode implements MuNode {
  private $nodelist;
  function __construct($nodelist) {
    $this->nodelist = $nodelist;
  }
  public function _render($context) {
    // MEMO: この実装は問題あると思うけど、Djangoの元の実装にあわせている
    return preg_replace('/>\s+</', '><', trim($this->nodelist->_render($context)));
  }
}

class MuTemplateTagNode implements MuNode {
  private $tagtype;
  // TODO: php5 don't support array as const...
  public static $mapping = array (
    'openblock' => MuParser::BLOCK_TAG_START,
    'closeblock' => MuParser::BLOCK_TAG_END,
    'openvariable' => MuParser::VARIABLE_TAG_START,
    'closevariable' => MuParser::VARIABLE_TAG_END,
    'openbrace' => MuParser::SINGLE_BRACE_START,
    'closebrace' => MuParser::SINGLE_BRACE_END,
    'opencomment' => MuParser::COMMENT_TAG_START,
    'closecomment' => MuParser::COMMENT_TAG_END,
  );
  function __construct($tagtype) {
    $this->tagtype = $tagtype;
  }
  public function _render($context) {
    return self::$mapping[$this->tagtype];
  }
}

class MuWidthRatioNode implements MuNode {
  private $val_expr;
  private $max_expr;
  private $max_width;
  function __construct($val_expr, $max_expr, $max_width) {
    $this->val_expr = $val_expr;
    $this->max_expr = $max_expr;
    $this->max_width = $max_width;
  }
  public function _render($context) {
    try {
      $value = $this->val_expr->resolve($context);
      $maxvalue = $this->max_expr->resolve($context);
    } catch (MuValueDoesNotExistException $e) {
      return '';
    }
    $value = floatval($value);
    $maxvalue = floatval($maxvalue);
    $ratio = ($value / $maxvalue) * $this->max_width;
    return strval(intval(round($ratio)));
  }
}

class MuNowNode implements MuNode {
  private $format_string;
  function __construct($format_string) {
    $this->format_string = $format_string;
  }
  public function _render($context) {
    return date($this->format_string);
  }
}

class MuFirstOfNode implements MuNode {
  private $vars;
  function __construct($vars) {
    $this->vars = $vars;
  }
  public function _render($context) {
    foreach ($this->vars as $var) {
      try {
        $value = $context->resolve($var);
      } catch (MuValueDoesNotExistException $e) {
      }
      if (isset($value)) {
        return $value;
      }
    }
    return '';
  }
}

class MuFilterExpression {
  private $var;
  private $filters;

  // TODO: php5 don't support array as const...
  private static $valid_filternames = array (
    'addslashes' => true,
    'length' => true,
    'escape' => true,
    'stringformat' => true,
    'urlencode' => true,
    'linebreaksbr' => true,
    'date' => true,
    'join' => true,
    'add' => true,
    'capfirst' => true,
    'center' => true,
    'cut' => true,
    'default' => true,
    'default_if_none' => true,
    'divisibleby' => true,
    'filesizeformat' => true,
    'first' => true,
    'fix_ampersands' => true,
    'floatformat' => true,
    'get_digit' => true,
    'length_is' => true,
    'linebreaks' => true,
    'linenumbers' => true,
    'ljust' => true,
    'lower' => true,
    'make_list' => true,
    'pprint' => true,
    'random' => true,
    'removetags' => true,
    'rjust' => true,
    'slice' => true,
    'striptags' => true,
    'title' => true,
    'truncatewords' => true,
    'upper' => true,
    'wordcount' => true,
    'wordwrap' => true,
    'yesno' => true,
  );

  function __construct($token) {
    // $token = 'variable|default:"Default value"|date:"Y-m-d"'
    // ってのがあったら、
    // $this->var = 'variable'
    // $this->filters = 'array(array('default, 'Default value'), array('date', 'Y-m-d'))'
    // ってする。
    // Djangoのは_で始まったらいけないらしい。

    $fils = MuParser::smart_split(trim($token), '|', false, True);
    $this->var = array_shift($fils);
    $this->filters = array();
    foreach ($fils as $fil) {
      $f = MuParser::smart_split($fil, ':', True, false);
      if (isset(self::$valid_filternames[$f[0]])) {
        $this->filters[] = $f;
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
          # arrayはcount、stringはstrlen(length_isも)
          if (is_array($val)) {
            $val = count($val);
          } else if (is_string($val)) {
            $val = strlen($val);
          }
          break;
        case 'length_is':
          if (is_array($val)) {
            $val = (count($val) == intval($fil[1]));
          } else if (is_string($val)) {
            $val = (strlen($val) == intval($fil[1]));
          }
          break;
        case 'escape':
          $val = htmlspecialchars($val, ENT_QUOTES);
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
        case 'time':
          $val = $val instanceof DateTime ? $val : new DateTime($val);
          $val = $val->format($fil[1]);
          break;
        case 'join':
          if (is_array($val)) {
            $val = implode($fil[1], $val);
          }
          break;
        case 'add':
          $val = int($val) + int($fil[1]);
          break;
        case 'capfirst':
          $val = ucfirst($val);
          break;
        case 'center':
          $len = intval($fil[1]);
          $val_len = strlen($val);
          if ($val_len < $len) {
            substr_replace(str_repeat(' ', $len), $val, ($len - $val_len) / 2);
          }
          break;
        case 'cut':
          // TODO: check specification of python replace
          $val = str_replace($fil[1], '', $val);
          break;
        case 'default':
          $val = isset($val) ? $val : $fil[1];
          break;
        case 'default_if_none':
          $val = is_null($val) ? $fil[1] : $val;
          break;
        case 'divisibleby':
          $val = (intval($val) % intval($fil[1]) == 0);
          break;
        case 'filesizeformat':
          if (!ctype_digit($val)) {
            $val = '0 bytes';
          } else {
            $bytes = floatval($val);
            if ($bytes < 1024) {
              // TODO: 1 byte
              $val = "$bytes bytes";
            } elseif ($bytes < 1024 * 1024) {
              $val = sprintf("%.1f KB", (bytes / 1024));
            } elseif ($bytes < 1024 * 1024 * 1024) {
              $val = sprintf("%.1f MB", (bytes / (1024 * 1024)));
            } else {
              $val = sprintf("%.1f GB", (bytes / (1024 * 1024 * 1024)));
            }
          }
          break;
        case 'first':
          if (is_array($val) && array_key_exists(0, $val)) {
            $val = $val[0];
          } else {
            $val = '';
          }
          break;
        case 'fix_ampersands':
          $val = preg_replace('/&(?!(\w+|#\d+);)/', '&amp;', $val);
          break;
        case 'floatformat':
          $f = floatval($val);
          $d = array_key_exists(1, $fil) ? intval($fil[1]) : -1;
          $m = $f - intval($f);
          if ($m != 0 && $d < 0) {
            $val = strval(intval($f));
          } else {
            $d = abs($d);
            $val = sprintf("%.{$d}f", $f);
          }
          break;
        case 'get_digit':
          $arg = intval($fil[1]);
          $val = intval($val);
          if ($arg >= 1) {
            $val = intval(substr(strval($val), -$arg, 1));
          }
          break;
        case 'linebreaks':
          $val = preg_replace('/\r\n|\r|\n/', "\n", $val);
          $paras = preg_split('/\n{2,}/', $val);
          $ret = array();
          foreach ($paras as $p) {
            $ret[] = '<p>'. nl2br(trim($p)) .'</p>';
          }
          $val = implode("\n\n", $ret);
          break;
        case 'linenumbers':
          $lines = explode("\n", $val);
          $count = count($lines);
          $width = strlen(strval($count));
          for ($i = 0; $i < $count; $i++) {
            $lines[$i] = sprintf("%{$width}d. ", $i + 1).
                        htmlspecialchars($lines[$i], ENT_QUOTES);
          }
          $val = implode("\n", $lines);
          break;
        case 'ljust':
          $len = intval($fil[1]);
          $val_len = strlen($val);
          if ($val_len < $len) {
            $val .= str_repeat(' ', $len - $val_len);
          }
          break;
        case 'lower':
          $val = strtolower($val);
          break;
        case 'make_list':
          $sval = strval($val);
          $sval_len = strlen($sval);
          $val = array();
          for ($i = 0; $i < $sval_len; $i++) {
            $val[] = $sval[$i];
          }
          break;
        case 'pprint':
          $val = print_r($val, true);
          break;
        case 'random':
          $val = array_rand($val);
          break;
        case 'removetags':
          // not tested...
          $tags = array_map(preg_quote, explode(' ', $fil[1]));
          $tags_re = '('. implode('|', $tags) .')';
          $starttag_re = '/<'. $tags_re .'(\/?>|(\s+[^>]*>))';
          $endtag_re = '/<\/'. $tags_re .'>/';
          $val = preg_replace($endtag_re, '', preg_replace($starttag_re, '', $val));
          break;
        case 'rjust':
          $len = intval($fil[1]);
          $val_len = strlen($val);
          if ($val_len < $len) {
            $val = str_repeat(' ', $len - $val_len) + $val;
          }
          break;
        case 'slice':
          // TODO: chanto test
          list($st, $ed) = explode(':', $val);
          if (is_array($val)) {
            $val = array_slice($val, $st, $ed);
          } else if (is_string($val)) {
            $val = substr($val, $st, $ed);
          }
          break;
        case 'striptags':
          $val = strip_tags($val);
          break;
        case 'title':
          $val = ucwords($val);
          break;
        case 'truncatewords':
          $limit = intval($fil[1]);
          $words = str_word_count($val, 2);
          $pos = array_keys($words);
          if (count($pos) < $limit) {
            $val = substr($val, 0, $pos[$limit]);
          }
          break;
        case 'lower':
          $val = strtoupper($val);
          break;
        case 'wordcount':
          $val = str_word_count($val, 0);
          break;
        case 'wordwrap':
          $val = wordwrap($val, $fil[1]);
          break;
        case 'yesno':
          $arg = isset($fil[1]) ? $fil[1] : 'yes,no,maybe';
          $bits = explode(',', $arg);
          if (count($bits) >= 2) {
            list($yes, $no, $maybe) = $bits;
            if (!isset($maybe)) { $maybe = $no; }
            if (is_null($value)) {
              $val = $maybe;
            } elseif ($value) {
              $val = $yes;
            } else {
              $val = $no;
            }
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

class MuParserException extends Exception
{
}

class MuParser {
  private $template;                // パース前のテンプレート文字列
  private $template_len;            // テンプレート文字列の長さ
  private $template_path;           // テンプレートのパス(あれば)
  private $serialize_path;          // パース済みのテンプレートをシリアライズしたものの保管path
  private $block_dict = array();    // blockの名前 => blockへの参照
  private $extends;                 // extendsのファイル名
  private $include_paths = array(); // includeしているファイル名の一覧
  private $spos = 0;                // 現在パース中の位置

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

  function __construct($template, $template_path = null, $serialize_path = null) {
    $this->template = $template;
    $this->template_len = strlen($template);
    $this->template_path = $template_path;
    $this->serialize_path = $serialize_path;
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
          // 何度もsmart_splitする場合は$decodeをfalseにしておく(ex. filter)
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
                $ret[] = $buf;
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
      $ret[] = $buf;
    }
    return $ret;
  }

  // 終了タグ(#}とか)を探して、その位置を返す
  private function find_closetag($closetag) {
    if (($fpos = strpos($this->template, $closetag, $this->spos)) === false) {
      throw new MuParserException('cannot find $closetag');
    }
    return $fpos;
  }

  public function make_errornode($errorCode) {
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
    return new MuErrorNode($errorCode, $this->template_path, $ln);
  }

  // {% %}の中身をパースして、MuNodeを返す。
  // extendsは頭に書かないといけない
  private function parse_block() {
    $this->spos += 2;
    $lpos = $this->find_closetag(self::BLOCK_TAG_END);
    $in = $this->smart_split(substr($this->template, $this->spos, $lpos - $this->spos));
    switch ($in[0]) {
      // TODO: 引数の数チェックを全般
      case 'extends':
        if (isset($this->extends)) {
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
        return null;
      case 'include':
        if (count($in) != 2) {
          return $this->make_errornode('numofparam_include_tag');
        }
        $param = explode('"', $in[1]);
        if (count($param) != 3) {
          // Djangoは変数もOKだけどね
          return $this->make_errornode('invalidparam_include_tag');
        }
        $this->spos = $lpos + 2;
        $node = new MuIncludeNode($param[1], $this->template_path);
        $tplfile = $node->get_tplfile();
        $this->include_paths[] = $tplfile->path;
        unset($tplfile->path);
        // インクルードのインクルード先が更新されたらキャッシュも更新
        if (isset($tplfile->include_paths)) {
          $this->include_paths = array_merge($this->include_paths, $tplfile->include_paths);
          unset($tplfile->include_paths);
        }
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
          $reversed = false;
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
          if (strpos($boolpair, ' ') !== false) {
            // TODO: error handling
            list($not, $boolvar) = explode(' ', $boolpair);
            if ($not != 'not') {
              return $this->make_errornode('invalidparam_if_tag');
            }
            $boolvars[] = array(True, $boolvar);
          } else {
            $boolvars[] = array(false, $boolpair);
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
        if (($filter_expr = new MuFilterExpression('var|'. $in[1])) === false) {
          return $this->make_errornode('invalidparam_filter_tag');
        }
        list($nodelist) = $this->_parse(array('endfilter'));
        $node = new MuFilterNode($filter_expr, $nodelist);
        break;
      case 'firstof':
        if (count($in) < 2) {
          return $this->make_errornode('numofparam_firstof_tag');
        }
        array_shift($in);
        $this->spos = $lpos + 2;
        $node = new MuFirstOfNode($in);
        break;
      case 'ifequal':
      case 'ifnotequal':
        if (count($in) != 3) {
          return $this->make_errornode('numofparam_ifequal_tag');
        }
        $this->spos = $lpos + 2;
        $negate = ($in[0] == 'ifnotequal');
        $endtag = 'end' . $in[0];
        list($nodelist_true, $nexttag) =
          $this->_parse(array('else', $endtag));
        if ($nexttag == 'else') {
          list($nodelist_false) = $this->_parse(array($endtag));
        } else {
          $nodelist_false = new MuNodeList();
        }
        $node = new MuIfEqualNode($in[1], $in[2], $nodelist_true, $nodelist_false, $negate);
        break;
      case 'spaceless':
        $this->spos = $lpos + 2;
        list($nodelist) = $this->_parse(array('endspaceless'));
        $node = new MuSpacelessNode($nodelist);
        break;
      case 'templatetag':
        if (count($in) != 2) {
          return $this->make_errornode('numofparam_templatetag_tag');
        }
        if (!array_key_exists($in[1], MuTemplateTagNode::$mapping)) {
          return $this->make_errornode('invalidparam_templatetag_tag');
        }
        $this->spos = $lpos + 2;
        $node = new MuTemplateTagNode($in[1]);
        break;
      case 'comment':
        $this->spos = $lpos + 2;
        // TODO: not parse but skip
        $this->_parse(array('endcomment'));
        break;
      case 'widthratio':
        if (count($in) != 4) {
          return $this->make_errornode('numofparam_widthratio_tag');
        }
        if (!ctype_digit($in[3])) {
          return $this->make_errornode('invalidparam_widthratio_tag');
        }
        $this->spos = $lpos + 2;
        // MEMO: $in[3]はfloatかもしらんがな…
        $node = new MuWidthRatioNode(new MuFilterExpression($in[1]),
                                     new MuFilterExpression($in[2]), intval($in[3]));
        break;
      case 'endblock':
      case 'else':
      case 'endif':
      case 'endfor':
      case 'endfilter':
      case 'endifequal':
      case 'endifnotequal':
      case 'endspaceless':
      case 'endcomment':
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
    $lpos = $this->find_closetag(self::VARIABLE_TAG_END);
    // TODO: handle empty {{ }}
    if (($fil = new MuFilterExpression(
                  substr($this->template, $this->spos, $lpos - $this->spos))) === false) {
      return $this->make_errornode('invalidparam_filter_variable');
    }
    $node = new MuVariableNode($fil);
    $this->spos = $lpos + 2;
    return $node;
  }

  private function parse_comment() {
    $this->spos += 2;
    $lpos = $this->find_closetag(self::COMMENT_TAG_END);
    $this->spos = $lpos + 2;
  }

  static public function parse_from_file($template_path, $serialize_store = null) {
    // キャッシュのチェック
    if (isset($serialize_store)) {
      if (stristr($serialize_store, 'file://') == 0) {
        // TODO: check whether absolute path or not
        if (($spath = realpath(substr($serialize_store, 7))) === false) {
          return false;
        }
        $sfpath = $spath . '/' . basename($template_path) . '.cache';
        if (file_exists($sfpath)) {
          if (($sfmtime = filemtime($sfpath)) === false) {
            return false;
          }
          if (filemtime($template_path) <= $sfmtime) {
            $mf = MuUtil::unserialize_from_file($sfpath);
            if (!$mf->check_cache_mtime($sfmtime)) {
              unset($mf);
            }
          }
        }
      } else {
        // TODO: now file cache only
        return false;
      }
    }
    // キャッシュになかったら生成
    if (!isset($mf)) {
      if (($t = file_get_contents($template_path)) === false) {
        return false;
      }
      $p = new MuParser($t, $template_path, $serialize_path);
      try {
        list($nl) = $p->_parse(array());
      } catch (MuParserException $e) {
        $nl = new MuNodeList();
        $nl->push($p->make_errornode($e->getMessage()));
      }
      $mf = new MuFile($nl, $p->block_dict, $p->include_paths, $template_path, $p->extends);
      // 指定したキャッシュに保存
      if (isset($sfpath)) {
        MuUtil::serialize_to_file($mf, $sfpath);
      }
    }
    return $mf;
  }

  static public function parse($templateStr) {
    $p = new MuParser($templateStr);
    try {
      list($nl) = $p->_parse(array());
    } catch (MuParserException $e) {
      $nl = new MuNodeList();
      $nl->push($p->make_errornode($e->getMessage()));
    }
    return new MuFile($nl, $p->block_dict, $p->include_paths);
  }

  private function add_textnode($nodelist, $tspos, $epos) {
    if ($tspos < $epos) {
      $nodelist->push(new MuTextNode(
        substr($this->template, $tspos, $epos - $tspos)));
    }
  }

  private function _parse($parse_until) {
    $nl = new MuNodeList();
    $fspos = $tspos = $this->spos;
    while (true) {
      $this->spos = strpos($this->template, self::SINGLE_BRACE_START, $this->spos);
      if ($this->spos === false) {
        if (count($parse_until) != 0) {
          $this->spos = $fspos; // for show correct lineno
          throw new MuParserException('cannot find close tags (' .
                                      implode(', ', $parse_until) .
                                      ')');
        }
        $this->spos = $this->template_len;
        $this->add_textnode($nl, $tspos, $this->template_len);
        return array($nl, null);
      }
      switch (substr($this->template, $this->spos, 2)) {
        case self::BLOCK_TAG_START:
          $this->add_textnode($nl, $tspos, $this->spos);
          $node = $this->parse_block();
          $tspos = $this->spos;
          if (is_string($node)) {
            // close tag
            if (in_array($node, $parse_until)) {
              return array($nl, $node);
            } else {
              throw new MuParserException('invalid close tag');
            }
          } elseif (isset($node)) {
            $nl->push($node);
          }
          break;
        case self::VARIABLE_TAG_START:
          $this->add_textnode($nl, $tspos, $this->spos);
          $node = $this->parse_variable();
          $nl->push($node);
          $tspos = $this->spos;
          break;
        case self::COMMENT_TAG_START:
          $this->add_textnode($nl, $tspos, $this->spos);
          $node = $this->parse_comment();
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
      $pargs[] = self::getArgument($arg);
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
            $pargs[] = self::getArgument($key, false). ' => '. self::getArgument($value, false);
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
        $p['backtrace'][] = $trace;
      } elseif (isset($bt['function'])) {
        $trace = 'in function '.$bt['function'].'(';
        if (isset($bt['args'])) {
          $trace .= implode(', ', self::getArguments($bt['args']));
        }
        $trace .= ')';
        $p['backtrace'][] = $trace;
      } else {
        $p['backtrace'][] = 'unknown';
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
