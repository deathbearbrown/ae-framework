<?php
namespace AEUtils\routes;

use \AEUtils\JsonResHandler;

class BaseJsonPostRoute extends BaseJsonRoute {

  protected function _sanitize_field(&$value, $name = '') {
    $value = $this->get_model_object()->sanitize_db_field_value($name, $value);
  }

  protected function _sanitize_data($data = array()) {
    array_walk($data, array($this, '_sanitize_field'));
    return $data;
  }

  public function create_new_model($data = array()) {
    $data = $this->underscoreKeys($data);

    // ensure that all of the fields actually exist
    $result = $this->validate_data($data);

    if ($result !== true) {
      JsonResHandler::render(array($result), 400);
      return;
    }

    // next, since we know all of the fields actually exist, sanitize the
    // data
    $data = $this->_sanitize_data($data);

    // finally, create this new model
    $class_name = $this->get_model_class();
    $model = new $class_name();

    // iterate through all of the posted data, setting it on the model object
    foreach ($data as $property => $value) {
      $model->$property = $value;
    }

    // save this new object and render the result
    $model->save();
    JsonResHandler::render($this->dashKeys($model->get_user_friendly_data()), 200);
  }

}
