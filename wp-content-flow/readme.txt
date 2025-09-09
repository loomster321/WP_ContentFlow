=== WordPress AI Content Flow ===
Contributors: wpcontentflowteam
Tags: ai, content, workflow, gutenberg, automation, openai, claude
Requires at least: 6.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered content workflow plugin that integrates multiple AI providers directly into the Gutenberg block editor for content generation, editing suggestions, and workflow automation.

== Description ==

WordPress AI Content Flow Plugin transforms your content creation process by integrating advanced AI capabilities directly into the WordPress block editor. Generate high-quality content, get intelligent editing suggestions, and automate your content workflows without leaving WordPress.

= Key Features =

* **AI Text Generator Block**: Generate content from prompts using OpenAI, Anthropic Claude, or Google AI
* **Inline Content Improvement**: Get AI suggestions for grammar, style, clarity, engagement, and SEO
* **Custom Workflow Automation**: Create automated content review and approval processes
* **Multi-AI Provider Support**: Switch between OpenAI, Anthropic, Google, and Azure AI services
* **Content History Tracking**: Complete change tracking with AI-assisted revision history
* **Team Collaboration**: Multi-user workflows with role-based permissions
* **Performance Optimized**: Caching and rate limiting for optimal performance
* **WordPress Integration**: Native integration with Gutenberg blocks and WordPress REST API

= Supported AI Providers =

* OpenAI (GPT-4, GPT-3.5)
* Anthropic Claude
* Google AI (Gemini)
* Azure OpenAI

= Use Cases =

* Blog post creation and enhancement
* Product description generation
* Social media content optimization
* Technical documentation assistance
* Creative writing support
* Content translation and localization

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wp-content-flow/` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to 'Settings > AI Content Flow' to configure your AI provider API keys
4. Create your first workflow and start using AI-powered content features in the block editor

== Frequently Asked Questions ==

= Which AI providers are supported? =

The plugin supports OpenAI, Anthropic Claude, Google AI, and Azure OpenAI. You can configure multiple providers and switch between them as needed.

= Do I need API keys for AI providers? =

Yes, you'll need API keys from your chosen AI providers. The plugin securely encrypts and stores these keys in your WordPress database.

= Is this compatible with multisite installations? =

Yes, the plugin is fully compatible with WordPress multisite installations with isolated configurations per site.

= How does the caching work? =

The plugin includes intelligent caching for AI responses to improve performance and reduce API costs. Cache duration is configurable in settings.

= Can I use this with page builders? =

The plugin is designed for the Gutenberg block editor. Compatibility with page builders like Elementor and Beaver Builder is planned for future releases.

== Screenshots ==

1. AI Text Generator block in the Gutenberg editor
2. Content improvement toolbar with AI suggestions
3. Workflow management interface
4. AI provider settings page
5. Content history with AI-assisted changes

== Changelog ==

= 1.0.0 =
* Initial release
* AI Text Generator block
* Content improvement suggestions
* Workflow automation system
* Multi-AI provider support
* REST API endpoints
* Performance optimization with caching and rate limiting
* WordPress multisite compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of WordPress AI Content Flow Plugin. Please configure your AI provider API keys after installation.

== Privacy Policy ==

This plugin sends content to third-party AI services for processing. Please review the privacy policies of your chosen AI providers:

* OpenAI: https://openai.com/privacy/
* Anthropic: https://www.anthropic.com/privacy
* Google AI: https://policies.google.com/privacy

The plugin does not store content on external servers beyond the AI processing requests. All content and settings remain in your WordPress database.

== Support ==

For support, documentation, and feature requests, please visit:
* GitHub: https://github.com/your-org/wp-content-flow
* Documentation: https://docs.wp-content-flow.com
* WordPress Support Forum: https://wordpress.org/support/plugin/wp-content-flow