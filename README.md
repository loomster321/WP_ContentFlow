# WP Content Flow

WordPress AI Content Workflow System with Multi-Agent Architecture

## Overview

WP Content Flow is a comprehensive WordPress plugin that integrates AI capabilities directly into the Gutenberg editor. It features a multi-agent architecture with specialized AI agents for content creation, layout design, stock art curation, and AI art generation, all powered by RAG knowledge management for brand consistency.

## Architecture

```
┌─────────────────────┐    ┌─────────────────────┐    ┌─────────────────────┐
│   WordPress Plugin  │    │    Cloud API        │    │    Dashboard        │
│   (Gutenberg UI)    │◄──►│  (Multi-Agent       │◄──►│  (Management UI)    │
│                     │    │   Orchestration)    │    │                     │
└─────────────────────┘    └─────────────────────┘    └─────────────────────┘
```

### Core Components

- **WordPress Plugin**: Gutenberg editor integration with AI Chat panel
- **Cloud API**: Multi-agent orchestration and AI provider management  
- **Dashboard**: System management and analytics interface
- **4 Specialized AI Agents**:
  - Content Agent: Copywriting and content generation
  - Layout Agent: UX/UI design suggestions
  - Stock Art Agent: Image curation and selection
  - AI Art Generation Agent: Custom image creation

## Quick Start

### Prerequisites

- Docker & Docker Compose
- Node.js 18+
- WordPress 6.0+
- PHP 8.1+

### Development Setup

1. **Clone and setup environment:**
   ```bash
   git clone <repository-url>
   cd WP_ContentFlow
   ./scripts/dev-setup.sh
   ```

2. **Configure API keys:**
   Edit `.env` files and add your AI provider API keys:
   ```bash
   OPENAI_API_KEY=your_key_here
   ANTHROPIC_API_KEY=your_key_here
   GOOGLE_AI_API_KEY=your_key_here
   ```

3. **Start development servers:**
   ```bash
   npm run dev
   ```

4. **Access services:**
   - WordPress: http://localhost:8080
   - Dashboard: http://localhost:3000
   - Cloud API: http://localhost:3001

## Project Structure

```
packages/
├── shared-types/         # TypeScript type definitions
├── cloud-api/           # Node.js API service
│   ├── src/agents/      # AI agent implementations
│   ├── src/services/    # Core services (RAG, Queue, etc.)
│   └── src/routes/      # API endpoints
├── wordpress-plugin/    # WordPress plugin
│   ├── includes/        # PHP backend logic
│   ├── assets/js/       # Gutenberg block scripts
│   └── blocks/          # Custom blocks
└── dashboard/           # Next.js management interface
    ├── src/components/  # React components
    └── src/pages/       # Dashboard pages
```

## Key Features

### WordPress Integration
- **AI Chat Panel**: Sidebar panel in Gutenberg editor
- **Custom Blocks**: AI Text Generator, Content Enhancer
- **Block Targeting**: Select and modify specific blocks
- **Real-time Suggestions**: Inline AI-powered improvements

### Multi-Agent System
- **Agent Coordination**: Orchestrated workflow between specialists
- **RAG Knowledge**: Brand voice, writing guidelines, visual standards
- **Queue Management**: Redis-based job processing
- **Human-in-the-loop**: Approval workflows for AI suggestions

### Cloud Architecture
- **Scalable API**: Node.js with TypeScript
- **Database**: PostgreSQL for cloud data, MySQL for WordPress
- **Caching**: Redis for performance optimization
- **Monitoring**: Comprehensive logging and metrics

## Development Commands

```bash
# Start all services
npm run dev

# Individual services
npm run dev:api        # Cloud API only
npm run dev:dashboard  # Dashboard only  
npm run dev:plugin     # WordPress plugin assets only

# Build for production
npm run build

# Run tests
npm run test

# Docker commands
npm run docker:up      # Start containers
npm run docker:down    # Stop containers
npm run docker:build   # Rebuild containers
```

## API Documentation

The Cloud API provides REST endpoints for AI operations:

- `POST /api/ai/generate` - Generate new content
- `POST /api/ai/improve` - Improve existing content
- `GET /api/workflows` - List available workflows
- `POST /api/suggestions/{id}/accept` - Accept AI suggestion

Full API documentation available at: http://localhost:3001/docs

## WordPress Plugin Features

### Gutenberg Blocks

1. **AI Text Generator Block**
   - Generate content from prompts
   - Select from multiple AI providers
   - Confidence scoring
   - Workflow selection

2. **AI Content Enhancer Block**
   - Improve existing content
   - Grammar, style, clarity enhancements
   - SEO optimization
   - Engagement improvements

### Admin Features

- **Workflow Management**: Create and configure AI workflows
- **API Configuration**: Manage AI provider settings
- **User Permissions**: Role-based access control
- **Analytics Dashboard**: Usage metrics and performance

## Testing

The system includes comprehensive testing:

```bash
# WordPress plugin tests
cd packages/wordpress-plugin
npm run test

# Cloud API tests  
cd packages/cloud-api
npm run test

# Dashboard tests
cd packages/dashboard
npm run test

# End-to-end tests
npm run test:e2e
```

## Configuration

### WordPress Plugin Configuration

Navigate to **Settings → AI Content Flow** in WordPress admin:

1. **API Keys**: Configure AI provider credentials
2. **Workflows**: Set up content generation workflows
3. **User Permissions**: Manage access controls
4. **Performance**: Configure caching and rate limiting

### Cloud API Configuration

Environment variables in `packages/cloud-api/.env`:

- Database and Redis connections
- AI provider API keys
- Queue processing settings
- Security configurations

## Deployment

### Production Build

```bash
npm run build
npm run docker:build
```

### Environment Variables

Required environment variables for production:

- `OPENAI_API_KEY`: OpenAI API access
- `ANTHROPIC_API_KEY`: Anthropic Claude access  
- `GOOGLE_AI_API_KEY`: Google AI access
- `JWT_SECRET`: JWT signing secret
- `DB_*`: Database connection settings
- `REDIS_URL`: Redis connection string

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/new-feature`
3. Make your changes following the coding standards
4. Add tests for new functionality
5. Submit a pull request

### Coding Standards

- **PHP**: WordPress coding standards
- **JavaScript**: ESLint with WordPress config
- **TypeScript**: Strict type checking enabled
- **Testing**: PHPUnit for PHP, Jest for JavaScript/TypeScript

## Support

- **Documentation**: [Full documentation](https://docs.wp-content-flow.com)
- **Issues**: [GitHub Issues](https://github.com/your-org/wp-content-flow/issues)
- **Community**: [WordPress.org Support Forum](https://wordpress.org/support/plugin/wp-content-flow)

## License

GPL v2 or later. See [LICENSE](LICENSE) for details.

## Changelog

### Version 1.0.0
- Initial release
- Multi-agent AI architecture
- WordPress Gutenberg integration
- RAG knowledge management
- Cloud API with queue processing
- Management dashboard