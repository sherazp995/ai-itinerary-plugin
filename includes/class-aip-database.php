<?php
if (!defined('ABSPATH')) exit;

class AIP_Database {
    private static $instance = null;
    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }
    private function __construct() {}
    public static function create_tables() {}
}
