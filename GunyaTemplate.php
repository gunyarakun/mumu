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

// 1�ƥ�ץ졼�ȥե����롣
class GTFile {
  private $nodelist;        // �ե������ѡ�������NodeList
  private $extends_index;   // nodelist�����extends�Τ�Τΰ���
  private $block_dict;      // nodelist����ˤ���block̾ => nodelist�����
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
    // ¹��������줿�֥�å�̾�ǡ��Ƥˤ��������Ƥ��ʤ�����
    // �ƤοƤˤ��������Ƥ�����Τ��ᡢ
    // ¹����Ƥ˥֥�å���ܤ�

    // �����Ĥη���GTExtendsNode
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
    // FIXME: �������ƥ������å���̵�¥롼�ץ����å�
    $this->nodelist = $nodelist;
    $this->block_dict = $block_dict;
    $p = new GTParser();
    if (($this->parent_tplfile = $p->parse_from_file($parentPath)) === FALSE) {
      // TODO: ���顼���������ƥ�ץ졼��̾������˶����Ƥ�����
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
    // FIXME: �������ƥ������å���̵�¥롼�ץ����å�
    $p = new GTParser();
    if (($this->tplfile = $p->parse_from_file($includePath)) === FALSE) {
      // TODO: ���顼���������ƥ�ץ졼��̾������˶����Ƥ�����
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
    // eval�Ȥ�call_user_func_array������switch-case��dispatch�����ɤ�����

    // TODO: resolve_variable
    $val = $context[$this->var];
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
  private $template;        // �ѡ������Υƥ�ץ졼��ʸ����
  private $errorStr;        // ���顼ʸ����
  private $block_dict;      // block��̾�� => block�ؤλ���
  private $extends = false; // extends�ξ��Υե�����̾

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

  // ��λ������õ���ơ����ΰ��֤��֤�
  private function find_closetag($t, &$spos, &$epos, $closetag) {
    if (($fpos = strpos($t, $closetag, $spos)) === FALSE || $fpos >= $epos) {
      $this->errorStr = "�������Ĥ����Ƥʤ��褦�Ǥ�($closetag�����Ĥ���ޤ���)��";
      return FALSE;
    }
    return $fpos;
  }

  // endblock��endif��endfor��õ��
  private function find_endtag($t, &$spos, &$epos) {
  }

  // {% %}����Ȥ�ѡ������ơ�GTNode���֤���
  // extends��Ƭ�˽񤫤ʤ��Ȥ����ʤ�
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
          $this->errorStr = 'extends�ϣ��Ĥ�����������Ǥ��ޤ���';
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
          $this->errorStr = 'Ʊ��̾����block�ϣ��Ĥ�����������Ǥ��ޤ���';
          return FALSE;
        }
        // block�����ϥͥ��Ȥ��ʤ��Τǡ����Τޤ��endblock����
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
    $spos = $lpos + 2;
    return $node;
  }

  // {{ }}����Ȥ�ѡ���
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

  // {# #}����Ȥ�ѡ���
  private function parse_comment(&$spos, &$epos) {
    $spos += 2;
    if (($lpos = $this->find_closetag($this->template, $spos, $epos, self::COMMENT_TAG_END))
        === FALSE) {
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
      $spos = $nspos;
    }
    return $nl;
  }
}

class GunyaTemplate {
}
?>
