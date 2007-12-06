<?php
// GunyaTemplate (c) Brazil, Inc.
// originally developed by Tasuku SUENAGA a.k.a. gunyarakun
// developed from 2007/09/04

// �߷���Ū
// - ���Υե�������������OK
// - ��®
// - ����å��嵡ǽ
// - �إå����եå���include
// - �ƥ�ץ졼����ǥ롼�פ�������
// - �ƥ�ץ졼�ȥե������ľ��html�Ȥ��Ʊ�����ǽ
// - url���󥳡��ɤ�htmlspecialchars���ñ�˻���Ǥ���
// - PHP5���׵᤹���

// �Ȥ����γ���(����ɸ)
// - ���٤ƤΥǡ�����ϥå���������ʻ��������ǡ�����������ʤ��Ȥ����ʤ���
// - �ƥ�ץ졼�ȥե��������ꤹ��
// - ��������(����å����̵ͭ�����Ǥ���)
// - �������줿���Ƥ��֤äƤ���
// - echo�ʤ�ʤ�ʤꤪ������

// ����ˡ

// �ѿ�
// {{ �ѿ�̾ }}             : �ѿ�̾���ִ�

// �֥�å�
// {% include "filename" %} : �ƥ�ץ졼�ȤΥ��󥯥롼��
// {% extends "filename" %} : �ƥ�ץ졼�Ȥγ�ĥ
// {% block blockname %}    : �֥�å��λϤޤ�
// {% endblock %}           : �֥�å��ν����
// {% for item in items %}  : �ѿ�items����item����Ф�
// {% endfor %}             : for�ν����
// {% cycle val1,val2 %}    : for�롼�פ����val1,val2���ߤ˽Ф�(ɽ�ǹԤ����طʿ��Ȥ�)
// {% if cond %} {% else %} : cond��郎�������줿�Ȥ�����������
// {% endif %}              : if�ν����
// {% debug %}              : �ƥ�ץ졼�Ȥ��Ϥ��줿��������פ���
// {% now "format" %}       : ���ߤ����դ����ե����ޥåȤǽ��Ϥ��ޤ�
// {% filter fil1|fil2 %}   : �֥�å��⥳��ƥ�Ĥ�ե��륿�ˤ����ޤ�
// {% endfilter %}          : filter�ν����
// {# comment #}            : ������

// �ѥ���
// {{ �ѿ�̾|�ѥ���1|�ѥ���2 }} : �ѿ���ե��륿���ƽ��Ϥ���
// |addslashes                  : \��\\�ˡ�JavaScript�����Ϥ�����Ȥ�����������
// |length                      : �����Ĺ��
// |escape                      : %<>"'�Υ���������
// |stringformat:"format"       : ���ꤷ���ե����ޥåȤ��ͤ�ե����ޥå�
// |urlencode                   : url���󥳡���
// |linebreaksbr                : ���Ԥ�<br />���Ѵ�

// �ü���ѿ�
// forloop.counter     : ���ߤΥ롼�ײ���ֹ� (1 ������������)
// forloop.counter0    : ���ߤΥ롼�ײ���ֹ� (0 ������������)
// forloop.revcounter  : ��������������롼�ײ���ֹ� (1 ������������)
// forloop.revcounter0 : ��������������롼�ײ���ֹ� (0 ������������)
// forloop.first       : �ǽ�Υ롼�פǤ���� true �ˤʤ�ޤ�
// forloop.last        : �Ǹ�Υ롼�פǤ���� true �ˤʤ�ޤ�
// forloop.parentloop  : ����ҤΥ롼�פξ�硢��ľ�Υ롼�פ�ɽ���ޤ�
// block.super         : �ƥƥ�ץ졼�Ȥ�block����Ȥ���Ф������Ƥ��ɲä������������

