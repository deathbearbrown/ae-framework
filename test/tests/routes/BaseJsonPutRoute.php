<?php
namespace AEUtils\test;

class JsonPutRoute extends \AEUtils\routes\BaseJsonPutRoute {
  public function __construct()
  {
    $this->_model_object = new TestModel();
    Parent::__construct();
  }
}

class BaseJsonPutRouteTest extends AEUtilsTestCase
{
  protected $_table_name = 'test_table';
  protected $_fixture = array(
    array( 'prim' => 1, 'test_attr1' => 4, 'test_attr2' => 6 ),
    array( 'prim' => 2, 'test_attr1' => 5, 'test_attr2' => 6 ),
    array( 'prim' => 3, 'test_attr1' => 5, 'test_attr2' => 7 )
  );
  protected $_schema = <<<SQL
    DROP TABLE IF EXISTS `test_table`;
    CREATE TABLE `test_table` (
      `prim` int(11) NOT NULL AUTO_INCREMENT,
      `test_attr1` int(11) NOT NULL,
      `test_attr2` int(11) NOT NULL,
      `inactive` boolean,
      PRIMARY KEY (`prim`)
    );
SQL;

  protected function setUp()
  {
    $this->r = new JsonPutRoute();
    parent::setUp();
  }

  public function testUpdate()
  {
    $result = $this->captureResponse(function($self) {
      $self->r->update_model(array( 'prim' => 2, 'test_attr2' => 9 ));
    });

    $queryTable = $this->tableFromQuery('SELECT prim, test_attr1, test_attr2 FROM test_table');

    $this->assertNull($result['exception']);
    $expectedTable = $this->tableFromArray(array(
      array( 'prim' => 1, 'test_attr1' => 4, 'test_attr2' => 6 ),
      array( 'prim' => 2, 'test_attr1' => 5, 'test_attr2' => 9 ),
      array( 'prim' => 3, 'test_attr1' => 5, 'test_attr2' => 7 )
    ));
    $this->assertTablesEqual($expectedTable, $queryTable);

    $this->assertEquals(200, http_response_code());
  }

  public function testUpdateResponse()
  {
    $result = $this->captureResponse(function($self) {
      $self->r->update_model(
        array(
          'prim' => 3,
          'test_attr1' => 468,
          'test_attr2' => 91,
          'inactive' => '1'
        )
      );
    });

    $this->assertEquals(200, http_response_code());
    $this->assertNotNull($result['decoded']);
    $this->assertEquals($result['decoded']->prim, 3);
    $this->assertEquals(
      $result['decoded']->{'test-attr1'},
      468,
      'replaces underscore characters with dashes in reported field'
    );
    $this->assertObjectNotHasAttribute(
      'test_attr1',
      $result['decoded'],
      'does not report field using underscore characters'
    );

    $this->assertEquals(
      $result['decoded']->{'test-attr2'},
      91,
      'replaces underscore characters with dashes in reported field'
    );
    $this->assertObjectNotHasAttribute(
      'test_attr2',
      $result['decoded'],
      'does not report field using underscore characters'
    );

    $this->assertObjectNotHasAttribute(
      'inactive', $result['decoded'], 'does not report value of "inactive" field'
    );
  }

  public function testUpdateWithDashes()
  {
    $result = $this->captureResponse(function($self) {
      $self->r->update_model(
        array( 'prim' => 1, 'test-attr1' => 23 )
      );
    });

    $queryTable = $this->tableFromQuery('SELECT prim, test_attr1, test_attr2 FROM test_table');

    $this->assertNull($result['exception']);
    $expectedTable = $this->tableFromArray(array(
      array( 'prim' => 1, 'test_attr1' => 23, 'test_attr2' => 6 ),
      array( 'prim' => 2, 'test_attr1' => 5, 'test_attr2' => 6 ),
      array( 'prim' => 3, 'test_attr1' => 5, 'test_attr2' => 7 )
    ));
    $this->assertTablesEqual($expectedTable, $queryTable);

    $this->assertEquals(200, http_response_code());
  }

  public function testUpdateWithBogusKeys()
  {
    $result = $this->captureResponse(function($self) {
      $self->r->update_model(
        array(
          'prim' => 2,
          'test_attr1' => 23,
          'test_attr2' => 45,
          'inactive' => '1',
          'bogus' => 23
        )
      );
    });

    $queryTable = $this->tableFromQuery('SELECT prim FROM test_table');

    $expectedTable = $this->tableFromArray(array(
      array( 'prim' => 1 ),
      array( 'prim' => 2 ),
      array( 'prim' => 3 )
    ));
    $this->assertTablesEqual($expectedTable, $queryTable);

    $this->assertNull($result['exception']);
    $this->assertNotNull($result['decoded']);
    $this->assertCount(1, $result['decoded']);
    $this->assertEquals('INVALID_FIELD', $result['decoded'][0]->code);
    $this->assertEquals(400, http_response_code());
  }

  public function testFieldSanitization()
  {
    $result = $this->captureResponse(function($self) {
      $self->r->update_model(
        array(
          'prim' => 3,
          'test_attr1' => 23.3,
          'test_attr2' => '45',
          'inactive' => 'true'
        )
      );
    });

    $queryTable = $this->tableFromQuery('SELECT * FROM test_table');

    $this->assertNull($result['exception']);
    $expectedTable = $this->tableFromArray(array(
      array( 'prim' => 1, 'test_attr1' => 4, 'test_attr2' => 6, 'inactive' => null ),
      array( 'prim' => 2, 'test_attr1' => 5, 'test_attr2' => 6, 'inactive' => null ),
      array( 'prim' => 3, 'test_attr1' => 23, 'test_attr2' => 45, 'inactive' => 1 )
    ));
    $this->assertTablesEqual($expectedTable, $queryTable);

    $this->assertEquals(200, http_response_code());
  }

  public function testUpdateNonExistent()
  {
    $result = $this->captureResponse(function($self) {
      $self->r->update_model(array('prim' => 86, 'test_attr1' => 23.3));
    });

    $queryTable = $this->tableFromQuery('SELECT * FROM test_table');

    $this->assertNull($result['exception']);
    $expectedTable = $this->tableFromArray(array(
      array( 'prim' => 1, 'test_attr1' => 4, 'test_attr2' => 6, 'inactive' => null ),
      array( 'prim' => 2, 'test_attr1' => 5, 'test_attr2' => 6, 'inactive' => null ),
      array( 'prim' => 3, 'test_attr1' => 5, 'test_attr2' => 7, 'inactive' => null )
    ));
    $this->assertTablesEqual($expectedTable, $queryTable);

    $this->assertCount(1, $result['decoded']);
    $this->assertEquals('NOT_FOUND', $result['decoded'][0]->code);
    $this->assertEquals(404, http_response_code());
  }
}
