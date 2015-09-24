<?php
namespace AEUtils\test;
use PDO;
use Exception;

define('DB_HOST', '127.0.0.1');
define('DB_USERNAME', 'api');
define('DB_PASSWORD', 'bocoup');
define('TEST_DB_DATABASE', 'api');
define('DB_DATABASE', 'api');
define('TESTMODE', true);

/**
 * Concrete subclasses must define the following attributes:
 *
 * - _schema
 * - _table_name
 * - _fixture
 */
abstract class AEUtilsTestCase extends \PHPUnit_Extensions_Database_TestCase
{
  static private $pdo = null;
  private $conn = null;

  public static function setupBeforeClass()
  {
    $tmppdo = new PDO('mysql:host=' . DB_HOST, DB_USERNAME, DB_PASSWORD);
    $tmppdo->exec('CREATE DATABASE IF NOT EXISTS ' + DB_DATABASE);
    self::$pdo = new PDO(
      'mysql:host=' . DB_HOST . ';dbname=' . DB_DATABASE, DB_USERNAME,
       DB_PASSWORD);
  }

  protected function setUp()
  {
    self::$pdo->exec($this->_schema);
    parent::setUp();
  }

  public static function tearDownAfterClass()
  {
    self::$pdo = null;
  }

  public function getConnection()
  {
    if ($this->conn === null) {
      $this->conn = $this->createDefaultDBConnection(self::$pdo, DB_DATABASE);
    }

    return $this->conn;
  }

  public function getDataSet()
  {
    $dataset = array(
      $this->_table_name => $this->_fixture
    );

    return $this->createArrayDataSet($dataset);
  }

  public function tableFromQuery($query)
  {
    return $this->getConnection()->createQueryTable(
      $this->_table_name, $query
    );
  }

  public function tableFromArray($array)
  {
    return $this->createArrayDataSet(array( $this->_table_name => $array ))
      ->getTable($this->_table_name);
  }

  /**
   * Returns an array with the following keys
   *
   * - output {string}
   * - decoded {object} - JSON-decoded version of output (where possible)
   * - ecxeption {null|Exception}
   */
  public function captureResponse($fn)
  {
    $result = array();

    $result['exception'] = null;
    $result['decoded'] = null;

    ob_start();
    try {
      $fn($this);
    } catch(Exception $e) {
      $result['exception'] = $e;
    }

    $result['output'] = ob_get_contents();

    try {
      $result['decoded'] = json_decode($result['output']);
    } catch (Exception $e) {}

    ob_end_clean();

    return $result;
  }
}
