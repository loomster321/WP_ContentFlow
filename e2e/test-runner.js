#!/usr/bin/env node

/**
 * Comprehensive E2E Test Runner for WordPress AI Content Flow Plugin
 * 
 * Provides different test execution modes and environments
 * Supports parallel execution, test filtering, and detailed reporting
 */

const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

// Test configurations
const TEST_CONFIGS = {
  smoke: {
    name: 'Smoke Tests',
    pattern: 'smoke-tests/**/*.spec.js',
    parallel: false,
    timeout: 30000,
    retries: 2
  },
  
  admin: {
    name: 'Admin Workflow Tests',
    pattern: 'admin-workflows/**/*.spec.js',
    parallel: true,
    timeout: 45000,
    retries: 1
  },
  
  blocks: {
    name: 'Block Editor Tests',
    pattern: 'block-editor/**/*.spec.js',
    parallel: true,
    timeout: 60000,
    retries: 1
  },
  
  workflows: {
    name: 'Content Workflow Tests',
    pattern: 'content-workflows/**/*.spec.js',
    parallel: true,
    timeout: 90000,
    retries: 1
  },
  
  api: {
    name: 'API Integration Tests',
    pattern: 'api-integration/**/*.spec.js',
    parallel: true,
    timeout: 30000,
    retries: 2
  },
  
  full: {
    name: 'Full Test Suite',
    pattern: '**/*.spec.js',
    parallel: true,
    timeout: 120000,
    retries: 1
  }
};

// Environment configurations
const ENVIRONMENTS = {
  local: {
    baseURL: 'http://localhost:8080',
    dockerCompose: 'docker-compose.yml'
  },
  
  ci: {
    baseURL: process.env.WORDPRESS_URL || 'http://localhost:8080',
    dockerCompose: 'docker-compose.ci.yml',
    headless: true,
    workers: 2
  },
  
  staging: {
    baseURL: process.env.STAGING_URL || 'https://staging.wpcontentflow.com',
    headless: true,
    workers: 1
  }
};

class E2ETestRunner {
  constructor() {
    this.args = process.argv.slice(2);
    this.config = this.parseArguments();
    this.results = {
      passed: 0,
      failed: 0,
      skipped: 0,
      duration: 0,
      failures: []
    };
  }

  parseArguments() {
    const config = {
      suite: 'smoke',
      environment: 'local',
      headed: false,
      debug: false,
      verbose: false,
      filter: null,
      workers: null,
      updateSnapshots: false
    };

    for (let i = 0; i < this.args.length; i++) {
      const arg = this.args[i];
      
      switch (arg) {
        case '--suite':
        case '-s':
          config.suite = this.args[++i];
          break;
          
        case '--environment':
        case '--env':
        case '-e':
          config.environment = this.args[++i];
          break;
          
        case '--headed':
        case '-h':
          config.headed = true;
          break;
          
        case '--debug':
        case '-d':
          config.debug = true;
          config.headed = true;
          break;
          
        case '--verbose':
        case '-v':
          config.verbose = true;
          break;
          
        case '--filter':
        case '-f':
          config.filter = this.args[++i];
          break;
          
        case '--workers':
        case '-w':
          config.workers = parseInt(this.args[++i]);
          break;
          
        case '--update-snapshots':
        case '-u':
          config.updateSnapshots = true;
          break;
          
        case '--help':
          this.showHelp();
          process.exit(0);
          break;
          
        default:
          if (!arg.startsWith('-')) {
            config.suite = arg;
          }
          break;
      }
    }

    return config;
  }

  showHelp() {
    console.log(`
WordPress AI Content Flow Plugin - E2E Test Runner

Usage: node test-runner.js [options]

Test Suites:
  smoke       Quick smoke tests (default)
  admin       Admin workflow tests
  blocks      Block editor tests  
  workflows   Content workflow tests
  api         API integration tests
  full        Complete test suite

Options:
  -s, --suite <name>         Test suite to run
  -e, --env <name>          Environment (local, ci, staging)
  -h, --headed              Run tests in headed mode
  -d, --debug               Debug mode (headed + verbose)
  -v, --verbose             Verbose output
  -f, --filter <pattern>    Filter tests by name pattern
  -w, --workers <number>    Number of parallel workers
  -u, --update-snapshots    Update visual snapshots

Examples:
  node test-runner.js smoke
  node test-runner.js --suite admin --headed
  node test-runner.js full --env ci --workers 4
  node test-runner.js --filter "workflow creation"
`);
  }

