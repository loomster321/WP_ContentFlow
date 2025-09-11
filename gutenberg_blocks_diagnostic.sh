#!/bin/bash

echo "=== WP Content Flow - Gutenberg Blocks Diagnostic Report ==="
echo "Generated: $(date)"
echo ""

# 1. Plugin File Structure Analysis
echo "1. PLUGIN FILE STRUCTURE ANALYSIS"
echo "================================="

PLUGIN_DIR="/home/timl/dev/WP_ContentFlow/wp-content-flow/"
MISSING_COUNT=0
EXISTING_COUNT=0

# Check required files
declare -A required_files=(
    ["wp-content-flow.php"]="Main plugin file"
    ["assets/js/blocks.js"]="Main block registration file"
    ["blocks/ai-text-generator/index.js"]="AI Text Generator block"
    ["assets/js/workflow-data-store.js"]="WordPress data store"
    ["assets/js/improvement-toolbar.js"]="Content improvement toolbar"
    ["assets/js/workflow-settings.js"]="Workflow settings panel"
    ["assets/css/editor.css"]="Block editor styles"
    ["assets/css/frontend.css"]="Frontend styles"
)

for file in "${!required_files[@]}"; do
    full_path="${PLUGIN_DIR}${file}"
    if [[ -f "$full_path" ]]; then
        size=$(stat -c%s "$full_path")
        echo "✓ ${file} (${required_files[$file]}) - ${size} bytes"
        ((EXISTING_COUNT++))
    else
        echo "✗ MISSING: ${file} (${required_files[$file]})"
        ((MISSING_COUNT++))
    fi
done

echo ""
echo "Summary: ${EXISTING_COUNT} files exist, ${MISSING_COUNT} files missing"
echo ""

# 2. Check CSS files specifically
echo "2. CSS FILES CHECK"
echo "=================="

if [[ -f "${PLUGIN_DIR}assets/css/editor.css" ]]; then
    size=$(stat -c%s "${PLUGIN_DIR}assets/css/editor.css")
    echo "✓ editor.css exists - ${size} bytes"
else
    echo "✗ CRITICAL: editor.css MISSING - blocks will not have styles"
fi

if [[ -f "${PLUGIN_DIR}assets/css/frontend.css" ]]; then
    size=$(stat -c%s "${PLUGIN_DIR}assets/css/frontend.css")
    echo "✓ frontend.css exists - ${size} bytes"
else
    echo "✗ CRITICAL: frontend.css MISSING - frontend display will be unstyled"
fi

echo ""

# 3. Check blocks.js content and size
echo "3. BLOCKS.JS ANALYSIS"
echo "====================="

if [[ -f "${PLUGIN_DIR}assets/js/blocks.js" ]]; then
    size=$(stat -c%s "${PLUGIN_DIR}assets/js/blocks.js")
    echo "blocks.js size: ${size} bytes"
    
    if [[ $size -lt 1000 ]]; then
        echo "⚠ WARNING: blocks.js is very small (${size} bytes)"
        echo "   This suggests it may contain only imports, not compiled code"
    else
        echo "✓ blocks.js has reasonable size"
    fi
    
    echo ""
    echo "blocks.js content:"
    cat "${PLUGIN_DIR}assets/js/blocks.js"
    echo ""
else
    echo "✗ CRITICAL: blocks.js missing"
fi

echo ""

# 4. Check if build process is needed
echo "4. BUILD PROCESS CHECK"
echo "======================"

if [[ -f "/home/timl/dev/WP_ContentFlow/package.json" ]]; then
    echo "✓ package.json exists"
    if [[ -f "/home/timl/dev/WP_ContentFlow/webpack.config.js" ]]; then
        echo "✓ webpack.config.js exists"
        echo "⚠ This suggests blocks.js needs to be built from source"
    fi
    if [[ -d "/home/timl/dev/WP_ContentFlow/node_modules" ]]; then
        echo "✓ node_modules exists"
    else
        echo "⚠ node_modules missing - run 'npm install'"
    fi
else
    echo "ℹ No package.json found - may not need build process"
