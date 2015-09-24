<?php
namespace AEUtils\test;

use Exception;
use \AEUtils\tests\JsonFixtureLoader;

class TestJsonFixtureLoader extends JsonFixtureLoader {

  public function __construct() {
    Parent::__construct(__DIR__ . '/fixtures/');
  }

}

class JsonFixtureLoaderTest extends \PHPUnit_Framework_TestCase {

  public function testMethodsExist() {
    $loader = new TestJsonFixtureLoader();
    $this->assertEquals(method_exists($loader, 'getOne'), true);
    $this->assertEquals(method_exists($loader, 'getRange'), true);
    $this->assertEquals(method_exists($loader, 'getAll'), true);
  }

  public function testGetAll() {
    $loader = new TestJsonFixtureLoader();
    $expected_data = array(
      array('foo' => 'bar'),
      array('testing' => 123),
      array('llamas' => 'are cool'),
      array('bocoup' => 'is awesome'),
    );

    $this->assertEquals($loader->getAll('sample'), $expected_data);
  }

  public function testGetRange() {
    $loader = new TestJsonFixtureLoader();
    $expected_data = array(
      array('llamas' => 'are cool'),
      array('bocoup' => 'is awesome')
    );

    $this->assertEquals($loader->getRange('sample', 2, 2), $expected_data);
  }

  public function testGetOne() {
    $loader = new TestJsonFixtureLoader();
    $expected_data = array('testing' => 123);

    $this->assertEquals($loader->getOne('sample', 1), $expected_data);
  }

}
