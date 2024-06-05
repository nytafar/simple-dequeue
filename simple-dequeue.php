<?php
/**
 * Plugin Name: Simple Dequeue
 * Description: A plugin to show and selectively disable enqueued CSS and JS files from other plugins.
 * Version: 1.3.1
 * Author: Lasse Jellum
 * License: GPL2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SimpleDequeue {

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
        $this->dequeue_file = plugin_dir_path(__FILE__) . 'dequeue-code.php';
        $this->file_error = false;

        add_action('admin_menu', array($this, 'create_admin_page'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('wp_enqueue_scripts', array($this, 'capture_enqueued_assets'), 100);
        add_action('admin_post_update_dequeues', array($this, 'update_dequeues'));
        add_action('admin_post_toggle_direct_file_mode', array($this, 'toggle_direct_file_mode'));

        if (get_option('simple_dequeue_direct_file_mode', false)) {
            add_action('wp_enqueue_scripts', array($this, 'run_dequeue_file'), 99);
        }
    }

    public function create_admin_page() {
        add_options_page('Simple Dequeue', 'Simple Dequeue', 'manage_options', 'simple-dequeue', array($this, 'admin_page'));
    }

    public function admin_page() {
        $enqueued_assets = get_option('simple_dequeue_assets', array());
        $dequeued_assets = get_option('simple_dequeue_dequeued_assets', array());
        $direct_file_mode = get_option('simple_dequeue_direct_file_mode', false);
        ?>
        <div class="wrap">
            <h1>Simple Dequeue</h1>
            <h2 class="nav-tab-wrapper">
                <a href="#manage" class="nav-tab nav-tab-active">Manage Dequeues</a>
                <a href="#manual" class="nav-tab">Manual Dequeue Code</a>
                <a href="#direct" class="nav-tab">Direct File Mode</a>
            </h2>
            <div id="manage" class="tab-content">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="update_dequeues">
                    <?php wp_nonce_field('update_dequeues_nonce', 'update_dequeues_nonce'); ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Type</th>
                                <th>Source</th>
                                <?php foreach ($this->contexts as $context => $label): ?>
                                    <th><?php echo esc_html($label); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($enqueued_assets)): ?>
                                <?php foreach ($enqueued_assets as $asset => $details): ?>
                                    <tr>
                                        <td><?php echo esc_html($asset); ?></td>
                                        <td><?php echo esc_html($details['type']); ?></td>
                                        <td title="<?php echo esc_attr($details['full_source']); ?>"><?php echo esc_html($details['source']); ?></td>
                                        <?php foreach ($this->contexts as $context => $label): ?>
                                            <td>
                                                <input type="checkbox" name="dequeues[<?php echo esc_attr($asset); ?>][<?php echo esc_attr($context); ?>]" value="1" <?php checked(isset($dequeued_assets[$asset][$context])); ?>>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo count($this->contexts) + 3; ?>">No enqueued assets found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p><input type="submit" class="button-primary" value="Save Changes"></p>
                </form>
            </div>
            <div id="manual" class="tab-content" style="display:none;">
                <?php if (!empty($dequeued_assets)): ?>
                    <h2>Manual Dequeue Code</h2>
                    <p>Copy the following code to your theme's <code>functions.php</code> file and disable this plugin:</p>
                    <textarea id="dequeue-code" rows="10" class="large-text" readonly><?php echo esc_textarea($this->generate_dequeue_code($dequeued_assets)); ?></textarea>
                    <button class="button" onclick="copyToClipboard()">Copy to Clipboard</button>
                    <script>
                        function copyToClipboard() {
                            var copyText = document.getElementById("dequeue-code");
                            copyText.select();
                            copyText.setSelectionRange(0, 99999); /* For mobile devices */
                            document.execCommand("copy");
                            alert("Code copied to clipboard");
                        }
                    </script>
                <?php endif; ?>
            </div>
            <div id="direct" class="tab-content" style="display:none;">
                <h2>Direct File Mode</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="toggle_direct_file_mode">
                    <?php wp_nonce_field('toggle_direct_file_mode_nonce', 'toggle_direct_file_mode_nonce'); ?>
                    <p>
                        <label>
                            <input type="checkbox" name="direct_file_mode" value="1" <?php checked($direct_file_mode); ?>>
                            Enable Direct File Mode
                        </label>
                    </p>
                    <p><input type="submit" class="button-primary" value="Save Changes"></p>
                </form>
                <?php if ($direct_file_mode): ?>
                    <h3>Current Dequeue Code</h3>
                    <textarea id="direct-dequeue-code" rows="10" class="large-text" readonly><?php echo esc_textarea(file_get_contents($this->dequeue_file)); ?></textarea>
                    <button class="button" onclick="copyDirectFileToClipboard()">Copy to Clipboard</button>
                    <script>
                        function copyDirectFileToClipboard() {
                            var copyText = document.getElementById("direct-dequeue-code");
                            copyText.select();
                            copyText.setSelectionRange(0, 99999); /* For mobile devices */
                            document.execCommand("copy");
                            alert("Code copied to clipboard");
                        }
                    </script>
                <?php endif; ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const tabs = document.querySelectorAll('.nav-tab');
                    const contents = document.querySelectorAll('.tab-content');
                    tabs.forEach(tab => {
                        tab.addEventListener('click', function (e) {
                            e.preventDefault();
                            tabs.forEach(t => t.classList.remove('nav-tab-active'));
                            contents.forEach(c => c.style.display = 'none');
                            tab.classList.add('nav-tab-active');
                            document.querySelector(tab.getAttribute('href')).style.display = 'block';
                        });
                    });
                });
            </script>
        </div>
        <?php
    }

    public function admin_notices() {
        if ($this->file_error) {
            echo '<div class="notice notice-error"><p>Simple Dequeue: Failed to create or write to the dequeue code file.</p></div>';
        }
    }

    public function capture_enqueued_assets() {
        global $wp_scripts, $wp_styles;

        $enqueued_assets = get_option('simple_dequeue_assets', array());

        // Capture scripts
        foreach ($wp_scripts->queue as $handle) {
            if (!isset($wp_scripts->registered[$handle])) continue;

            $src = $wp_scripts->registered[$handle]->src;
            $source = $this->get_asset_source($src);

            if ($source) {
                $enqueued_assets[$handle] = array('type' => 'js', 'source' => $source, 'full_source' => $src);
            }
        }

        // Capture styles
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

        // Update the direct file with current dequeue code
        $this->update_dequeue_file($dequeued_assets);

        // Dequeue selected assets based on context
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

new SimpleDequeue();