<?php
namespace AEUtils\models;

use Exception;
use \AEUtils\Database;

class BaseModel {

  /**
   * Internal container for the database record
   * @var array
   */
  protected $_data = array();

  /**
   * The JSON type this model represents
   * @var string
   */
  protected $_json_type;

  /**
   * The table that this database will use to store data
   * @var string
   */
  protected $_db_table;

  /**
   * The primary identifier key of the table
   *
   * Please note: this key must exist within the schema.
   *
   * @var string
   */
  protected $_db_primary_key;

  /**
   * An array representation of what the database schema looks like
   * @var array
   */
  protected $_db_schema = array();

  /**
   * Attributes of the model that can be read or written
   * @var array
   */
  protected $_attributes = array();

  /**
   * Links to other tables
   * @var array
   */
  protected $_linked_tables = array();

  /**
   * OPTIONAL: Tables that need to be cleaned up if
   * the model is marked inactive
   * @var array
   */
  protected $_inactive_cleanup = array();

  /**
   * Ensures that anything that used this `BaseModel` is setup properly. Also,
   * handles fetching an existing database record (by its identifier) or
   * creating a new set of data (to be modified at a later time).
   *
   * Note: there is a lot of strict error checking that needs to occur to ensure
   * that developers are using this object correctly.
   *
   * @param mixed $identifier The primary key value that will be used when
   *   looking up the database record
   */
  public function __construct($identifier = null) {
    // pre-flight
    $this->_validate_model_dependencies();

    if (is_null($identifier)) {
      // if no constraints were specified, assume this model is being created
      $this->_hydrate_data_with_defaults();
    } else {
      // otherwise, lookup the item from the database and cache it for use later
      $this->_fetch_and_cache_db_record($identifier);
    }
  }

  /**
   * Fetches the record (by its identifier) and caches the result internally.
   *
   * @param mixed $identifier The primary key value that will be used when
   *   looking up the database record
   * @throws Exception
   */
  protected function _fetch_and_cache_db_record($identifier) {
    // convert the identifier to an array to make it easier on us going forward
    if (!is_array($identifier)) {
      $identifier = array($this->get_db_primary_key() => $identifier);
    }

    // validate the identifier(s)
    foreach ($identifier as $field => $value) {
      if (!$this->is_valid_db_field_value($field, $value)) {
        trigger_error('Cannot fetch and cache with incorrect identifier type', E_USER_ERROR);
      }
    }

    // fetch the results
    Database::connect();
    foreach ($identifier as $field => $value) {
      Database::where($field, $value);
    }

    $records = Database::get($this->get_db_table());
    Database::disconnect();

    $num_records = count($records);
    $tableQuery = 'Querying table: ' . $this->_db_table;
    if ($num_records > 1) {
      throw new Exception('The specified identifier resulted in more than 1 database record. ' . $tableQuery);
    } else if ($num_records < 1) {
      throw new Exception('The specified identifier results in no database records. ' . $tableQuery);
    }

    $this->_data = $this->_scrub_for_booleans($records[0]);
  }

  /**
   * Takes all the data from the database and turns tinyINT (0, 1) into True False booleans
   * removes the inactive key from the DB results
   */
  protected function _scrub_for_booleans($data) {
    foreach ($data as $field => $value) {
      $schema = $this->get_db_schema();
      $field_info = $schema[$field];
      if ($field_info['type'] == 'bool') {
        $data[$field] = ($value == 1) ? true : false;
      }
    }

    if (array_key_exists('inactive', $data)) {
      unset($data['inactive']);
    }

    return $data;
  }

  /**
   * Populates the internal data so that all of the defined attributes have
   * default data
   */
  protected function _hydrate_data_with_defaults() {
    $this->_data = array();

    // fill all of the defaults for the internal data
    $schema = $this->get_db_schema();
    foreach ($this->get_attributes() as $attr) {
      $info = $schema[$attr];
      $default = array_key_exists('default', $info) ? $info['default'] : null;
      if (array_key_exists('defaultFunction', $info)) {
        if (is_callable($info['defaultFunction'])) {
          $this->_data[$attr] = call_user_func($info['defaultFunction']);
        } else {
          throw new Exception('defaultFunction was provided but is not callable');
        }
      } else {
        $this->_data[$attr] = $default;
      }
    }
  }

