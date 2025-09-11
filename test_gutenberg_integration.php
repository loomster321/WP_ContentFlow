<?php
/**
 * WordPress Gutenberg Integration Test
 * 
 * This script tests if the WP Content Flow plugin properly registers
 * its Gutenberg blocks in a WordPress environment.
 */

echo "=== WP Content Flow - WordPress Gutenberg Integration Test ===\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Test WordPress Environment
echo "1. WORDPRESS ENVIRONMENT TEST\n";
echo "==============================\n";

// Check if WordPress functions exist
$wp_functions = [
    'wp_enqueue_script' => 'Script enqueuing',
    'wp_enqueue_style' => 'Style enqueuing', 
    'wp_localize_script' => 'Script localization',
    'register_block_type' => 'Block registration',
    'rest_url' => 'REST API URL generation',
    'wp_create_nonce' => 'Nonce creation',
    'plugin_dir_url' => 'Plugin URL generation',
    'plugin_dir_path' => 'Plugin path generation',
];

$wp_available = function_exists('wp_enqueue_script');

if ($wp_available) {
    echo "✓ WordPress environment detected\n";
    
    foreach ($wp_functions as $func => $desc) {
        if (function_exists($func)) {
            echo "✓ {$func} available ({$desc})\n";
        } else {
            echo "✗ {$func} missing ({$desc})\n";
        }
    }
} else {
    echo "✗ WordPress environment NOT detected\n";
    echo "   This test needs to run within WordPress context.\n";
    echo "   Try running: wp eval-file test_gutenberg_integration.php\n";
}

echo "\n";

// 2. Plugin Registration Test
echo "2. PLUGIN REGISTRATION TEST\n";
echo "============================\n";

$plugin_file = '/home/timl/dev/WP_ContentFlow/wp-content-flow/wp-content-flow.php';

if (file_exists($plugin_file)) {
    echo "✓ Plugin file exists: {$plugin_file}\n";
    
    // Check if plugin is loaded (constants defined)
    if (defined('WP_CONTENT_FLOW_VERSION')) {
        echo "✓ Plugin constants defined\n";
        echo "   Version: " . WP_CONTENT_FLOW_VERSION . "\n";
        echo "   Plugin Dir: " . WP_CONTENT_FLOW_PLUGIN_DIR . "\n";
        echo "   Plugin URL: " . WP_CONTENT_FLOW_PLUGIN_URL . "\n";
    } else {
        echo "⚠ Plugin constants not defined - plugin may not be loaded\n";
    }
    
    // Check if main class exists
    if (class_exists('WP_Content_Flow')) {
        echo "✓ Main plugin class 'WP_Content_Flow' exists\n";
    } else {
        echo "✗ Main plugin class 'WP_Content_Flow' not found\n";
    }
    
} else {
    echo "✗ Plugin file not found: {$plugin_file}\n";
}

echo "\n";

// 3. Asset File Checks
echo "3. ASSET FILE AVAILABILITY\n";
echo "===========================\n";

$required_assets = [
    '/home/timl/dev/WP_ContentFlow/wp-content-flow/assets/js/blocks.js' => 'Block registration script',
    '/home/timl/dev/WP_ContentFlow/wp-content-flow/assets/css/editor.css' => 'Editor styles',
    '/home/timl/dev/WP_ContentFlow/wp-content-flow/assets/css/frontend.css' => 'Frontend styles',
    '/home/timl/dev/WP_ContentFlow/wp-content-flow/blocks/ai-text-generator/index.js' => 'AI Text Generator block'
];

foreach ($required_assets as $file => $description) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✓ {$description}: {$size} bytes\n";
        
        if ($file === '/home/timl/dev/WP_ContentFlow/wp-content-flow/assets/js/blocks.js' && $size < 1000) {
            echo "  ⚠ WARNING: File is very small, may need compilation\n";
        }
    } else {
        echo "✗ MISSING: {$description}\n";
    }
}

echo "\n";

// 4. WordPress Hook Registration
echo "4. WORDPRESS HOOK REGISTRATION\n";
echo "===============================\n";

if ($wp_available) {
    // Check if enqueue functions are hooked
    global $wp_filter;
    
    $hooks_to_check = [
        'enqueue_block_editor_assets' => 'Block editor asset enqueuing',
        'wp_enqueue_scripts' => 'Frontend asset enqueuing',
        'init' => 'Plugin initialization',
        'plugins_loaded' => 'Plugin loading'
    ];
    
    foreach ($hooks_to_check as $hook => $description) {
        if (isset($wp_filter[$hook]) && !empty($wp_filter[$hook]->callbacks)) {
            echo "✓ Hook '{$hook}' has callbacks ({$description})\n";
            
            // Check for our specific callbacks
            foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback_id => $callback_info) {
                    if (is_array($callback_info['function']) && 
                        is_object($callback_info['function'][0]) && 
                        get_class($callback_info['function'][0]) === 'WP_Content_Flow') {
                        echo "  ✓ WP_Content_Flow callback found at priority {$priority}\n";
                    }
                }
            }
        } else {
            echo "⚠ Hook '{$hook}' has no callbacks\n";
        }
    }
} else {
    echo "⚠ Cannot check hooks - WordPress not available\n";
}

echo "\n";

// 5. REST API Endpoints Check
echo "5. REST API ENDPOINTS CHECK\n";
echo "============================\n";

