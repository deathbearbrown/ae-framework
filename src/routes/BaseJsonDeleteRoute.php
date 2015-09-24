<?php
namespace AEUtils\routes;

use \AEUtils\JsonResHandler;
use Exception;

class BaseJsonDeleteRoute extends BaseJsonRoute {

  public function delete($identifier = null) {
    $identifier = $this->sanitize_model_identifer($identifier);
    $class_name = $this->get_model_class();

    try {
      // retrieve the existing model (if it exists) and then
      // flag it as inactive
      $model = new $class_name($identifier);
      $model->soft_delete();
      http_response_code(204);
    } catch (Exception $err) {
      $this->_handle_deletion_error($err);
    }
  }

  protected function _handle_deletion_error(Exception $err) {
    $message = $err->getMessage();

    if (strpos($message, 'results in no database records')) {
      $error = array(
        'code' => 'NOT_FOUND',
        'detail' => $message
      );
      $res = JsonResHandler::render(array($error), 404);
      return;
    }

    trigger_error($message, E_USER_ERROR);
  }

}
