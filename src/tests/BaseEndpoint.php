<?php
namespace AEUtils\tests;

use PDO;
use Exception;

abstract class BaseEndpoint extends \PHPUnit_Extensions_Database_TestCase {
  protected $_request_base_url = null;
  protected $_db_credentials = null;

  protected $_guzzle = null;
  static private $_pdo = null;
  protected $_conn = null;
  protected $_data_set = array();
  protected $_fixture_data = array();

  public function getConnection() {
    if (!$this->_conn) {
      $this->assertNotNull(
        $this->_db_credentials, 'Database credentials specified'
      );

      if (!self::$_pdo) {
        self::$_pdo = new PDO(
          'mysql:host=' . $this->_db_credentials['host'] .';dbname=' . $this->_db_credentials['database'],
          $this->_db_credentials['username'],
          $this->_db_credentials['password']
        );
      }

      $this->_conn = $this->createDefaultDBConnection(
        self::$_pdo, $this->_db_credentials['database']
      );
    }

    return $this->_conn;
  }

  public function getDataSet() {
    $dataset = $this->_data_set;
    return $this->createArrayDataSet($dataset);
  }

  protected function setUp() {
    $this->assertNotNull(
      $this->_request_base_url, 'Base URL for web requests specified'
    );

    $params = array(
      'base_url' => $this->_request_base_url,
      'defaults' => array(
        'query'  => array('testmode' => 'true')
      )
    );

    $this->_guzzle = new \GuzzleHttp\Client($params);
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

  const HTTP_METHODS_REQUIRING_DATA = array('post', 'patch', 'put');

  /**
   * Perform an HTTP request.
   *
   * @param string $url The location where the request should be sent
   * @param string $method The HTTP verb to use
   * @param array $options Optional modifiers for the request as supported by
   *                       version 3 of the "Guzzle" library. Reference:
   *                       https://guzzle3.readthedocs.org/http-client/client.html#request-options
   */
  private function _request($url, $method, $options = array()) {
    $method = strtolower($method);
    if (!method_exists($this->_guzzle, $method)) {
      throw new Exception('Invalid guzzle HTTP method specified');
    }

    // Disable exceptions in order to simplify process of testing expected HTTP
    // errors
    $options = array_merge( $options, array( 'exceptions' => false ));

    if (in_array($method, self::HTTP_METHODS_REQUIRING_DATA)) {
      if (!array_key_exists('body', $options)) {
        throw new Exception('Please specify valid data to ' . $method);
      }
    }

    return $this->_guzzle->$method($url, $options);
  }

  protected function _fetch_results($url, $method, $data = null) {
    $options = array();

    if (is_array($data)) {
      if (!array_key_exists('body', $data)) {
        $options['body'] = $data;
      } else {
        $options = $data;
      }
    } else if ($data) {
      $options['body'] = $data;
    }

    return $this->_request($url, $method, $options);
  }

  protected function _fetch_results_json($url, $method, $data = null) {
    $options = array(
      'headers' => array( 'Content-Type' => 'application/json' )
    );

    if ($data !== null) {
      $options['body'] = json_encode($data);
    }

    return $this->_request($url, $method, $options);
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

  public function truncate($table){
    if (!$this->_conn) {
      return;
    }

    $query = 'SET FOREIGN_KEY_CHECKS=0; truncate `' . $table . '`; SET FOREIGN_KEY_CHECKS=1;';
    $this->_conn->getConnection()->exec($query);
  }

  public function tearDown() {
    //always remove the dataset
    $dataset = $this->_data_set;
    foreach ($dataset as $table => $row) {
      $this->truncate($table);
    }

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
