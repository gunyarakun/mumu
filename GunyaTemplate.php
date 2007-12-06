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
  private $template; // �ѡ������Υƥ�ץ졼��ʸ����
  private $errorStr; // ���顼ʸ����
  private $ptemplate; // �ѡ�����Υƥ�ץ졼��(Node��array)

  // �ѡ�����
  private $blockmode; // 'f': for, 'i': if, 'e': else
  private $outmode;   // 'a': �ɲ� 'i': ̵�� 'b': �֥�å����ɲ�

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

  // ��λ������õ���ơ����ΰ��֤��֤�
  private function find_closetag($t, &$spos, $closetag) {
    if (($epos = strpos($t, $closetag, $spos)) === FALSE) {
      $this->errorStr = "�������Ĥ����Ƥʤ��褦�Ǥ�($closetag�����Ĥ���ޤ���)��"
      return FALSE;
    }
    return $epos;
  }

  // {% %}����Ȥ�ѡ������ơ�GTNode���֤���
  // extends��Ƭ�˽񤫤ʤ��Ȥ����ʤ�
  private function parse_block(&$spos) {
    $spos += 2
    if (($epos = find_closetag($this->template, $spos, self::BLOCK_TAG_END)) === FALSE) {
      return FALSE;
    }
    $in = trim(substr($this->template, $spos, $epos));
    switch ($in[0]) {
      case 'extends':
        // FIXME: �롼�ץ����å�
        $node = new GTExtendsNode(nodelist, parent_name, parent_name_expr);
      case 'include':
        // FIXME: �롼�ץ����å�
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

  // ����ʴ����˲ù�
  // ��: variable|filter1|filter2:"test"|filter3
  // ��: array('variable', array('filter1'), array(filter2, 'test'), array('filter3'))
  // �������Ȥˡ�

  // {{ }}����Ȥ�ѡ���
  private function parse_variable(&$spos) {
    $spos += 2
    if (($epos = find_closetag($this->template, $spos, self::VARIABLE_TAG_END)) === FALSE) {
      return FALSE;
    }
    // TODO: use limit for explode
    $in = explode(' ', trim(substr($this->template, $spos, $epos)));

    $spos = $epos + 2;
  }

  // {# #}����Ȥ�ѡ���
  private function parse_comment(&$spos) {
    $spos += 2
    if (($epos = find_closetag($this->template, $spos, self::COMMENT_TAG_END)) === FALSE) {
      return FALSE;
    }
    $spos = $epos + 2; // #}�Τ���
  }

  public function parse($templatePath) {
    if (($t = $file_get_contents($templatePath)) === FALSE) {
      $this->errorStr = '�ե����뤬�����ޤ���';
      return FALSE;
    }
    $pos = 0;

    $this->template = $t;

    // �����γ�����ʬ�򸫤Ĥ���
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
          // ñ�ʤ�{���ɤߤȤФ�
          $pos += 1;
      }
    }
  }
}
?>