fi

echo ""

# 5. Check WordPress enqueuing
echo "5. WORDPRESS ENQUEUING CHECK"
echo "============================"

if [[ -f "${PLUGIN_DIR}wp-content-flow.php" ]]; then
    if grep -q "enqueue_block_editor_assets" "${PLUGIN_DIR}wp-content-flow.php"; then
        echo "✓ Block editor assets enqueue function exists"
    else
        echo "✗ Block editor assets enqueue function missing"
    fi
    
    if grep -q "wp-content-flow-blocks" "${PLUGIN_DIR}wp-content-flow.php"; then
        echo "✓ blocks.js is enqueued with correct handle"
    else
        echo "✗ blocks.js enqueue handle not found"
    fi
    
    if grep -q "wpContentFlow" "${PLUGIN_DIR}wp-content-flow.php"; then
        echo "✓ JavaScript configuration object is localized"
    else
        echo "✗ JavaScript configuration object missing"
    fi
fi

echo ""

# 6. Directory structure overview
echo "6. DIRECTORY STRUCTURE"
echo "======================"
echo "Plugin directory structure:"
find "$PLUGIN_DIR" -type f -name "*.js" -o -name "*.css" -o -name "*.php" | head -20

echo ""

# 7. Critical Issues Summary
echo "7. CRITICAL ISSUES SUMMARY"
echo "=========================="

ISSUES=()

if [[ ! -f "${PLUGIN_DIR}assets/css/editor.css" ]]; then
    ISSUES+=("CRITICAL: editor.css missing - blocks won't have proper styles")
fi

if [[ ! -f "${PLUGIN_DIR}assets/css/frontend.css" ]]; then
    ISSUES+=("CRITICAL: frontend.css missing - saved blocks won't display properly")
fi

if [[ -f "${PLUGIN_DIR}assets/js/blocks.js" ]]; then
    size=$(stat -c%s "${PLUGIN_DIR}assets/js/blocks.js")
    if [[ $size -lt 1000 ]]; then
        ISSUES+=("WARNING: blocks.js is very small (${size} bytes) - may need compilation")
    fi
else
    ISSUES+=("CRITICAL: blocks.js missing")
fi

if [[ ${#ISSUES[@]} -eq 0 ]]; then
    echo "No critical issues found in file structure!"
else
    echo "Issues found:"
    for i in "${!ISSUES[@]}"; do
        echo "$((i+1)). ${ISSUES[$i]}"
    done
fi

echo ""

# 8. Recommended Actions
echo "8. RECOMMENDED ACTIONS"
echo "======================"

echo "To fix Gutenberg blocks not appearing:"
echo ""

if [[ ! -f "${PLUGIN_DIR}assets/css/editor.css" ]]; then
    echo "1. CREATE editor.css:"
    echo "   File: ${PLUGIN_DIR}assets/css/editor.css"
    echo "   Should contain: Block editor styles for wp-content-flow blocks"
    echo ""
fi

if [[ ! -f "${PLUGIN_DIR}assets/css/frontend.css" ]]; then
    echo "2. CREATE frontend.css:"
    echo "   File: ${PLUGIN_DIR}assets/css/frontend.css"
    echo "   Should contain: Frontend styles for saved blocks"
    echo ""
fi

echo "3. IMMEDIATE TESTING STEPS:"
echo "   a. Create missing CSS files (even if empty initially)"
echo "   b. Activate plugin in WordPress"
echo "   c. Go to Posts > Add New in WordPress admin"
echo "   d. Click + button to add block"
echo "   e. Search for 'AI Text' or check 'Text' category"
echo "   f. Open browser dev tools console to check for errors"
echo ""

echo "4. IF BLOCKS STILL DON'T APPEAR:"
echo "   a. Check browser console for JavaScript errors"
echo "   b. Verify REST API endpoints work: /wp-json/wp-content-flow/v1/"
echo "   c. Clear WordPress object cache if using caching"
echo "   d. Try different WordPress theme to rule out theme conflicts"
echo ""

echo "=== END DIAGNOSTIC REPORT ==="