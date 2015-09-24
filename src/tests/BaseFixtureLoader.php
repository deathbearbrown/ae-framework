<?php
namespace AEUtils\tests;

use Exception;
use string;
use integer;

class BaseFixtureLoader {

  protected $_loaded_files = array();
  protected $_fixture_directory;
  protected $_fixture_file_ending;

  public function __construct($fixture_directory, $file_ending) {
    if (!is_string($fixture_directory) || !is_dir($fixture_directory)) {
      throw new Exception('Fixture directory must be a string and a valid directory');
    } else if (!is_string($file_ending)) {
      throw new Exception('File ending must be a string');
    }

    $this->_fixture_directory = $fixture_directory;
    $this->_fixture_file_ending = $file_ending;
  }

  public function getOne($table, $index) {
    if (!is_string($table)) {
      throw new Exception('Table must be a string');
    } else if (!is_int($index)) {
      throw new Exception('Index must be an integer');
    }

    $fixtures = $this->getAll($table);

    if (!array_key_exists($index, $fixtures)) {
      throw new Exception('Please specify an index that exists');
    }

    return $fixtures[$index];
  }

  public function getRange($table, $offset, $length) {
    if (!is_string($table)) {
      throw new Exception('Table must be a string');
    } else if (!is_int($offset) || $offset < 0) {
      throw new Exception('Offset must be a positive integer');
    } else if (!is_int($length) || $length <= 0) {
      throw new Exception('Length must be a positive integer greater than zero');
    }

    $fixtures = $this->getAll($table);

    if (!(array_key_exists($offset, $fixtures))) {
      throw new Exception('Please specify an offset that exists');
    } else if (($offset + $length) > count($fixtures)) {
      throw new Exception('Please specify a valid range');
    }

    return array_slice($fixtures, $offset, $length);
  }

  public function getAll($table) {
    if (!is_string($table)) {
      throw new Exception('Table must be a string');
    }

    if (!($fixtures = $this->_load_fixture_data($table))) {
      throw new Exception('Failed to load fixture data for "' . $table . '"');
    }

    return $fixtures;
  }

  protected function _load_fixture_data($table) {
    if (!is_string($table)) {
      throw new Exception('Table must be a string');
    }

    $table = strtolower($table);

    // try loading from the cache first
    if ($cache = $this->_retrieve_table_from_cache($table)) {
      return $cache;
    }

    $fixture_filename = ($this->_fixture_directory . '/' . $table . $this->_fixture_file_ending);
    if (!is_file($fixture_filename)) {
      throw new Exception('Fixture data for table "' . $table . '" not found');
    }

    if (!($file_contents = file_get_contents($fixture_filename))) {
      return false;
    }

    if (!($fixtures = $this->_parse_fixture_data($file_contents))) {
      return false;
    }

    $this->_cache_fixture_data($table, $fixtures);
    return $fixtures;
  }

  protected function _cache_fixture_data($table, array $fixtures) {
    if (!is_string($table)) {
      throw new Exception('Table must be a string');
    }

    $this->_loaded_files[$table] = $fixtures;
  }

  protected function _parse_fixture_data($file_contents) {
    throw new Exception('Please specify a parser for the fixture data');
  }

  protected function _retrieve_table_from_cache($table) {
    if (!is_string($table)) {
      throw new Exception('Table must be a string');
    }

    $table = strtolower($table);

    if (!array_key_exists($table, $this->_loaded_files)) {
      return false;
    }

    return $this->_loaded_files[$table];
  }

}
