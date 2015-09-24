<?php
namespace AEUtils\collections;

use \AEUtils\Database;
use \AEUtils\models\BaseModel;
use \AEUtils\Helpers;

class BaseCollection {

  /**
   * Internal reference to the exact model object (which is used to perform most
   * of the heavy-lifting in this class)
   *
   * @var BaseModel
   */
  protected $_model_object;

  /**
   * Internal container for storing all of the models that are relative to this
   * collection
   *
   * @var array
   */
  protected $_data = array();

  /**
   * Internal container for storing the default field to be used when no
   * callable is specified during `sort`
   *
   * @var null|string
   */
  protected $_default_sort_field = null;

  /**
   * Ensures that the collection is correctly setup and the specified filters
   * are all valid before doing anything. After, it will fetch all of the
   * corresponding models using the specified filters. If you want to limit the
   * collection to a max, specify that value with `$limit`.
   *
   * @param array $filters The filters to be used; note, these must be valid
   *   fields in the database schema of the model (or an error is thrown). The
   *   values of this filters must also match the expected type (indicated in
   *   the model database schema)
   * @param int|null $limit Used to specify a limit for the number of models to
   *   fetch.
   */
  public function __construct() {
    // pre-flight
    $this->_validate_collection_dependencies();
  }

  /**
   * Ensures that the collection is properly setup before use. If anything
   * doesn't match the predefined expectations, a corresponding error is thrown.
   */
  protected function _validate_collection_dependencies() {
    // ensure that the internal `_model_object` was set
    if (!isset($this->_model_object)) {
      trigger_error('Please set the `_model_object` on this collection', E_USER_ERROR);
    } else if (!($this->_model_object instanceof BaseModel)) {
      trigger_error('The `_model_object` must be a child of `BaseModel`', E_USER_ERROR);
    }
  }

  /**
  *  Public function to fetch and cash db records
  **/
  public function fetch($filters = array(), $limit = null) {
    // if the limit is specified, make sure it is an integer
    if (!is_null($limit) && !is_integer($limit)) {
      trigger_error('Please ensure that the specified limit is an integer', E_USER_ERROR);
    }

    // default to filtering out inactive
    if (!array_key_exists('inactive', $filters)){
      $filters['inactive'] = 0;
    }

    $this->_fetch_and_cache_db_records($filters, $limit);
  }

  /**
   * Attempts to fetch and cache the database records to the internal storage
   * container as models. Please note, this function handles the validation of
   * any/all filters before it does anything.
   *
   * @param array $filters The filters to be used when building the SQL query;
   *   note, these must be valid fields in the database schema of the model
   *   (or an error is thrown). The values of this filters must also match the
   *   expected type (indicated in the model database schema)
   * @param int|mixed $limit Used to specify a limit for the number of models to
   *   fetch.
   */
  protected function _fetch_and_cache_db_records($filters = array(), $limit = null) {
    // validate the filters before we begin
    $this->_validate_filters($filters);

    // now, begin the search
    Database::connect();

    // add all of the filters to the query
    foreach ($filters as $name => $value) {
      Database::where($name, $value);
    }

    // finally, fetch the results
    $primary_key = $this->_model_object->get_db_primary_key();
    $fields = array($primary_key);
    if (is_integer($limit)) {
      $results = Database::get($this->_model_object->get_db_table(), $limit, $fields);
    } else {
      $results = Database::get($this->_model_object->get_db_table(), false, $fields);
    }

    // make sure we don't leave any hanging connections
    Database::disconnect();

    // now, cache the results
    if (empty($results)) {
      return;
    }

    // first, pluck the id's from results
    $ids = Helpers::array_pluck($primary_key, $results);

    // finally, instantiate new models for each result that was fetched
    $class_name = $this->get_model_class();
    $this->_data = array();
    foreach ($ids as $id) {
      $this->_data[] = new $class_name($id);
    }
  }

  /**
   * Ensures all of the specified filters are correct and can be used in
   * conjuction with the associated `BaseModel`. If a filter field does not
   * exist (or has an incorrect value type), an error is thrown.
   *
   * @param array $filters Contains all of the filter fields and corresponding
   *   values that will be checked against the model's database schema.
   */
  protected function _validate_filters($filters = array()) {
    // ensure that filters is a valid array
    if (!is_array($filters)) {
      trigger_error('Please make sure that the specified filters are a valid array', E_USER_ERROR);
    }

    // if the user didn't specify any filters, simply return (no validation must occur)
    if (empty($filters)) {
      return;
    }

    // ensure that each filter is a valid database field AND that the specified
    // value (for that field) is of the expected type
    foreach ($filters as $field => $value) {
      if (!$this->_model_object->is_valid_db_field($field)) {
        trigger_error('Unsupported filter field; "' . $field . '" is not declared in the schema', E_USER_ERROR);
      } else if (!$this->_model_object->is_valid_db_field_value($field, $value)) {
        trigger_error('Unexpected filter value type; "' . $field . '" does not match the schema type', E_USER_ERROR);
      }
    }
  }

  /**
   * Retrieves the name of the model object's class.
   *
   * @return string
   */
  public function get_model_class() {
    return get_class($this->_model_object);
  }

  /**
   * Retrieves the class name of this collection object.
   *
   * @return string
   */
  public function get_class_name() {
    return get_class($this);
  }

