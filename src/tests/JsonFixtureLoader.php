<?php
namespace AEUtils\tests;

use Exception;

class JsonFixtureLoader extends \AEUtils\tests\BaseFixtureLoader {

  public function __construct($fixture_directory) {
    Parent::__construct($fixture_directory, '.json');
  }

  protected function _parse_fixture_data($file_contents) {
    $fixtures = json_decode($file_contents, true);

    if (!$fixtures) {
      throw new Exception('Fixture data contains invalid JSON');
    }

    return $fixtures;
  }

}
