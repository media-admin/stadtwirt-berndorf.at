import { test, expect } from '@playwright/test';

test.describe('Visual Regression', () => {
  test('homepage should match snapshot', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Take screenshot and compare
    await expect(page).toHaveScreenshot('homepage.png', {
      fullPage: true,
      maxDiffPixels: 100,
    });
  });
  
  test('pricing tables should match snapshot', async ({ page }) => {
    await page.goto('/beispiel-seite');
    
    const pricingTables = page.locator('.pricing-tables');
    
    await expect(pricingTables).toHaveScreenshot('pricing-tables.png', {
      maxDiffPixels: 50,
    });
  });
  
  test('hero slider should match snapshot', async ({ page }) => {
    await page.goto('/beispiel-seite');
    
    const heroSlider = page.locator('.hero-slider');
    await page.waitForTimeout(1000); // Wait for slider init
    
    await expect(heroSlider).toHaveScreenshot('hero-slider.png');
  });
});