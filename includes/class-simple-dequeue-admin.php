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
        add_options_page(
            __('Simple Dequeue', 'simple-dequeue'),
            __('Simple Dequeue', 'simple-dequeue'),
            'manage_options',
            'simple-dequeue',
            array($this, 'admin_page')
        );
    }

    public function admin_page() {
        $enqueued_assets = get_option('simple_dequeue_assets', array());
        $dequeued_assets = get_option('simple_dequeue_dequeued_assets', array());
        $direct_file_mode = get_option('simple_dequeue_direct_file_mode', false);
        $dequeue_mode = get_option('simple_dequeue_mode', 'settings');
        $dequeue_file_contents = file_exists($this->plugin->get_dequeue_file_path()) ? file_get_contents($this->plugin->get_dequeue_file_path()) : '';
        ?>
        <div class="wrap">
            <h1><?php _e('Simple Dequeue', 'simple-dequeue'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="#manage" class="nav-tab"><?php _e('Manage Dequeues', 'simple-dequeue'); ?></a>
                <a href="#settings" class="nav-tab"><?php _e('Settings', 'simple-dequeue'); ?></a>
                <a href="#manual" class="nav-tab"><?php _e('Manual Dequeue Code', 'simple-dequeue'); ?></a>
                <a href="#direct" class="nav-tab"><?php _e('Direct File Mode', 'simple-dequeue'); ?></a>
            </h2>
            <div id="manage" class="tab-content">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="update_dequeues">
                    <?php wp_nonce_field('update_dequeues_nonce', 'update_dequeues_nonce'); ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Asset', 'simple-dequeue'); ?></th>
                                <th><?php _e('Type', 'simple-dequeue'); ?></th>
                                <th><?php _e('Source', 'simple-dequeue'); ?></th>
                                <?php foreach ($this->plugin->get_contexts() as $context => $label): ?>
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
                                        <?php foreach ($this->plugin->get_contexts() as $context => $label): ?>
                                            <td>
                                                <input type="checkbox" name="dequeues[<?php echo esc_attr($asset); ?>][<?php echo esc_attr($context); ?>]" value="1" <?php checked(isset($dequeued_assets[$asset][$context])); ?>>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo count($this->plugin->get_contexts()) + 3; ?>"><?php _e('No enqueued assets found.', 'simple-dequeue'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p><input type="submit" class="button-primary" value="<?php _e('Save Changes', 'simple-dequeue'); ?>"></p>
                </form>
            </div>
            <div id="settings" class="tab-content" style="display:none;">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="update_dequeue_mode">
                    <?php wp_nonce_field('update_dequeue_mode_nonce', 'update_dequeue_mode_nonce'); ?>
                    <h2><?php _e('Choose Dequeue Mode', 'simple-dequeue'); ?></h2>
                    <p>
                        <label>
                            <input type="radio" name="dequeue_mode" value="settings" <?php checked($dequeue_mode, 'settings'); ?>>
                            <strong><?php _e('Direct from settings:', 'simple-dequeue'); ?></strong> <?php _e('The plugin will dequeue assets on the frontend according to your settings, ideal for building and testing.', 'simple-dequeue'); ?>
                        </label>
                    </p>
                    <p>
                        <label>
                            <input type="radio" name="dequeue_mode" value="functions_file" <?php checked($dequeue_mode, 'functions_file'); ?>>
                            <strong><?php _e('Theme Functions File:', 'simple-dequeue'); ?></strong> <?php _e('The plugin generates code you can paste into your theme\'s functions.php file. The plugin will not dequeue assets by itself, only generate the code. You can safely disable the plugin when the code is implemented.', 'simple-dequeue'); ?>
                        </label>
                    </p>
                    <p>
                        <label>
                            <input type="radio" name="dequeue_mode" value="direct_file" <?php checked($dequeue_mode, 'direct_file'); ?>>
                            <strong><?php _e('Direct File Mode:', 'simple-dequeue'); ?></strong> <?php _e('The plugin will generate a file that is loaded on the frontend but not access its settings in the database to change the file when you make changes. This is a higher performance option bypassing the need to keep a copy (requires system write permissions).', 'simple-dequeue'); ?>
                        </label>
                    </p>
                    <p><input type="submit" class="button-primary" value="<?php _e('Save Changes', 'simple-dequeue'); ?>"></p>
                </form>
            </div>
            <div id="manual" class="tab-content" style="display:none;">
                <?php if (!empty($dequeued_assets)): ?>
                    <h2><?php _e('Manual Dequeue Code', 'simple-dequeue'); ?></h2>
                    <p><?php _e('Copy the following code to your theme\'s functions.php file and disable this plugin:', 'simple-dequeue'); ?></p>
                    <textarea id="dequeue-code" rows="10" class="large-text" readonly><?php echo esc_textarea($this->plugin->generate_dequeue_code($dequeued_assets)); ?></textarea>
                    <button class="button" onclick="copyToClipboard()"><?php _e('Copy to Clipboard', 'simple-dequeue'); ?></button>
                    <script>
                        function copyToClipboard() {
                            var copyText = document.getElementById("dequeue-code");
                            copyText.select();
                            copyText.setSelectionRange(0, 99999); /* For mobile devices */
                            document.execCommand("copy");
                            alert("<?php _e('Code copied to clipboard', 'simple-dequeue'); ?>");
                        }
                    </script>
                <?php endif; ?>
            </div>
            <div id="direct" class="tab-content" style="display:none;">
                <h2><?php _e('Direct File Mode', 'simple-dequeue'); ?></h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="toggle_direct_file_mode">
                    <?php wp_nonce_field('toggle_direct_file_mode_nonce', 'toggle_direct_file_mode_nonce'); ?>
                    <p>
                        <label>
                            <input type="checkbox" name="direct_file_mode" value="1" <?php checked($direct_file_mode); ?>>
                            <?php _e('Enable Direct File Mode', 'simple-dequeue'); ?>
                        </label>
                    </p>
                    <p><input type="submit" class="button-primary" value="<?php _e('Save Changes', 'simple-dequeue'); ?>"></p>
                </form>
                <?php if ($direct_file_mode): ?>
                    <h3><?php _e('Current Dequeue Code', 'simple-dequeue'); ?></h3>
                    <textarea id="direct-dequeue-code" rows="10" class="large-text" readonly><?php echo esc_textarea($dequeue_file_contents); ?></textarea>
                    <button class="button" onclick="copyDirectFileToClipboard()"><?php _e('Copy to Clipboard', 'simple-dequeue'); ?></button>
                    <script>
                        function copyDirectFileToClipboard() {
                            var copyText = document.getElementById("direct-dequeue-code");
                            copyText.select();
                            copyText.setSelectionRange(0, 99999); /* For mobile devices */
                            document.execCommand("copy");
                            alert("<?php _e('Code copied to clipboard', 'simple-dequeue'); ?>");
                        }
                    </script>
                <?php endif; ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const tabs = document.querySelectorAll('.nav-tab');
                    const contents = document.querySelectorAll('.tab-content');
                    const hash = window.location.hash;
                    const activeTab = hash ? document.querySelector('a[href="' + hash + '"]') : tabs[0];
                    if (activeTab) {
                        tabs.forEach(tab => tab.classList.remove('nav-tab-active'));
                        contents.forEach(content => content.style.display = 'none');
                        activeTab.classList.add('nav-tab-active');
                        document.querySelector(activeTab.getAttribute('href')).style.display = 'block';
                    }
                    tabs.forEach(tab => {
                        tab.addEventListener('click', function (e) {
                            e.preventDefault();
                            window.location.hash = this.getAttribute('href');
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
            echo '<div class="notice notice-error"><p>' . __('Simple Dequeue: Failed to create or write to the dequeue code file.', 'simple-dequeue') . '</p></div>';
        }
    }
}
