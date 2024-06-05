<?php
/**
 * Plugin Name: Simple Dequeue
 * Description: A plugin to show and selectively disable enqueued CSS and JS files from other plugins.
 * Version: 1.4.0
 * Author: Lasse Jellum
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SIMPLE_DEQUEUE_PATH', plugin_dir_path(__FILE__));
define('SIMPLE_DEQUEUE_URL', plugin_dir_url(__FILE__));

// Include the main plugin class
require_once SIMPLE_DEQUEUE_PATH . 'includes/class-simple-dequeue.php';

// Initialize the plugin
function simple_dequeue_init() {
    new Simple_Dequeue();
}
add_action('plugins_loaded', 'simple_dequeue_init');