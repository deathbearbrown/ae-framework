<?php
namespace AEUtils\test;
use Exception;

class BaseRouteTest extends \PHPUnit_Framework_TestCase
{
  protected function setUp()
  {
    $this->r = new \AEUtils\routes\BaseRoute();
    parent::setUp();
  }

  public function testParseRequestBodyFormData()
  {
    $this->r->set_request(array(
      'headers' => array(
        'Content-Type' => 'application/x-www-form-urlencoded'
      ),
      'body' => '{"foo": "bar"}',
      'post' => array('baz' => 'qux')
    ));

    $data = $this->r->parseRequestBody();

    $this->assertCount(1, $data);
    $this->assertArrayHasKey('baz', $data);
    $this->assertSame('qux', $data['baz']);
  }

  public function testParseRequestBodyFormDataOddTypeCase()
  {
    $this->r->set_request(array(
      'headers' => array(
        'content-type' => 'application/x-www-form-urlencoded'
      ),
      'body' => '{"foo": "bar"}',
      'post' => array('baz' => 'qux')
    ));

    $data = $this->r->parseRequestBody();

    $this->assertCount(1, $data);
    $this->assertArrayHasKey('baz', $data);
    $this->assertSame('qux', $data['baz']);
  }

  public function testParseRequestBodyJson()
  {
    $this->r->set_request(array(
      'headers' => array(
        'Content-Type' => 'application/json'
      ),
      'body' => '{"foo": "bar"}',
      'post' => array('baz' => 'qux')
    ));

    $data = $this->r->parseRequestBody();

    $this->assertCount(1, $data);
    $this->assertArrayHasKey('foo', $data);
    $this->assertSame('bar', $data['foo']);
  }

  public function testParseRequestBodyJsonOddTypeCase()
  {
    $this->r->set_request(array(
      'headers' => array(
        'cOntent-tYpe' => 'application/json'
      ),
      'body' => '{"foo": "bar"}',
      'post' => array('baz' => 'qux')
    ));

    $data = $this->r->parseRequestBody();

    $this->assertCount(1, $data);
    $this->assertArrayHasKey('foo', $data);
    $this->assertSame('bar', $data['foo']);
  }

  public function testParseRequestBodyJsonWithParameters()
  {
    $this->r->set_request(array(
      'headers' => array(
        'Content-Type' => 'application/json; charset=utf-8'
      ),
      'body' => '{"foo": "bar"}',
      'post' => array('baz' => 'qux')
    ));

    $data = $this->r->parseRequestBody();

    $this->assertCount(1, $data);
    $this->assertArrayHasKey('foo', $data);
    $this->assertSame('bar', $data['foo']);
  }

  public function testParseRequestBodyInvalidJson()
  {
    $error = null;
    $this->r->set_request(array(
      'headers' => array(
        'Content-Type' => 'application/json'
      ),
      'body' => '{"foo": "bar""}',
      'post' => array('baz' => 'qux')
    ));

    try {
      $this->r->parseRequestBody();
    } catch (Exception $e) {
      $error = $e;
    }

    $this->assertNotNull($error);
  }

  public function testParseRequestBodyJsonNull()
  {
    $this->r->set_request(array(
      'headers' => array(
        'Content-Type' => 'application/json'
      ),
      'body' => '  null  ',
      'post' => array('baz' => 'qux')
    ));

    $data = $this->r->parseRequestBody();

    $this->assertNull($data);
  }

  public function testParseRequestBodyUnknown()
  {
    $this->r->set_request(array(
      'headers' => array(
      ),
      'body' => '{"foo": "bar"}',
      'post' => array('baz' => 'qux')
    ));

    $data = $this->r->parseRequestBody();

    $this->assertSame('{"foo": "bar"}', $data);
  }
}
