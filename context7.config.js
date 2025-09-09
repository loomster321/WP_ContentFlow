/**
 * Context7 Configuration for WordPress AI Content Flow Plugin
 * 
 * Context7 provides advanced debugging, error tracking, and performance monitoring
 * specifically tailored for WordPress plugin development and AI operations.
 * 
 * @see https://github.com/context7/context7
 */

module.exports = {
  // Core Context7 configuration
  appName: 'WordPress AI Content Flow Plugin',
  version: '1.0.0',
  environment: process.env.NODE_ENV || 'development',
  
  // WordPress-specific settings
  wordpress: {
    baseUrl: process.env.WP_BASE_URL || 'http://localhost:8080',
    adminUrl: process.env.WP_ADMIN_URL || 'http://localhost:8080/wp-admin',
    apiUrl: process.env.WP_API_URL || 'http://localhost:8080/wp-json/wp-content-flow/v1',
    pluginPath: '/wp-content/plugins/wp-content-flow/',
    debug: process.env.WP_DEBUG === 'true'
  },
  
  // Error tracking and reporting
  errorTracking: {
    enabled: true,
    captureUnhandledRejections: true,
    captureUncaughtExceptions: true,
    
    // WordPress-specific error patterns
    wordpressErrors: {
      capturePhpErrors: true,
      captureJsErrors: true,
      captureAjaxErrors: true,
      captureRestApiErrors: true
    },
    
    // AI operation specific errors
    aiErrors: {
      captureProviderErrors: true,
      captureGenerationFailures: true,
      captureRateLimitExceeded: true,
      captureContentValidationErrors: true
    },
    
    // Error filtering
    ignoreErrors: [
      /Non-Error promise rejection captured/,
      /ResizeObserver loop limit exceeded/,
      /NetworkError when attempting to fetch resource/
    ],
    
    // Error grouping
    groupingKey: function(error) {
      // Group by error type and location
      return `${error.name}:${error.filename}:${error.lineno}`;
    }
  },
  
  // Performance monitoring
  performance: {
    enabled: true,
    
    // WordPress-specific metrics
    wordpress: {
      trackPageLoads: true,
      trackAdminPageLoads: true,
      trackAjaxRequests: true,
      trackRestApiCalls: true,
      trackDatabaseQueries: false, // Requires PHP integration
    },
    
    // AI operation metrics
    aiMetrics: {
      trackGenerationTime: true,
      trackImprovementTime: true,
      trackProviderResponseTime: true,
      trackContentValidationTime: true,
      trackCacheHitRates: true
    },
    
    // Performance thresholds
    thresholds: {
      pageLoad: 3000, // 3 seconds
      ajaxRequest: 5000, // 5 seconds
      aiGeneration: 30000, // 30 seconds
      apiCall: 10000 // 10 seconds
    },
    
    // Automatic performance issue detection
    alerts: {
      slowQueries: true,
      memoryLeaks: true,
      highCpuUsage: true,
      slowAiResponses: true
    }
  },
  
  // Logging configuration
  logging: {
    enabled: true,
    level: process.env.LOG_LEVEL || 'info', // debug, info, warn, error
    
    // WordPress integration
    wordpress: {
      useWordPressLogger: true,
      logToPhpErrorLog: true,
      logToConsole: process.env.NODE_ENV === 'development'
    },
    
    // Log categories
    categories: {
      ai: { enabled: true, level: 'debug' },
      api: { enabled: true, level: 'info' },
      performance: { enabled: true, level: 'warn' },
      security: { enabled: true, level: 'error' },
      user: { enabled: true, level: 'info' }
    },
    
    // Log formatting
    format: {
      timestamp: true,
      userId: true,
      sessionId: true,
      requestId: true,
      context: true
    }
  },
  
  // Session and user tracking
  userTracking: {
    enabled: true,
    anonymize: process.env.NODE_ENV === 'production',
    
    // WordPress user integration
    wordpress: {
      trackUserRoles: true,
      trackUserCapabilities: true,
      trackUserPreferences: true
    },
    
    // AI usage tracking
    aiUsage: {
      trackGenerationAttempts: true,
      trackProviderSwitching: true,
      trackWorkflowUsage: true,
      trackContentApproval: true
    }
  },
  
  // Security monitoring
  security: {
    enabled: true,
    
    // WordPress security
    wordpress: {
      trackFailedLogins: true,
      trackPrivilegeEscalation: true,
      trackSuspiciousRequests: true,
      trackNonceFailures: true
    },
    
    // AI security
    ai: {
      trackPromptInjection: true,
      trackContentFiltering: true,
      trackUnauthorizedAccess: true,
      trackRateLimitViolations: true
    }
  },
  
  // Real-time debugging
  debugging: {
    enabled: process.env.NODE_ENV === 'development',
    
    // WordPress debug integration
    wordpress: {
      showPhpErrors: true,
      showJsErrors: true,
      showDatabaseQueries: false,
      showHookCalls: false // Can be very verbose
    },
    
    // AI debugging
    ai: {
      logProviderRequests: true,
      logProviderResponses: true,
      logContentGeneration: true,
      logContentImprovement: true,
      showCacheOperations: true
    },
    
    // Debug UI
    ui: {
      showDebugPanel: process.env.NODE_ENV === 'development',
      position: 'bottom-right',
      collapsed: false
    }
  },
  
  // Data collection and analytics
  analytics: {
    enabled: true,
    
    // Feature usage analytics
    features: {
      trackBlockUsage: true,
      trackWorkflowCreation: true,
      trackSettingsChanges: true,
      trackProviderSwitching: true
    },
    
    // Performance analytics
    performance: {
      trackLoadTimes: true,
      trackErrorRates: true,
      trackSuccessRates: true,
      trackUserSatisfaction: false
    },
    
    // Privacy compliance
    privacy: {
      anonymizeIpAddresses: true,
      respectDoNotTrack: true,
      allowOptOut: true,
      dataRetentionDays: 90
    }
  },
  
  // Integration settings
  integrations: {
    // WordPress hooks integration
    wordpress: {
      useWordPressHooks: true,
      hookPriority: 10,
      hookPrefix: 'wp_content_flow_context7_'
    },
    
    // External services
    external: {
      // Could integrate with external monitoring services
      // sentry: { enabled: false, dsn: '' },
      // datadog: { enabled: false, apiKey: '' }
    }
  },
  
  // Development tools
  development: {
    enabled: process.env.NODE_ENV === 'development',
    
    // Hot reloading for debug configuration
    hotReload: true,
    
    // Debug overlays
    overlays: {
      errors: true,
      performance: true,
      network: true,
      console: true
    },
    
    // Testing integration
    testing: {
      mockAiProviders: false,
      simulateSlowNetwork: false,
      simulateErrors: false
    }
  },
  
  // Output and reporting
  output: {
    // Console output
    console: {
      enabled: process.env.NODE_ENV === 'development',
      colors: true,
      timestamp: true
    },
    
    // File output
    file: {
      enabled: true,
      path: './logs/context7.log',
      maxSize: '10MB',
      maxFiles: 5,
      rotate: true
    },
    
    // WordPress integration
    wordpress: {
      adminNotices: true,
      dashboardWidget: true,
      toolsPage: true
    }
  }
};