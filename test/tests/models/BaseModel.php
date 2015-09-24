<?php
namespace AEUtils\test;
use Exception;

class Model extends \AEUtils\models\BaseModel {
  protected $_json_type = 'test json type';
  protected $_db_table = 'model_test_table';
  protected $_db_schema = array(
    'prim' => array('type' => 'int'),
    'test_attr1' => array('type' => 'int'),
    'test_attr2' => array('type' => 'float', 'default' => 45.),
    'test_attr3' => array(
      'type' => 'string',
      'default' => 'via value',
      'defaultFunction' => '\AEUtils\test\Model::attr3DefaultFunction'
    ),
    'test_attr4' => array(
      'type' => 'bool',
      'default' => 1
    ),
    'inactive' => array('type' => 'bool', 'default' => null)
  );
  protected $_db_primary_key = 'prim';
  protected $_attributes = array(
    'test_attr1', 'test_attr2', 'test_attr3', 'test_attr4', 'inactive'
  );
  protected $_linked_tables = array('linked1', 'linked2');

  static public function attr3DefaultFunction() {
    return 'via function';
  }
}

class BaseModelTest extends AEUtilsTestCase
{
  protected $_table_name = 'model_test_table';
  protected $_fixture = array(
    array( 'prim' => 1, 'test_attr1' => 4, 'test_attr2' => 6 ),
    array( 'prim' => 2, 'test_attr1' => 5, 'test_attr2' => 6 ),
    array( 'prim' => 3, 'test_attr1' => 5, 'test_attr2' => 7 )
  );
  protected $_schema = <<<SQL
    DROP TABLE IF EXISTS `model_test_table`;
    CREATE TABLE `model_test_table` (
      `prim` int(11) NOT NULL AUTO_INCREMENT,
      `test_attr1` int(11) DEFAULT 0,
      `test_attr2` float DEFAULT 0,
      `test_attr3` varchar(30) DEFAULT 'foo',
      `test_attr4` bool DEFAULT 1,
      `inactive` bool DEFAULT 0,
      PRIMARY KEY (`prim`)
    );
SQL;

  public function setUp(){
    $this->m = new Model();
    parent::setUp();
  }

  public function testInitFromDefaults()
  {
    $data = $this->m->get_data();

    $this->assertSame(
      null,
      $data['test_attr1'],
      'Uses `null` as default value when no value is specified'
    );
    $this->assertSame(45., $data['test_attr2']);
    $this->assertSame(
      'via function',
      $data['test_attr3'],
      'Derives from `defaultFunction` where specified (prefering over `default` if present)'
    );
  }

  public function testInitFromIdLiteral()
  {
    $r = new Model(2);

    $data = $r->get_data();

    $this->assertSame(2, $data['prim']);
    $this->assertSame(5, $data['test_attr1']);
    $this->assertSame(6., $data['test_attr2']);
    $this->assertSame('foo', $data['test_attr3']);
    $this->assertArrayNotHasKey(
      'inactive', $data, 'removes the `inactive` attribute'
    );
  }

  public function testInitFromArrayWithId()
  {
    $r = new Model(array( 'prim' => 3 ));

    $data = $r->get_data();

    $this->assertSame(3, $data['prim']);
    $this->assertSame(5, $data['test_attr1']);
    $this->assertSame(7., $data['test_attr2']);
    $this->assertSame('foo', $data['test_attr3']);
    $this->assertArrayNotHasKey(
      'inactive', $data, 'removes the `inactive` attribute'
    );
  }

  public function testInitFromNotFound()
  {
    $error = null;

    try {
      new Model(array( 'test_attr2' => 4000 ));
    } catch (Exception $e) {
      $error = $e;
    }

    $this->assertNotNull($error);
  }

  public function testInitFromUnderconstrained()
  {
    $error = null;

    try {
      new Model(array( 'test_attr2' => 4 ));
    } catch (Exception $e) {
      $error = $e;
    }

    $this->assertNotNull($error);
  }

  public function testInitFromArrayWithoutId()
  {
    $r = new Model(array( 'test_attr2' => 6., 'test_attr1' => 4 ));

    $data = $r->get_data();

    $this->assertSame(1, $data['prim']);
    $this->assertSame(4, $data['test_attr1']);
    $this->assertSame(6., $data['test_attr2']);
    $this->assertSame('foo', $data['test_attr3']);
    $this->assertArrayNotHasKey(
      'inactive', $data, 'removes the `inactive` attribute'
    );
  }

