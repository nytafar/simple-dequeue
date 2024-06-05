<?php
if (!defined('ABSPATH')) {
    exit;
}

class Simple_Dequeue {

    private $contexts = array(
        'is_front_page' => 'Front Page',
        'is_home' => 'Blog Page',
        'is_single' => 'Single Post',
        'is_page' => 'Single Page',
        'is_product' => 'Product Page (WooCommerce)',
        // Add more contexts as needed
    );

    private $dequeue_file;
    private $file_error;

    public function __construct() {
        $this->dequeue_file = SIMPLE_DEQUEUE_PATH . 'dequeue-code.php';
        $this->file_error = false;

        add_action('wp_enqueue_scripts', array($this, 'capture_enqueued_assets'), 100);
        add_action('admin_post_update_dequeues', array($this, 'update_dequeues'));
        add_action('admin_post_toggle_direct_file_mode', array($this, 'toggle_direct_file_mode'));

        if (get_option('simple_dequeue_direct_file_mode', false)) {
            add_action('wp_enqueue_scripts', array($this, 'run_dequeue_file'), 99);
        }

        // Initialize the admin functionality
        if (is_admin()) {
            require_once SIMPLE_DEQUEUE_PATH . 'includes/class-simple-dequeue-admin.php';
            new Simple_Dequeue_Admin($this);
        }
    }

    public function capture_enqueued_assets() {
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

        $this->update_dequeue_file($dequeued_assets);

        foreach ($dequeued_assets as $asset => $contexts) {
            foreach ($contexts as $context => $value) {
                if (!$this->is_direct_file_mode() && $this->is_context($context)) {
                    wp_dequeue_script($asset);
                    wp_dequeue_style($asset);
                }
            }
        }
    }

    public function update_dequeues() {
        if (!current_user_can('manage_options') || !isset($_POST['update_dequeues_nonce']) || !wp_verify_nonce($_POST['update_dequeues_nonce'], 'update_dequeues_nonce')) {
            wp_die('Unauthorized request.');
        }

        $dequeues = isset($_POST['dequeues']) ? $_POST['dequeues'] : array();
        update_option('simple_dequeue_dequeued_assets', $dequeues);

        wp_redirect(admin_url('options-general.php?page=simple-dequeue&updated=true'));
        exit;
    }

    public function toggle_direct_file_mode() {
        if (!current_user_can('manage_options') || !isset($_POST['toggle_direct_file_mode_nonce']) || !wp_verify_nonce($_POST['toggle_direct_file_mode_nonce'], 'toggle_direct_file_mode_nonce')) {
            wp_die('Unauthorized request.');
        }

        $direct_file_mode = isset($_POST['direct_file_mode']) ? 1 : 0;
        update_option('simple_dequeue_direct_file_mode', $direct_file_mode);

        wp_redirect(admin_url('options-general.php?page=simple-dequeue&updated=true'));
        exit;
    }

    private function get_asset_source($src) {
        $plugins_url = plugins_url();
        $matches = array();

        if (preg_match("#$plugins_url/([^/]+)/#", $src, $matches)) {
            return $matches[1];
        }

        return 'Unknown';
    }

    private function generate_dequeue_code($assets) {
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
        if (false === @file_put_contents($this->dequeue_file, $code)) {
            $this->file_error = true;
        }
    }

    private function run_dequeue_file() {
        if (file_exists($this->dequeue_file)) {
            include $this->dequeue_file;
        } else {
            $this->file_error = true;
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