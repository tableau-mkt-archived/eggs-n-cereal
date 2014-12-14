<?php

/**
 * @file
 */

namespace EggsCereal\Utils;


/**
 * Iterates recursively over a DOMNodeList.
 */
class RecursiveDOMIterator implements \RecursiveIterator {

  /**
   * Current Position in DOMNodeList.
   *
   *  @var integer
   */
  protected $position = 0;

  /**
   * The DOMNodeList with all children to iterate over.
   *
   * @var DOMNodeList
   */
  protected $nodeList;

  /**
   * Constructor.
   *
   * @param DOMNode $node
   *   A DOMNode to iterate over.
   */
  public function __construct(\DOMNode $node) {
    $this->nodeList = $node->childNodes;
  }

  /**
   * Returns the current DOMNode.
   *
   * @return DOMNode
   */
  public function current() {
    return $this->nodeList->item($this->position);
  }

  /**
   * Returns an iterator for the current iterator entry.
   *
   * @return TableauRecursiveDOMIterator
   */
  public function getChildren() {

    // Poor mans late static binding.
    $class = get_class($this);
    return new $class($this->current());
  }

  /**
   * Returns if an iterator can be created for the current entry.
   *
   * @return bool
   */
  public function hasChildren() {
    return $this->current()->hasChildNodes();
  }

  /**
   * Returns the current position.
   *
   * @return int
   */
  public function key() {
    return $this->position;
  }

  /**
   * Moves the current position to the next element.
   */
  public function next() {
    $this->position++;
  }

  /**
   * Rewinds the Iterator to the first element.
   */
  public function rewind() {
    $this->position = 0;
  }

  /**
   * Checks if current position is valid.
   *
   * @return bool
   */
  public function valid() {
    return $this->position < $this->nodeList->length;
  }

}
