<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Used to include all inc/inc.*.php files
 * v0.1
 */

 foreach (glob(__DIR__ . '/inc.*.php') as $file) {
     include_once($file);
 }