<?php

/**
 * @file
 * Performs XLIFF serialization and unserialization.
 */

namespace EggsCereal;

use EggsCereal\Interfaces\TranslatableInterface;
use EggsCereal\Utils\Converter;
use EggsCereal\Utils\ConverterToHTML;
use EggsCereal\Utils\Data;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Export to XLIFF format.
 */
class Serializer extends \XMLWriter implements LoggerAwareInterface {

  /**
   * @var LoggerInterface
   */
  private $logger = NULL;

  /**
   * Language code representing document source language; conforms to RFC 4646
   * language code.
   *
   * @var string
   */
  private $sourceLang;

  /**
   * Initializes this Serializer.
   *
   * @param string $sourceLanguage
   *   (Optional) The language code to be used as the <source> language when
   *   serializing data or validating data during unserialization. The code
   *   should conform to language codes as described in RFC 4646 (e.g. "en" or
   *   "en-US").
   */
  public function __construct($sourceLanguage = 'en') {
    $this->sourceLang = $sourceLanguage;
  }

  /**
   * {@inheritdoc}
   */
  public function setLogger(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Serializes a given translatable into an XLIFF file with the specified
   * target language.
   *
   * @param TranslatableInterface $translatable
   *   The translatable to be serialized.
   *
   * @param string $targetLang
   *   The desired target language.
   *
   * @return string
   *   The resultant XLIFF file as a string.
   */
  public function serialize(TranslatableInterface $translatable, $targetLang) {
    $output = $this->beginExport($translatable, $targetLang);
    $output .= $this->exportTranslatable($translatable, $targetLang);
    $output .= $this->endExport();
    return $output;
  }

  /**
   * Unserializes given XLIFF data; before unserialization occurs, the provided
   * XLIFF is also validated against the provided translatable and target
   * language.
   *
   * @param TranslatableInterface $translatable
   *   The translatable against which validation will occur.
   *
   * @param $targetLang
   *   The language code of the target language (used for validation).
   *
   * @param string $xliff
   *   XLIFF data to be unserialized.
   *
   * @param bool $callSetData
   *   (Optional) Whether or not to call the setData method on the provided
   *   translatable. Defaults to TRUE, pass FALSE to disable the setter call.
   *
   * @return array
   *   Returns unserialized data as an array in the exact same form that was
   *   provided via the translatable's getData method. If there was an error
   *   unserializing the provided XLIFF, an empty array will be returned.
   */
  public function unserialize(TranslatableInterface $translatable, $targetLang, $xliff, $callSetData = TRUE) {
    // First, validate the provided xliff.
    if ($this->validateImport($translatable, $targetLang, $xliff)) {
      // If valid, return the imported data as an array.
      $data = $this->import($xliff);

      // Call TranslatableInterface::setData() on the translatable if desired.
      if ($callSetData) {
        $translatable->setData($data, $targetLang);
      }

      return $data;
    }
    else {
      // If there was an error, return an empty array.
      return array();
    }
  }

  /**
   * Starts an export.
   *
   * @param TranslatableInterface $translatable
   *   The translatable for which we will be generating an XLIFF.
   *
   * @param string $targetLang
   *   The language code of the target language for this XLIFF.
   *
   * @return string
   *   The generated XML.
   */
  public function beginExport(TranslatableInterface $translatable, $targetLang) {

    $this->openMemory();
    $this->setIndent(TRUE);
    $this->setIndentString(' ');
    $this->startDocument('1.0', 'UTF-8');

    // Root element with schema definition.
    $this->startElement('xliff');
    $this->writeAttribute('version', '1.2');
    $this->writeAttribute('xmlns:xlf', 'urn:oasis:names:tc:xliff:document:1.2');
    $this->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $this->writeAttribute('xmlns:html', 'http://www.w3.org/1999/xhtml');
    $this->writeAttribute('xsi:schemaLocation', 'urn:oasis:names:tc:xliff:document:1.2 xliff-core-1.2-strict.xsd');

    // File element.
    $this->startElement('file');
    $this->writeAttribute('original', 'xliff-core-1.2-strict.xsd');
    $this->writeAttribute('source-language', $this->sourceLang);
    $this->writeAttribute('target-language', $targetLang);
    $this->writeAttribute('datatype', 'plaintext');

    // Date needs to be in ISO-8601 UTC.
    $this->writeAttribute('date', date('Y-m-d\Th:m:i\Z'));

    $this->startElement('header');
    $this->startElement('phase-group');
    $this->startElement('phase');
    $this->writeAttribute('tool-id', 'eggs-n-cereal');
    $this->writeAttribute('phase-name', 'extraction');
    $this->writeAttribute('process-name', 'extraction');
    $this->writeAttribute('job-id', $translatable->getIdentifier());

    $this->endElement();
    $this->endElement();
    $this->startElement('tool');
    $this->writeAttribute('tool-id', 'eggs-n-cereal');
    $this->writeAttribute('tool-name', 'Eggs-n-Cereal XLIFF Serializer');
    $this->endElement();
    $this->endElement();

    return $this->outputMemory() . '<body>';
  }

  /**
   * Adds a translatable item to the XML export.
   *
   * @param TranslatableInterface $translatable
   *   The translatable to serialize.
   *
   * @param string $targetLang
   *   The desired translatable.
   *
   * @return string
   *   The generated XML.
   */
  public function exportTranslatable(TranslatableInterface $translatable, $targetLang) {
    $this->openMemory();
    $this->setIndent(TRUE);
    $this->setIndentString(' ');

    $this->startElement('xlf:group');
    $this->writeAttribute('id', $translatable->getIdentifier());
    $this->writeAttribute('restype', 'x-eggs-n-cereal-translatable');

    // Retrieve and flatten translatable data.
    $translatableData = $translatable->getData();
    $flattenedData = Data::flattenData($translatableData);

    // @todo: Write in nested groups instead of flattening it.
    $data = array_filter($flattenedData, array('EggsCereal\Utils\Data', 'filterData'));
    foreach ($data as $key => $element) {
      $this->addTransUnit($key, $element, $targetLang);
    }
    $this->endElement();
    return $this->outputMemory();
  }

  /**
   * Adds a single translation unit for a data element.
   *
   * @param string $key
   *   The unique identifier for this data element.
   *
   * @param string $element
   *   An element array with the following properties:
   *   - #text: The text string to be translated.
   *   - #label: (Optional) Label, intended for translators, that provides more
   *     context around the translated string.
   *
   * @param string $targetLang
   *   The target language of the translatable.
   *
   * @return string
   *   The generated XML.
   */
  protected function addTransUnit($key, $element, $targetLang) {
    $this->startElement('xlf:group');
    $this->writeAttribute('id', $key);
    $this->writeAttribute('resname', $key);
    $this->writeAttribute('restype', 'x-eggs-n-cereal-field');

    //escape named html entities prior to conversion
    $list = get_html_translation_table(HTML_ENTITIES);
    $namedTable = array();
    foreach($list as $k=>$v){
      $namedTable[$v]= "&amp;".str_replace('&', '',$v);
    }
    $element['#text'] = strtr($element['#text'], $namedTable);

    try {
      $converter = new Converter($element['#text'], $this->sourceLang, $targetLang);
      if ($this->logger) {
        $converter->setLogger($this->logger);
      }
      $this->writeRaw($converter->toXLIFF());
    }
    catch (\Exception $e) {
      $this->startElement('trans-unit');
      $this->writeAttribute('id', uniqid('text-'));
      $this->writeAttribute('restype', 'x-eggs-n-cereal-failure');
      $this->startElement('source');
      $this->writeAttribute('xml:lang', $this->sourceLang);
      $this->text($element['#text']);
      $this->endElement();

      $this->startElement('target');
      $this->writeAttribute('xml:lang', $targetLang);
      $this->text($element['#text']);
      $this->endElement();
      $this->endElement();
    }
    if (isset($element['#label'])) {
      $this->writeElement('note', $element['#label']);
    }
    $this->endElement();
  }

  /**
   * Ends an export.
   *
   * @return string
   *   The generated XML.
   */
  public function endExport() {
    return '  </body>
 </file>
</xliff>';
  }

  /**
   * @todo This should take a string of XML.
   * @param string $xmlString
   * @return array
   */
  public function import($xmlString) {
    // It is not possible to load the file directly with simplexml as it gets
    // url encoded due to the temporary://. This is a PHP bug, see
    // https://bugs.php.net/bug.php?id=61469
    $xml = $this->serializerSimplexmlLoadString($xmlString);

    // Register the html namespace, required for xpath.
    $xml->registerXPathNamespace('html', 'http://www.w3.org/1999/xhtml');

    $translatables = $xml->xpath('//xlf:group[@restype="x-eggs-n-cereal-translatable"]');
    if (empty($translatables)) {
      return $this->oldImport($xml);
    }

    $data = array();
    foreach ($translatables as $translatable) {
      foreach ($translatable->xpath('//*[@restype="x-eggs-n-cereal-field"]') as $field) {
        $id = (string) $field['id'];
        if (isset($field->source)) {
          $data[$id]['#label'] = (string) $field->source;
        }
        else
        if (isset($field->note)) {
          $data[$id]['#label'] = (string) $field->note;
        }
        if ($field->{'trans-unit'}->attributes()->restype == 'x-eggs-n-cereal-failure') {
          $data[$id]['#text'] = (string) $field->{'trans-unit'}->target;
        }
        else {
          $converter = new ConverterToHTML($field->saveXML());
          $data[$id]['#text'] = $converter->toHTML();
        }
      }
    }

    return Data::unflattenData($data);
  }

  /**
   * @todo Remove?
   * Imports a file.
   *
   * @param \SimpleXMLElement $xml
   *   The XML to be imported.
   *
   * @return array
   *   The resultant unserialized, flattened data array.
   */
  public function oldImport(\SimpleXMLElement $xml) {
    $data = array();
    foreach ($xml->xpath('//trans-unit') as $unit) {
      $data[(string) $unit['id']]['#text'] = (string) $unit->target;
    }
    return Data::unflattenData($data);
  }

  /**
   * Converts xml to string and handles entity encoding.
   *
   * @param string $xmlString
   *   The xml string to convert to xml.
   *
   * @return \SimpleXMLElement
   *   Returns SimpleXml element
   */
  public function serializerSimplexmlLoadString($xmlString){
    $numericTable = array();
    //commonly present restricted characters that can safely be replaced
    $numericTable['&'] = '&#38;';
    $trans = get_html_translation_table(HTML_ENTITIES, ENT_QUOTES);
    foreach ($trans as $k=>$v){
      $numericTable[$v]= "&#".ord($k).";";
    }
    $xmlString = strtr($xmlString, $numericTable);
    return simplexml_load_string($xmlString);
  }

  /**
   * Validates an import.
   *
   * @param TranslatableInterface $translatable
   *   A translatable object.
   * @param string $targetLang
   *   The target language for this translatable.
   * @param string $xmlString
   *   The XLIFF data as a string to validate.
   *
   * @return bool
   *   Returns TRUE on success or FALSE on failure.
   */
  public function validateImport(TranslatableInterface $translatable, $targetLang, $xmlString) {
    $xmlString = Converter::filterXmlControlCharacters($xmlString);

    $error = $this->errorStart();

    // XML does not support most named HTML entities (eg, &nbsp;), but should be
    // able to handle the UTF-8 uncoded entity characters just fine.
    $xml = $this->serializerSimplexmlLoadString($xmlString);

    $this->errorStop($error);

    if (!$xml) {
      return FALSE;
    }

    $errorArgs = array(
      '%lang' => $targetLang,
      '%srclang' => $this->sourceLang,
      '%name' => $translatable->getLabel(),
      '%id' => $translatable->getIdentifier(),
    );

    // Check if our phase information is there.
    $phase = $xml->xpath("//phase[@phase-name='extraction']");
    if ($phase) {
      $phase = reset($phase);
    }
    else {
      $this->log(LogLevel::ERROR, 'Phase missing from XML.', $errorArgs);
      return FALSE;
    }

    // Check if the project can be loaded.
    if (!isset($phase['job-id']) || ($translatable->getIdentifier() != (string) $phase['job-id'])) {
      $this->log(LogLevel::ERROR, 'The project id is missing in the XML.', $errorArgs);
      return FALSE;
    }
    elseif ($translatable->getIdentifier() != (string) $phase['job-id']) {
      $this->log(LogLevel::ERROR, 'The project id is invalid in the XML. Correct id: %id.', $errorArgs);
      return FALSE;
    }

    // Compare source language.
    if (!isset($xml->file['source-language'])) {
      $this->log(LogLevel::ERROR, 'The source language is missing in the XML.', $errorArgs);
      return FALSE;
    }
    elseif ($xml->file['source-language'] != $this->sourceLang) {
      $this->log(LogLevel::ERROR, 'The source language is invalid in the XML. Correct langcode: %srclang.', $errorArgs);
      return FALSE;
    }

    // Compare target language.
    if (!isset($xml->file['target-language'])) {
      $this->log(LogLevel::ERROR, 'The target language is missing in the XML.', $errorArgs);
      return FALSE;
    }
    elseif ($targetLang != Data::normalizeLangcode($xml->file['target-language'])) {
      $errorArgs['%wrong'] = $xml->file['target-language'];
      $this->log(LogLevel::ERROR, 'The target language %wrong is invalid in the XML. Correct langcode: %lang.', $errorArgs);
      return FALSE;
    }

    // Validation successful.
    return TRUE;
  }

  /**
   * Starts custom error handling.
   *
   * @return bool
   *   The previous value of use_errors.
   */
  protected function errorStart() {
    return libxml_use_internal_errors(TRUE);
  }

  /**
   * Ends custom error handling.
   *
   * @param bool $use
   *   The return value of Serializer::errorStart().
   */
  protected function errorStop($use) {
    foreach (libxml_get_errors() as $error) {
      switch ($error->level) {
        case LIBXML_ERR_WARNING:
        case LIBXML_ERR_ERROR:
          $level = LogLevel::WARNING;
          break;

        case LIBXML_ERR_FATAL:
          $level = LogLevel::ERROR;
          break;

        default:
          $level = LogLevel::INFO;
      }

      $this->log($level, '%error on line %num. Error code: %code.', array(
        '%error' => trim($error->message),
        '%num' => $error->line,
        '%code' => $error->code,
      ));
    }
    libxml_clear_errors();
    libxml_use_internal_errors($use);
  }

  /**
   * Logs a message using the logger, assuming a logger has been provided.
   *
   * @param string $level
   *   One of the log levels as defined by the PSR-3 standard.
   *
   * @param string $message
   *   The message to log.
   *
   * @param array $context
   *   (Optional) An associative array of contextual information about the log
   *   message. May contain placeholders used in the $message.
   */
  protected function log($level, $message, $context = array()) {
    if ($this->logger) {
      $this->logger->log($level, $message, $context);
    }
  }

}
