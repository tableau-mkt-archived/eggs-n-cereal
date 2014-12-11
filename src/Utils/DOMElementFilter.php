<?php

/**
 * @file
 */

namespace EggsCereal\Utils;


class DOMElementFilter extends \RecursiveFilterIterator {

  public function accept() {
    return $this->current()->nodeType === XML_ELEMENT_NODE;
  }

}
