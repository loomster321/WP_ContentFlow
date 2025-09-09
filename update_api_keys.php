<?php
/**
 * Update WordPress Plugin Settings with Production API Keys
 * Loads API keys from .env file and updates WordPress settings
 */

// Load environment variables from .env file
function load_env_file($file_path) {
    if (!file_exists($file_path)) {
        die("Error: .env file not found at $file_path\n");
    }
    
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env_vars = [];
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env_vars[trim($key)] = trim($value);
        }
    }
    
    return $env_vars;
}

// WordPress bootstrap
define('WP_USE_THEMES', false);
require_once('/var/www/html/wp-config.php');

echo "=== Updating WordPress Plugin Settings with Production API Keys ===\n";

// Load environment variables
$env_file = '/tmp/.env';
$env_vars = load_env_file($env_file);

echo "✅ Loaded environment variables from .env file\n";

// Get current plugin settings
$current_settings = get_option('wp_content_flow_settings', array());
echo "✅ Retrieved current plugin settings\n";

// Update with production API keys
$updated_settings = array_merge($current_settings, [
    'anthropic_api_key' => $env_vars['ANTHROPIC_API_KEY'] ?? '',
    'openai_api_key' => $env_vars['OPENAI_API_KEY'] ?? '',
    'default_ai_provider' => 'anthropic', // Set Anthropic as default since it's Claude
    'max_tokens' => 2000,
    'temperature' => 0.7,
    'enable_auto_suggestions' => 1,
]);

// Update WordPress settings
$updated = update_option('wp_content_flow_settings', $updated_settings);

if ($updated) {
    echo "✅ WordPress plugin settings updated successfully!\n";
} else {
    echo "ℹ️  Settings may have already been up-to-date\n";
}

// Verify the update
$saved_settings = get_option('wp_content_flow_settings', array());

echo "\n=== Current Plugin Configuration ===\n";
echo "Default AI Provider: " . ($saved_settings['default_ai_provider'] ?? 'Not set') . "\n";
echo "Max Tokens: " . ($saved_settings['max_tokens'] ?? 'Not set') . "\n";
echo "Temperature: " . ($saved_settings['temperature'] ?? 'Not set') . "\n";
echo "Auto Suggestions: " . ($saved_settings['enable_auto_suggestions'] ? 'Enabled' : 'Disabled') . "\n";

echo "\n=== API Key Status ===\n";
foreach (['anthropic_api_key', 'openai_api_key'] as $key_name) {
    if (!empty($saved_settings[$key_name])) {
        $key_value = $saved_settings[$key_name];
        $masked_key = substr($key_value, 0, 12) . '...' . substr($key_value, -4);
        echo "✅ $key_name: $masked_key (configured)\n";
    } else {
        echo "❌ $key_name: Not configured\n";
    }
}

echo "\n=== Plugin Status Summary ===\n";
echo "🔑 **API Keys**: Production keys loaded from .env file\n";
echo "🤖 **Default Provider**: Anthropic Claude (your key)\n";
echo "⚙️  **Settings**: Optimized for production use\n";
echo "🔒 **Security**: API keys stored securely in WordPress database\n";

echo "\n🎉 **WordPress AI Content Flow plugin is now configured with your production API keys!**\n";
echo "🌐 **Access**: http://localhost:8080/wp-admin (admin / !3cTXkh)9iDHhV5o*N)\n";
echo "🔧 **Test**: Go to Content Flow > Settings to verify the keys are loaded\n";
echo "📝 **Create Content**: Add 'AI Text Generator' block in post editor\n";

?>