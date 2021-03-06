<?php
namespace AEUtils;
use \mysqli;

class Database implements DatabaseInterface {

  /**
   * @var Database contains the single instance of this class adhering to the
   *  principle of singleton's
   */
  protected static $_instance;

  /**
   * @var bool|mysqli contains the MySQLi object after the database connection
   *  has been established
   */
  protected static $_mysql = false;

  /**
   * @var string The query that's built through several commands
   */
  protected static $_query = '';

  /**
   * @var string The type of query to be built, aids in the string concatenation
   *  process.
   */
  protected static $_query_type = '';

  /**
   * @var array contains all of the variables using for binding the where
   *  clauses
   */
  protected static $_where = array();

  /**
   * @var array contains all of the variables that will be bound to the MySQLi
   *  object before query execution
   */
  protected static $_variable_binds = array();

  /**
   * @var array contains query data for updates, insertions, etc.
   */
  protected static $_query_data = array();

  /**
   * Parameter type descriptors used by MySQLI to bind PHP values to MySQL
   * prepared statements.
   *
   * Reference:
   * https://secure.php.net/manual/en/mysqli-stmt.bind-param.php
   */
  const MYSQLI_BIND_PARAM_TYPES = array(
    'string' => 's',
    'NULL' => 's',
    'double' => 'd',
    'integer' => 'i',
    'boolean' => 'i'
  );

  /**
   * Blank constructor
   */
  public function __construct() {}

  /**
   * Closes the database connection if it was left open for any reason
   */
  public function __destruct() {
    self::disconnect();
  }

  /**
   * If an instance of this class has been not been instantiated quite yet, this
   * function will create, cache and return the instance. If the instance of
   * this class has already been instantiated and cached, the cached version of
   * this class instantiation will be returned. Thus, adhering to the rules of
   * singleton's.
   *
   * @return Database The singleton instance of this class. Only ever
   *  instantiated but one time.
   */
  public static function instance() {
    if (!(self::$_instance instanceof self)) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  /**
   * If the Database timed our for any reason, we protect errors by closing out
   * the socket to the Database through the MySQLi object. Otherwise, this
   * handles connecting to the MySQL database specified in the config file.
   */
  public static function connect() {
    self::disconnect();

    //throw errors, but not too many
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    if (TESTMODE === 'true') {
      $database = TEST_DB_DATABASE;
    } else {
      $database = DB_DATABASE;
    }

    self::$_mysql = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, $database);
    self::$_mysql->autocommit(false);
  }

  /**
   * If we had previously connected to the MySQL database, this function will
   * disconnect from the MySQL server and close the socket.
   */
  public static function disconnect() {
    if (!self::$_mysql || !(self::$_mysql instanceof mysqli)) {
      return;
    }

    self::$_mysql->close();
    self::$_mysql = false;
  }

  /**
   * Switches to a selected database on the MySQLi socket
   *
   * @param string $database The database we need to switch to.
   */
  public static function switch_database($database = '') {
    if (!self::$_mysql || !(self::$_mysql instanceof mysqli)) {
      return;
    }

    self::$_mysql->select_db($database);
  }

  /**
   * Handles performing an advanced MySQL query. Does not take into account the
   * where() clauses executed prior to this function call. We rely on the
   * developer to indicate all of their where clauses within the query
   * statement.
   *
   * @param string $query The query to be performed by the MySQL server.
   * @param bool $get_results Indicates whether or not we need the results
   *  returned to us.
   * @return array|bool Data is returned if we're expecting the results back,
   *  otherwise true for the query performed.
   */
  public static function query($query = '', $get_results = true) {
    self::$_query = $query;
    $statement = self::_prepare_query();
    self::_execute($statement);

    if ($get_results) {
      return self::_fetch_results($statement);
    }

    return true;
  }

  /**
   * Handles a basic version of retrieving information from a table using the
   * API commands. Takes into account the previous where() clauses setup prior
   * to calling this function.
   *
   * @param string $table The table to get the information from.
   * @param bool|integer $num_rows The number of rows to get or false for all
   *  rows.
   * @param array $fields The fields to get; asterisk indicates to get all
   *  fields.
   * @return array Returns the results from the MySQLi query, namely an
   *  associative array containing the data requested.
   */
  public static function get($table = null, $num_rows = false, $fields = array('*')) {
    if (!is_null($table)){
      if (!is_array($fields)) {
        $fields = array($fields);
      }

      $query_fields = '`' . implode('`, `', $fields) . '`';
      if (count($fields) === 1 && $fields[0] === '*') {
        $query_fields = '*';
      }

      self::_set_query_type('GET');
      self::$_query = 'SELECT ' . $query_fields . ' FROM `' . $table . '`';
      $statement = self::_build_query($num_rows);
      self::_execute($statement);
      $results = self::_fetch_results($statement);
      return $results;
    }
  }

