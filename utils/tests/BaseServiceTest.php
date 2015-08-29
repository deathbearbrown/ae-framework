<?php

abstract class BaseServiceTest extends PHPUnit_Extensions_Database_TestCase {

  static private $_pdo = null;
  protected $_conn = null;
  protected $_data_set = array();
  protected $_fixture_data = array();

  public function getConnection() {
    if (!$this->_conn) {
      if (!self::$_pdo) {
        self::$_pdo = new PDO('mysql:host=' . DB_HOST .';dbname=' .
          TEST_DB_DATABASE , DB_USERNAME , DB_PASSWORD);
      }

      $this->_conn = $this->createDefaultDBConnection(self::$_pdo, TEST_DB_DATABASE);
    }

    return $this->_conn;
  }

  public function getDataSet() {
    $dataset = $this->_data_set;
    return $this->createArrayDataSet($dataset);
  }

  protected function _db_insert_row($table, $data) {
    $query = 'INSERT INTO `' . $table . '` (';
    $keys = array_keys($data);
    $query_keys = array();

    foreach ($keys as $key) {
      $query_keys[] = '`' . $key . '`';
    }

    $query .= implode(', ', $query_keys) . ') VALUES(';

    $values = array_values($data);
    $query_values = array();
    foreach ($values as $value) {
      if (is_bool($value)) {
        $query_values[] = ($value === true) ? 1 : 0;
      } else if (is_string($value)) {
        $query_values[] = '"' . addslashes($value) . '"';
      } else {
        $query_values[] = $value;
      }
    }

    $query .= implode(', ', $query_values) . ');';
    $this->_conn->getConnection()->exec($query);
  }

  protected function _db_insert($data) {
    if (!is_array($data) || !count($data)) {
      throw new Exception('Please specify a valid array of data to insert.');
    }

    // data should be in an array where key is the tablename, and value is an
    // array of data
    foreach ($data as $table => $row) {
      // assume it's an array of dat
      if (is_array($row)) {
        foreach ($row as $newrow) {
          $this->_db_insert_row($table, $newrow);
        }
      } else {
        throw new Exception('Specify table data to insert as an array.');
      }
    }
  }

  protected function _db_remove_row($table, $id) {
    $query = 'DELETE FROM `' . $table . '` WHERE id=' . $id . ';';
    $this->_conn->getConnection()->exec($query);
  }

  protected function _db_remove_rows($data) {
    if (!is_array($data) && !count($data)) {
      throw new Exception('Specify data to delete as an array.');
    }

    foreach ($data as $table => $row) {
      if (is_array($row)) {
        foreach ($row as $newrow) {
          $this->_db_remove_row($table, $newrow['id']);
        }
      } else {
        throw new Exception('Specify table data to delete as an array.');
      }
    }
  }

  public function tearDown() {
    //always remove the dataset
    $dataset = $this->_data_set;
    $this->_db_remove_rows($dataset);
    parent::tearDown();
  }
}