  /**
   * Validates all of the dependencies for the model (in order for it to work
   * as intended).
   *
   * If something doesn't match what's expected, an error will be thrown.
   * @throws Exception
   */
  protected function _validate_model_dependencies() {
    // make sure we have values for the things that require setup
    if (!is_string($this->get_json_type())) {
      trigger_error('Please set the `_json_type` on this model', E_USER_ERROR);
    }

    $this->_validate_model_db_schema();
    $this->_validate_model_attributes();
  }

  /**
   * Validates the database schema for this model. It ensures that all of the
   * required corresponding properties were set by the developer when it was
   * implemented and then validates that those properties align with each other.
   *
   * If something doesn't match what's expected, an error will be thrown.
   * @throws Exception
   */
  protected function _validate_model_db_schema() {
    // cache a few variables
    $db_table = $this->get_db_table();
    $db_schema = $this->get_db_schema();
    $db_primary_key = $this->get_db_primary_key();

    // ensure all of the database values were setup for use
    if (!is_string($db_table) || empty($db_table)) {
      trigger_error('Please set the `_db_table` on this model', E_USER_ERROR);
    } else if (!is_array($db_schema) || empty($db_schema)) {
      trigger_error('Please set the `_db_schema` on this model', E_USER_ERROR);
    } else if (!is_string($db_primary_key) || empty($db_primary_key)) {
      trigger_error('Please set the `_db_primary_key` on this model', E_USER_ERROR);
    }

    // now, make sure that the primary key is specified within the schema
    if (!array_key_exists($db_primary_key, $db_schema)) {
      trigger_error('The `_db_primary_key` must be set in the `_db_schema`', E_USER_ERROR);
    }

    // next, make sure all of the schema types are valid
    foreach ($db_schema as $name => $info) {
      if (!array_key_exists('type', $info)) {
        trigger_error('Please set a "type" for the "' . $name . '" column (in the `_db_schema`)', E_USER_ERROR);
      } else if (!$this->is_valid_schema_type($info['type'])) {
        trigger_error('The "type" for the "' . $name . '" column is invalid (in the `_db_schema`)', E_USER_ERROR);
      }
    }
  }

  /**
   * Validates the database schema for this model. It ensures that all of the
   * required corresponding properties were set by the developer when it was
   * implemented and then validates that those properties align with each other.
   *
   * If something doesn't match what's expected, an error will be thrown.
   * @throws Exception
   */
  protected function _validate_model_attributes() {
    $attributes = $this->get_attributes();
    $db_schema = $this->get_db_schema();

    // ensure the attributes were actually set
    if (empty($attributes)) {
      trigger_error('Please set the `_attributes` on this model', E_USER_ERROR);
    }

    // finally, ensure that all of the attributes exist within the `_db_schema`
    foreach ($attributes as $attr) {
      if (!is_string($attr)) {
        trigger_error('Please make sure all values within `_attributes` are strings', E_USER_ERROR);
      } else if (!array_key_exists($attr, $db_schema)) {
        trigger_error('The attribute "' . $attr . '" must be specified within the `_db_schema`', E_USER_ERROR);
      }
    }
  }

  /**
   * Magic getter that allows us to access attributes of this model safely.
   *
   * Please note: the attribute name itself must be supported by the model or
   * an error is thrown.
   *
   * @param string $name The attribute that is being accessed.
   * @return mixed|null If the attribute is valid and exists, a mixed value will
   *   be returned. Otherwise, null is returned.
   * @throws Exception
   */
  public function __get($name) {
    $data = $this->get_data();

    if (!is_array($data)) {
      return null;
    } else if (!$this->is_valid_db_field($name)) {
      trigger_error('Can not retrieve unknown model property', E_USER_ERROR);
    }

    return $data[$name];
  }

  /**
   * Magic setter that allows us to set attributes of this model safely.
   *
   * Please note: the attribute name itself must be supported by the model or
   * an error is thrown. The attribute value type must match the expected type
   * or an error is thrown.
   *
   * @param string $name The attribute that is being written to.
   * @param mixed $value The value of the attribute that needs to be set.
   * @throws Exception
   */
  public function __set($name, $value) {
    if (!$this->is_valid_attribute($name)) {
      trigger_error('Cannot set "' . $name . '"; attribute not supported', E_USER_ERROR);
    } else if (!$this->is_valid_db_field_value($name, $value)) {
      trigger_error('Cannot set model attribute to specified value; incorrect type', E_USER_ERROR);
    }

    $this->_data[$name] = $value;
  }

