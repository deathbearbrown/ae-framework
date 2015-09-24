<?php
namespace AEUtils;

Interface DatabaseInterface {

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
  public static function instance();

  /**
   * If the Database timed our for any reason, we protect errors by closing out
   * the socket to the Database through the MySQLi object. Otherwise, this
   * handles connecting to the MySQL database specified in the config file.
   */
  public static function connect();

  /**
   * If we had previously connected to the MySQL database, this function will
   * disconnect from the MySQL server and close the socket.
   */
  public static function disconnect();

  /**
   * Switches to a selected database on the MySQLi socket
   *
   * @param string $database The database we need to switch to.
   */
  public static function switch_database($database = '');

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
  public static function query($query = '', $get_results = true);

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
  public static function get($table = null, $num_rows = false, $fields = array('*'));

  /**
   * Performs a deletion command and takes into account the where() clauses
   * setup prior to calling this function.
   *
   * @param string $table The table to delete information from.
   * @return bool Indicates whether or not our delete request actually affected
   *  rows contained within the specified table.
   */
  public static function delete($table = null);

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
   */
  public static function insert($table = null, $data = array());

  /**
   * Performs an update on the specified table with the specified data. The data
   * must be an array with the keys being the field name and values being the
   * value to be inserted. Takes into account prior where() clauses.
   *
   * @param string $table The table to perform the update to
   * @param array $data The data used to be updated
   * @return bool Indicates whether or not the insertion affected any rows
   *  contained within the table.
   */
  public static function update($table = null, $data = array());

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
  public static function num_rows($table = null, $num_rows = false, $fields = array('*'));

  /**
   * Enqueues a where clause to be used with the current MySQL statement being
   * built.
   *
   * @param string $field The field from the table we are using to filter with
   *  the where
   * @param mixed $value The value for the correlated field specified
   */
  public static function where($field = '', $value = '');

  /**
   * Adds the variable bindings to the internal container that will eventually
   * be written to the current MySQLi statement being prepared.
   *
   * @param mixed $value Data to be bound to the MySQLi statement later on.
   */
  public static function add_variable_binding($value);
}
