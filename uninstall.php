<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

foreach (array('simple_dequeue_assets', 'simple_dequeue_dequeued_assets', 'simple_dequeue_mode', 'simple_dequeue_version', 'simple_dequeue_direct_file_mode') as $option) {
    delete_option($option);
}
