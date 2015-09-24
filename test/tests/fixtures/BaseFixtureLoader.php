<?php
namespace AEUtils\test;

use Exception;
use \AEUtils\tests\BaseFixtureLoader;

class TestBaseFixtureLoader extends BaseFixtureLoader {

  public function __construct() {
    Parent::__construct(__DIR__ . '/fixtures/', '.json');
  }

  protected function _parse_fixture_data($file_contents) {
    return json_decode($file_contents, true);
  }

  public function get_loaded_files() {
    return $this->_loaded_files;
  }

  public function get_fixture_directory() {
    return $this->_fixture_directory;
  }

  public function get_fixture_file_ending() {
    return $this->_fixture_file_ending;
  }

}

class BaseFixtureLoaderTest extends \PHPUnit_Framework_TestCase {

  public function testNonStringDirectory() {
    try {
      new BaseFixtureLoader(1, '.json');
    } catch (Exception $error) {
      $this->assertEquals('Fixture directory must be a string and a valid directory', $error->getMessage());
      return;
    }

    $this->fail('Expected exception not thrown');
  }

  public function testNonStringFileEnding() {
    try {
      new BaseFixtureLoader(__DIR__ . '/fixtures/', 1);
    } catch (Exception $error) {
      $this->assertEquals('File ending must be a string', $error->getMessage());
      return;
    }

    $this->fail('Expected exception not thrown');
  }

  public function testInvalidDirectory() {
    try {
      new BaseFixtureLoader('/llama', '.json');
    } catch (Exception $error) {
      $this->assertEquals('Fixture directory must be a string and a valid directory', $error->getMessage());
      return;
    }

    $this->fail('Expected exception not thrown');
  }

  public function testValidDirectory() {
    try {
      new BaseFixtureLoader(__DIR__ . '/fixtures/', '.json');
    } catch (Exception $error) {
      $this->fail('Exception was thrown: ' . $error->getMessage());
      return;
    }
  }

  public function testMethodsExist() {
    $loader = new BaseFixtureLoader(__DIR__ . '/fixtures/', '.json');
    $this->assertEquals(method_exists($loader, 'getOne'), true);
    $this->assertEquals(method_exists($loader, 'getRange'), true);
    $this->assertEquals(method_exists($loader, 'getAll'), true);
  }

  public function testNonStringTable() {
    $loader = new BaseFixtureLoader(__DIR__ . '/fixtures/', '.json');

    try {
      $loader->getAll(1337);
    } catch (Exception $error) {
      $this->assertEquals('Table must be a string', $error->getMessage());
      return;
    }

    $this->fail('Expected exception not thrown');
  }

  public function testNoParseMethodOverloaded() {
    $loader = new BaseFixtureLoader(__DIR__ . '/fixtures/', '.json');

    try {
      $loader->getAll('sample');
    } catch (Exception $error) {
      $this->assertEquals('Please specify a parser for the fixture data', $error->getMessage());
      return;
    }

    $this->fail('Expected exception not thrown');
  }

  public function testFixtureDirectoryIsSaved() {
    $loader = new TestBaseFixtureLoader();
    $this->assertEquals(is_string($loader->get_fixture_directory()), true);
  }

  public function testFixtureFileEndingIsSaved() {
    $loader = new TestBaseFixtureLoader();
    $this->assertEquals($loader->get_fixture_file_ending(), '.json');
  }

  public function testInvalidTable() {
    $loader = new TestBaseFixtureLoader();

    try {
      $loader->getAll('doesntexist');
    } catch (Exception $error) {
      $this->assertEquals('Fixture data for table "doesntexist" not found', $error->getMessage());
      return;
    }

    $this->fail('Expected exception not thrown');
  }

  public function testGetAll() {
    $expected_data = array(
      array('foo' => 'bar'),
      array('testing' => 123),
      array('llamas' => 'are cool'),
      array('bocoup' => 'is awesome'),
    );

    $loader = new TestBaseFixtureLoader();
    $this->assertEquals($loader->getAll('sample'), $expected_data);
  }

  public function testNonIntegerIndexForGetOne() {
    $loader = new TestBaseFixtureLoader();

    try {
      $loader->getOne('sample', '1');
    } catch (Exception $error) {
      $this->assertEquals('Index must be an integer', $error->getMessage());
      return;
    }

    $this->fail('Expected exception not thrown');
  }

  public function testGetOne() {
    $expected_data = array('testing' => 123);

    $loader = new TestBaseFixtureLoader();
    $this->assertEquals($loader->getOne('sample', 1), $expected_data);
  }

  public function testNonIntegerOffsetForGetRange() {
    $loader = new TestBaseFixtureLoader();

    try {
      $loader->getRange('sample', '1', 1);
    } catch (Exception $error) {
      $this->assertEquals('Offset must be a positive integer', $error->getMessage());
      return;
    }

    $this->fail('Expected exception not thrown');
  }

  public function testNonIntegerLengthForGetRange() {
    $loader = new TestBaseFixtureLoader();

    try {
      $loader->getRange('sample', 1, '1');
    } catch (Exception $error) {
      $this->assertEquals('Length must be a positive integer greater than zero', $error->getMessage());
      return;
    }

    $this->fail('Expected exception not thrown');
  }

  public function testGetRange() {
    $loader = new TestBaseFixtureLoader();
    $expected_data = array(
      array('testing' => 123),
      array('llamas' => 'are cool')
    );

    try {
      $fixtures = $loader->getRange('sample', 1, 2);
      $this->assertEquals($fixtures, $expected_data);
    } catch(Exception $error) {
      $this->fail('Exception was thrown: ' . $error->getMessage());
    }
  }

  public function testCacheIsWorking() {
    $loader = new TestBaseFixtureLoader();
    $this->assertEquals($loader->get_loaded_files(), array());

    $fixtures = $loader->getAll('sample');
    $expected_data = array('sample' => $fixtures);
    $this->assertEquals($loader->get_loaded_files(), $expected_data);
  }

}
