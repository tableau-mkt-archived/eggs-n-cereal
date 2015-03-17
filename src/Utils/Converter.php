<?php

/**
 * @file
 * Converts HTML tags into their corresponding XLIFF tags.
 */

namespace EggsCereal\Utils;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;


class Converter implements LoggerAwareInterface {

  /**
   * @var \DOMDocument
   */
  protected $doc;

  /**
   * @var \DOMDocument
   */
  protected $out;

  /**
   * @var LoggerInterface
   */
  private $logger = NULL;

  /**
   * Include a target or not
   *
   * @var boolean
   */
  private $includeTarget = true;

  protected $elementMap = array(
    'b' => 'bold',
    'br' => 'lb',
    'caption' => 'caption',
    'fieldset' => 'groupbox',
    'footer' => 'footer',
    'form' => 'dialog',
    'frame' => 'frame',
    'head' => 'header',
    'i' => 'italic',
    'img' => 'image',
    'li' => 'listitem',
    'menu' => 'menu',
    'table' => 'table',
    'td' => 'cell',
    'tfoot' => 'footer',
    'tr' => 'row',
    'u' => 'underlined',
  );

  protected $inlineTags = array(
    'a' => TRUE,
    'abbr' => TRUE,
    'acronym' => TRUE,
    'address' => TRUE,
    'applet' => TRUE,
    'area' => TRUE,
    'audio' => TRUE,
    'b' => TRUE,
    'bdo' => TRUE,
    'big' => TRUE,
    'blink' => TRUE,
    'br' => TRUE,
    'button' => TRUE,
    'cite' => TRUE,
    'code' => TRUE,
    'command' => TRUE,
    'datalist' => TRUE,
    'del' => TRUE,
    'details' => TRUE,
    'dfn' => TRUE,
    'em' => TRUE,
    'embed' => TRUE,
    'face' => TRUE,
    // 'font' => TRUE,
    'i' => TRUE,
    'iframe' => TRUE,
    'img' => TRUE,
    'input' => TRUE,
    'ins' => TRUE,
    'kbd' => TRUE,
    'label' => TRUE,
    'legend' => TRUE,
    'link' => TRUE,
    'map' => TRUE,
    'mark' => TRUE,
    'meter' => TRUE,
    'nav' => TRUE,
    'nobr' => TRUE,
    'object' => TRUE,
    'optgroup' => TRUE,
    'option' => TRUE,
    'param' => TRUE,
    'q' => TRUE,
    'rb' => TRUE,
    'rbc' => TRUE,
    'rp' => TRUE,
    'rt' => TRUE,
    'rtc' => TRUE,
    'ruby' => TRUE,
    's' => TRUE,
    'samp' => TRUE,
    'select' => TRUE,
    'small' => TRUE,
    'source' => TRUE,
    'span' => TRUE,
    'spacer' => TRUE,
    'strike' => TRUE,
    'strong' => TRUE,
    'sub' => TRUE,
    'summary' => TRUE,
    'sup' => TRUE,
    'symbol' => TRUE,
    'textarea' => TRUE,
    'time' => TRUE,
    'tt' => TRUE,
    'u' => TRUE,
    'var' => TRUE,
    'wbr' => TRUE,
  );

  protected $selfClosingTags = array(
    'area' => TRUE,
    'base' => TRUE,
    'basefont' => TRUE,
    'br' => TRUE,
    'col' => TRUE,
    'frame' => TRUE,
    'hr' => TRUE,
    'img' => TRUE,
    'input' => TRUE,
    'link' => TRUE,
    'meta' => TRUE,
    'param' => TRUE,
  );

  protected $inTransUnit = FALSE;

  public function __construct($html, $langcode, $includeTarget = true) {
    $this->doc = new \DOMDocument();
    $this->doc->strictErrorChecking = FALSE;
    $this->langcode = $langcode;
    $this->includeTarget = $includeTarget;
    $error = $this->errorStart();

    // Setting meta below is a hack to get our DomDocument into utf-8. All other
    // methods tried didn't work.
    $success = $this->doc->loadHTML('<meta http-equiv="content-type" content="text/html; charset=utf-8"><div id="eggs-n-cereal-dont-ever-use-this-id">' . $html . '</div>');
    $this->errorStop($error);

    if (!$success) {
      throw new \Exception('Invalid HTML');
    }
  }