  /**
   * Saves the object.
   *
   * Please note: if the object was never originally fetched from the database,
   * it will be created. Otherwise, the existing database record will be updated
   * with whatever changes have been made to our internal `_data`.
   */
  public function save() {
    // check to see if we're creating or updating
    if (array_key_exists($this->get_db_primary_key(), $this->get_data())) {
      $this->_update();
    } else {
      $this->_create();
    }
  }


  /**
   * Soft Deletes the object
   *
   * This sets the 'inactive' column to true and will clean up any dependents by setting them to inactive true
   */
  public function soft_delete() {
    $this->__set('inactive',1);
    $this->_update();
    if (count($this->_inactive_cleanup)) {
      foreach ($this->_inactive_cleanup as $key => $value) {
        $primaryKey = $this->get_db_primary_key();
        $search = array(
                  $value['identifier'] => $this->__get($primaryKey)
                );
        $cleanCollection = new $key();
        $cleanCollection->fetch($search);

        $cleanCollection->each(function($model){
          $model->soft_delete();
        });
      }
    }
  }

  /**
   * Updates the existing database record with our internal `_data`.
   *
   * Please note: this assumes that the existing `_db_primary_key` exists within
   * our internal `_data` container; thus, it assumes the data was fetched when
   * this model was first instantiated.
   */
  protected function _update() {
    $primary_key = $this->get_db_primary_key();
    $data = $this->get_data();

    Database::connect();
    Database::where($primary_key, $data[$primary_key]);
    Database::update($this->get_db_table(), $data);
    Database::disconnect();
  }

  /**
   * Creates a new database record with our internal `_data`.
   */
  protected function _create() {
    Database::connect();
    $identifier = Database::insert($this->get_db_table(), $this->get_data());
    Database::disconnect();

    $this->_data[$this->get_db_primary_key()] = $identifier;
  }

  /**
   * Deletes the database record from memory.
   *
   * Please note: this assumes that the model was either fetched or saved before
   * being called. Otherwise, an error is thrown.
   * @throws Exception
   */
  public function delete() {
    $primary_key = $this->get_db_primary_key();
    $data = $this->get_data();

    if (!array_key_exists($primary_key, $data)) {
      trigger_error('Cannot delete this model; it was not saved', E_USER_ERROR);
      return;
    }

    Database::connect();
    Database::where($primary_key, $data[$primary_key]);
    Database::delete($this->get_db_table());
    Database::disconnect();
  }

  /**
   * Converts this object to a JSON string.
   *
   * @return string A JSON representation of this object.
   */
  public function to_json() {
    return json_encode($this->_data);
  }

  /**
   * Retrieves the name of the internal database table we're using to store data
   *
   * @return string
   */
  public function get_db_table() {
    return $this->_db_table;
  }

  /**
   * Retrieves the internal JSON type property
   *
   * @return string
   */
  public function get_json_type() {
    return $this->_json_type;
  }

  /**
   * Retrieves the internal data for this model & model data from linked tables
   *
   * @return array|mixed
   */

  public function get_linked_data(Array $includes) {
    if (is_array($includes) && count($includes) && count($this->_linked_tables)) {
      return $this->fetch_link_data($includes);
    } else if (count($includes) && !count($this->_linked_tables)) {
      // if includes are passed and there isn no link table data, then throw an error
      // - jsonapi
      $json_error = array(
        'code' => '400 Bad Request',
        'detail' => 'This endpoint does not support ?include.'
      );
      JsonResHandler::render(array($json_error), 400);
    } else {
      return $this->get_data();
    }
  }

  /**
   * Retrieves the internal data for this model
   *
   * @return array|mixed
   */
  public function get_data() {
    return $this->_data;
  }

  /**
   * Retrieves the internal data for scrubbed for booleans
   * (note: Booleans needs to be converted to ints in order for mysqli bind_param to work)
   *
   * @return array|mixed
   */
  public function get_user_friendly_data() {
    return $this->_scrub_for_booleans($this->get_data());
  }

  /**
   * Breaks down includes to retrieve nested data.
   * Takes an array of includes
   *
   * @return array|mixed
   */

  public function get_sub_includes($includes) {
    $subincludes = array();
    foreach ($includes as $include) {
      $sub = explode('.', $include, 2);

      if (array_key_exists($sub[0], $subincludes)) {
        if (count($sub)) {
          array_push($subincludes[$sub[0]], $sub[1]);
        }
      } else {
        if (count($sub) > 1) {
          $subincludes[$sub[0]] = array($sub[1]);
        } else {
          $subincludes[$sub[0]] = array();
        }
      }
    }

    return $subincludes;
  }

