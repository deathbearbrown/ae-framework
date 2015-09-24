<?php
namespace AEUtils\test;

use Exception;
use \AEUtils\tests\XmlFixtureLoader;

class TestXmlFixtureLoader extends XmlFixtureLoader {

  public function __construct() {
    Parent::__construct(__DIR__ . '/fixtures/');
  }

}

class XmlFixtureLoaderTest extends \PHPUnit_Framework_TestCase {

  public function testMethodsExist() {
    $loader = new TestXmlFixtureLoader();
    $this->assertEquals(method_exists($loader, 'getOne'), true);
    $this->assertEquals(method_exists($loader, 'getRange'), true);
    $this->assertEquals(method_exists($loader, 'getAll'), true);
  }

  public function testGetAll() {
    $loader = new TestXmlFixtureLoader();
    $expected_data = array(
      'record' => array(
        array('foo' => 'bar'),
        array('testing' => 123),
        array('llamas' => 'are cool'),
        array('bocoup' => 'is awesome'),
      ),
    );

    $this->assertEquals($loader->getAll('sample'), $expected_data);
  }

}
