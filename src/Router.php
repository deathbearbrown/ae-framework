<?php
namespace AEUtils;

use \AEUtils\JsonResHandler;

class Router Implements RouterInterface {

  /**
   * Our list of endpoints to load.
   *
   * Note: these are explicitly declared so we avoid searching the disk and
   * dynamically loading the routes. The order in which they are declared is the
   * order in which they are loaded & registered.
   *
   * @var array
   */
  protected $_route_mappings = array();

  protected $_routes = array();

  protected static $_whitelisted_http_methods = array(
    'get', 'patch', 'put', 'delete', 'post'
  );

  public function __construct() {
    $this->_load_routes();
  }

  public function __destruct() {
    unset($this->_routes);
  }

  /**
   * Converts the file name into a camel cased class name with the first letter
   * of the class being an uppercased one.
   *
   * @param  string $file The file path entry from our internal
   *   `_route_mappings` array.
   * @return string The class name that we expect to exist after the specified
   *   file is loaded
   */
  protected function _get_class_name($file = '') {
    $class = str_replace('/', ' ', $file);
    $class = trim($class);
    $parts = explode(' ', $class);
    $suffix = array_pop($parts);

    // handle dashes in the parts
    if (count($parts)) {
      foreach($parts as $index => $part) {
        $part = str_replace('-', ' ', $part);
        $part = ucwords($part);
        $parts[$index] = str_replace(' ', '', $part);
      }
    }

    // handle dashes in the suffix
    $suffix = str_replace('-', ' ', $suffix);
    $suffix = ucwords($suffix);
    $suffix = str_replace(' ', '', $suffix);

    array_unshift($parts, $suffix);
    $class = implode(' ', $parts);
    $class = ucwords($class);
    $class = str_replace(' ', '', $class);

    return $class;
  }

  protected function _load_routes() {
    foreach ($this->_route_mappings as $file) {
      $path = API_BASE_PATH . '/routes' . $file . '.php';

      // load the endpoint's path so that it can take care of everything
      require_once($path);

      // try instantiating the class so we can register any url pertaining to
      // that endpoint
      $class = $this->_get_class_name($file);
      $this->add_route(new $class());
    }
  }

  public function add_route(routes\BaseRoute $route) {
    // validate the method
    $method = $route->get_method();
    if (!in_array($method, self::$_whitelisted_http_methods)) {
      trigger_error('Unsupported HTTP method; "' . $method . '" is not supported by the router', E_USER_ERROR);
    }

    // finally, push this route onto the stack
    $this->_routes[] = $route;
  }

  public static function get_current_url() {
    return $_SERVER['REQUEST_URI'];
  }

  public static function get_base_url() {
    $url = self::get_current_url();
    $parts = explode('?', $url);
    $url = array_shift($parts);
    return $url;
  }

  public static function get_query_parameters() {
    $query = $_SERVER['QUERY_STRING'];
    $parts = explode('?', $query);
    $query = array_pop($parts);
    parse_str($query, $params);
    return $params;
  }

  // get everything, including the api version.
  public static function get_all_url_parts(){
    $url = self::get_base_url();
    $url = ltrim($url, '/');
    $url = rtrim($url, '/');
    $parts = explode('/', $url);
    return $parts;
  }

  // get just the url parts to run the classes
  public static function get_base_url_parts() {
    $parts = self::get_all_url_parts();
    //remove api
    $api = array_shift($parts);
    return $parts;
  }

  public static function get_api_version(){
    $parts = self::get_all_url_parts();
    return $parts[0];
  }

  public function run() {
    // collect the necessary data about this request
    $request = array(
      'base_url' => self::get_base_url(),
      'base_url_parts' => self::get_base_url_parts(),
      'api_version' => self::get_api_version(),
      'url' => self::get_current_url(),
      'method' => strtolower($_SERVER['REQUEST_METHOD']),
      'headers' => getallheaders(),
      'body' => file_get_contents('php://input'),
      'query_params' => self::get_query_parameters(),
      'cookies' => $_COOKIE,
      'post' => $_POST
    );

    if ($request['api_version'] !== VERSION) {
      self::_send_404();
    }

    // iterate through all of the registered routes looking for ones that
    // match
    $function_params = array($request);
    foreach ($this->_routes as $route) {
      // skip this route if the methods don't match
      if ($request['method'] !== $route->get_method()) {
        continue;
      }

      if(preg_match($route->get_uri_regex(), rtrim(join('/', $request['base_url_parts']), '/'))) {
        $route->set_request($request);
        $route->set_uri_data();
        $route->validate_url();
        $route->on_route();
        return;
      }
    }

    // If we didn't find a match 404
    self::_send_404();
  }

  protected static function _send_404() {
    $error = array(
      'code' => 'NOT_FOUND',
      'detail' => 'The endpoint that you have requested could not be found.'
    );

    $res = JsonResHandler::render(array($error), 404);

    exit();
  }
}