  public function testInitFromArrayWithoutInvalid()
  {
    $error = null;

    try {
      new Model(array( 'test_attr2' => 'not a number' ));
    } catch (Exception $e) {
      $error = $e;
    }

    $this->assertNotNull($error);
  }

  public function testGetUnknown()
  {
    $error = null;

    try {
      $this->m->foo;
    } catch (Exception $e) {
      $error = $e;
    }

    $this->assertNotNull($error);
  }

  public function testSetValid()
  {
    $this->m->test_attr1 = 86;

    $this->assertSame(86, $this->m->get_data()['test_attr1']);
  }

  public function testSetUnknownAttr()
  {
    $err = null;

    try {
      $this->m->foo = 23;
    } catch (Exception $e) {
      $err = $e;
    }

    $this->assertNotNull($err);
  }

  public function testSetInvalidValue()
  {
    $err = null;

    try {
      $this->m->test_attr2 = 'not a number';
    } catch (Exception $e) {
      $err = $e;
    }

    $this->assertNotNull($err);
  }

  public function testSaveCreate()
  {
    $this->m->test_attr1 = 45;
    $this->m->test_attr2 = 86.;
    $this->m->inactive = 1;

    $this->m->save();

    $this->assertSame(
      $this->m->prim,
      4,
      'sets the primary key on the newly-inserted model object'
    );

    $queryTable = $this->tableFromQuery(
      'SELECT prim, test_attr1, test_attr2, test_attr3, inactive FROM model_test_table'
    );

    $expectedTable = $this->tableFromArray(array(
      array(
        'prim' => 1,
        'test_attr1' => 4,
        'test_attr2' => 6,
        'test_attr3' => 'foo',
        'inactive' => 0
      ),
      array(
        'prim' => 2,
        'test_attr1' => 5,
        'test_attr2' => 6,
        'test_attr3' => 'foo',
        'inactive' => 0
      ),
      array(
        'prim' => 3,
        'test_attr1' => 5,
        'test_attr2' => 7,
        'test_attr3' => 'foo',
        'inactive' => 0
      ),
      array(
        'prim' => 4,
        'test_attr1' => 45,
        'test_attr2' => 86,
        'test_attr3' => 'via function',
        'inactive' => 1
      )
    ));
    $this->assertTablesEqual($expectedTable, $queryTable);
  }

  public function testSaveCreateWithNullDefault()
  {
    $this->m->test_attr1 = 45;
    $this->m->test_attr2 = 86.;

    $this->m->save();

    $this->assertSame(
      $this->m->prim,
      4,
      'sets the primary key on the newly-inserted model object'
    );

    $queryTable = $this->tableFromQuery(
      'SELECT prim, test_attr1, test_attr2, test_attr3, inactive FROM model_test_table'
    );

    $expectedTable = $this->tableFromArray(array(
      array(
        'prim' => 1,
        'test_attr1' => 4,
        'test_attr2' => 6,
        'test_attr3' => 'foo',
        'inactive' => 0
      ),
      array(
        'prim' => 2,
        'test_attr1' => 5,
        'test_attr2' => 6,
        'test_attr3' => 'foo',
        'inactive' => 0
      ),
      array(
        'prim' => 3,
        'test_attr1' => 5,
        'test_attr2' => 7,
        'test_attr3' => 'foo',
        'inactive' => 0
      ),
      array(
        'prim' => 4,
        'test_attr1' => 45,
        'test_attr2' => 86,
        'test_attr3' => 'via function',
        'inactive' => null
      )
    ));
    $this->assertTablesEqual($expectedTable, $queryTable);
  }

  public function testSaveUpdateFromDB()
  {
    $m = new Model(2);
    $m->test_attr1 = 45;
    $m->test_attr2 = 86.;
    $m->inactive = 1;

    $m->save();

    $queryTable = $this->tableFromQuery(
      'SELECT prim, test_attr1, test_attr2, test_attr3, inactive FROM model_test_table'
    );

    $expectedTable = $this->tableFromArray(array(
      array(
        'prim' => 1,
        'test_attr1' => 4,
        'test_attr2' => 6,
        'test_attr3' => 'foo',
        'inactive' => 0
      ),
      array(
        'prim' => 2,
        'test_attr1' => 45,
        'test_attr2' => 86,
        'test_attr3' => 'foo',
        'inactive' => 1
      ),
      array(
        'prim' => 3,
        'test_attr1' => 5,
        'test_attr2' => 7,
        'test_attr3' => 'foo',
        'inactive' => 0
      )
    ));
    $this->assertTablesEqual($expectedTable, $queryTable);
  }