  /**
   * Sets a logger instance on the object
   *
   * @param LoggerInterface $logger
   * @return null
   */
  public function setLogger(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Converts HTML to the corresponding XLIFF representation.
   *
   * @return string
   *   The source HTML converted to XLIFF.
   */
  public function toXLIFF($pretty_print = FALSE) {
    $this->doc->formatOutput = $pretty_print;

    // Do not use getElementById to comply with older versions of libxml.
    // getElementById doesn't work properly on libxml 2.7.6 (CentOS)
    $xpath = new \DOMXPath($this->doc);
    $wrapper_div = $xpath->query("//*[@id='eggs-n-cereal-dont-ever-use-this-id']")->item(0);

    $out = $this->doc->createDocumentFragment();

    $domNodeList = array();
    for ($i = 0; $i < $wrapper_div->childNodes->length; ++$i) {
      $domNodeList[] = $wrapper_div->childNodes->item($i);
    }

    $this->sanitizeMixedDomNodeList($this->doc, $domNodeList);

    foreach($domNodeList as $domNode){
      if ($output = $this->convert($domNode)) {
        $out->appendChild($output);
      }
    }

    return $this->doc->saveXML($out);
  }

  /**
   * Test for mixed dom node types (DOMText and DOMElement)
   * Wraps sibling DOMText and DOMElement inline tags into single DOMElement with a text attribute
   *
   * @return string
   *   The source HTML converted to XLIFF.
   */
  protected function sanitizeMixedDomNodeList(\DOMDocument $doc, &$list){

    $NewElement = $doc->createElement('text');

    $test = array(XML_ELEMENT_NODE=> FALSE, XML_TEXT_NODE=>FALSE);
    foreach($list as $node){
      $test[$node->nodeType] = TRUE;
    }

    //text only so exit
    if($test[XML_TEXT_NODE] == TRUE && $test[XML_ELEMENT_NODE] == FALSE){
      return;
    }

    //mixed group logic
    $newList = array();
    foreach($list as $k => $node){
      if (array_key_exists($node->nodeName, $this->inlineTags)||
        array_key_exists($node->nodeName, array('#text' => true))){
        unset($list[$k]);
        $NewElement->appendChild($node->cloneNode(true));
        if (!isset($node->nextSibling->nodeName)){
          $newList[$k] = $NewElement;
        }else if(array_key_exists($node->nextSibling->nodeName, $this->inlineTags)||
          array_key_exists($node->nextSibling->nodeName, array('#text' => true))){
          //do nothing
        }else{
          $newList[$k] = $NewElement;
        }
      }else{
        $newList[$k] = $node;
        $NewElement = $doc->createElement('text');
      }
    }
    $list = $newList;
  }

  protected function convert(\DOMNode $node) {

    switch ($node->nodeType) {
      case XML_ELEMENT_NODE:
        return $this->convertElement($node);

      case XML_TEXT_NODE:
        //if (!trim($node->nodeValue)) {
        //  break;
        //}

        return $this->addText($node);

      // case XML_CDATA_SECTION_NODE:
      //   $out->appendChild($this->addText($node));
      //   return $out;

    }

    return FALSE;
  }

  protected function convertElement(\DOMElement $element) {
    $translated_element = $this->convertElementTag($element);

    foreach ($this->xliffAttrs($element) as $attr) {
      $translated_element->setAttributeNode($attr);
    }

    //Correction for inline tags created as trans-units
    if ((array_key_exists($element->nodeName, $this->inlineTags)) && $translated_element->tagName == 'trans-unit'){
      $tmpEl = $element;
      $element = $this->doc->createElement('text');
      $translated_element = $this->convertElementTag($element);
      foreach ($this->xliffAttrs($element) as $attr) {
        $translated_element->setAttributeNode($attr);
      }
      $element->appendchild(clone $tmpEl);
    }

    if ($translated_element->tagName == 'trans-unit') {
      $out = $this->createSource();
      $translated_element->appendChild($out);
      $target = $this->createTarget();
      $translated_element->appendChild($target);
      $this->inTransUnit = TRUE;
    }
    else {
      $out = $translated_element;
    }

    if ($element->hasChildNodes()) {
      foreach ($element->childNodes as $child) {
        if ($converted = $this->convert(clone $child)) {
          $out->appendChild($converted);
          if ($out->tagName == 'source') {
            $target->appendChild(clone $converted);
          }
        }
      }
    }

    if ($translated_element->tagName == 'trans-unit') {
      $this->inTransUnit = FALSE;
    }

    return $translated_element;
  }

  protected function addText(\DOMText $text) {
    // $text->nodeValue = htmlentities($text->nodeValue);
    if (!$this->inTransUnit) {
      $trans = $this->doc->createElement('trans-unit');
      $trans->setAttribute('id', uniqid('text-'));
      $source = $this->createSource('en');
      $target = $this->createTarget('fr');
      $trans->appendChild($source);
      if ($this->includeTarget) {
        $trans->appendChild($target);
      }
      $source->appendChild($text);
      $target->appendChild(clone $text);
      return $trans;
    }

    return $text;
  }

  protected function convertElementTag(\DOMElement $element) {

    if ($this->isBlockElement($element) && $this->hasBlockChild($element)) {
      return $this->doc->createElement('xlf:group');
    }
    if (isset($this->selfClosingTags[$element->tagName]) && $this->inTransUnit) {
      $out = $this->doc->createElement('x');
    }
    elseif (isset($this->inlineTags[$element->tagName]) && $this->inTransUnit) {
      $out = $this->doc->createElement('g');
    }
    else {
      $out = $this->doc->createElement('trans-unit');
    }
    $out->setAttribute('id', uniqid($element->tagName . '-'));
    return $out;
  }

  protected function isBlockElement(\DOMElement $element) {
    return !isset($this->inlineTags[$element->tagName]);
  }

  protected function hasBlockChild(\DOMElement $element) {

    if ($element->hasChildNodes()) {
      $filter = new DOMElementFilter(new RecursiveDOMIterator($element));
      $recursive = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::SELF_FIRST);

      foreach ($recursive as $element) {
        if ($this->isBlockElement($element)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  protected function xliffAttrs(\DOMElement $element) {
    $attrs = array();

    if (isset($this->inlineTags[$element->tagName])) {
      $attrs[] = new \DOMAttr('ctype', $this->mapHTMLTagToXLIFF($element));
    }
    else if($element->tagName != 'text'){
      $attrs[] = new \DOMAttr('restype', $this->mapHTMLTagToXLIFF($element));
    }

    foreach ($element->attributes as $attr) {
      switch ($attr->name) {
        case 'style':
          $name = 'css-style';
          break;

        default:
          $name = 'html:' . $attr->name;
          break;
      }
      $attrs[] = new \DOMAttr($name, self::filterXmlControlCharacters($attr->value));
    }

    return $attrs;
  }

  protected function createSource() {
    $element = $this->doc->createElement('source');
    $element->setAttribute('xml:lang', 'en');
    return $element;
  }

  protected function createTarget() {
    $element = $this->doc->createElement('target');
    $element->setAttribute('xml:lang', $this->langcode);
    return $element;
  }

  protected function mapHTMLTagToXLIFF(\DOMElement $element) {
    if (isset($this->elementMap[$element->tagName])) {
      return $this->elementMap[$element->tagName];
    }

    return 'x-html-' . $element->tagName;
  }

  /**
   * Start custom error handling.
   *
   * @return bool
   *   The previous value of use_errors.
   */
  protected function errorStart() {
    return libxml_use_internal_errors(TRUE);
  }

  /**
   * Stop custom error handling.
   *
   * @param bool $use
   *   The previous value of use_errors.
   *
   * @throws \Exception
   */
  protected function errorStop($use) {
    foreach (libxml_get_errors() as $error) {
      // Invalid tag. Skip this as DOMDocument does not support HTML5.
      if ($error->code == 801) {
        continue;
      }

      switch ($error->level) {
        case LIBXML_ERR_WARNING:
        case LIBXML_ERR_ERROR:
          $level = LogLevel::WARNING;
          break;

        case LIBXML_ERR_FATAL:
          throw new \Exception('Fatal error');
          break;

        default:
          $level = LogLevel::INFO;
      }
      if ($this->logger) {
        $this->logger->log($level, '%message on line %line. Error code: %code', array(
          '%message' => trim($error->message),
          '%line' => $error->line,
          '%code' => $error->code,
        ));
      }
    }
    libxml_clear_errors();
    libxml_use_internal_errors($use);
  }

  /**
   * String filter to strip out xml control characters prior to constructing or
   * loading xml objects.
   *
   * @param string $string
   *   The string to be filtered.
   *
   * @return string
   *   Returns the filtered string.
   */
  public static function filterXmlControlCharacters($string) {
    return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
  }

}
