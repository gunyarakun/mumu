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
// - PHP4�ʾ��ư���褦�ˡ��Ȼפä����ɤ�����᤿

// �Ȥ����γ���
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
// {# comment #}            : ������

// �ѥ���
// {{ �ѿ�̾|�ѥ���1|�ѥ���2 }} : �ѿ���ե��륿���ƽ��Ϥ���
// |addslashes                  : \��\\�ˡ�JavaScript�����Ϥ�����Ȥ�����������
// |length                      : �����Ĺ��
// |escape                      : %<>"'�Υ���������
// |stringformat:"format"       : ���ꤷ���ե����ޥåȤ��ͤ�ե����ޥå�
// |truncatewords:"100"         : ���ꤷ��ʸ�����ޤ��ڤ�ͤ��
// |urlencode                   : url���󥳡���

// �ü���ѿ�
// forloop.counter     : ���ߤΥ롼�ײ���ֹ� (1 ������������)
// forloop.counter0    : ���ߤΥ롼�ײ���ֹ� (0 ������������)
// forloop.revcounter  : ��������������롼�ײ���ֹ� (1 ������������)
// forloop.revcounter0 : ��������������롼�ײ���ֹ� (0 ������������)
// forloop.first       : �ǽ�Υ롼�פǤ���� true �ˤʤ�ޤ�
// forloop.last        : �Ǹ�Υ롼�פǤ���� true �ˤʤ�ޤ�
// forloop.parentloop  : ����ҤΥ롼�פξ�硢��ľ�Υ롼�פ�ɽ���ޤ�
// block.super         : �ƥƥ�ץ졼�Ȥ�block����Ȥ���Ф������Ƥ��ɲä������������

// �Ρ��ɤ������ѡ������줿�ƥ�ץ졼�Ȥ�GTNodeList��ɽ�����
// GTNode��$nodelist�Ȥ���GTNodeList�Υ��Ф���ľ�礬���롣
// GTNodeList��$nodes�Ȥ���GTNode��array����ġ�
// GTNodeList��render��Ƥ٤С��ƥ�ץ졼�Ȥ��ͤ����ƤϤ���롣

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
    return $filter_expression->resolve($context);
  }
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
      // TODO: ���Ƚ��(resolve)
      return $nodelist_false->render($context);
    } else { // self::LINKTYPE_AND
      // TODO: ���Ƚ��(resolve)
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
    // �äƤΤ����ä��顢
    // $this->var = 'variable'
    // $this->filters = 'array(array('default, 'Default value'), array('date', 'Y-m-d'))'
    // �äƤ��롣
    // Django�Τ�_�ǻϤޤä��餤���ʤ��餷����
    $fils = GTParser::smart_split($token, '|');
    $this->var = array_shift($fils);
    foreach ($fils as $fil) {
      $fils = GTParser::smart_split($fil, ':');
    }
  }

  // TODO: support ignore_failures
  public function resolve($context) {

  }
}

class GTParser {
  private $template; // �ѡ������Υƥ�ץ졼��ʸ����
  private $errorStr; // ���顼ʸ����
  private $ptemplate; // �ѡ�����Υƥ�ץ졼��(Node��array)

  // �ѡ�����
  private $blockmode; // 'f': for, 'i': if, 'e': else
  private $outmode;   // 'a': �ɲ� 'i': ̵�� 'b': �֥�å����ɲ�

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

  // "��'�ǥ������Ȥ��줿��Τ�����ƥ��ڡ�����ʬ��
  // "��'���"'��Ȥ����ˤϡ�\"\'�Ȥ��롣
  // ��ľ������ɽ���ǽ񤱤Ф褫�ä������ޤ����ä���
  // �ޥ���Х��ȥ����դʥǥ�ߥ���Ȥ��褦�˵���Ĥ�������
  static public function smart_split($text, $delimiter = ' ') {
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
          $buf .= "'";
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
          $buf .= '"';
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

  // ��λ������õ���ơ����ΰ��֤��֤�
  private function find_closetag($t, &$spos, $closetag) {
    if (($epos = strpos($t, $closetag, $spos)) === FALSE) {
      $this->errorStr = "�������Ĥ����Ƥʤ��褦�Ǥ�($closetag�����Ĥ���ޤ���)��";
      return FALSE;
    }
    return $epos;
  }

  // {% %}����Ȥ�ѡ������ơ�GTNode���֤���
  // extends��Ƭ�˽񤫤ʤ��Ȥ����ʤ�
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
        // FIXME: �롼�ץ����å�
        $node = new GTExtendsNode(nodelist, parent_name, parent_name_expr);
        break;
      case 'include':
        // FIXME: �롼�ץ����å�
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
        break;
      default:
        $node = new GTUnknownNode();
        break;
    }
    $spos = $epos + 2;
    return $node;
  }

  // {{ }}����Ȥ�ѡ���
  private function parse_variable(&$spos) {
    $spos += 2;
    if (($epos = find_closetag($this->template, $spos, self::VARIABLE_TAG_END)) === FALSE) {
      return FALSE;
    }
    $in = $this->smart_split(substr($this->template, $spos, $epos));
    $spos = $epos + 2;
  }

  // {# #}����Ȥ�ѡ���
  private function parse_comment(&$spos) {
    $spos += 2;
    if (($epos = find_closetag($this->template, $spos, self::COMMENT_TAG_END)) === FALSE) {
      return FALSE;
    }
    $spos = $epos + 2; // #}�Τ���
  }

  public function parse_from_file($templatePath) {
    if (($t = file_get_contents($templatePath)) === FALSE) {
      $this->errorStr = '�ե����뤬�����ޤ���';
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
      // �����γ�����ʬ�򸫤Ĥ���
      $nspos = strpos($this->template, self::SINGLE_BRACE_START, $spos);
      if ($nspos === FALSE) {
        $nspos = $epos;
      }
      if ($spos < $nspos) {
        // �����ʳ�����ʬ��GTTextNode�Ȥ�����¸
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
          // ñ�ʤ�{���ɤߤȤФ�
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
