const { defineConfig, devices } = require('@playwright/test');

/**
 * Playwright Configuration for WordPress AI Content Flow Plugin
 * 
 * Adapted from backtester project patterns for WordPress environment
 * Supports full end-to-end testing of WordPress plugin functionality
 * 
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './e2e',
  
  /* Run tests in files in parallel */
  fullyParallel: true,
  
  /* Fail the build on CI if you accidentally left test.only in the source code */
  forbidOnly: !!process.env.CI,
  
  /* Retry on CI only - smoke tests get 1 retry always */
  retries: process.env.CI ? 2 : 1,
  
  /* Opt out of parallel tests on CI for stability */
  workers: process.env.CI ? 1 : undefined,
  
  /* Timeout for WordPress tests - 45 seconds per test (WordPress can be slow) */
  timeout: 45 * 1000,
  
  /* Global timeout for test suite - 5 minutes total */
  globalTimeout: 5 * 60 * 1000,
  
  /* Reporter configuration */
  reporter: [
    ['html', { outputFolder: 'test-results/html' }],
    ['json', { outputFile: 'test-results/results.json' }],
    ['list'],
    ...(process.env.CI ? [['github']] : [])
  ],
  
  /* Global test configuration */
  use: {
    /* WordPress Docker instance base URL */
    baseURL: 'http://localhost:8080',
    
    /* Collect trace when retrying failed tests */
    trace: 'on-first-retry',
    
    /* Screenshot on failure for debugging */
    screenshot: 'only-on-failure',
    
    /* Video recording on failure */
    video: 'retain-on-failure',
    
    /* Action timeout for WordPress interactions */
    actionTimeout: 15 * 1000,
    
    /* Navigation timeout for page loads */
    navigationTimeout: 30 * 1000,
    
    /* WordPress-specific headers */
    extraHTTPHeaders: {
      'Accept-Language': 'en-US,en;q=0.9'
    }
  },

  /* Test projects for different browsers and scenarios */
  projects: [
    {
      name: 'chromium-admin',
      testDir: './e2e/admin',
      use: { 
        ...devices['Desktop Chrome'],
        /* WordPress admin specific settings */
        contextOptions: {
          /* Increase viewport for WordPress admin */
          viewport: { width: 1440, height: 900 }
        }
      },
    },
    
    {
      name: 'chromium-blocks',
      testDir: './e2e/blocks',
      use: { 
        ...devices['Desktop Chrome'],
        /* Block editor specific settings */
        contextOptions: {
          viewport: { width: 1200, height: 800 }
        }
      },
    },
    
    {
      name: 'firefox',
      testDir: './e2e/workflows',
      use: { 
        ...devices['Desktop Firefox'],
        /* Cross-browser workflow testing */
        contextOptions: {
          viewport: { width: 1280, height: 720 }
        }
      },
    },
    
    /* Smoke tests run on all projects */
    {
      name: 'smoke-chromium',
      testDir: './e2e/smoke-tests',
      use: { 
        ...devices['Desktop Chrome'],
        /* Fast smoke test configuration */
        actionTimeout: 10 * 1000,
        navigationTimeout: 20 * 1000
      },
    },

    /* Mobile testing for responsive WordPress themes */
    {
      name: 'mobile-chrome',
      testDir: './e2e/mobile',
      use: { ...devices['Pixel 5'] },
    },
    
    /* Accessibility testing */
    {
      name: 'accessibility',
      testDir: './e2e/accessibility',
      use: { 
        ...devices['Desktop Chrome'],
        /* Enable accessibility tree snapshots */
        contextOptions: {
          reducedMotion: 'reduce'
        }
      },
    }
  ],

  /* WordPress Docker services setup */
  webServer: {
    command: 'docker-compose up -d wordpress mysql',
    url: 'http://localhost:8080',
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000,
    /* Wait for WordPress to be fully ready */
    stdout: 'pipe',
    stderr: 'pipe'
  },

  /* Global setup and teardown */
  globalSetup: require.resolve('./e2e/global-setup.js'),
  globalTeardown: require.resolve('./e2e/global-teardown.js'),

  /* Expect configuration */
  expect: {
    /* Custom timeout for WordPress assertions */
    timeout: 10 * 1000,
    /* Screenshot comparison threshold */
    threshold: 0.2,
    /* Animation handling */
    toHaveScreenshot: { 
      animations: 'disabled',
      caret: 'hide'
    }
  },

  /* Output directory for test artifacts */
  outputDir: 'test-results/',
  
  /* Test metadata */
  metadata: {
    'wp-version': '6.4',
    'php-version': '8.1',
    'plugin-version': '1.0.0'
  }
});