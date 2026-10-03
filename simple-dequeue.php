<?php
/**
 * Plugin Name: Simple Dequeue
 * Description: Show and selectively disable CSS and JS files enqueued by other plugins.
 * Version: 2.0.0
 * Author: Lasse Jellum
 * License: GPL2
 * Text Domain: simple-dequeue
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

const SIMPLE_DEQUEUE_VERSION = '2.0.0';

// Conditional tags a rule can target. Whitelist: rule keys are called as functions.
const SIMPLE_DEQUEUE_CONTEXTS = array('is_front_page', 'is_home', 'is_single', 'is_page', 'is_product');

// Rules file: edit by hand or under Settings > Simple Dequeue. Outside the plugin dir so updates keep it.
define('SIMPLE_DEQUEUE_FILE', WP_CONTENT_DIR . '/simple-dequeue-rules.php');

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('init', function () {
    load_plugin_textdomain('simple-dequeue', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

add_action('wp_enqueue_scripts', 'simple_dequeue_run', 100);

/**
 * Frontend: includes the rules file (opcache-cached), no DB reads for rules, no writes. Admins also
 * refresh the asset list (logged-in requests bypass the page cache, so this never runs for visitors).
 */
function simple_dequeue_run() {
    if (current_user_can('manage_options')) {
        simple_dequeue_capture();
    }
    if (get_option('simple_dequeue_mode', 'settings') === 'functions_file') {
        return;
    }
    $active = array_flip(array_filter(SIMPLE_DEQUEUE_CONTEXTS, function ($context) {
        return function_exists($context) && $context();
    }));
    if (!$active) {
        return;
    }
    foreach (simple_dequeue_rules() as $handle => $contexts) {
        if (array_intersect_key((array) $contexts, $active)) {
            wp_dequeue_script($handle);
            wp_dequeue_style($handle);
        }
    }
}

// Record handles queued on this page. Not autoloaded: only the settings page reads it.
function simple_dequeue_capture() {
    $seen   = get_option('simple_dequeue_assets', array());
    $assets = $seen;
    foreach (array('js' => wp_scripts(), 'css' => wp_styles()) as $type => $deps) {
        foreach ($deps->queue as $handle) {
            if (empty($deps->registered[$handle]->src)) {
                continue;
            }
            $src = $deps->registered[$handle]->src;
            $assets[$handle] = array(
                'type'        => $type,
                'source'      => preg_match('#/plugins/([^/]+)/#', $src, $m) ? $m[1] : 'Unknown',
                'full_source' => $src,
            );
        }
    }
    if ($assets !== $seen) {
        update_option('simple_dequeue_assets', $assets, false);
    }
}

/**
 * Rules as [handle => [context => '1']]. Until the 1.x option is migrated into the file, the option is
 * used. A broken hand edit must not take the site down: it logs and dequeues nothing.
 */
function simple_dequeue_rules($file = SIMPLE_DEQUEUE_FILE) {
    if (!is_file($file)) {
        return simple_dequeue_sanitize_rules(get_option('simple_dequeue_dequeued_assets', array()));
    }
    try {
        return simple_dequeue_sanitize_rules(include $file);
    } catch (\Throwable $e) {
        error_log('Simple Dequeue: ' . $file . ': ' . $e->getMessage());
        return array();
    }
}

/** Write the rules file, only when its content changes. Atomic, so a visitor never includes half a file. */
function simple_dequeue_write_rules($rules, $file = SIMPLE_DEQUEUE_FILE) {
    $code = "<?php\n// Simple Dequeue rules: handle => contexts (" . implode(', ', SIMPLE_DEQUEUE_CONTEXTS) . ").\n"
          . "// Edit here or under Settings > Simple Dequeue. Hand edits apply within opcache.revalidate_freq.\n"
          . "defined('ABSPATH') || exit;\n\nreturn array(\n";
    foreach (simple_dequeue_sanitize_rules($rules) as $handle => $contexts) {
        $code .= '    ' . var_export((string) $handle, true) . ' => array(' . implode(', ', array_map(function ($c) {
            return var_export($c, true);
        }, array_keys($contexts))) . "),\n";
    }
    $code .= ");\n";
    if (is_file($file) && file_get_contents($file) === $code) {
        return true;
    }
    $tmp = $file . '.' . wp_generate_password(8, false) . '.tmp';
    if (false === file_put_contents($tmp, $code) || !rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($file, true);
    }
    return true;
}