class GTContext {
  // �ƥ�ץ졼�Ȥ����ƤϤ���ͤξ�����ݻ����륯�饹
  private $dicts;
  const VARIABLE_ATTRIBUTE_SEPARATOR = '.';
  function __construct($dict = array()) {
    $this->dicts = array($dict);
  }
  // �ɥå�Ϣ��ɽ�������ͤ���Ф�
  function resolve($expr) {
    $bits = explode(self::VARIABLE_ATTRIBUTE_SEPARATOR, $expr);
    $current = $this->get($bits[0]);
    array_shift($bits);
    while ($bits) {
      if (is_array($current) && array_key_exists($bits[0], $current)) {
        // array����μ������(���󥭡��⥳��Ȱ��)
        $current = $current[$bits[0]];
      } elseif (is_callable($current)) {
        // �ؿ�������
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

// 1�ĤΥƥ�ץ졼�ȥե������ѡ���������Ρ�
// ���������˽Ф�Τ�������
class GTFile extends GTNode {
  public $nodelist;        // �ե������ѡ�������NodeList
  private $block_dict;      // nodelist����ˤ���block̾ => GTBlockNode(�λ���)
  private $parent_tfile;    // extends��������οƥƥ�ץ졼��
  function __construct($nodelist, $block_dict, $parentPath = false) {
    $this->nodelist = $nodelist;
    $this->block_dict = $block_dict;
    if ($parentPath) {
      $p = new GTParser();
      if (($this->parent_tfile = $p->parse_from_file($parentPath)) === FALSE) {
        // TODO: ���顼���������ƥ�ץ졼��̾������˶����Ƥ�����
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
    // ¹��������줿�֥�å�̾�ǡ��Ƥˤ��������Ƥ��ʤ�����
    // �ƤοƤˤ��������Ƥ�����Τ��ᡢ
    // ¹����Ƥ˥֥�å���ܤ�
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
    // FIXME: �������ƥ������å���̵�¥롼�ץ����å�
    $p = new GTParser();
    if (($this->tplfile = $p->parse_from_file($includePath)) === FALSE) {
      // TODO: ���顼���������ƥ�ץ졼��̾������˶����Ƥ�����
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
    $context->set('block', $this); // block.super��
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
    // �äƤΤ����ä��顢
    // $this->var = 'variable'
    // $this->filters = 'array(array('default, 'Default value'), array('date', 'Y-m-d'))'
    // �äƤ��롣
    // Django�Τ�_�ǻϤޤä��餤���ʤ��餷����

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
    // eval�Ȥ�call_user_func_array������switch-case��dispatch�����ɤ�����

    $val = $context->resolve($this->var);
    foreach ($this->filters as $fil) {
      // TODO: ���������å�
      switch ($fil[0]) {
        case 'addslashes':
          $val = addslashes($val);
          break;
        case 'length':
          # array��count��string��strlen
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
          // $fil[1]�˥�Ф�ʸ������ʤ��褦�˵���Ĥ�������
          $val = sprintf($fil[1], $val);
          break;
        case 'urlencode':
          $val = urlencode($val);
          break;
        case 'linebreaksbr':
          $val = nl2br($val);
          break;
        default:
          // �ɤ�ʥե��륿̾���ޥ����ä��������Ƥ����������ɡ�
          // ������ǰ�̣���ä�ʸ�������äƤ�ȥޥ��������Կ��ڤ�

          // TODO: �ե��륿̾�򥢥�ե��٥åȤ�_�Ȥ��Τߤ˥ե��륿���Ƥ���
          $val = 'unknown filter specified';
      }
    }
    return $val;
  }
}

class GTParser {
  private $template;             // �ѡ������Υƥ�ץ졼��ʸ����
  private $errorStr;             // ���顼ʸ����
  private $block_dict = array(); // block��̾�� => block�ؤλ���
  private $extends = false;      // extends�ξ��Υե�����̾

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
  // FIXME: ������Ĺ���Ǥ������2���ѡ�������˻���ФäƤޤ�

  // "��'�ǥ������Ȥ��줿��Τ�����ƥ��ڡ�����ʬ��
  // "��'���"'��Ȥ����ˤϡ�\"\'�Ȥ��롣
  // ��ľ������ɽ���ǽ񤱤Ф褫�ä������ޤ����ä���
  // �ޥ���Х��ȥ����դʥǥ�ߥ���Ȥ��褦�˵���Ĥ�������

  // $decode : quote���\�ǤΥ��������פ��ᤷ��Ÿ�����뤫�ɤ���
  // $quote  : quoteʸ�����Τ�Τ���Ϥ��뤫�ɤ���
  static public function smart_split($text, $delimiter = ' ', $decode = True, $quote = True) {
    $epos = strlen($text);
    $ret = array();
    $mode = 'n';  // 'n': not quoted, 'd': in ", 'q': in '
    for ($spos = 0; $spos < $epos; $spos++) {
      $a = $text[$spos];
      switch ($a) {
        case '\\':
          // ���٤�smart_split�������$decode��False�ˤ��Ƥ���(ex. filter)
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

  // ��λ����(#}�Ȥ�)��õ���ơ����ΰ��֤��֤�
  private function find_closetag(&$spos, &$epos, $closetag) {
    if (($fpos = strpos($this->template, $closetag, $spos)) === FALSE || $fpos >= $epos) {
      $this->errorStr = "�������Ĥ����Ƥʤ��褦�Ǥ�($closetag�����Ĥ���ޤ���)��";
      return FALSE;
    }
    return $fpos;
  }

  // ľ���else��endblock��endif��endfor��õ��
  private function find_endtags($spos, &$epos, $endtags) {
    // �ޤ���ľ��Υ֥�å����ϥ�����õ����
    while (($spos = strpos($this->template, self::BLOCK_TAG_START, $spos)) !== FALSE
           && $spos < $epos) {
      $spos += 2;
      if (($lpos = $this->find_closetag($spos, $epos, self::BLOCK_TAG_END)) !== FALSE
          && $lpos < $epos) {
        // ������Ȥ�trim���ƥ����å�
        $c = trim(substr($this->template, $spos, $lpos - $spos));
        if (in_array($c, $endtags)) {
          return array($spos - 2, $lpos + 2, $c);
        }
        $spos = $lpos + 2;
      }
    }
    $this->errorStr = "block/if/for���Ĥ����Ƥ��ʤ��褦�Ǥ���";
    return FALSE;
  }

  // {% %}����Ȥ�ѡ������ơ�GTNode���֤���
  // extends��Ƭ�˽񤫤ʤ��Ȥ����ʤ�
  private function parse_block(&$spos, &$epos) {
    $spos += 2;
    if (($lpos = $this->find_closetag($spos, $epos, self::BLOCK_TAG_END)) === FALSE) {
      return FALSE;
    }
    $in = $this->smart_split(substr($this->template, $spos, $lpos - $spos));
    switch ($in[0]) {
      // TODO: �����ο������å�������
      case 'extends':
        if ($this->extends !== FALSE) {
          $this->errorStr = 'extends�ϣ��Ĥ�����������Ǥ��ޤ���';
          return FALSE;
        }
        if (count($in) != 2) {
          $this->errorStr = 'extends�Υѥ�᡼������ꤷ�Ƥ�������';
          return FALSE;
        }
        $param = explode('"', $in[1]);
        if (count($param) != 3) {
          // Django���ѿ���OK�����ɤ�
          $this->errorStr = 'extends�Υѥ�᡼���ϥե�����̾�ΤߤǤ�';
          return FALSE;
        }
        $this->extends = $param[1];
        $spos = $lpos + 2;
        break;
      case 'include':
        if (count($in) != 2) {
          $this->errorStr = 'include�Υѥ�᡼������ꤷ�Ƥ�������';
          return FALSE;
        }
        $param = explode('"', $in[1]);
        if (count($param) != 3) {
          // Django���ѿ���OK�����ɤ�
          $this->errorStr = 'include�Υѥ�᡼���ϥե�����̾�ΤߤǤ�';
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
          $this->errorStr = 'Ʊ��̾����block�ϣ��Ĥ�����������Ǥ��ޤ���';
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
          $this->errorStr = 'for�Υѥ�᡼���ο��ޤ��Ͻ񼰤������Ǥ�';
          return FALSE;
        }
        if (count($in) == 5) {
          if ($in[4] == 'reversed') {
            $reversed = True;
          } else {
            $this->errorStr = 'for��5�Ĥ�Υѥ�᡼����reversed�Τ߻���Ǥ��ޤ���';
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
          $this->errorStr = 'cycle�ˤϥѥ�᡼��������ɬ�פǤ���';
          return FALSE;
        }
        $cyclevars = explode(',', $in[1]);
        if (count($cyclevars) == 0) {
          $this->errorStr = 'cycle�ˤ�,�Ƕ��ڤ�줿ʸ����ɬ�פǤ���';
          return FALSE;
        }
        $node = new GTCycleNode($cyclevars);
        $spos = $lpos + 2;
        break;
      case 'if': // else, endif
        array_shift($in);
        if (count($in) < 1) {
          $this->errorStr = 'if�Υѥ�᡼��������ޤ���';
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
            $this->errorStr = 'if��and��or�򺮤��뤳�Ȥ��Ǥ��ޤ���';
            return FALSE;
          }
        }
        foreach ($boolpairs as $boolpair) {
          // not�ν���
          if (strpos($boolpair, ' ') !== FALSE) {
            // TODO: error handling
            list($not, $boolvar) = explode(' ', $boolpair);
            if ($not != 'not') {
              $this->errorStr = 'if��not������٤��Ȥ�����̤Τ�Τ����äƤ��ޤ���';
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
          $this->errorStr = 'now�Ͻ�ʸ����ɬ�פǤ�';
          return FALSE;
        }
        $param = explode('"', $in[1]);
        if (count($param) != 3) {
          $this->errorStr = 'now�ν�ʸ�����"�Ǥ����äƤ�������';
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

  // {{ }}����Ȥ�ѡ���
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

  // {# #}����Ȥ�ѡ���
  private function parse_comment(&$spos, &$epos) {
    $spos += 2;
    if (($lpos = $this->find_closetag($spos, $epos, self::COMMENT_TAG_END)) === FALSE) {
      return FALSE;
    }
    $spos = $lpos + 2; // #}�Τ���
  }

  public function parse_from_file($templatePath) {
    if (($t = file_get_contents($templatePath)) === FALSE) {
      $this->errorStr = '�ե����뤬�����ޤ���';
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
      // �����γ�����ʬ�򸫤Ĥ���
      $nspos = strpos($this->template, self::SINGLE_BRACE_START, $spos);
      if ($nspos === FALSE) {
        $nspos = $epos;
      }
      if ($spos < $nspos) {
        // �����ʳ�����ʬ��GTTextNode�Ȥ�����¸
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
            // ñ�ʤ�{���ɤߤȤФ�
            $nspos += 1;
        }
      }
      $spos = $nspos;
    }
    return $nl;
  }
}
?>
