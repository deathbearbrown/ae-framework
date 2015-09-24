<?php
namespace AEUtils\test;

class TestModel extends \AEUtils\models\BaseModel {
  protected $_json_type = 'test json type';
  protected $_db_table = 'test_table';
  protected $_db_schema = array(
    'prim' => array('type' => 'int'),
    'test_attr1' => array('type' => 'int'),
    'test_attr2' => array('type' => 'int'),
    'inactive' => array('type' => 'bool')
  );
  protected $_db_primary_key = 'prim';
  protected $_attributes = array('test_attr1', 'test_attr2', 'inactive');
}
