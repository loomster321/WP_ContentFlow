<?php
require_once '/var/www/html/wp-load.php';

$settings = get_option('wp_content_flow_settings', array());
echo "Settings keys: ";
print_r(array_keys($settings));
echo "\n\n";

foreach($settings as $key => $value) {
    if (strpos($key, '_encrypted') !== false) {
        echo $key . ": (encrypted, length=" . strlen($value) . ")\n";
    } elseif (strpos($key, 'api_key') !== false) {
        echo $key . ": " . (empty($value) ? "(empty)" : "(masked)") . "\n";
    } else {
        echo $key . ": " . var_export($value, true) . "\n";
    }
}