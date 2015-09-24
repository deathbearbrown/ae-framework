<?php
namespace AEUtils\routes;

use \AEUtils\JsonResHandler;
use \AEUtils\collections\BaseCollection;
use Exception;

class BaseJsonGetAllRoute extends BaseJsonRoute {

  protected $_collection_object;

  public function __construct($route = array()) {
    Parent::__construct($route);
    $this->_validate_route();
  }

  public function get_collection_object() {
    return $this->_collection_object;
  }

  protected function _validate_route() {
    // ensure our collection object is valid
    if (!isset($this->_collection_object)) {
      trigger_error('Please set `_collection_object` before using this route', E_USER_ERROR);
    } else if (!($this->_collection_object instanceof BaseCollection)) {
      trigger_error('Invalid `_collection_object`; Make sure it\'s an instance of `BaseCollection`', E_USER_ERROR);
    }
  }

  protected function _validate_filters($filters = array()) {
    // ensure we're dealing with an array
    if (!is_array($filters)) {
      return array(
        'code' => 'INVALID_FILTER_FORMAT',
        'detail' => 'The "filter" GET parameter could not be parsed as an array'
      );
    }

    // next, check that all of the filters are legitimate entries in the
    // database schema for this route's model
    $fields = array_keys($filters);
    $model = $this->get_model_object();

    foreach ($fields as $field) {
      if (!$model->is_valid_db_field($field)) {
        $error = array(
          'code' => 'INVALID_FILTER',
          'detail' => 'The "' . $field . '" filter is not supported by this endpoint'
        );

        $res = JsonResHandler::render(array($error), 400);
      }
    }
  }

  protected function _sanitize_filter(&$value, $name = '') {
    $value = $this->get_model_object()->sanitize_db_field_value($name, $value);
  }

  public function get_filters($params = array()) {
    $filters = array();

    // check to see if the request even contains filters
    if (!array_key_exists('filter', $params)) {
      return $filters;
    }

    // validate that all of the filters exist
    $filters = $params['filter'];
    $this->_validate_filters($filters);

    // since the validation of these filters was successful, sanitize them
    array_walk($filters, array($this, '_sanitize_filter'));

    // lastly, return our sanitized filters to the user so some action can be
    // taken
    return $filters;
  }

  public function render($search_params = array()) {
    $collection_class = $this->get_collection_class();
    $collection = new $collection_class();
    // fetch collection
    $collection->fetch($search_params);

    $include = $this->get_include();

    $data = array();

    // add all of the data from each model in the collection
    $collection->each(function($model) use (&$data, $include) {
      $data[] = $this->dashKeys($model->get_linked_data($include));
    });

    $res = JsonResHandler::render($data, 200);
  }

  public function get_collection_class() {
    return get_class($this->get_collection_object());
  }

}