  /**
   * Fetches the data for this model.
   * Takes a boolean to determine if linked tables should be included
   *
   * @return array|mixed
   */
  public function fetch_link_data(Array $includes) {
    $allData = $this->get_data();
    $parentIncludes = $this->get_sub_includes($includes);

    foreach ($this->get_linked_tables() as $key => $value) {
      $tableName = str_replace('_id', '', $key);
      if (array_key_exists($tableName, $parentIncludes)) {
        $linkModelData = new $value[0]($allData[$key]);
        unset($allData[$key]);
        $jsonKey = str_replace('_id', '', $key);
        $allData[$jsonKey] = $linkModelData->get_linked_data($parentIncludes[$tableName]);
      }
    }

    return $allData;
  }

  /**
   * Retrieves the internal database schema for this model
   *
   * @return array
   */
  public function get_db_schema() {
    return $this->_db_schema;
  }

  /**
   * Retrieves the database primary key for this model
   *
   * @return string
   */
  public function get_db_primary_key() {
    return $this->_db_primary_key;
  }

  /**
   * Retrieves the internal attributes for this model
   *
   * @return array
   */
  public function get_attributes() {
    return $this->_attributes;
  }

  /**
   * Retrieves the internal linked tables for this model
   *
   * @return array
   */
  public function get_linked_tables() {
    return $this->_linked_tables;
  }

  /**
   * Checks if the specified attribute is supported by this model.
   *
   * @param string $attribute The name of the attribute we're checking
   * @return boolean Indicates whether or not the attribute is supported by this
   *   model.
   */
  public function is_valid_attribute($attribute = '') {
    // make sure the attribute exists with the specified attributes
    return in_array($attribute, $this->_attributes);
  }

  /**
   * Checks if the specified field is defined within this model's schema or not.
   *
   * @param string $field The name of the field we're checking
   * @return boolean Indicates whether or not the field is defined.
   */
  public function is_valid_db_field($field = '') {
    // make sure the field exists within the attributes
    return array_key_exists($field, $this->_db_schema);
  }

  /**
   * Checks if a value matches the expected type of a field defined within our
   * schema.
   *
   * @param string $field The name of the field we're checking expected types
   *   against.
   * @param string $value The value of the field we're checking.
   * @return boolean Indicates whether or not the value type was the same as the
   *   expected value type for the field defined in the database schema.
   * @throws Exception
   */
  public function is_valid_db_field_value($field = '', $value = '') {
    if (!$this->is_valid_db_field($field)) {
      return false;
    }

    $info = $this->_db_schema[$field];

    // check the type of this field
    switch ($info['type']) {
      case 'string':
        return is_string($value);
        break;
      case 'int':
        return is_int($value);
        break;
      case 'bool':
        // bind param in mysqli needs booleans to be integers
        return is_bool($value) || (is_int($value) && $value === 0) || (is_int($value) && $value === 1);
        break;
      case 'float':
        return is_float($value);
        break;
      default:
        trigger_error('Unknown field type', E_USER_ERROR);
        break;
    }
  }

  /**
   * Checks to see if the schema type is supported by our code.
   *
   * @param string $type The type that we're checking.
   * @return boolean Indicates whether or not the specified type is supported.
   */
  public function is_valid_schema_type($type = '') {
    $allowed_types = array('int', 'string', 'bool', 'float');
    return in_array($type, $allowed_types);
  }

  /**
   * Sanitizes a database field value by using it's expected type to parse the
   * value passed in.
   *
   * @param string $field The name of the field we'd like to sanitize
   * @param mixed $value The value that we're trying to sanitize accordingly
   * @return mixed The sanitized value
   */
  public function sanitize_db_field_value($field = '', $value) {
    $schema = $this->get_db_schema();
    $field_info = $schema[$field];

    switch ($field_info['type']) {
      case 'int':
        $value = intval($value);
        break;
      case 'float':
        $value = floatval($value);
        break;
      case 'bool':
        // bind param in mysqli needs booleans to be integers
        $string_version = strtolower($value);
        $value = ($string_version === '1' || $string_version === 'true') ? 1 : 0;
        break;
      case 'string':
        $value = (string) $value;
        break;
      default:
        $value = null;
        break;
    }

    return $value;
  }
}