  /**
   * Performs a deletion command and takes into account the where() clauses
   * setup prior to calling this function.
   *
   * @param string $table The table to delete information from.
   * @return bool Indicates whether or not our delete request actually affected
   *  rows contained within the specified table.
   * @throws Exception
   */
  public static function delete($table = null) {
    if (!is_null($table)){
      self::_set_query_type('DELETE');
      self::$_query = ' DELETE FROM `' . $table . '`';
      $statement = self::_build_query();

      try {
        self::_execute($statement);
        self::$_mysql->commit();
      }
      catch(Exception $e) {
        self::$_mysql->rollback();
        throw new Exception('Could not perform delete: ' . $e->getMessage());
      }

      return (0 < $statement->affected_rows) ? true : false;
    }
  }

  /**
   * Handles performing insertions to the table specified with the data
   * specified. The data must be an array with keys being the field name and
   * values being the value to be inserted. This takes into account prior
   * where() clauses.
   *
   * @param string $table the table to insert the data to
   * @param array $data The data to be inserted into the table
   * @return bool Indicates whether or not the insertion affected any rows
   *  contained within the table. Returns the insert ID of the newly added
   *  record or, False if not inserted.
   * @throws Exception
   */
  public static function insert($table = null, $data = array()) {
    if (!is_null($table)){
      self::_set_query_type('INSERT');
      self::$_query = 'INSERT INTO `' . $table . '`';
      self::$_query_data = $data;
      $statement = self::_build_query();

      try {
        self::_execute($statement);
        self::$_mysql->commit();
      }
      catch(Exception $e) {
        self::$_mysql->rollback();
        throw new Exception('Could not perform insert: ' . $e->getMessage());
      }

      return (0 < $statement->affected_rows) ? $statement->insert_id : false;
    }
  }

  /**
   * Performs an update on the specified table with the specified data. The data
   * must be an array with the keys being the field name and values being the
   * value to be inserted. Takes into account prior where() clauses.
   *
   * @param string $table The table to perform the update to
   * @param array $data The data used to be updated
   * @return bool Indicates whether or not the insertion affected any rows
   *  contained within the table.
   * @throws Exception
   */
  public static function update($table = null, $data = array()) {
    if (!is_null($table)){
      self::_set_query_type('UPDATE');
      self::$_query = 'UPDATE `' . $table . '` SET ';
      self::$_query_data = $data;
      $statement = self::_build_query();

      try {
        self::_execute($statement);
        self::$_mysql->commit();
      }
      catch(Exception $e) {
        self::$_mysql->rollback();
        throw new Exception('Could not perform update: ' . $e->getMessage());
      }

      return (0 < $statement->affected_rows) ? true : false;
    }
  }

  /**
   * Gets the number of rows that were affected by the query without actually
   * returning the result. Takes into account any prior where() clauses that
   * were performed. You can specify num_rows and fields to cut down on MySQL
   * lookup time, etc.
   *
   * @param string $table The table to perform the query on
   * @param bool $num_rows The number of rows that MySQL will care about when
   *  performing the command
   * @param array $fields The fields that you want MySQL to care about when
   *  performing the command
   * @return integer returns the number of rows affected by the MySQL query
   */
  public static function num_rows($table = null, $num_rows = false, $fields = array('*')) {
    if (!is_null($table)){
      self::_set_query_type('GET');

      $query_fields = '`' . implode('`, `', $fields) . '`';
      if (count($fields) === 1 && $fields[0] === '*') {
        $query_fields = '*';
      }

      self::$_query = 'SELECT ' . $query_fields . ' FROM `' . $table . '`';
      $statement = self::_build_query($num_rows);
      self::_execute($statement);

      return $statement->num_rows;
    }
  }

  /**
   * Enqueues a where clause to be used with the current MySQL statement being
   * built.
   *
   * @param string $field The field from the table we are using to filter with
   *  the where
   * @param mixed $value The value for the correlated field specified
   */
  public static function where($field = '', $value = '') {
    self::$_where[$field] = $value;
  }

  /**
   * Internally sets the query type so we know how to build the query when the
   * user wants to execute it
   *
   * @param string $type
   */
  protected static function _set_query_type($type = '') {
    self::$_query_type = $type;
  }

  /**
   * Binds all of the user's variables directly to the MySQL statement that's
   * being prepared for execution.
   *
   * @param mysqli_stmt $statement The MySQLi statement for the current query
   *  being built
   */
  protected static function _bind_variables($statement) {
    if (count(self::$_variable_binds) === 0) {
      return;
    }

    $types = '';
    $values = array();

    array_walk(self::$_variable_binds, function(&$item) use (&$types, &$values) {
      $types .= $item['type'];
      $values[] = &$item['value'];
    });

    array_unshift($values, $types);
    call_user_func_array(array($statement, 'bind_param'), $values);
  }

