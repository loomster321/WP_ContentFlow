<?php
/**
 * Comprehensive Gutenberg Blocks Diagnostic Test
 * 
 * This script diagnoses why WP Content Flow Gutenberg blocks aren't appearing
 * in the WordPress block editor.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', '/home/timl/dev/WP_ContentFlow/');
}

echo "=== WP Content Flow - Gutenberg Blocks Diagnostic Report ===\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Plugin File Structure Analysis
echo "1. PLUGIN FILE STRUCTURE ANALYSIS\n";
echo "=================================\n";

$plugin_dir = '/home/timl/dev/WP_ContentFlow/wp-content-flow/';
$required_files = [
    'wp-content-flow.php' => 'Main plugin file',
    'assets/js/blocks.js' => 'Main block registration file',
    'blocks/ai-text-generator/index.js' => 'AI Text Generator block',
    'assets/js/workflow-data-store.js' => 'WordPress data store',
    'assets/js/improvement-toolbar.js' => 'Content improvement toolbar',
    'assets/js/workflow-settings.js' => 'Workflow settings panel',
    'assets/css/editor.css' => 'Block editor styles',
    'assets/css/frontend.css' => 'Frontend styles',
];

$missing_files = [];
$existing_files = [];

foreach ($required_files as $file => $description) {
    $full_path = $plugin_dir . $file;
    if (file_exists($full_path)) {
        $size = filesize($full_path);
        echo "✓ {$file} ({$description}) - {$size} bytes\n";
        $existing_files[] = $file;
    } else {
        echo "✗ MISSING: {$file} ({$description})\n";
        $missing_files[] = $file;
    }
}

echo "\nSummary: " . count($existing_files) . " files exist, " . count($missing_files) . " files missing\n\n";

// 2. Block Registration Analysis
echo "2. BLOCK REGISTRATION ANALYSIS\n";
echo "===============================\n";

// Check main plugin file for enqueuing
$main_plugin_content = file_get_contents($plugin_dir . 'wp-content-flow.php');
if (strpos($main_plugin_content, 'enqueue_block_editor_assets') !== false) {
    echo "✓ Block editor assets enqueue function exists\n";
    
    if (strpos($main_plugin_content, 'wp-content-flow-blocks') !== false) {
        echo "✓ blocks.js is properly enqueued with handle 'wp-content-flow-blocks'\n";
    } else {
        echo "✗ blocks.js enqueue handle not found\n";
    }
    
    // Check dependencies
    if (strpos($main_plugin_content, "'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data'") !== false) {
        echo "✓ Required WordPress dependencies are specified\n";
    } else {
        echo "⚠ WordPress dependencies might be incomplete\n";
    }
} else {
    echo "✗ Block editor assets enqueue function missing\n";
}

// Check if wpContentFlow is localized
if (strpos($main_plugin_content, 'wpContentFlow') !== false) {
    echo "✓ JavaScript configuration object 'wpContentFlow' is localized\n";
} else {
    echo "✗ JavaScript configuration object missing\n";
}

echo "\n";

// 3. Block Files Analysis
echo "3. BLOCK FILES ANALYSIS\n";
echo "========================\n";

// Check blocks.js imports
if (file_exists($plugin_dir . 'assets/js/blocks.js')) {
    $blocks_content = file_get_contents($plugin_dir . 'assets/js/blocks.js');
    $imports = [
        './workflow-data-store' => 'assets/js/workflow-data-store.js',
        '../blocks/ai-text-generator' => 'blocks/ai-text-generator/index.js',
        './improvement-toolbar' => 'assets/js/improvement-toolbar.js',
        './workflow-settings' => 'assets/js/workflow-settings.js'
    ];
    
    foreach ($imports as $import_path => $actual_file) {
        if (strpos($blocks_content, "import '{$import_path}'") !== false || 
            strpos($blocks_content, "import.*from '{$import_path}'") !== false) {
            
            if (file_exists($plugin_dir . $actual_file)) {
                echo "✓ Import '{$import_path}' → {$actual_file} (file exists)\n";
            } else {
                echo "✗ Import '{$import_path}' → {$actual_file} (FILE MISSING)\n";
            }
        }
    }
} else {
    echo "✗ blocks.js file missing - cannot analyze imports\n";
}

echo "\n";

// 4. Block Registration Check
echo "4. BLOCK REGISTRATION CHECK\n";
echo "============================\n";

if (file_exists($plugin_dir . 'blocks/ai-text-generator/index.js')) {
    $block_content = file_get_contents($plugin_dir . 'blocks/ai-text-generator/index.js');
    
    if (strpos($block_content, 'registerBlockType') !== false) {
        echo "✓ Block uses registerBlockType function\n";
    } else {
        echo "✗ Block doesn't use registerBlockType\n";
    }
    
    if (strpos($block_content, 'wp-content-flow/ai-text') !== false) {
        echo "✓ Block has proper name 'wp-content-flow/ai-text'\n";
    } else {
        echo "✗ Block name not found or incorrect\n";
    }
    
    if (strpos($block_content, 'category:') !== false) {
        echo "✓ Block has category defined\n";
    } else {
        echo "✗ Block category missing\n";
    }
    
    // Check for required WordPress imports
    $required_imports = ['@wordpress/blocks', '@wordpress/block-editor', '@wordpress/components'];
    foreach ($required_imports as $import) {
        if (strpos($block_content, $import) !== false) {
            echo "✓ Imports from {$import}\n";
        } else {
            echo "✗ Missing import from {$import}\n";
        }
    }
} else {
    echo "✗ Block file missing - cannot check registration\n";
}

echo "\n";

// 5. CSS Files Check
echo "5. STYLESHEET FILES CHECK\n";
echo "==========================\n";

$css_files = [
    'assets/css/editor.css' => 'Block editor styles',
    'assets/css/frontend.css' => 'Frontend styles'
];

foreach ($css_files as $css_file => $description) {
    if (file_exists($plugin_dir . $css_file)) {
        $size = filesize($plugin_dir . $css_file);
        echo "✓ {$css_file} ({$description}) - {$size} bytes\n";
    } else {
        echo "✗ MISSING: {$css_file}\n";
    }
}

echo "\n";

// 6. API Endpoints Check
echo "6. API ENDPOINTS CHECK\n";
echo "=======================\n";

$api_files = [
    'includes/api/class-rest-api.php' => 'Main REST API class',
    'includes/api/class-ai-controller.php' => 'AI generation endpoints',
    'includes/api/class-workflows-controller.php' => 'Workflow management',
    'includes/api/class-suggestions-controller.php' => 'Content suggestions'
];

foreach ($api_files as $api_file => $description) {
    if (file_exists($plugin_dir . $api_file)) {
        echo "✓ {$api_file} ({$description})\n";
    } else {
        echo "✗ MISSING: {$api_file}\n";
    }
}

echo "\n";

// 7. Build Process Analysis
echo "7. BUILD PROCESS ANALYSIS\n";
echo "==========================\n";

// Check if there are source files that need building
$has_package_json = file_exists('/home/timl/dev/WP_ContentFlow/package.json');
$has_webpack = file_exists('/home/timl/dev/WP_ContentFlow/webpack.config.js');
$has_node_modules = is_dir('/home/timl/dev/WP_ContentFlow/node_modules');

if ($has_package_json) {
    echo "✓ package.json exists\n";
} else {
    echo "⚠ package.json not found - may not need build process\n";
}

if ($has_webpack) {
    echo "✓ webpack.config.js exists\n";
} else {
    echo "⚠ webpack.config.js not found\n";
}

if ($has_node_modules) {
    echo "✓ node_modules directory exists\n";
} else {
    echo "⚠ node_modules not found\n";
}

// Check if blocks.js is built or source
$blocks_js_size = file_exists($plugin_dir . 'assets/js/blocks.js') ? filesize($plugin_dir . 'assets/js/blocks.js') : 0;
if ($blocks_js_size < 1000) {
    echo "⚠ blocks.js is very small ({$blocks_js_size} bytes) - may need compilation\n";
} else {
    echo "✓ blocks.js has reasonable size ({$blocks_js_size} bytes)\n";
}

echo "\n";

// 8. WordPress Dependencies Check
echo "8. WORDPRESS DEPENDENCIES CHECK\n";
echo "=================================\n";

$wp_script_dependencies = [
    'wp-blocks' => 'Block registration',
    'wp-element' => 'React-like components',
    'wp-block-editor' => 'Block editor components',
    'wp-components' => 'UI components',
    'wp-data' => 'State management',
    'wp-i18n' => 'Internationalization',
    'wp-api-fetch' => 'API requests'
];

echo "Required WordPress script dependencies:\n";
foreach ($wp_script_dependencies as $handle => $purpose) {
    echo "  • {$handle} ({$purpose})\n";
}

echo "\n";

// 9. Potential Issues Summary
echo "9. POTENTIAL ISSUES SUMMARY\n";
echo "============================\n";

$issues = [];

if (count($missing_files) > 0) {
    $issues[] = "Missing required files: " . implode(', ', $missing_files);
}

if (!file_exists($plugin_dir . 'assets/css/editor.css')) {
    $issues[] = "Editor CSS file missing - blocks may not display correctly";
}

if ($blocks_js_size < 1000 && file_exists($plugin_dir . 'assets/js/blocks.js')) {
    $issues[] = "blocks.js file is very small - may not contain compiled code";
}

if (!strpos($main_plugin_content, 'wpContentFlow')) {
    $issues[] = "JavaScript configuration object not localized";
}

if (count($issues) > 0) {
    echo "IDENTIFIED ISSUES:\n";
    foreach ($issues as $i => $issue) {
        echo ($i + 1) . ". {$issue}\n";
    }
} else {
    echo "No critical issues identified in file structure.\n";
}

echo "\n";

// 10. Recommended Actions
echo "10. RECOMMENDED ACTIONS\n";
echo "========================\n";

echo "To fix Gutenberg blocks not appearing:\n\n";

echo "A. Missing Files:\n";
if (in_array('assets/css/editor.css', $missing_files)) {
    echo "   1. Create assets/css/editor.css with block styles\n";
}
if (in_array('assets/css/frontend.css', $missing_files)) {
    echo "   2. Create assets/css/frontend.css with frontend styles\n";
}

echo "\nB. Build Process:\n";
if ($blocks_js_size < 1000) {
    echo "   1. Check if blocks.js needs to be compiled from source\n";
    echo "   2. Run build command if package.json exists (npm run build)\n";
}

echo "\nC. WordPress Integration:\n";
echo "   1. Ensure plugin is activated in WordPress\n";
echo "   2. Clear any caching (object cache, page cache)\n";
echo "   3. Check browser console for JavaScript errors\n";
echo "   4. Verify REST API endpoints are accessible\n";

echo "\nD. Testing Steps:\n";
echo "   1. Activate plugin in WordPress admin\n";
echo "   2. Go to Posts > Add New\n";
echo "   3. Click + to add block\n";
echo "   4. Search for 'AI Text' or check 'Text' category\n";
echo "   5. Open browser dev tools to check for errors\n";

echo "\n=== END DIAGNOSTIC REPORT ===\n";

// Optional: Output as JSON for programmatic use
$diagnostic_data = [
    'timestamp' => date('c'),
    'existing_files' => $existing_files,
    'missing_files' => $missing_files,
    'issues' => $issues,
    'blocks_js_size' => $blocks_js_size,
    'has_build_process' => $has_package_json && $has_webpack
];

file_put_contents('/home/timl/dev/WP_ContentFlow/diagnostic_report.json', json_encode($diagnostic_data, JSON_PRETTY_PRINT));
echo "\nDiagnostic data saved to diagnostic_report.json\n";
?>