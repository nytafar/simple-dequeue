<?php
if (!defined('ABSPATH')) {
    exit;
}

class Simple_Dequeue_Admin {

    private $plugin;

    public function __construct($plugin) {
        $this->plugin = $plugin;

        add_action('admin_menu', array($this, 'create_admin_page'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    public function create_admin_page() {
        add_options_page('Simple Dequeue', 'Simple Dequeue', 'manage_options', 'simple-dequeue', array($this, 'admin_page'));
    }

    public function admin_page() {
        $enqueued_assets = get_option('simple_dequeue_assets', array());
        $dequeued_assets = get_option('simple_dequeue_dequeued_assets', array());
        $direct_file_mode = get_option('simple_dequeue_direct_file_mode', false);
        $dequeue_file_contents = file_exists($this->plugin->get_dequeue_file_path()) ? file_get_contents($this->plugin->get_dequeue_file_path()) : '';
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
                                <?php foreach ($this->plugin->get_contexts() as $context => $label): ?> <!-- Use getter method -->
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
                                        <?php foreach ($this->plugin->get_contexts() as $context => $label): ?> <!-- Use getter method -->
                                            <td>
                                                <input type="checkbox" name="dequeues[<?php echo esc_attr($asset); ?>][<?php echo esc_attr($context); ?>]" value="1" <?php checked(isset($dequeued_assets[$asset][$context])); ?>>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo count($this->plugin->get_contexts()) + 3; ?>">No enqueued assets found.</td>
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
                    <textarea id="dequeue-code" rows="10" class="large-text" readonly><?php echo esc_textarea($this->plugin->generate_dequeue_code($dequeued_assets)); ?></textarea>
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
                    <textarea id="direct-dequeue-code" rows="10" class="large-text" readonly><?php echo esc_textarea($dequeue_file_contents); ?></textarea>
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
        if ($this->plugin->has_file_error()) {
            echo '<div class="notice notice-error"><p>Simple Dequeue: Failed to create or write to the dequeue code file.</p></div>';
        }
    }
}