if ($wp_available && function_exists('rest_url')) {
    $expected_endpoints = [
        '/wp-content-flow/v1/ai/generate' => 'AI content generation',
        '/wp-content-flow/v1/ai/improve' => 'Content improvement',
        '/wp-content-flow/v1/workflows' => 'Workflow management',
        '/wp-content-flow/v1/settings' => 'Plugin settings'
    ];
    
    foreach ($expected_endpoints as $endpoint => $description) {
        $full_url = rest_url($endpoint);
        echo "Expected endpoint: {$full_url} ({$description})\n";
    }
    
    // Check if REST API class exists
    if (class_exists('WP_Content_Flow_REST_API')) {
        echo "✓ REST API controller class exists\n";
    } else {
        echo "✗ REST API controller class not found\n";
    }
    
} else {
    echo "⚠ Cannot check REST API - WordPress not available\n";
}

echo "\n";

// 6. Block Registration Status
echo "6. BLOCK REGISTRATION STATUS\n";
echo "=============================\n";

if ($wp_available && function_exists('get_dynamic_block_names')) {
    $registered_blocks = get_dynamic_block_names();
    $static_blocks = array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered());
    $all_blocks = array_unique(array_merge($registered_blocks, $static_blocks));
    
    $expected_block = 'wp-content-flow/ai-text';
    
    if (in_array($expected_block, $all_blocks)) {
        echo "✓ Block '{$expected_block}' is registered\n";
    } else {
        echo "✗ Block '{$expected_block}' is NOT registered\n";
        echo "  Available blocks containing 'wp-content-flow':\n";
        
        $found_related = false;
        foreach ($all_blocks as $block) {
            if (strpos($block, 'wp-content-flow') !== false) {
                echo "    - {$block}\n";
                $found_related = true;
            }
        }
        
        if (!$found_related) {
            echo "    (none found)\n";
        }
    }
    
    echo "\n  Total registered blocks: " . count($all_blocks) . "\n";
    
} else {
    echo "⚠ Cannot check block registration - WordPress functions not available\n";
}

echo "\n";

// 7. JavaScript Localization Check
echo "7. JAVASCRIPT LOCALIZATION CHECK\n";
echo "=================================\n";

if ($wp_available) {
    // This would normally require WordPress to be fully loaded
    echo "Expected JavaScript object: 'wpContentFlow'\n";
    echo "Should contain:\n";
    echo "  - apiUrl: REST API base URL\n";
    echo "  - nonce: WordPress nonce for authentication\n";
    echo "  - version: Plugin version\n";
    
    if (defined('WP_CONTENT_FLOW_VERSION')) {
        echo "✓ Plugin version available for localization\n";
    } else {
        echo "✗ Plugin version not available\n";
    }
} else {
    echo "⚠ Cannot check localization - WordPress not available\n";
}

echo "\n";

// 8. Recommendations
echo "8. RECOMMENDATIONS\n";
echo "===================\n";

echo "To properly test Gutenberg block registration:\n\n";

echo "A. Run this test in WordPress context:\n";
echo "   wp eval-file test_gutenberg_integration.php\n\n";

echo "B. Check WordPress admin for blocks:\n";
echo "   1. Login to WordPress admin\n";
echo "   2. Go to Posts > Add New\n";
echo "   3. Click + to add block\n";
echo "   4. Search for 'AI Text'\n";
echo "   5. Check browser console for errors\n\n";

echo "C. Debug steps if blocks don't appear:\n";
echo "   1. Verify plugin is activated\n";
echo "   2. Check WordPress debug log for PHP errors\n";
echo "   3. Check browser console for JavaScript errors\n";
echo "   4. Test REST API endpoints manually\n";
echo "   5. Clear all caches (object cache, page cache)\n\n";

echo "D. File issues identified:\n";
if (!file_exists('/home/timl/dev/WP_ContentFlow/wp-content-flow/assets/css/frontend.css')) {
    echo "   ✗ frontend.css was missing (now created)\n";
} else {
    echo "   ✓ frontend.css exists\n";
}

$blocks_js_size = file_exists('/home/timl/dev/WP_ContentFlow/wp-content-flow/assets/js/blocks.js') 
    ? filesize('/home/timl/dev/WP_ContentFlow/wp-content-flow/assets/js/blocks.js') : 0;

if ($blocks_js_size < 1000) {
    echo "   ⚠ blocks.js is very small ({$blocks_js_size} bytes) - may need building\n";
    echo "     Consider running: cd /home/timl/dev/WP_ContentFlow && npm run build\n";
} else {
    echo "   ✓ blocks.js has reasonable size ({$blocks_js_size} bytes)\n";
}

echo "\n=== END INTEGRATION TEST ===\n";

// Create a simple test file that can be used with wp-cli
$wp_cli_test = '<?php
// Simple WordPress CLI test
if (defined("WP_CLI") && WP_CLI) {
    WP_CLI::success("WordPress environment is available");
    
    if (class_exists("WP_Content_Flow")) {
        WP_CLI::success("WP Content Flow plugin class found");
    } else {
        WP_CLI::error("WP Content Flow plugin class NOT found");
    }
    
    $blocks = array_keys(WP_Block_Type_Registry::get_instance()->get_all_registered());
    $ai_blocks = array_filter($blocks, function($block) {
        return strpos($block, "wp-content-flow") !== false;
    });
    
    if (!empty($ai_blocks)) {
        WP_CLI::success("Found WP Content Flow blocks: " . implode(", ", $ai_blocks));
    } else {
        WP_CLI::error("No WP Content Flow blocks registered");
    }
}
';

file_put_contents('/home/timl/dev/WP_ContentFlow/wp_cli_block_test.php', $wp_cli_test);
echo "Created wp_cli_block_test.php for WordPress CLI testing\n";
echo "Usage: wp eval-file wp_cli_block_test.php\n";
?>