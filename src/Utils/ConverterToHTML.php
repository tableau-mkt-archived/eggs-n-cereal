<?php

/**
 * @file
 */

namespace EggsCereal\Utils;


class ConverterToHTML extends Converter {
  public function __construct($xml) {
    $this->doc = new \DOMDocument('1.0', 'UTF-8');
    $this->doc->strictErrorChecking = TRUE;
    $error = $this->errorStart();

    // Setting meta below is a hack to get our DomDocument into utf-8. All other
    // methods tried didn't work.
    $success = $this->doc->loadXML('<xliff version="1.2" xmlns:xlf="urn:oasis:names:tc:xliff:document:1.2" xmlns:html="http://www.w3.org/1999/xhtml" xmlns="urn:oasis:names:tc:xliff:document:1.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2 xliff-core-1.2-strict.xsd">' . $xml . '</xliff>');
    $this->errorStop($error);
    $this->elementMap = array_flip($this->elementMap);

    if (!$success) {
      throw new \Exception('Invalid XML');
    }

    $this->xpath = new \DOMXPath($this->doc);
    $this->xpath->registerNamespace('html', 'http://www.w3.org/1999/xhtml');
  }

  /**
   * Converts XML to the corresponding HTML representation.
   *
   * @return string
   *   The source XML converted to HTML.
   */
  public function toHTML($pretty_print = TRUE) {

    $this->out = new \DOMDocument('1.0', 'UTF-8');
    $this->out->formatOutput = $pretty_print;
    $field = $this->doc->getElementsByTagName('group')->item(0);

    foreach ($field->childNodes as $child) {
      if ($output = $this->convert($child)) {
        $this->out->appendChild($output);
      }
    }

    return html_entity_decode($this->out->saveHTML(), ENT_QUOTES, 'UTF-8');
  }

  protected function convert(\DOMNode $node) {

    if ($node->nodeType == XML_ELEMENT_NODE) {
      switch ($node->tagName) {
        case 'xlf:group':
        case 'group':
          return $this->convertGroup($node);

        case 'trans-unit':
          return $this->convertTransUnit($node);

        case 'g':
        case 'x':
          return $this->convertXG($node);
      }
    }
    elseif ($node->nodeType == XML_TEXT_NODE) {
      if (trim($node->nodeValue)) {
        return $node->nodeValue;
      }
    }

    return FALSE;
  }

  protected function addChildren(\DOMNode $node, \DOMNode $elem) {
    foreach ($node->childNodes as $child) {
      if ($new_child = $this->convert($child)) {
        $elem->appendChild($new_child);
      }
    }
    return $elem;
  }

  protected function htmlTag(\DOMElement $element) {
    switch ($element->tagName) {
      case 'xlf:group':
      case 'group':
      case 'trans-unit':
        $attr = $element->getAttribute('restype');
        break;

      case 'g':
      case 'x':
        $attr = $element->getAttribute('ctype');
        break;

      default:
        // var_export( $element->nodeValue);
    }

    if (!$attr) {
      return $this->out->createDocumentFragment();
    }

    if (isset($this->elementMap[$attr])) {
      $html_element = $this->elementMap[$attr];
    }
    else {
      $html_element = substr($attr, 7);
    }

    $out = $this->out->createElement($html_element);
    $this->addAttrs($out, $element);
    return $out;
  }

  protected function convertGroup(\DOMElement $node) {
    $elem = $this->htmlTag($node);
    return $this->addChildren($node, $elem);
  }

  protected function convertTransUnit(\DOMElement $node) {
    $elem = $this->htmlTag($node);
    $target = $node->getElementsByTagName('target')->item(0);
    foreach ($target->childNodes as $child) {
      $elem->appendChild($this->convertTarget($child));
    }
    return $elem;
  }

  protected function convertTarget(\DOMNode $node) {
    switch ($node->nodeType) {
      case XML_ELEMENT_NODE:
        $tag = $this->htmlTag($node);
        foreach ($node->childNodes as $child) {
          $tag->appendChild($this->convertTarget($child));
        }
        return $tag;

      case XML_TEXT_NODE:
        return $this->out->createTextNode($node->nodeValue);
    }

  }

  protected function convertXG(\DOMElement $elem) {
    $html_element = substr($elem->getAttribute('ctype')->nodeValue, 7);
    $out = $this->out->createElement($html_element);
    $this->addAttrs($out, $elem);
    return $this->addChildren($elem, $out);
  }

  protected function addAttrs($out, $elem) {
    foreach ($elem->attributes as $key => $attr) {
      if ($attr->prefix == 'html') {
        $out->setAttribute($key, $attr->nodeValue);
      }
      elseif ($key == 'css-style') {
        $out->setAttribute('style', $attr->nodeValue);
      }
    }
  }

}
