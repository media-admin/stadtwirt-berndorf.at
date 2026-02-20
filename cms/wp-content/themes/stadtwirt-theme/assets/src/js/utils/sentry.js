/**
 * Sentry Browser Integration
 */

import * as Sentry from "@sentry/browser";

export function initSentry() {
  // Don't initialize in development
  if (window.location.hostname === 'localhost' || 
      window.location.hostname.includes('.localdev')) {
    return;
  }
  
  Sentry.init({
    dsn: "https://your-sentry-dsn@sentry.io/your-project-id",
    environment: getSentryEnvironment(),
    release: "1.0.0", // Should match backend
    
    // Performance Monitoring
    integrations: [
      new Sentry.BrowserTracing({
        tracePropagationTargets: [
          "localhost",
          /^\//,
          window.location.hostname
        ],
      }),
    ],
    
    tracesSampleRate: 0.2,
    
    // Ignore common errors
    ignoreErrors: [
      'ResizeObserver loop limit exceeded',
      'Non-Error promise rejection captured',
    ],
    
    // Add custom context
    beforeSend(event, hint) {
      // Add WordPress context
      if (window.wp) {
        event.contexts = event.contexts || {};
        event.contexts.wordpress = {
          ajaxUrl: window.customTheme?.ajaxUrl,
          themePath: window.customTheme?.themePath,
        };
      }
      
      return event;
    },
  });
}

function getSentryEnvironment() {
  const hostname = window.location.hostname;
  
  if (hostname.includes('staging')) {
    return 'staging';
  } else if (hostname.includes('.localdev') || hostname === 'localhost') {
    return 'development';
  } else {
    return 'production';
  }
}

// Catch unhandled promise rejections
window.addEventListener('unhandledrejection', event => {
  Sentry.captureException(event.reason);
});