/** Keep only whitelisted contexts and non-empty handles: [handle => [context => '1']]. Accepts list form. */
function simple_dequeue_sanitize_rules($input) {
    $rules = array();
    foreach ((array) $input as $handle => $contexts) {
        $handle   = sanitize_text_field((string) $handle);
        $contexts = (array) $contexts;
        if ($contexts && array_keys($contexts) === range(0, count($contexts) - 1)) {
            $contexts = array_fill_keys(array_map('strval', $contexts), '1');
        }
        $contexts = array_intersect_key($contexts, array_flip(SIMPLE_DEQUEUE_CONTEXTS));
        if ($handle !== '' && $contexts) {
            $rules[$handle] = array_fill_keys(array_keys($contexts), '1');
        }
    }
    return $rules;
}

/** PHP snippet equivalent to the saved rules, for pasting into a theme. */
function simple_dequeue_generate_code($rules) {
    $by_context = array();
    foreach (simple_dequeue_sanitize_rules($rules) as $handle => $contexts) {
        foreach ($contexts as $context => $on) {
            $by_context[$context][] = $handle;
        }
    }
    $code = "add_action('wp_enqueue_scripts', function () {\n";
    foreach ($by_context as $context => $handles) {
        $code .= "    if (function_exists('$context') && $context()) {\n";
        foreach ($handles as $handle) {
            $h     = var_export($handle, true);
            $code .= "        wp_dequeue_script($h);\n        wp_dequeue_style($h);\n";
        }
        $code .= "    }\n";
    }
    return $code . "}, 100);\n";
}

if (is_admin()) {
    add_action('admin_init', 'simple_dequeue_upgrade');
    add_action('admin_menu', function () {
        add_options_page(__('Simple Dequeue', 'simple-dequeue'), __('Simple Dequeue', 'simple-dequeue'), 'manage_options', 'simple-dequeue', 'simple_dequeue_admin_page');
    });
    add_action('admin_post_simple_dequeue_save', 'simple_dequeue_save');
}

// 1.x autoloaded the asset list, kept rules in an option and rewrote dequeue-code.php on every page view.
function simple_dequeue_upgrade() {
    if (get_option('simple_dequeue_version') === SIMPLE_DEQUEUE_VERSION) {
        return;
    }
    if (!is_file(SIMPLE_DEQUEUE_FILE)) {
        if (!simple_dequeue_write_rules(simple_dequeue_rules())) {
            return; // Retry next admin load; the frontend keeps using the option meanwhile.
        }
    }
    wp_set_option_autoload('simple_dequeue_assets', false);
    if (get_option('simple_dequeue_mode') === 'direct_file') {
        update_option('simple_dequeue_mode', 'settings');
    }
    delete_option('simple_dequeue_direct_file_mode');
    wp_delete_file(plugin_dir_path(__FILE__) . 'dequeue-code.php');
    update_option('simple_dequeue_version', SIMPLE_DEQUEUE_VERSION);
}

function simple_dequeue_save() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized request.', 'simple-dequeue'));
    }
    check_admin_referer('simple_dequeue_save');

    $written = simple_dequeue_write_rules(wp_unslash($_POST['rules'] ?? array()));
    $mode    = ($_POST['dequeue_mode'] ?? '') === 'functions_file' ? 'functions_file' : 'settings';
    update_option('simple_dequeue_mode', $mode);

    wp_safe_redirect(admin_url('options-general.php?page=simple-dequeue&' . ($written ? 'updated=true' : 'write_error=1')));
    exit;
}