  async run() {
    try {
      console.log(`🚀 Starting E2E Test Runner`);
      console.log(`📋 Suite: ${this.config.suite}`);
      console.log(`🌍 Environment: ${this.config.environment}`);
      console.log(`⚙️  Configuration: ${this.config.headed ? 'headed' : 'headless'}${this.config.debug ? ' (debug)' : ''}`);
      
      // Validate configuration
      await this.validateConfig();
      
      // Setup environment
      await this.setupEnvironment();
      
      // Run tests
      const startTime = Date.now();
      await this.executeTests();
      this.results.duration = Date.now() - startTime;
      
      // Generate reports
      await this.generateReports();
      
      // Show results
      this.showResults();
      
      // Exit with appropriate code
      process.exit(this.results.failed > 0 ? 1 : 0);
      
    } catch (error) {
      console.error('❌ Test runner failed:', error);
      process.exit(1);
    }
  }

  async validateConfig() {
    const testConfig = TEST_CONFIGS[this.config.suite];
    if (!testConfig) {
      throw new Error(`Unknown test suite: ${this.config.suite}. Available: ${Object.keys(TEST_CONFIGS).join(', ')}`);
    }

    const envConfig = ENVIRONMENTS[this.config.environment];
    if (!envConfig) {
      throw new Error(`Unknown environment: ${this.config.environment}. Available: ${Object.keys(ENVIRONMENTS).join(', ')}`);
    }

    // Check if test files exist
    const testDir = path.join(__dirname);
    const testPattern = testConfig.pattern;
    
    // Simple pattern matching using fs instead of glob
    const findTestFiles = (dir, pattern) => {
      try {
        const files = [];
        const items = fs.readdirSync(dir, { withFileTypes: true });
        
        for (const item of items) {
          const fullPath = path.join(dir, item.name);
          if (item.isDirectory()) {
            files.push(...findTestFiles(fullPath, pattern));
          } else if (item.name.endsWith('.spec.js')) {
            files.push(fullPath);
          }
        }
        return files;
      } catch (error) {
        return [];
      }
    };
    
    const testFiles = findTestFiles(testDir, testPattern);
    
    if (testFiles.length === 0) {
      throw new Error(`No test files found matching pattern: ${testPattern}`);
    }
    
    console.log(`✅ Found ${testFiles.length} test files`);
  }

  async setupEnvironment() {
    const envConfig = ENVIRONMENTS[this.config.environment];
    
    if (this.config.environment === 'local') {
      // Check if Docker containers are running
      console.log('🐳 Checking Docker environment...');
      
      const { execSync } = require('child_process');
      try {
        const containers = execSync('docker compose ps -q wordpress', { encoding: 'utf8' });
        if (!containers.trim()) {
          console.log('🚀 Starting Docker containers...');
          execSync('docker compose up -d wordpress mysql phpmyadmin', { stdio: 'inherit' });
          
          // Wait for WordPress to be ready
          console.log('⏳ Waiting for WordPress to be ready...');
          await this.waitForWordPress(envConfig.baseURL);
        } else {
          console.log('✅ Docker containers already running');
        }
      } catch (error) {
        throw new Error('Failed to setup Docker environment: ' + error.message);
      }
    }
  }