  public function testSoftDeleteExisting()
  {
    $m = new Model(3);
    $m->inactive = 0;

    $m->soft_delete();

    $this->assertSame(1, $m->inactive);

    $queryTable = $this->tableFromQuery(
      'SELECT prim, test_attr1, test_attr2, test_attr3, inactive FROM model_test_table'
    );

    $expectedTable = $this->tableFromArray(array(
      array(
        'prim' => 1,
        'test_attr1' => 4,
        'test_attr2' => 6,
        'test_attr3' => 'foo',
        'inactive' => 0
      ),
      array(
        'prim' => 2,
        'test_attr1' => 5,
        'test_attr2' => 6,
        'test_attr3' => 'foo',
        'inactive' => 0
      ),
      array(
        'prim' => 3,
        'test_attr1' => 5,
        'test_attr2' => 7,
        'test_attr3' => 'foo',
        'inactive' => 1
      )
    ));
    $this->assertTablesEqual($expectedTable, $queryTable);
  }

  public function testDeleteNew()
  {
    $error = null;

    try {
      $this->m->delete();
    } catch (Exception $e) {
      $error = $e;
    }

    $this->assertNotNull($error);
  }

  public function testDeleteSaved()
  {
    $m = new Model(1);

    $m->delete();

    $queryTable = $this->tableFromQuery(
      'SELECT prim, test_attr1, test_attr2, test_attr3, inactive FROM model_test_table'
    );

    $expectedTable = $this->tableFromArray(array(
      array(
        'prim' => 2,
        'test_attr1' => 5,
        'test_attr2' => 6,
        'test_attr3' => 'foo',
        'inactive' => 0
      ),
      array(
        'prim' => 3,
        'test_attr1' => 5,
        'test_attr2' => 7,
        'test_attr3' => 'foo',
        'inactive' => 0
      )
    ));
    $this->assertTablesEqual($expectedTable, $queryTable);
  }

  public function testToJsonNew()
  {
    $this->m->test_attr1 = 333;
    $this->m->test_attr2 = 444.1;
    $this->m->test_attr3 = 'mosey';

    $actual = json_decode($this->m->to_json());

    $this->assertSame($actual->test_attr1, 333);
    $this->assertSame($actual->test_attr2, 444.1);
    $this->assertSame($actual->test_attr3, 'mosey');
    $this->assertSame($actual->inactive, null);
  }

  public function testToJsonSaved()
  {
    $m = new Model(2);

    $actual = json_decode($m->to_json());

    $this->assertSame($actual->test_attr1, 5);
    $this->assertSame($actual->test_attr2, 6);
    $this->assertSame($actual->test_attr3, 'foo');
  }

  public function testGetDBTable()
  {
    $this->assertSame('model_test_table', $this->m->get_db_table());
  }

  public function testGetJSONType()
  {
    $this->assertSame('test json type', $this->m->get_json_type());
  }

  public function testGetUserFriendlyData()
  {
    $this->m->test_attr1 = 1;
    $this->m->test_attr2 = 2.;
    $this->m->test_attr3 = '3';
    $this->m->test_attr4 = 0;
    $this->m->inactive = 0;

    $actual = $this->m->get_user_friendly_data();

    $this->assertSame($actual['test_attr1'], 1);
    $this->assertSame($actual['test_attr2'], 2.);
    $this->assertSame($actual['test_attr3'], '3');
    $this->assertSame($actual['test_attr4'], false);
    $this->assertArrayNotHasKey(
      'inactive', $actual, 'removes the `inactive` attribute'
    );

    $this->m->test_attr1 = 1;
    $this->m->test_attr2 = 2.;
    $this->m->test_attr3 = '3';
    $this->m->test_attr4 = 1;
    $this->m->inactive = 1;

    $actual = $this->m->get_user_friendly_data();

    $this->assertSame($actual['test_attr1'], 1);
    $this->assertSame($actual['test_attr2'], 2.);
    $this->assertSame($actual['test_attr3'], '3');
    $this->assertSame($actual['test_attr4'], true);
    $this->assertArrayNotHasKey(
      'inactive', $actual, 'removes the `inactive` attribute'
    );
  }

