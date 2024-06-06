<?php
if (!defined('ABSPATH')) {
    exit;
}

class Simple_Dequeue {

    private $contexts;
    private $dequeue_file;
    private $file_error;
    private $dequeue_mode;

    public function __construct() {
        $this->contexts = array(
            'is_front_page' => __('Front Page', 'simple-dequeue'),
            'is_home' => __('Blog Page', 'simple-dequeue'),
            'is_single' => __('Single Post', 'simple-dequeue'),
            'is_page' => __('Single Page', 'simple-dequeue'),
            'is_product' => __('Product Page (WooCommerce)', 'simple-dequeue'),
            // Add more contexts as needed
        );
        $this->dequeue_file = SIMPLE_DEQUEUE_PATH . 'dequeue-code.php';
        $this->file_error = false;
        $this->dequeue_mode = get_option('simple_dequeue_mode', 'settings');

        add_action('wp_enqueue_scripts', array($this, 'capture_enqueued_assets'), 100);
        add_action('admin_post_update_dequeues', array($this, 'update_dequeues'));
        add_action('admin_post_toggle_direct_file_mode', array($this, 'toggle_direct_file_mode'));
        add_action('admin_post_update_dequeue_mode', array($this, 'update_dequeue_mode'));

        if ($this->dequeue_mode === 'direct_file' && get_option('simple_dequeue_direct_file_mode', false)) {
            add_action('wp_enqueue_scripts', array($this, 'run_dequeue_file'), 99);
        }

        // Initialize the admin functionality
        if (is_admin()) {
            require_once SIMPLE_DEQUEUE_PATH . 'includes/class-simple-dequeue-admin.php';
            new Simple_Dequeue_Admin($this);
        }
    }

    public function get_contexts() {
        return $this->contexts;
    }

    public function has_file_error() {
        return $this->file_error;
    }

    public function get_dequeue_file_path() {
        return $this->dequeue_file;
    }

    public function capture_enqueued_assets() {
        if ($this->dequeue_mode === 'functions_file' || $this->dequeue_mode === 'direct_file') {
            return;
        }

        global $wp_scripts, $wp_styles;

        $enqueued_assets = get_option('simple_dequeue_assets', array());

        foreach ($wp_scripts->queue as $handle) {
            if (!isset($wp_scripts->registered[$handle])) continue;

            $src = $wp_scripts->registered[$handle]->src;
            $source = $this->get_asset_source($src);

            if ($source) {
                $enqueued_assets[$handle] = array('type' => 'js', 'source' => $source, 'full_source' => $src);
            }
        }

        foreach ($wp_styles->queue as $handle) {
            if (!isset($wp_styles->registered[$handle])) continue;

            $src = $wp_styles->registered[$handle]->src;
            $source = $this->get_asset_source($src);

            if ($source) {
                $enqueued_assets[$handle] = array('type' => 'css', 'source' => $source, 'full_source' => $src);
            }
        }

        update_option('simple_dequeue_assets', $enqueued_assets);

        $dequeued_assets = get_option('simple_dequeue_dequeued_assets', array());

        if ($this->dequeue_mode === 'settings') {
            foreach ($dequeued_assets as $asset => $contexts) {
                foreach ($contexts as $context => $value) {
                    if ($this->is_context($context)) {
                        wp_dequeue_script($asset);
                        wp_dequeue_style($asset);
                    }
                }
            }
        }

        $this->update_dequeue_file($dequeued_assets);
    }

    public function update_dequeues() {
        if (!current_user_can('manage_options') || !isset($_POST['update_dequeues_nonce']) || !wp_verify_nonce($_POST['update_dequeues_nonce'], 'update_dequeues_nonce')) {
            wp_die(__('Unauthorized request.', 'simple-dequeue'));
        }

        $dequeues = isset($_POST['dequeues']) ? $_POST['dequeues'] : array();
        update_option('simple_dequeue_dequeued_assets', $dequeues);

        $this->update_dequeue_file($dequeues);

        wp_redirect(add_query_arg('tab', 'manage', admin_url('options-general.php?page=simple-dequeue&updated=true')));
        exit;
    }

    public function toggle_direct_file_mode() {
        if (!current_user_can('manage_options') || !isset($_POST['toggle_direct_file_mode_nonce']) || !wp_verify_nonce($_POST['toggle_direct_file_mode_nonce'], 'toggle_direct_file_mode_nonce')) {
            wp_die(__('Unauthorized request.', 'simple-dequeue'));
        }

        $direct_file_mode = isset($_POST['direct_file_mode']) ? 1 : 0;
        update_option('simple_dequeue_direct_file_mode', $direct_file_mode);

        wp_redirect(add_query_arg('tab', 'direct', admin_url('options-general.php?page=simple-dequeue&updated=true')));
        exit;
    }

    public function update_dequeue_mode() {
        if (!current_user_can('manage_options') || !isset($_POST['update_dequeue_mode_nonce']) || !wp_verify_nonce($_POST['update_dequeue_mode_nonce'], 'update_dequeue_mode_nonce')) {
            wp_die(__('Unauthorized request.', 'simple-dequeue'));
        }

        $mode = isset($_POST['dequeue_mode']) ? sanitize_text_field($_POST['dequeue_mode']) : 'settings';
        update_option('simple_dequeue_mode', $mode);

        wp_redirect(add_query_arg('tab', 'settings', admin_url('options-general.php?page=simple-dequeue&updated=true')));
        exit;
    }

    private function get_asset_source($src) {
        $plugins_url = plugins_url();
        $matches = array();

        if (preg_match("#$plugins_url/([^/]+)/#", $src, $matches)) {
            return $matches[1];
        }

        return __('Unknown', 'simple-dequeue');
    }

    public function generate_dequeue_code($assets) {
        $code = "<?php\n";
        $code .= "function dequeue_selected_assets() {\n";
        foreach ($assets as $asset => $contexts) {
            foreach ($contexts as $context => $value) {
                $code .= "    if ($context()) {\n";
                $code .= "        wp_dequeue_script('" . esc_js($asset) . "');\n";
                $code .= "        wp_dequeue_style('" . esc_js($asset) . "');\n";
                $code .= "    }\n";
            }
        }
        $code .= "}\n";
        $code .= "add_action('wp_enqueue_scripts', 'dequeue_selected_assets', 100);\n";
        return $code;
    }

    private function update_dequeue_file($assets) {
        $code = $this->generate_dequeue_code($assets);

        error_log('Simple Dequeue: ' . __('Generated dequeue code:', 'simple-dequeue') . ' ' . $code);

        $file_written = @file_put_contents($this->dequeue_file, $code);
        
        if ($file_written === false) {
            $this->file_error = true;
            error_log('Simple Dequeue: ' . __('Failed to write to', 'simple-dequeue') . ' ' . $this->dequeue_file);
        } else {
            error_log('Simple Dequeue: ' . __('Successfully wrote to', 'simple-dequeue') . ' ' . $this->dequeue_file);
        }
    }

    public function run_dequeue_file() { // Changed to public
        if (file_exists($this->dequeue_file)) {
            include $this->dequeue_file;
        } else {
            $this->file_error = true;
            error_log('Simple Dequeue: ' . __('Dequeue file does not exist at', 'simple-dequeue') . ' ' . $this->dequeue_file);
        }
    }

    private function is_context($context) {
        if (function_exists($context)) {
            return call_user_func($context);
        }
        return false;
    }

    private function is_direct_file_mode() {
        return get_option('simple_dequeue_direct_file_mode', false);
    }
}