function simple_dequeue_admin_page() {
    $assets   = get_option('simple_dequeue_assets', array());
    $rules    = simple_dequeue_rules();
    $mode     = get_option('simple_dequeue_mode', 'settings');
    $contexts = array(
        'is_front_page' => __('Front Page', 'simple-dequeue'),
        'is_home'       => __('Blog Page', 'simple-dequeue'),
        'is_single'     => __('Single Post', 'simple-dequeue'),
        'is_page'       => __('Single Page', 'simple-dequeue'),
        'is_product'    => __('Product Page (WooCommerce)', 'simple-dequeue'),
    );
    ksort($assets);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Simple Dequeue', 'simple-dequeue'); ?></h1>
        <?php if (isset($_GET['write_error'])) : ?>
            <div class="notice notice-error"><p><?php echo esc_html(sprintf(__('Could not write %s. Nothing was saved.', 'simple-dequeue'), SIMPLE_DEQUEUE_FILE)); ?></p></div>
        <?php endif; ?>
        <p><?php esc_html_e('Assets are recorded while you browse the frontend logged in as an administrator.', 'simple-dequeue'); ?></p>
        <p><?php echo esc_html(sprintf(__('Rules are stored in %s. You can also edit that file directly.', 'simple-dequeue'), SIMPLE_DEQUEUE_FILE)); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="simple_dequeue_save">
            <?php wp_nonce_field('simple_dequeue_save'); ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Asset', 'simple-dequeue'); ?></th>
                        <th><?php esc_html_e('Type', 'simple-dequeue'); ?></th>
                        <th><?php esc_html_e('Source', 'simple-dequeue'); ?></th>
                        <?php foreach ($contexts as $label) : ?>
                            <th><?php echo esc_html($label); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $handle => $details) : ?>
                        <tr>
                            <td><?php echo esc_html($handle); ?></td>
                            <td><?php echo esc_html($details['type']); ?></td>
                            <td title="<?php echo esc_attr($details['full_source']); ?>"><?php echo esc_html($details['source']); ?></td>
                            <?php foreach ($contexts as $context => $label) : ?>
                                <td><input type="checkbox" name="rules[<?php echo esc_attr($handle); ?>][<?php echo esc_attr($context); ?>]" value="1" aria-label="<?php echo esc_attr("$handle: $label"); ?>" <?php checked(isset($rules[$handle][$context])); ?>></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$assets) : ?>
                        <tr><td colspan="<?php echo count($contexts) + 3; ?>"><?php esc_html_e('No enqueued assets found.', 'simple-dequeue'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('Choose Dequeue Mode', 'simple-dequeue'); ?></h2>
            <p><label>
                <input type="radio" name="dequeue_mode" value="settings" <?php checked($mode !== 'functions_file'); ?>>
                <strong><?php esc_html_e('Direct from settings:', 'simple-dequeue'); ?></strong>
                <?php esc_html_e('The plugin will dequeue assets on the frontend according to your settings, ideal for building and testing.', 'simple-dequeue'); ?>
            </label></p>
            <p><label>
                <input type="radio" name="dequeue_mode" value="functions_file" <?php checked($mode, 'functions_file'); ?>>
                <strong><?php esc_html_e('Theme Functions File:', 'simple-dequeue'); ?></strong>
                <?php esc_html_e('The plugin generates code you can paste into your theme\'s functions.php file. The plugin will not dequeue assets by itself, only generate the code. You can safely disable the plugin when the code is implemented.', 'simple-dequeue'); ?>
            </label></p>
            <?php submit_button(__('Save Changes', 'simple-dequeue')); ?>
        </form>

        <?php if ($rules) : ?>
            <h2><?php esc_html_e('Manual Dequeue Code', 'simple-dequeue'); ?></h2>
            <p><?php esc_html_e('Copy the following code to your theme\'s functions.php file and disable this plugin:', 'simple-dequeue'); ?></p>
            <textarea rows="12" class="large-text code" readonly onclick="this.select()"><?php echo esc_textarea(simple_dequeue_generate_code($rules)); ?></textarea>
        <?php endif; ?>
    </div>
    <?php
}
