<?php
namespace AEUtils;

Interface RouterInterface {

  public function add_route(routes\BaseRoute $route);

  public static function get_current_url();

  public static function get_base_url();

  public static function get_query_parameters();

  // get everything, including the api version.
  public static function get_all_url_parts();

  // get just the url parts to run the classes
  public static function get_base_url_parts();

  public static function get_api_version();

  public function run();

}
