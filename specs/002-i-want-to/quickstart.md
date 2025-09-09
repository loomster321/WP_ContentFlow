# WordPress AI Content Workflow Plugin - Quickstart Guide

## Overview
This quickstart guide provides step-by-step instructions for setting up, testing, and using the WordPress AI Content Workflow Plugin. It serves as both user documentation and integration testing validation.

## Prerequisites

### System Requirements
- WordPress 6.0+ with Gutenberg block editor
- PHP 8.1+ with cURL extension
- MySQL 5.7+ or MariaDB 10.3+
- SSL certificate (required for AI API calls)

### Development Environment
- Node.js 16+ and npm 8+
- Composer 2.0+
- WordPress development environment (Local, XAMPP, or Docker)

## Installation

### 1. Plugin Installation
```bash
# Clone the plugin repository
git clone https://github.com/your-org/wp-content-flow.git
cd wp-content-flow

# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install

# Build assets
npm run build
```

### 2. WordPress Setup
1. Upload plugin folder to `/wp-content/plugins/wp-content-flow/`
2. Activate plugin in WordPress Admin → Plugins
3. Go to Settings → AI Content Flow to configure

### 3. AI Provider Configuration
1. Navigate to **Settings → AI Content Flow → API Keys**
2. Add your AI provider API keys:
   - OpenAI: Add API key from https://platform.openai.com/api-keys
   - Anthropic: Add API key from https://console.anthropic.com/
   - Google: Configure Google AI Studio credentials
3. Test API connection using the "Test Connection" button

## Basic Usage Workflow

### Step 1: Create Your First Workflow

1. **Access Workflow Management**
   - Go to **AI Workflows** in WordPress admin menu
   - Click "Add New Workflow"

2. **Configure Workflow Settings**
   ```
   Name: "Blog Post Assistant"
   Description: "Helps generate and improve blog post content"
   AI Provider: OpenAI
   Model: gpt-4
   ```

3. **Set AI Parameters**
   ```json
   {
     "temperature": 0.7,
     "max_tokens": 1500,
     "system_prompt": "You are a helpful content writing assistant for a WordPress blog."
   }
   ```

4. **Save and Activate** the workflow

### Step 2: Generate AI Content

1. **Create New Post**
   - Go to **Posts → Add New**
   - The Gutenberg editor will load with AI features

2. **Use AI Text Generator Block**
   - Click the "+" button to add a block
   - Search for "AI Text Generator"
   - Insert the block

3. **Generate Content**
   - Enter a prompt: "Write an introduction about sustainable gardening"
   - Select your "Blog Post Assistant" workflow
   - Click "Generate Content"
   - Wait for AI response (typically 2-5 seconds)

4. **Review and Accept**
   - Review the generated content
   - Check the confidence score
   - Click "Accept" to use the content or "Regenerate" to try again

### Step 3: Improve Existing Content

1. **Select Text to Improve**
   - Highlight any text in your post
   - Click the AI toolbar that appears

2. **Choose Improvement Type**
   - Grammar: Fix grammatical errors
   - Style: Improve writing style and tone
   - Clarity: Make content clearer and more concise
   - Engagement: Increase reader engagement
   - SEO: Optimize for search engines

3. **Apply Suggestions**
   - Review AI suggestions with confidence scores
   - Accept individual suggestions or all at once
   - Reject suggestions that don't fit your needs

### Step 4: Workflow Automation

1. **Set Up Automated Workflow**
   - Edit your workflow settings
   - Enable "Auto-run on save" option
   - Configure rules: "Run spell check before publishing"

2. **Test Automation**
   - Save a draft post with intentional errors
   - Check that AI automatically provides corrections
   - Verify suggestions appear in the AI panel

## Advanced Features

### Multi-User Collaboration

1. **Team Workflow Setup**
   - Create workflows with approval processes
   - Assign roles: Content Creator, Editor, Reviewer
   - Configure notification settings

2. **Content Review Process**
   - Content creators generate AI-assisted drafts
   - Editors receive AI improvement suggestions
   - Reviewers see complete change history

### Custom Workflow Templates

