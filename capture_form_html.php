<?php
/**
 * Capture Form HTML Test
 * 
 * This script simulates the exact form rendering to see what HTML is generated
 */

// WordPress bootstrap
require_once('/var/www/html/wp-config.php');

echo "=== Form HTML Capture Test ===\n\n";

// Simulate the exact rendering from the settings page
$option_name = 'wp_content_flow_settings';
$settings = get_option($option_name, array());
$value = isset($settings['default_ai_provider']) ? $settings['default_ai_provider'] : 'openai';

echo "1. Current database value: '$value'\n\n";

echo "2. Generated HTML for dropdown:\n";

// Simulate exact HTML generation from render_default_provider_field()
ob_start();
?>
<select name="<?php echo $option_name; ?>[default_ai_provider]" class="regular-text">
    <option value="openai" <?php selected($value, 'openai'); ?>>OpenAI (GPT)</option>
    <option value="anthropic" <?php selected($value, 'anthropic'); ?>>Anthropic (Claude)</option>
    <option value="google" <?php selected($value, 'google'); ?>>Google AI (Gemini)</option>
</select>
<?php
$html = ob_get_clean();

echo $html . "\n\n";

echo "3. WordPress selected() function results:\n";
foreach (['openai', 'anthropic', 'google'] as $option_value) {
    $selected_attr = selected($value, $option_value, false);
    echo "   selected('$value', '$option_value', false) = '$selected_attr'\n";
}

echo "\n4. Testing WordPress selected() function directly:\n";
// Test different scenarios
$test_cases = [
    ['anthropic', 'anthropic'],
    ['anthropic', 'openai'],
    ['openai', 'openai'],
    ['openai', 'anthropic']
];

foreach ($test_cases as $case) {
    $result = selected($case[0], $case[1], false);
    echo "   selected('{$case[0]}', '{$case[1]}', false) = '$result'\n";
}

echo "\n5. Raw option value examination:\n";
echo "   Raw value type: " . gettype($value) . "\n";
echo "   Raw value length: " . strlen($value) . "\n";
echo "   Raw value (var_export): " . var_export($value, true) . "\n";
echo "   Raw value (json_encode): " . json_encode($value) . "\n";

// Check for invisible characters
echo "   Byte-by-byte: ";
for ($i = 0; $i < strlen($value); $i++) {
    echo ord($value[$i]) . " ";
}
echo "\n";

echo "\n6. Complete settings array:\n";
echo var_export($settings, true) . "\n";

echo "\n=== Test Complete ===\n";
?>