  async waitForWordPress(baseURL, maxAttempts = 30) {
    const http = require('http');
    const https = require('https');
    const httpModule = baseURL.startsWith('https') ? https : http;
    
    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
      try {
        await new Promise((resolve, reject) => {
          const req = httpModule.get(baseURL, (res) => {
            resolve(res.statusCode);
          });
          
          req.on('error', reject);
          req.setTimeout(5000, () => reject(new Error('Timeout')));
        });
        
        console.log(`✅ WordPress is ready (attempt ${attempt})`);
        return;
        
      } catch (error) {
        if (attempt === maxAttempts) {
          throw new Error(`WordPress not ready after ${maxAttempts} attempts`);
        }
        
        console.log(`⏳ Waiting for WordPress... (attempt ${attempt})`);
        await new Promise(resolve => setTimeout(resolve, 2000));
      }
    }
  }

  async executeTests() {
    const testConfig = TEST_CONFIGS[this.config.suite];
    const envConfig = ENVIRONMENTS[this.config.environment];
    
    // Build Playwright command
    const playwrightArgs = [
      'test',
      testConfig.pattern,
      `--timeout=${testConfig.timeout}`,
      `--retries=${testConfig.retries}`,
      '--reporter=list,html,json'
    ];

    // Environment-specific options (headless is default, no need to specify)

    if (this.config.headed || this.config.debug) {
      playwrightArgs.push('--headed');
    }

    if (this.config.debug) {
      playwrightArgs.push('--debug');
      playwrightArgs.push('--workers=1');
    } else if (testConfig.parallel && !this.config.workers) {
      playwrightArgs.push(`--workers=${envConfig.workers || 4}`);
    } else if (this.config.workers) {
      playwrightArgs.push(`--workers=${this.config.workers}`);
    }

    if (this.config.filter) {
      playwrightArgs.push(`--grep="${this.config.filter}"`);
    }

    if (this.config.updateSnapshots) {
      playwrightArgs.push('--update-snapshots');
    }

    // Set environment variables
    const env = {
      ...process.env,
      BASE_URL: envConfig.baseURL,
      TEST_SUITE: this.config.suite,
      TEST_ENV: this.config.environment
    };

    console.log(`🧪 Running tests: ${testConfig.name}`);
    
    if (this.config.verbose) {
      console.log(`🔧 Command: npx playwright ${playwrightArgs.join(' ')}`);
    }

    // Execute Playwright
    return new Promise((resolve, reject) => {
      const playwright = spawn('npx', ['playwright', ...playwrightArgs], {
        stdio: this.config.verbose ? 'inherit' : 'pipe',
        env,
        cwd: path.join(__dirname, '..')
      });

      let output = '';
      let errorOutput = '';

      if (!this.config.verbose) {
        playwright.stdout.on('data', (data) => {
          output += data.toString();
          process.stdout.write('.');
        });

        playwright.stderr.on('data', (data) => {
          errorOutput += data.toString();
        });
      }

      playwright.on('close', (code) => {
        if (!this.config.verbose) {
          console.log(''); // New line after progress dots
        }

        if (code === 0) {
          console.log('✅ Tests completed successfully');
          this.parseTestResults(output);
          resolve();
        } else {
          console.error(`❌ Tests failed with exit code ${code}`);
          if (errorOutput) {
            console.error('Error output:', errorOutput);
          }
          this.parseTestResults(output);
          resolve(); // Don't reject, let results be processed
        }
      });

      playwright.on('error', (error) => {
        console.error('❌ Failed to start test runner:', error);
        reject(error);
      });
    });
  }

  parseTestResults(output) {
    // Parse Playwright output to extract results
    // This is a simplified parser - in production you'd use JSON reporter
    const passedMatch = output.match(/(\d+) passed/);
    const failedMatch = output.match(/(\d+) failed/);
    const skippedMatch = output.match(/(\d+) skipped/);

    if (passedMatch) this.results.passed = parseInt(passedMatch[1]);
    if (failedMatch) this.results.failed = parseInt(failedMatch[1]);
    if (skippedMatch) this.results.skipped = parseInt(skippedMatch[1]);
  }

  async generateReports() {
    console.log('📊 Generating test reports...');
    
    // Reports would be generated by Playwright
    // Additional custom reporting could be added here
    
    const reportsDir = path.join(__dirname, '..', 'test-results');
    if (fs.existsSync(reportsDir)) {
      console.log(`📁 Reports available in: ${reportsDir}`);
    }
  }

  showResults() {
    const total = this.results.passed + this.results.failed + this.results.skipped;
    const duration = (this.results.duration / 1000).toFixed(2);
    
    console.log('\n' + '='.repeat(60));
    console.log('📊 TEST RESULTS SUMMARY');
    console.log('='.repeat(60));
    console.log(`🏃 Total tests: ${total}`);
    console.log(`✅ Passed: ${this.results.passed}`);
    console.log(`❌ Failed: ${this.results.failed}`);
    console.log(`⏭️  Skipped: ${this.results.skipped}`);
    console.log(`⏱️  Duration: ${duration}s`);
    console.log('='.repeat(60));
    
    if (this.results.failed > 0) {
      console.log('❌ Some tests failed. Check the detailed reports for more information.');
    } else {
      console.log('🎉 All tests passed!');
    }
  }
}

// Run if called directly
if (require.main === module) {
  const runner = new E2ETestRunner();
  runner.run();
}

module.exports = E2ETestRunner;