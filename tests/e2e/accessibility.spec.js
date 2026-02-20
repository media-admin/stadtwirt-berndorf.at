import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Accessibility', () => {
  test('homepage should not have accessibility violations', async ({ page }) => {
    await page.goto('/');
    
    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();
    
    expect(accessibilityScanResults.violations).toEqual([]);
  });
  
  test('pricing tables should be accessible', async ({ page }) => {
    await page.goto('/beispiel-seite');
    
    const pricingSection = page.locator('.pricing-tables');
    
    const accessibilityScanResults = await new AxeBuilder({ page })
      .include('.pricing-tables')
      .analyze();
    
    expect(accessibilityScanResults.violations).toEqual([]);
  });
  
  test('navigation should be keyboard accessible', async ({ page }) => {
    await page.goto('/');
    
    const nav = page.locator('nav');
    await expect(nav).toBeVisible();
    
    // Tab through navigation
    await page.keyboard.press('Tab');
    
    // Check if focus is within navigation
    const focusedElement = await page.evaluate(() => 
      document.activeElement.closest('nav') !== null
    );
    
    expect(focusedElement).toBeTruthy();
  });
  
  test('images should have alt text', async ({ page }) => {
    await page.goto('/beispiel-seite');
    
    const images = await page.locator('img').all();
    
    for (const img of images) {
      const alt = await img.getAttribute('alt');
      
      // Alt can be empty for decorative images, but must be present
      expect(alt).not.toBeNull();
    }
  });
});