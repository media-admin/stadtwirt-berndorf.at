import { test, expect } from '@playwright/test';

test.describe('Performance', () => {
  test('should load homepage quickly', async ({ page }) => {
    const startTime = Date.now();
    
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    const loadTime = Date.now() - startTime;
    
    // Should load in under 3 seconds
    expect(loadTime).toBeLessThan(3000);
  });
  
  test('should have good Core Web Vitals', async ({ page }) => {
    await page.goto('/');
    
    // Get performance metrics
    const metrics = await page.evaluate(() => {
      return new Promise((resolve) => {
        new PerformanceObserver((list) => {
          const entries = list.getEntries();
          const vitals = {};
          
          entries.forEach((entry) => {
            if (entry.name === 'first-contentful-paint') {
              vitals.fcp = entry.startTime;
            }
          });
          
          resolve(vitals);
        }).observe({ entryTypes: ['paint'] });
        
        // Fallback after 5 seconds
        setTimeout(() => resolve({}), 5000);
      });
    });
    
    // FCP should be under 2 seconds
    if (metrics.fcp) {
      expect(metrics.fcp).toBeLessThan(2000);
    }
  });
  
  test('should not have memory leaks', async ({ page }) => {
    await page.goto('/');
    
    // Get initial memory
    const initialMetrics = await page.metrics();
    const initialJSHeapSize = initialMetrics.JSHeapUsedSize;
    
    // Navigate and interact
    await page.reload();
    await page.waitForLoadState('networkidle');
    
    // Get memory after reload
    const finalMetrics = await page.metrics();
    const finalJSHeapSize = finalMetrics.JSHeapUsedSize;
    
    // Memory should not grow significantly (allow 50% increase)
    const memoryGrowth = (finalJSHeapSize - initialJSHeapSize) / initialJSHeapSize;
    expect(memoryGrowth).toBeLessThan(0.5);
  });
});