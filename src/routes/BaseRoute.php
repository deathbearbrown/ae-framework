<?php
namespace AEUtils\routes;

use Exception;
use UnexpectedValueException;

class BaseRoute {

  /**
   * Used to determine the HTTP method of this route
   *
   * @var string
   */
  protected $_method = 'GET';

  protected $_uri = 'none';

  protected $_request;

  protected $_uri_data;

  /**
   * Returns the method this route was registered as.
   *
   * @return string The HTTP method this route was registered as
   */
  public function get_method() {
    return strtolower($this->_method);
  }

  /**
   * get_uri_regex
   * Helper method that returns a regex string that can be used to
   * determine if a given api request is intended for this route or not.
   * The assumption made is that any piece of the URI prefixed with : is
   * meant for numeric ID data.
   *
   * @return string Regex pattern for the route's URI
   */
  public function get_uri_regex() {
    $pattern = '/:\w+/';
    $replacement = preg_replace($pattern, '\d+', $this->_uri);
    return '/^' . str_replace('/', '\/', $replacement) . '$/';
  }

  public function set_request($request) {
    $this->_request = $request;
  }

  public function get_include() {
    if (isset($this->_request['query_params']) &&
      isset($this->_request['query_params']['include'])) {
      return array_map('trim', explode(',', $this->_request['query_params']['include']));
    }

    return array();
  }

  public function set_uri_data() {
    $request_parts = $this->_request['base_url_parts'];
    $uri_parts = explode('/',$this->_uri);

    for ($i = 0; $i < count($uri_parts); $i++) {
      $uriPart = $uri_parts[$i];
      $requestPart = $request_parts[$i];
      if (strpos($uriPart, ':') === 0 && is_numeric($requestPart)) {
        $this->_uri_data[ltrim($uriPart, ':')] = intval($requestPart);
      }
    }
  }

  /**
   * Retrieve the value of a given header in a request.
   *
   * Header field names are case-insensitive, so dereferencing the `headers`
   * array with a single key may not yield the desired results.
   *
   * Reference:
   * http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
   *
   * @param array $request
   * @param string $name the name of the header to retrieve
   *
   * @return string|null The header value if present, `null` otherwise
   */
  private static function _get_header($request, $name)
  {
    $name = strtolower($name);

    foreach ($request['headers'] as $header_name => $header_value) {
      if (strtolower($header_name) === $name) {
        return $header_value;
      }
    }

    return null;
  }

  /**
   * Create an associative array containing the data described by the current
   * request's body, parsed according to the request's `Content-Type` field.
   *
   * Supported types:
   *
   * - application/x-www-form-urlencoded
   * - application/json
   *
   * See RFC 2046 for details on MIME type formatting:
   * https://www.ietf.org/rfc/rfc2046.txt
   *
   * @return string|array The parsed body in the case of a supported
   *                      `Content-Type`; the "raw" body in string form
   *                      otherwise
   */
  public function parseRequestBody()
  {
    $request = $this->_request;
    $content_type = self::_get_header($request, 'content-type');

    if ($content_type !== null) {
      $type_and_subtype = explode(';', $content_type, 2)[0];

      if ($type_and_subtype === 'application/x-www-form-urlencoded') {
        return $request['post'];
      } else if ($type_and_subtype === 'application/json') {
        $decoded = @json_decode($request['body'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
          throw new UnexpectedValueException(json_last_error_msg());
        }

        return $decoded;
      }
    }

    return $request['body'];
  }

  public function validate_url() {
    $dependencies = array();

    if (!is_null($this->_uri_data)) {
      foreach ($this->_uri_data as $key => $identifier) {
        $modelName = $key . 'Model';

        $this->_try_fetching_model_dependencies($dependencies, $modelName, $identifier);
        $dependencies[] = $key;
      }
    }
  }

  protected function _try_fetching_model_dependencies($dependencies, $modelName, $identifier) {
    try {
      // don't do any searching until we need to
      $dummyModel = new $modelName();
      $primaryKey = $dummyModel->get_db_primary_key();
      $search = array($primaryKey => $identifier);
      $modelAttributes = $dummyModel->get_attributes();

      // iterate through all of the dependencies, adding them to the search
      // requirements
      if (!empty($dependencies)) {
        foreach ($dependencies as $dependency) {
          if (in_array($dependency, $modelAttributes)) {
            $search[$dependency . '-id'] = $this->_uri_data[$dependency];
          }
        }
      }

      // now, perform the search; if this succeeds, we know the model
      // dependencies exist.
      $model = new $modelName($search);
    } catch (Exception $err) {
      $this->_handle_url_validation_error($err, $modelName, $identifier);
    }
  }

  protected function _handle_url_validation_error(Exception $err, $modelName, $identifier) {
    $message = $err->getMessage();

    if (strpos($message, 'results in no database records')) {
      $error = array(
        'code' => 'NOT_FOUND',
        'detail' => $message . ' for ' . $modelName . ' with id ' . $identifier
      );

      $res = JsonResHandler::render(array($error), 404);
    }

    trigger_error($message, E_USER_ERROR);
  }
}