  public function testGetDBSchema()
  {
    $schema = $this->m->get_db_schema();

    $this->assertArrayHasKey('prim', $schema);
    $this->assertArrayHasKey('type', $schema['prim']);
    $this->assertSame('int', $schema['prim']['type']);

    $this->assertArrayHasKey('test_attr1', $schema);
    $this->assertArrayHasKey('type', $schema['test_attr1']);
    $this->assertSame('int', $schema['test_attr1']['type']);

    $this->assertArrayHasKey('test_attr2', $schema);
    $this->assertArrayHasKey('type', $schema['test_attr2']);
    $this->assertSame('float', $schema['test_attr2']['type']);

    $this->assertArrayHasKey('test_attr3', $schema);
    $this->assertArrayHasKey('type', $schema['test_attr3']);
    $this->assertSame('string', $schema['test_attr3']['type']);
    $this->assertArrayHasKey('default', $schema['test_attr3']);
    $this->assertSame('via value', $schema['test_attr3']['default']);
    $this->assertArrayHasKey('defaultFunction', $schema['test_attr3']);

    $this->assertArrayHasKey('test_attr4', $schema);
    $this->assertArrayHasKey('type', $schema['test_attr4']);
    $this->assertSame('bool', $schema['test_attr4']['type']);
    $this->assertArrayHasKey('default', $schema['test_attr4']);
    $this->assertSame(1, $schema['test_attr4']['default']);

    $this->assertArrayHasKey('inactive', $schema);
    $this->assertArrayHasKey('type', $schema['inactive']);
    $this->assertSame('bool', $schema['inactive']['type']);
  }

  public function testGetDbPrimaryKey()
  {
    $this->assertSame('prim', $this->m->get_db_primary_key());
  }

  public function testGetAttributes()
  {
    $attrs = $this->m->get_attributes();

    $this->assertTrue(in_array('test_attr1', $attrs));
    $this->assertTrue(in_array('test_attr2', $attrs));
    $this->assertTrue(in_array('test_attr3', $attrs));
    $this->assertTrue(in_array('test_attr4', $attrs));
    $this->assertTrue(in_array('inactive', $attrs));
    $this->assertCount(5, $attrs);
  }

  public function testGetLinkedTables()
  {
    $tables = $this->m->get_linked_tables();

    $this->assertTrue(in_array('linked1', $tables));
    $this->assertTrue(in_array('linked2', $tables));
  }

  public function testIsValidAttribute()
  {
    $this->assertFalse(
      $this->m->is_valid_attribute('prim'), 'The primary key is not an "attribute"'
    );

    $this->assertTrue($this->m->is_valid_attribute('test_attr1'));
    $this->assertTrue($this->m->is_valid_attribute('test_attr2'));
    $this->assertTrue($this->m->is_valid_attribute('test_attr3'));
    $this->assertTrue($this->m->is_valid_attribute('test_attr4'));
    $this->assertTrue($this->m->is_valid_attribute('inactive'));

    $this->assertFalse($this->m->is_valid_attribute('test_attr5'));
  }

  public function testIsValidDBField()
  {
    $this->assertTrue($this->m->is_valid_db_field('prim'));
    $this->assertTrue($this->m->is_valid_db_field('test_attr1'));
    $this->assertTrue($this->m->is_valid_db_field('test_attr2'));
    $this->assertTrue($this->m->is_valid_db_field('test_attr3'));
    $this->assertTrue($this->m->is_valid_db_field('test_attr4'));
    $this->assertTrue($this->m->is_valid_db_field('inactive'));

    $this->assertFalse($this->m->is_valid_db_field('test_attr5'));
  }

