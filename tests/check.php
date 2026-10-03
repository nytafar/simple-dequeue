<?php
/**
 * Self-check. Run: gp wp <site> eval-file wp-content/plugins/simple-dequeue/tests/check.php
 * Exits non-zero on failure. Touches no options.
 */
defined('ABSPATH') || exit;

$fail = function ($msg) { WP_CLI::error($msg); };

// Sanitizer drops non-whitelisted contexts (they are called as functions) and empty rules.
$rules = simple_dequeue_sanitize_rules(array(
    'vipps-gw'  => array('is_front_page' => '1', 'phpinfo' => '1'),
    'evil'      => array('system' => '1'),
    ''          => array('is_page' => '1'),
    "a'b"       => array('is_page' => 'on'),
));
$rules === array('vipps-gw' => array('is_front_page' => '1'), "a'b" => array('is_page' => '1'))
    || $fail('sanitize_rules: ' . var_export($rules, true));

// Generated snippet must be valid PHP with quotes escaped and no stray contexts.
$code = simple_dequeue_generate_code($rules + array('x' => array('exec' => '1')));
(strpos($code, 'exec') === false && strpos($code, "'a\\'b'") !== false) || $fail("generate_code:\n$code");
try {
    token_get_all("<?php\n$code", TOKEN_PARSE);
} catch (ParseError $e) {
    $fail('generate_code parse error: ' . $e->getMessage() . "\n$code");
}

// Rules file: round-trip, list form for hand edits, write only on change, broken file dequeues nothing.
$tmp = wp_tempnam('simple-dequeue-check');
@unlink($tmp);
$want = array('vipps-gw' => array('is_front_page' => '1', 'is_page' => '1'), "a'b" => array('is_product' => '1'));
simple_dequeue_write_rules($want + array('x' => array('system' => '1')), $tmp) || $fail('write_rules failed');
simple_dequeue_rules($tmp) === $want || $fail('round-trip: ' . var_export(simple_dequeue_rules($tmp), true));
touch($tmp, 1000000000);
clearstatcache();
simple_dequeue_write_rules($want, $tmp);
clearstatcache();
filemtime($tmp) === 1000000000 || $fail('unchanged rules rewrote the file');
file_put_contents($tmp, "<?php return array('wc-bis-main' => array('is_front_page', 'phpinfo'));");
simple_dequeue_rules($tmp) === array('wc-bis-main' => array('is_front_page' => '1')) || $fail('list form not read');
file_put_contents($tmp, "<?php return array(");
simple_dequeue_rules($tmp) === array() || $fail('broken file did not fall back to no rules');
unlink($tmp);

// The asset list must not be autoloaded (1.x loaded ~23 KB of it on every request).
$autoload = $GLOBALS['wpdb']->get_var("SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = 'simple_dequeue_assets'");
(null === $autoload || !in_array($autoload, wp_autoload_values_to_autoload(), true))
    || $fail("simple_dequeue_assets is autoloaded ($autoload) — open Settings > Simple Dequeue once to migrate");

WP_CLI::success('simple-dequeue checks passed');
