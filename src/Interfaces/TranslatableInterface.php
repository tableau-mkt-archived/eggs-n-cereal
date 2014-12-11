<?php

/**
 * @file
 *
 */

namespace EggsCereal\Interfaces;

/**
 * Interface TranslatableInterface
 * @package EggsCereal\Interfaces
 */
interface TranslatableInterface {

  /**
   * Returns a unique identifier associated with this translatable.
   *
   * @return mixed
   *   The identifier for this translatable
   */
  public function getIdentifier();

  /**
   * Returns the human-readable label, name, or title associated with this
   * translatable.
   *
   * @return string
   *   The human-readable label for this translatable.
   */
  public function getLabel();

  /**
   * Returns all translatable data for this translatable.
   *
   * The exact format is up to the implementor, but whatever keys, fields,
   * elements, or items are provided will need to be updated/set in
   * TranslatableInterface::setData(). This may guide your implementation.
   *
   * @return array
   *   A(n optionally) multidimensional array of translatable data associated
   *   with this translatable.
   *
   * @see EggsCereal\Serializer::exportTranslatable()
   * @see TranslatableInterface::setData()
   */
  public function getData();

  /**
   * Sets translated data on this translatable.
   *
   * The format of $data will exactly match the format you provided in your
   * implementation of TranslatableInterface::getData().
   *
   * @param array $data
   *   An array of translated data exactly matching the format provided by you
   *   in your implementation of TranslatableInterface::getData().
   *
   * @param string $targetLanguage
   *   The language code of the target language.
   *
   * @see EggsCereal\Serializer::unserialize()
   * @see TranslatableInterface::getData()
   */
  public function setData(array $data, $targetLanguage);

}
