# Create GitHub Issues (CGI) Command

This command creates well-structured GitHub issues for the WordPress AI Content Flow Plugin project, supporting feature requests, bug reports, and enhancement ideas. It automatically processes screenshots via Cloudinary CDN and follows WordPress plugin development best practices.

## Input
- Issue description (required)
- Screenshots (optional) - automatically detected and uploaded
- Issue type: feature/bug/enhancement (auto-detected or specified)

## Workflow

### 1. Context Analysis
- Analyze the issue within the WordPress AI Content Flow Plugin context
- Review project documentation including `CLAUDE.md` and WordPress standards
- Identify relevant technical components (WordPress core, Gutenberg blocks, AI providers, REST API)
- Check existing issues to avoid duplicates
- Consider WordPress plugin repository submission requirements

### 2. Issue Classification & Research
- **MANDATORY**: Classify as either "bug" OR "enhancement" (required for GitHub labeling)
  - **Bug**: Issues that break existing functionality, cause WordPress errors, plugin activation failures, AI provider failures, block editor issues, or produce incorrect results
  - **Enhancement**: New features, improvements to existing features, UI/UX improvements, performance optimizations, new AI providers, block editor enhancements
- For bugs: Research potential root causes in WordPress environment and affected components
- For features/enhancements: Align with WordPress plugin development standards and project architecture
- Identify priority level (P1-P4) based on project impact and WordPress compatibility

### 3. Issue Structure Creation
Generate comprehensive issue with WordPress plugin context:

**Title Format:**
- Bug: `[BUG] Brief description - Component (WordPress/Gutenberg/AI)`
- Enhancement: `[ENHANCEMENT] Brief description - Component (WordPress/Gutenberg/AI)`

**Content Sections:**
- **Summary**: Clear problem/feature description in WordPress context
- **WordPress Environment** (for bugs): WordPress version, PHP version, browser, theme, other plugins
- **Steps to Reproduce** (for bugs): Numbered steps in WordPress admin/frontend
- **Expected vs Actual Behavior** (for bugs)
- **Acceptance Criteria**: Measurable success conditions following WordPress standards
- **Technical Considerations**: WordPress architecture, hooks, filters, database considerations
- **Testing Requirements**: How to verify in WordPress environment (E2E tests, unit tests)
- **WordPress Compatibility**: Version compatibility and plugin requirements
- **Priority Justification**: Why this priority level for WordPress users

### 4. Project Integration & Labeling
- **REQUIRED LABELS**: Every issue MUST have exactly one of these labels:
  - Apply "bug" label for issues that break existing WordPress functionality
  - Apply "enhancement" label for new features, improvements, or optimizations
- **Additional Labels**: Apply WordPress-specific labels (wordpress, gutenberg, ai-providers, rest-api, admin-interface)
- **Milestone**: Link to relevant project phase
- **References**: Link related issues, PRs, WordPress documentation, or AI provider docs
- **Assignee**: Suggest based on component ownership (WordPress developer, AI specialist, etc.)

### 5. Quality Validation
Before creation, verify:
- Title is concise and searchable with WordPress context
- Description provides sufficient context for WordPress plugin implementation
- Acceptance criteria are testable in WordPress environment
- Screenshots are referenced properly (especially WordPress admin screenshots)
- Links to relevant WordPress/plugin code/docs are included
- WordPress coding standards compliance noted

### 6. Screenshot Processing & Upload
- **AUTOMATIC**: Process any image paths found in descriptions using Cloudinary CDN
- **Detection**: Automatically find image paths in format `/path/to/image.png` or `tmp/screenshot.png`
- **Upload**: Upload screenshots to Cloudinary for permanent, public access
- **Embedding**: Replace file paths with HTML img tags for immediate display
- **Validation**: Verify file existence before processing
- **WordPress Screenshots**: Special handling for WordPress admin interface screenshots

### 7. GitHub Integration
- Create issue using `gh` CLI with proper formatting
- **MANDATORY**: Apply either "bug" OR "enhancement" label during issue creation
- Apply additional WordPress-specific labels and milestone as needed
- Add to project board if applicable
- Provide issue URL for reference

### 8. Label Application Commands
Use these specific GitHub CLI commands to apply labels:

**For Bug Issues:**
```bash
gh issue create --title "[BUG] Title" --body "Description" --label "bug"
```

**For Enhancement Issues:**
```bash
gh issue create --title "[ENHANCEMENT] Title" --body "Description" --label "enhancement"
```

**For Multiple Labels (WordPress-specific):**
```bash
gh issue create --title "Title" --body "Description" --label "bug,wordpress,gutenberg,priority-high"
```

## Git Repository Screenshot Integration

### Automatic Screenshot Processing
The CGI command includes **automatic Git repository integration** for screenshot processing:

```bash
# Git Repository Screenshot Organization Function
organize_screenshot() {
    local image_path="$1"
    local issue_type="$2"  # issues, features, bugs
    
    echo "📁 Organizing screenshot: $image_path" >&2
    
    # Validate file exists
    if [[ ! -f "$image_path" ]]; then
        echo "❌ File not found: $image_path" >&2
        return 1
    fi
    
    # Create organized path structure
    local date_dir=$(date +%Y-%m-%d)
    local screenshot_dir="docs/screenshots/${issue_type}/${date_dir}"
    local filename=$(basename "$image_path")
    local organized_path="${screenshot_dir}/${filename}"
    
    # Create directory if it doesn't exist
    mkdir -p "$screenshot_dir"
    
    # Copy screenshot to organized location
    cp "$image_path" "$organized_path"
    
    # Add to git
    git add "$organized_path" >/dev/null 2>&1
    
    echo "✅ Organized: $image_path → $organized_path" >&2
    echo "$organized_path"
    
    return 0
}

# Process screenshots in CGI description using Git repository
process_git_screenshots() {
    local description="$1"
    local issue_type="${2:-issues}"  # default to issues
    local processed_description="$description"
    
    # Find image file paths (handle various formats)
    local image_paths=$(echo "$description" | grep -oE 'tmp/[^[:space:]]*\.(png|jpg|jpeg|gif|webp)|/[^[:space:]]*\.(png|jpg|jpeg|gif|webp)' | head -10 || true)
    
    if [[ -n "$image_paths" ]]; then
        echo "📸 Found WordPress screenshots to process:" >&2
        echo "$image_paths" >&2
        
        local repo_url=$(get_github_repo_url)
        
        # Process each image
        while IFS= read -r image_path; do
            if [[ -n "$image_path" && -f "$image_path" ]]; then
                echo "Processing WordPress screenshot: $image_path" >&2
                
                # Organize screenshot in repository
                local organized_path=$(organize_screenshot "$image_path" "$issue_type")
                
                if [[ $? -eq 0 && -n "$organized_path" ]]; then
                    # Create GitHub repository URL for the image
                    local github_image_url="${repo_url}/blob/main/${organized_path}"
                    
                    # Create markdown image link for GitHub display
                    local img_markdown="![WordPress Screenshot](${organized_path})"
                    
                    # Replace path with GitHub repository image link
                    processed_description=$(echo "$processed_description" | sed "s|${image_path}|${img_markdown}|g")
                    echo "✅ Replaced: $image_path → GitHub repository link" >&2
                else
                    echo "⚠️  Organization failed, keeping original path: $image_path" >&2
                fi
            else
                echo "⚠️  File not found: $image_path" >&2
            fi
        done <<< "$image_paths"
        
        # Commit all organized screenshots
        commit_screenshots "$description"
        
    else
        echo "ℹ️  No screenshots found in description" >&2
    fi
    
    echo "$processed_description"
}
```

### Repository Organization Structure
Screenshots are automatically organized in the repository using this structure:

```
docs/screenshots/
├── issues/           # General issue screenshots
│   └── 2025-09-10/
│       ├── settings-error.png
│       └── api-validation.png
├── features/         # Feature demo screenshots
│   └── 2025-09-10/
│       ├── new-block-demo.png
│       └── ui-mockup.png
└── bugs/            # Bug reproduction screenshots
    └── 2025-09-10/
        ├── crash-report.png
        └── error-log.png
```

### Implementation Requirements
- **Automatic Detection**: Finds image paths like `/path/to/screenshot.png` or `tmp/screenshot.png` in descriptions
- **Git Repository Storage**: Uses version-controlled storage with organized structure
- **GitHub Native Hosting**: GitHub automatically hosts repository images
- **Automatic Commits**: Screenshots are committed with descriptive messages
- **WordPress Context**: Optimized for WordPress admin interface screenshots
- **Zero External Dependencies**: No API calls or authentication required
- **Intelligent Categorization**: Automatically detects issue type and organizes accordingly

## Output
- GitHub issue URL with confirmation of applied labels
- Summary of created issue with key details including classification (bug/enhancement)
- Applied labels listing with WordPress-specific context
- Suggested next steps for the assignee (WordPress developer, AI specialist, etc.)

## WordPress-Specific Classification Guidelines

### What Makes Something a "Bug" in WordPress Context
- WordPress plugin activation/deactivation failures
- Fatal PHP errors in WordPress environment
- Gutenberg block editor crashes or errors
- AI provider API failures or incorrect responses
- WordPress admin interface not rendering properly
- REST API endpoints returning errors
- Database schema creation/migration failures
- WordPress hooks/filters not working correctly
- Settings page save functionality broken
- Plugin conflicts with WordPress core or other plugins
- Security vulnerabilities in WordPress context

### What Makes Something an "Enhancement" in WordPress Context
- New Gutenberg blocks or block variations
- Additional AI provider integrations
- New WordPress admin interface features
- Improved user experience in WordPress admin
- Performance optimizations for WordPress environment
- New REST API endpoints
- Enhanced settings pages or configuration options
- Better WordPress coding standards compliance
- Accessibility improvements for WordPress admin
- New database table features or optimizations
- Documentation improvements for WordPress developers
- WordPress multisite support enhancements

## WordPress Plugin Development Context
- All issues should consider WordPress coding standards and best practices
- Issues should account for WordPress version compatibility (6.0+)
- PHP version requirements (8.1+) should be noted
- Plugin repository submission standards should be followed
- WordPress security guidelines must be considered
- Gutenberg block development standards apply to block-related issues
- REST API issues should follow WordPress REST API conventions