  public function testIsValidDBFieldValue()
  {
    $this->assertTrue($this->m->is_valid_db_field_value('prim', 86));
    $this->assertFalse($this->m->is_valid_db_field_value('prim', ''));
    $this->assertFalse($this->m->is_valid_db_field_value('prim', true));
    $this->assertFalse($this->m->is_valid_db_field_value('prim', 86.));
    $this->assertFalse($this->m->is_valid_db_field_value('prim', null));
    $this->assertFalse($this->m->is_valid_db_field_value('prim'));

    $this->assertTrue($this->m->is_valid_db_field_value('test_attr1', 86));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr1', ''));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr1', true));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr1', 86.));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr1', null));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr1'));

    $this->assertTrue($this->m->is_valid_db_field_value('test_attr2', 86.));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr2', ''));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr2', true));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr2', 86));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr2', null));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr2'));

    $this->assertTrue($this->m->is_valid_db_field_value('test_attr3', ''));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr3', 86));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr3', true));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr3', 86.));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr3', null));
    $this->assertTrue($this->m->is_valid_db_field_value('test_attr3'));

    $this->assertTrue($this->m->is_valid_db_field_value('test_attr4', true));
    $this->assertTrue($this->m->is_valid_db_field_value('test_attr4', false));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr4', 86));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr4', ''));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr4', 86.));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr4', null));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr4'));

    $this->assertTrue($this->m->is_valid_db_field_value('inactive', true));
    $this->assertTrue($this->m->is_valid_db_field_value('inactive', false));
    $this->assertFalse($this->m->is_valid_db_field_value('inactive', 86));
    $this->assertFalse($this->m->is_valid_db_field_value('inactive', ''));
    $this->assertFalse($this->m->is_valid_db_field_value('inactive', 86.));
    $this->assertFalse($this->m->is_valid_db_field_value('inactive', null));
    $this->assertFalse($this->m->is_valid_db_field_value('inactive'));

    $this->assertFalse($this->m->is_valid_db_field_value('test_attr5', 'int'));
    $this->assertFalse($this->m->is_valid_db_field_value('test_attr5'));
    $this->assertFalse($this->m->is_valid_db_field_value());
  }

  public function testIsValidSchemaType()
  {
    $this->assertTrue($this->m->is_valid_schema_type('int'));
    $this->assertTrue($this->m->is_valid_schema_type('string'));
    $this->assertTrue($this->m->is_valid_schema_type('bool'));
    $this->assertTrue($this->m->is_valid_schema_type('float'));

    $this->assertFalse($this->m->is_valid_schema_type());
    $this->assertFalse($this->m->is_valid_schema_type('ints'));
    $this->assertFalse($this->m->is_valid_schema_type('tinyint'));
    $this->assertFalse($this->m->is_valid_schema_type('double'));
    $this->assertFalse($this->m->is_valid_schema_type('varchar'));
  }

  public function testSanitizeDBFieldValue()
  {
    $this->assertSame($this->m->sanitize_db_field_value('prim', 1), 1);
    $this->assertSame($this->m->sanitize_db_field_value('prim', 1.2), 1);
    $this->assertSame($this->m->sanitize_db_field_value('prim', '2.999'), 2);
    $this->assertSame($this->m->sanitize_db_field_value('prim', true), 1);
    $this->assertSame($this->m->sanitize_db_field_value('prim', false), 0);
    $this->assertSame($this->m->sanitize_db_field_value('prim', null), 0);

    $this->assertSame($this->m->sanitize_db_field_value('test_attr2', 1), 1.);
    $this->assertSame($this->m->sanitize_db_field_value('test_attr2', 1.2), 1.2);
    $this->assertSame($this->m->sanitize_db_field_value('test_attr2', '2.999'), 2.999);
    $this->assertSame($this->m->sanitize_db_field_value('test_attr2', true), 1.);
    $this->assertSame($this->m->sanitize_db_field_value('test_attr2', false), 0.);
    $this->assertSame($this->m->sanitize_db_field_value('test_attr2', null), 0.);

    $this->assertSame($this->m->sanitize_db_field_value('test_attr3', 1), '1');
    $this->assertSame($this->m->sanitize_db_field_value('test_attr3', 1.2), '1.2');
    $this->assertSame($this->m->sanitize_db_field_value('test_attr3', '2.999'), '2.999');
    $this->assertSame($this->m->sanitize_db_field_value('test_attr3', true), '1');
    $this->assertSame($this->m->sanitize_db_field_value('test_attr3', false), '');
    $this->assertSame($this->m->sanitize_db_field_value('test_attr3', null), '');

    $this->assertSame($this->m->sanitize_db_field_value('inactive', true), 1);
    $this->assertSame($this->m->sanitize_db_field_value('inactive', '1'), 1);
    $this->assertSame($this->m->sanitize_db_field_value('inactive', 1), 1);
    $this->assertSame($this->m->sanitize_db_field_value('inactive', 'TRUE'), 1);
    $this->assertSame($this->m->sanitize_db_field_value('inactive', 'true'), 1);
    $this->assertSame($this->m->sanitize_db_field_value('inactive', false), 0);
  }
}