  /**
   * Builds the query by checking the query type, adding the where clauses,
   * specifying the limit the user passed, if any.
   *
   * @param bool $num_rows Number of rows to add as a limit for the MySQLi query
   * @return mysqli_stmt returns the MySQLi statement with the variables
   *  properly bound to it
   */
  protected static function _build_query($num_rows = false) {
    self::_build_insert_clause();
    self::_build_update_clause();
    self::_append_where_clause();

    self::$_query .= (false !== $num_rows) ? ' LIMIT ' . intval($num_rows) : '';

    $statement = self::_prepare_query();
    self::_bind_variables($statement);
    return $statement;
  }

  /**
   * Builds the insertion portion of the MySQLi query and binds any information
   * the user might have passed to the parent wrapper function.
   */
  protected static function _build_insert_clause() {
    if ((self::$_query_type !== 'INSERT') || empty(self::$_query_data)) {
      return;
    }

    $keys = array_keys(self::$_query_data);
    $values = array_fill(0, count($keys), '?');

    $clause = ' ( `' . implode('`, `', $keys) . '` ) VALUES ( ' . implode(', ', $values) . ' ) ';

    array_walk(self::$_query_data, array(self::instance(), 'add_variable_binding'));

    self::$_query .= $clause;
  }

  /**
   * Builds the update portion of the MySQLi query and binds any information the
   * user might have passed to the parent wrapper function.
   */
  protected static function _build_update_clause() {
    if (self::$_query_type !== 'UPDATE' || empty(self::$_query_data)) {
      return;
    }

    $clauses = array();
    $this_scope = self::instance();
    array_walk(self::$_query_data, function($item, $key) use(&$clauses, &$this_scope) {
      $clauses[] = '`' . $key . '`= ?';
      $this_scope->add_variable_binding($item);
    });

    self::$_query .= implode(', ', $clauses);
  }

  /**
   * Takes care of appending the where portion of the MySQLi query. Also binds
   * the data to the MySQLi statement as specified in prior where() clauses
   */
  protected static function _append_where_clause() {
    if (empty(self::$_where)) {
      return;
    }

    $clauses = array();
    foreach (self::$_where as $field => $value) {
      $clauses[] = '`' . $field . '` = ?';
      self::add_variable_binding($value);
    }

    self::$_query .= ' WHERE ' . implode(' AND ', $clauses);
  }

  /**
   * Adds the variable bindings to the internal container that will eventually
   * be written to the current MySQLi statement being prepared.
   *
   * @param mixed $value Data to be bound to the MySQLi statement later on.
   */
  public static function add_variable_binding($value) {
    $php_type = gettype($value);
    $mysqli_type = self::MYSQLI_BIND_PARAM_TYPES[$php_type];

    // account for boolean variables that need to be converted to tinyints
    if ($php_type === 'boolean') {
      $value = ($value) ? 1 : 0;
    }

    self::$_variable_binds[] = array(
      'type' => $mysqli_type,
      'value' => $value
    );
  }

  /**
   * Prepares the query and handles any errors that occurred during the
   * preparation phase
   *
   * @return mysqli_stmt The prepared MySQLi statement
   * @throws Exception
   */
  protected static function _prepare_query() {
    if (!($statement = self::$_mysql->prepare(self::$_query))) {
      trigger_error('Query could not be prepared.<br/>Query: <b>' .
        self::$_query . '</b><br/>' . self::$_mysql->error, E_USER_ERROR);
    }

    return $statement;
  }

  /**
   * Executes the specified MySQLi statement, stores the result and resets the
   * cache for things like where clauses, etc
   *
   * @param mysqli_stmt $statement The MySQLi statement that was prepared and is
   *  now ready to be executed
   */
  protected static function _execute($statement) {
    $statement->execute();
    $statement->store_result();
    self::_reset_cache();
  }

  /**
   * Resets the internal cache for various things that are needed on a
   * per-query basis.
   */
  protected static function _reset_cache() {
    self::$_query = '';
    self::$_query_type = '';
    self::$_where = array();
    self::$_variable_binds = array();
    self::$_query_data = array();
  }

  /**
   * Fetches the results from the MySQLi statement that was prepared and
   * executed. Will build an associative array containing all of the relevant
   * data from the query/statement that was executed.
   *
   * @param mysqli_stmt $statement The MySQLi statement to fetch the results
   *  from.
   * @return array Container for all information the statement stored after
   *  executing
   */
  protected static function _fetch_results($statement) {
    $params = array();
    $results = array();
    $metadata = $statement->result_metadata();

    while ($field = $metadata->fetch_field()) {
      $params[] = &$row[$field->name];
    }

    call_user_func_array(array($statement, 'bind_result'), $params);

    while ($statement->fetch()) {
      $tmp = array();
      foreach ($row as $key => $value) {
        if (is_string($value)) {
          $value = stripslashes($value);
        }

        $tmp[$key] = $value;
      }

      $results[] = $tmp;
    }

    return $results;
  }
}
