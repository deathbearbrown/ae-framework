<?php
namespace AEUtils\tests;

use Exception;

class XmlFixtureLoader extends \AEUtils\tests\BaseFixtureLoader {

  public function __construct($fixture_directory) {
    Parent::__construct($fixture_directory, '.xml');
  }

  protected function _parse_fixture_data($file_contents) {
    $xml = simplexml_load_string($file_contents);
    if (!$xml) {
      throw new Exception('Fixture data contains invalid XML');
    }

    // the following is a trick to return the XML as an
    // associative array
    $json = json_encode($xml);
    return json_decode($json, true);
  }

}
