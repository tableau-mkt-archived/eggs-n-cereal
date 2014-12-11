<?php

/**
 * @file
 * Utility data manipulation and validation functions.
 */

namespace EggsCereal\Utils;

class Data {

  CONST ARRAY_DELIMITER = '][';

  /**
   * Normalizes language code.
   *
   * @param string $langcode
   *   The lang code string to normalize.
   *
   * @return string
   *   The normalized language code.
   */
  public static function normalizeLangcode($langcode) {
    return str_replace('_', '-', strtolower($langcode));
  }

  /**
   * Converts string keys to array keys.
   *
   * There are three conventions for data keys in use. This function accepts each
   * and ensures an array of keys.
   *
   * @param array|string $key
   *   The key can be either be an array containing the keys of a nested array
   *   hierarchy path or a string with '][' or '|' as delimiter.
   *
   * @return array
   *   Array of keys.
   */
  public static function ensureArrayKeys($key) {
    if (empty($key)) {
      return array();
    }
    if (!is_array($key)) {
      if (strstr($key, '|')) {
        $key = str_replace('|', self::ARRAY_DELIMITER, $key);
      }
      $key = explode(self::ARRAY_DELIMITER, $key);
    }
    return $key;
  }

  /**
   * Converts a nested data array into a flattened structure with a combined key.
   *
   * This function can be used by translators to help with the data conversion.
   *
   * Nested keys will be joined together using a colon, so for example
   * $data['key1']['key2']['key3'] will be converted into
   * $flattened_data['key1][key2][key3'].
   *
   * @param array $data
   *   The nested array structure that should be flattened.
   *
   * @param string $prefix
   *   Internal use only, indicates the current key prefix when recursing into
   *   the data array.
   *
   * @param array $label
   *   Internal use only.
   *
   * @return array
   *   The flattened data array.
   *
   * @see TableauXliffSerializer::unflattenData()
   */
  public static function flattenData(array $data, $prefix = NULL, $label = array()) {
    $flattened_data = array();
    if (isset($data['#label'])) {
      $label[] = $data['#label'];
    }
    // Each element is either a text (has #text property defined) or has children,
    // not both.
    if (!empty($data['#text'])) {
      $flattened_data[$prefix] = $data;
      $flattened_data[$prefix]['#parent_label'] = $label;
    }
    else {
      $prefix = isset($prefix) ? $prefix . self::ARRAY_DELIMITER : '';
      foreach (self::elementChildren($data) as $key) {
        $flattened_data += self::flattenData($data[$key], $prefix . $key, $label);
      }
    }
    return $flattened_data;
  }

  /**
   * Converts a flattened data structure into a nested array.
   *
   * This function can be used by translators to help with the data conversion.
   *
   * Nested keys will be created based on the colon, so for example
   * $flattened_data['key1][key2][key3'] will be converted into
   * $data['key1']['key2']['key3'].
   *
   * @param array $flattened_data
   *   The flattened data array.
   *
   * @return array
   *   The nested data array.
   *
   * @see TableauXliffSerializer::flattenData()
   */
  public static function unflattenData($flattened_data) {
    $data = array();
    foreach ($flattened_data as $key => $flattened_data_entry) {
      self::arraySetNestedValue($data, explode(self::ARRAY_DELIMITER, $key), $flattened_data_entry);
    }
    return $data;
  }

  /**
   * Array filter callback for filtering untranslatable source data elements.
   *
   * @param array $value
   *   An element array consisting of at least the following keys:
   *   - #text: The value to be translated.
   *   - #translate: (Optional) A boolean indicating whether or not the element
   *     can be translated.
   *
   * @return bool
   *   TRUE if the element is translatable. FALSE otherwise.
   */
  public static function filterData($value) {
    return !(empty($value['#text']) || (isset($value['#translate']) && $value['#translate'] === FALSE));
  }

  /**
   * Identifies the children of an element array.
   *
   * The children of a element array are those key/value pairs whose key does
   * not start with a '#'.
   *
   * @param array $elements
   *   The element array whose children are to be identified.
   *
   * @return array
   *   The array keys of the element's children.
   */
  public static function elementChildren($elements) {
    // Filter out properties from the element, leaving only children.
    $children = array();
    foreach ($elements as $key => $value) {
      if ($key === '' || $key[0] !== '#') {
        $children[$key] = $value;
      }
    }
    return array_keys($children);
  }

  /**
   * Sets a value in a nested array with variable depth.
   *
   * @param array $array
   *   A reference to the array to modify.
   *
   * @param array $parents
   *   An array of parent keys, starting with the outermost key.
   *
   * @param $value
   *   The value to set.
   *
   * @param bool $force
   *   (Optional) If TRUE, the value is forced into the structure even if it
   *   requires the deletion of an already existing non-array parent value. If
   *   FALSE, PHP throws an error if trying to add into a value that is not an
   *   array. Defaults to FALSE.
   */
  public static function arraySetNestedValue(array &$array, array $parents, $value, $force = FALSE) {
    $ref = &$array;
    foreach ($parents as $parent) {
      // PHP auto-creates container arrays and NULL entries without error if $ref
      // is NULL, but throws an error if $ref is set, but not an array.
      if ($force && isset($ref) && !is_array($ref)) {
        $ref = array();
      }
      $ref = &$ref[$parent];
    }
    $ref = $value;
  }

}