  /**
   * Calls `usort` on the internal data; thus, allowing the user to sort the
   * internal data based on their own expectations. Note: this can be overloaded
   * in each child class to provide a default value or even do something else.
   *
   * Note: This will modify the internal container based on the result of the
   * specified callable.
   *
   * @param array $callable The callback to be used within our `usort` function
   */
  public function sort($callable = array()) {
    // if no callable was specified and the default sorting field was set,
    // create a default sorting function
    if (empty($callable) && is_string($this->_default_sort_field)) {
      $default_field = $this->_default_sort_field;
      $callable = function($a, $b) use ($default_field) {
        return strcmp($a->$default_field, $b->$default_field);
      };
    } else if (!is_callable($callable)) {
      trigger_error('Please specify a valid callable', E_USER_ERROR);
    }

    usort($this->_data, $callable);
  }

  /**
   * Sets the internal default field to be used when no callable is specified
   * for our `sort` function.
   *
   * @param string $field The database field that will be used to sort
   */
  public function set_default_sort_field($field = '') {
    if (!$this->_model_object->is_valid_db_field($field)) {
      trigger_error('Cannot set default sort field; unknown schema field');
    }

    $this->_default_sort_field = $field;
  }

  /**
   * Filters the internal data to return a subset of that collection which
   * matches (with strict equality) whatever the specified filters are.
   *
   * @param array $filters An array containing the fields and values to be
   *   used when filtering the internal collection
   * @return array The newly filtered array
   */
  public function filter($filters = array()) {
    // first, ensure we're dealing with valid filters
    $this->_validate_filters($filters);

    // filter the internal data (ensuring we only fetch models that match ALL
    // of the specified filters)
    $filtered = array_filter($this->_data, function($model) use ($filters) {
      $all_match = true;

      foreach ($filters as $field => $value) {
        if ($model->$field !== $value) {
          $all_match = false;
          break;
        }
      }

      return $all_match;
    });

    return array_values($filtered);
  }

  /**
   * Retrieves the total count of the internally stored models contained within
   * this collection
   *
   * @return int
   */
  public function length() {
    return count($this->_data);
  }

  /**
   * Retrieves all of the internally stored models.
   *
   * @return array
   */
  public function get() {
    return $this->_data;
  }

  /**
   * Retrieves a model by it's database primary key identifier.
   *
   * Note: an error is thrown if more than 1 record exists with the specified
   * primary key identifier.
   *
   * @param mixed $identifier The value of the database primary key we're trying
   *   to retrieve.
   * @return null|BaseModel If successful, a child instance of `BaseModel` is
   *   returned. Otherwise, null is returned (or an error is thrown).
   */
  public function get_by_identifier($identifier) {
    $primary_key = $this->_model_object->get_db_primary_key();
    $results = $this->filter(array($primary_key => $identifier));
    $count = count($results);

    if ($count > 1) {
      trigger_error('Retrieved more than 1 record by its identifier', E_USER_ERROR);
    } else if ($count === 0) {
      return null;
    }

    return $results[0];
  }

  /**
   * Retrieves a model from the internal model storage container based on it's
   * position (or index) within the `_data` array.
   *
   * Note: an error is thrown if the value of index is out of bounds (or does
   * not exist with the internal storage container)
   *
   * @param int $index The position (or index) of the model the user is trying
   *   to retrieve from the internal collection of models.
   * @return null|BaseModel If successful, a child instance of `BaseModel` is
   *   returned. Otherwise, null is returned (or an error is thrown).
   */
  public function get_by_index($index) {
    if (!is_int($index)) {
      trigger_error('Please make sure the specified index is an integer', E_USER_ERROR);
    }

    if (!array_key_exists($index, $this->_data)) {
      return null;
    }

    return $this->_data[$index];
  }

  /**
   * Resets this collection so that it starts "fresh" (with no models)
   *
   * @return [type] [description]
   */
  public function reset() {
    $this->_data = array();
  }

  /**
   * Iterates through each model in the collection, calling delete on all them.
   *
   * Note: Please use this at your own risk; it could potentially delete every
   * model record (in a table) in the database.
   */
  public function delete() {
    $this->each(function($model) {
      $model->delete();
    });
    $this->reset();
  }

  /**
   * Adds the specified model to our internal storage collection (given it's the
   * same exact type of model [just with different data])
   *
   * Note: if the model does NOT meet our expectations, it will not be added and
   * an error is thrown; don't mess with us!
   *
   * @param BaseModel $model The model we're trying to add to the internal
   *   storage collection
   */
  public function add($model = null) {
    $model_class = $this->get_model_class();

    // ensure this model is one of ours (same base extension and exact same
    // class as the _model_object)
    if (!($model instanceof BaseModel)) {
      trigger_error('Please make sure the model you\'re adding is a child of the `BaseModel` class', E_USER_ERROR);
    } else if ($model_class !== get_class($model)) {
      trigger_error('Please make sure the model you\'re adding is of the "' . $model_class . '" class', E_USER_ERROR);
    }

    // finally, add this model to the internal storage container (since we know
    // it's one of ours)
    $this->_data[] = $model;
  }

  /**
   * Allows the user to provide a callback that will be called while iterating
   * through each of the models stored within the internal storage container.
   *
   * @param mixed $callable The callback to be used when iterating.
   */
  public function each($callable = array()) {
    if (!is_callable($callable)) {
      trigger_error('Please specify a valid callable', E_USER_ERROR);
    }

    array_map($callable, $this->_data);
  }

}
