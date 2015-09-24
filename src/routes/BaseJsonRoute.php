<?php
namespace AEUtils\routes;

use \AEUtils\models\BaseModel;
use \AEUtils\JsonResHandler;

class BaseJsonRoute extends BaseRoute {

  /**
   * These whitelisted fields are found in the notes here:
   * http://jsonapi.org/format/#errors
   *
   * @var array
   */

  protected $_model_object;

  public function __construct() {
    $this->_validate_route();
  }

  protected function _validate_route() {
    // ensure that the model object was set correctly
    if (!isset($this->_model_object)) {
      trigger_error('Please set the `_model_object` on this route before using it', E_USER_ERROR);
    } else if (!($this->_model_object instanceof BaseModel)) {
      trigger_error('Invalid `_model_object`; Must be an instance of `BaseModel`', E_USER_ERROR);
    }
  }

  public function get_model_object() {
    return $this->_model_object;
  }

  public function get_model_class() {
    return get_class($this->_model_object);
  }

  public function sanitize_model_identifer($identifier = null) {
    $model = $this->get_model_object();

    // if this was not an array of search params, assume it was the primary
    // identifier for this table and convert it to an array
    if (!is_array($identifier)) {
      $primary_key = $model->get_db_primary_key();
      $identifier = array($primary_key => $identifier);
    }

    // now sanitize each value in the array
    foreach ($identifier as $field => $value) {
      // ensure this is a valid db field
      if (!$model->is_valid_db_field($field)) {
        $error = array(
          'code' => 'INVALID_MODEL_IDENTIFIER_FIELD',
          'detail' => 'An invalid model identifier was specified; "' .
            $field . '" does not exist within the specified database schema'
        );
        JsonResHandler::render(array($error), 400);
      }

      $identifier[$field] = $model->sanitize_db_field_value($field, $value);
    }

    return $identifier;
  }

  /**
   * Verify that the route's model supports the provided data.
   *
   * @return mixed `true` if the data is valid; an array with string attributes
   *               `code` and `detail` properties otherwise
   */
  protected function validate_data($data) {
    // ensure we're dealing with an array
    if (!is_array($data)) {
      return array(
        'code' => 'NO_POSTED_DATA',
        'detail' => 'There was a problem receiving the posted data'
      );
    }

    // now, iterate through each field - validating that it is a valid
    // attribute
    $model = $this->get_model_object();
    $primary_key = $model->get_db_primary_key();
    foreach ($data as $field => $value) {
      // make sure the field exists
      if ($field !== $primary_key && !$model->is_valid_attribute($field)) {
        return array(
          'code' => 'INVALID_FIELD',
          'detail' => 'This endpoint does not support the "' .
            $field . '" field'
        );
      }
    }

    return true;
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

  public function underscoreKeys($array, $arrayHolder = array()) {
    $underscoreArray = !empty($arrayHolder) ? $arrayHolder : array();
    foreach ($array as $key => $val) {
      $newKey = str_replace('-', '_', $key);
      if (!is_array($val)) {
        $underscoreArray[$newKey] = $val;
      } else {
        $underscoreArray[$newKey] = $this->underscoreKeys($val);
      }
    }

    return $underscoreArray;
  }
}