1. **Create Template**
   ```json
   {
     "name": "Product Description Generator",
     "category": "ecommerce",
     "ai_parameters": {
       "temperature": 0.5,
       "max_tokens": 500
     },
     "processing_steps": [
       "generate_features",
       "add_benefits", 
       "create_call_to_action"
     ]
   }
   ```

2. **Share Template**
   - Mark template as "Public" to share with team
   - Export template for use on other sites

### Performance Optimization

1. **Enable Caching**
   - Go to Settings → AI Content Flow → Performance
   - Enable "Cache AI Responses" (recommended: 30 minutes)
   - Enable object caching if available (Redis/Memcached)

2. **Configure Rate Limiting**
   ```
   Requests per minute: 10
   Requests per hour: 100
   Daily token limit: 50,000
   ```

## Testing Scenarios

### Functional Tests

1. **Content Generation Test**
   ```
   Given: User is on post editor with AI workflow configured
   When: User enters prompt "Write about climate change" and clicks generate
   Then: AI content appears within 5 seconds with confidence score > 0.7
   ```

2. **Content Improvement Test**
   ```
   Given: User has text with grammatical errors selected
   When: User clicks AI toolbar and selects "Grammar" improvement
   Then: Corrected suggestions appear with specific error highlights
   ```

3. **Workflow Automation Test**
   ```
   Given: Workflow set to auto-run spell check on save
   When: User saves post with spelling errors
   Then: AI suggestions appear automatically without user action
   ```

### Integration Tests

1. **WordPress Multisite Compatibility**
   - Test plugin activation across network sites
   - Verify isolated workflow configurations per site
   - Check user capability inheritance

2. **Third-Party Plugin Compatibility**
   - Test with popular SEO plugins (Yoast, RankMath)
   - Verify compatibility with page builders (Elementor, Beaver Builder)
   - Check conflict resolution with other AI plugins

3. **Performance Benchmarks**
   - Measure page load impact: < 100ms additional load time
   - Test concurrent AI requests: Handle 10+ simultaneous users
   - Validate cache effectiveness: 80%+ cache hit rate

### Security Tests

1. **API Key Security**
   ```
   Test: Verify API keys are encrypted in database
   Test: Confirm keys not exposed in frontend code
   Test: Validate secure transmission to AI providers
   ```

2. **User Permission Validation**
   ```
   Test: Users without edit_posts cannot access AI features
   Test: Workflow isolation between users works correctly
   Test: Admin-only settings protected from regular users
   ```

## Troubleshooting

### Common Issues

1. **"AI Provider Connection Failed"**
   - Check API key validity and format
   - Verify SSL certificate on WordPress site
   - Test network connectivity to AI provider

2. **"Content Generation Taking Too Long"**
   - Check AI provider service status
   - Reduce max_tokens parameter
   - Enable response caching

3. **"Suggestions Not Appearing"**
   - Verify workflow is active
   - Check user has edit_posts capability
   - Confirm block editor is properly loaded

### Debug Information

```bash
# Enable WordPress debug mode
wp config set WP_DEBUG true --type=constant
wp config set WP_DEBUG_LOG true --type=constant

# Check plugin logs
tail -f /wp-content/debug.log | grep "wp_content_flow"

# Verify database tables
wp db query "SHOW TABLES LIKE 'wp_ai_%'"
```

## Support and Resources

- **Documentation**: https://docs.wp-content-flow.com
- **GitHub Issues**: https://github.com/your-org/wp-content-flow/issues
- **Community Forum**: https://wordpress.org/support/plugin/wp-content-flow
- **API Reference**: https://docs.wp-content-flow.com/api/

## Success Criteria

✅ **Installation Success**: Plugin activates without errors, creates database tables  
✅ **Configuration Success**: AI provider connection test passes  
✅ **Basic Usage Success**: User can generate and improve content within 60 seconds  
✅ **Performance Success**: AI operations complete within 5 seconds  
✅ **Security Success**: All security tests pass, no sensitive data exposed  

This quickstart guide serves as both user onboarding and integration test validation. Each step should be verified during development to ensure the plugin meets all requirements.