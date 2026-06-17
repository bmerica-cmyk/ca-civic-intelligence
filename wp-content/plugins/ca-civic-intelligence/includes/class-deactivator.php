<?php
namespace CA_Civic_Intel;
defined( 'ABSPATH' ) || exit;

class Deactivator {
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
