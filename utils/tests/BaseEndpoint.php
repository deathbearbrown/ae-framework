<?php

abstract class BaseEndpoint extends PHPUnit_Extensions_Database_TestCase {

  const API_BASE_URL = 'http://auth.dev/test/';

  protected $_guzzle = null;
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

  protected function setUp() {
    $params = array(
      'base_url' => self::API_BASE_URL
    );

    $this->_guzzle = new GuzzleHttp\Client($params);
    parent::setUp();
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

  protected function _fetch_results($url, $method, $data = null) {
    $method = strtolower($method);
    if (!method_exists($this->_guzzle, $method)) {
      throw new Exception('Invalid guzzle HTTP method specified');
    }

    if ($method === 'post' || $method === 'patch') {
      if (is_null($data)) {
        throw new Exception('Please specify valid data to ' . $method);
      }

      $data = array(
        'body' => $data,
        'exceptions' => false
      );

      $response = $this->_guzzle->$method($url, $data);
    } else {
      $response = $this->_guzzle->$method($url);
    }

    return $response;
  }

  protected function _db_get_by_id($table, $id) {
    $query = 'SELECT * FROM `' . $table . '` WHERE id=' . $id . ' LIMIT 1;';
    $statement = $this->_conn->getConnection()->prepare($query);
    $statement->execute();
    $results = $statement->fetchAll();

    if (count($results) === 1) {
      return $results[0];
    }

    return null;
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

  public function truncate($table, $foriegnKey=false){
  	if ($foriegnKey){
    	$query = 'truncate`' . $table . '`;';
  	} else {
  		$query = 'delete`' . $table . '`;';
  	}
    $this->_conn->getConnection()->exec($query);
  }

  public function tearDown() {
    //always remove the dataset
    $dataset = $this->_data_set;
    $this->_db_remove_rows($dataset);
    parent::tearDown();
  }

  public function dashKeys($array, $arrayHolder = array()) {
    $dashArray = !empty($arrayHolder) ? $arrayHolder : array();
    foreach ($array as $key => $val) {
      $newKey = str_replace('_', '-', $key);
      if (!is_array($val)) {
        $dashArray[$newKey] = $val;
      } else {
        $dashArray[$newKey] = $this->dashKeys($val);
      }
    }
    return $dashArray;
  }
}
