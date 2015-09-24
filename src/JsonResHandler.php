<?php
namespace AEUtils;

Class JsonResHandler {

  protected static $_whitelisted_json_error_codes = array(
    'id',
    'href',
    'status',
    'code',
    'title',
    'detail',
    'links',
    'paths'
  );

  public static function render(Array $data, $responseCode = 200){
    if ($responseCode === 200) {
      self::write_json_response($data, $responseCode);
    } else {
      self::write_json_errors($data, $responseCode);
    }
  }

  /**
   * Sets the required/expected JSON API headers
   *
   * See: http://jsonapi.org/format/ for more information.
   */
  protected static function _set_required_json_headers() {
    if (!headers_sent()) {
      header('Content-Type: application/vnd.api+json');
    }
  }


  /**
   * Handles writing the success JSON response to the page buffer.
   *
   * Note: This also takes care of setting the necessary HTTP headers
   * for the request
   *
   * @param array $data The JSON data that will be converted from array to
   *   JSON string.
   */
  public static function write_json_response($data, $responseCode) {
    // set the necessary headers
    self::_set_required_json_headers();

    // simply echo the JSON out
    http_response_code($responseCode);
    echo json_encode($data);
  }

  /**
   * Handles writing the error JSON response to the page buffer.
   *
   * Note: This also takes care of setting the necessary HTTP headers for the
   * request.
   *
   * @param array $errors The errors that occurred and need to be rendered to
   *   the requesting resource.
   */
  public static function write_json_errors($data, $responseCode) {
    // ensure that the specified errors are the correct type
    if (!is_array($data)) {
      trigger_error('Please make sure the errors specified are contained within an array', E_USER_ERROR);
    }

    // make sure all of the JSON errors meet our expectations
    $errors = self::parse_json_errors($data);

    self::write_json_response($errors, $responseCode);
  }



  /**
   * Iterates through each supplied JSON error and parses the values out of it;
   * thus, validating it. If the supplied error was invalid, it will be
   * overwritten by a default error with a code of 'INVALID_ERROR_KEY'.
   *
   * @param array $errors The user-supplied errors that will be parsed.
   * @return array The parsed array of errors (with invalid errors replaced).
   */
  public static function parse_json_errors(Array $errors)  {
    // check each of the errors, ensuring they are correctly formatted
    $parsed_errors = array();

    array_map(function($error) use (&$parsed_errors) {
      // pull the array keys out and scan each one
      $keys = array_keys($error);
      $found_error = false;

      // begin checking each key in the specified error
      array_map(function($key) use (&$parsed_errors, &$found_error) {
        // if the key is not whitelisted, log it so we know for later
        if (!in_array($key, self::$_whitelisted_json_error_codes)) {
          $found_error = true;
        }

      }, $keys);

      // finally, if there was an error, replace it
      $error_to_add = $error;
      if ($found_error) {
        $error_to_add = array(
          'code' => 'INVALID_ERROR_KEY',
          'detail' => 'An invalid error key was specified.',
          'href' => 'http://jsonapi.org/format/#errors'
        );
      }

      $parsed_errors[] = $error_to_add;

    }, $errors);

    return $parsed_errors;
  }

}
