<?php
namespace AEUtils\routes;

use \AEUtils\JsonResHandler;
use Exception;

class BaseJsonPutRoute extends BaseJsonRoute {

  protected function _sanitize_field(&$value, $name = '') {
    $value = $this->get_model_object()->sanitize_db_field_value($name, $value);
  }

  protected function _sanitize_data($data = array()) {
    array_walk($data, array($this, '_sanitize_field'));
    return $data;
  }

  public function update_model($criteria) {
    $criteria = $this->underscoreKeys($criteria);

    // ensure that all of the fields actually exist
    $result = $this->validate_data($criteria);

    if ($result !== true) {
      JsonResHandler::render(array($result), 400);
      return;
    }

    // next, since we know all of the fields actually exist, sanitize the
    // data
    $criteria = $this->_sanitize_data($criteria);

    // finally, create this new model
    $class_name = $this->get_model_class();
    $primary_key = $this->get_model_object()->get_db_primary_key();

    try {
      // retrieve the existing model
      $model = new $class_name(array($primary_key => $criteria[$primary_key]));
    } catch(Exception $e) {
      $message = $e->getMessage();
      if (strpos($message, 'results in no database records')) {
        $error = array(
          'code' => 'NOT_FOUND',
          'detail' => $message
        );
        JsonResHandler::render(array($error), 404);
        return;
      }

      trigger_error($message, E_USER_ERROR);
      return;
    }

    // iterate through all of the posted data, setting it on the model object
    foreach ($criteria as $property => $value) {
      if ($property === $primary_key) {
        continue;
      }

      $model->$property = $value;
    }

    // save this new object and render the result
    $model->save();
    JsonResHandler::render($this->dashKeys($model->get_user_friendly_data()), 200);
  }
}
