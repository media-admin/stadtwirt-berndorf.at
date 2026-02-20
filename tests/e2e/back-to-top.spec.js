import { test, expect } from '@playwright/test';

test.describe('Back to Top Button', () => {
  test('should appear after scrolling', async ({ page }) => {
    await page.goto('/beispiel-seite');
    
    const button = page.locator('.back-to-top');
    
    // Should be hidden initially
    await expect(button).toHaveCSS('opacity', '0');
    
    // Scroll down
    await page.evaluate(() => window.scrollTo(0, 500));
    await page.waitForTimeout(500);
    
    // Should be visible after scroll
    await expect(button).toHaveCSS('opacity', '1');
  });
  
  test('should scroll to top when clicked', async ({ page }) => {
    await page.goto('/beispiel-seite');
    
    // Scroll down
    await page.evaluate(() => window.scrollTo(0, 1000));
    await page.waitForTimeout(500);
    
    // Click button
    const button = page.locator('.back-to-top');
    await button.click();
    
    // Wait for scroll animation
    await page.waitForTimeout(1000);
    
    // Check we're at the top
    const scrollY = await page.evaluate(() => window.scrollY);
    expect(scrollY).toBeLessThan(50);
  });
});