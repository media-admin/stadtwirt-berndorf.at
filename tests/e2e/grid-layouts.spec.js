import { test, expect } from '@playwright/test';
import { BasePage } from '../helpers/base';

test.describe('Grid Layouts', () => {
  let basePage;
  
  test.beforeEach(async ({ page }) => {
    basePage = new BasePage(page);
    await basePage.goto('/beispiel-seite');
  });
  
  test.describe('Pricing Tables', () => {
    test('should display in 3 columns on desktop', async ({ page }) => {
      await page.setViewportSize({ width: 1920, height: 1080 });
      
      const pricingTables = page.locator('.pricing-tables');
      await expect(pricingTables).toBeVisible();
      
      // Check grid layout
      const gridColumns = await pricingTables.evaluate(el => 
        window.getComputedStyle(el).gridTemplateColumns
      );
      
      // Should have 3 columns (format: "XXXpx XXXpx XXXpx")
      const columnCount = gridColumns.split(' ').length;
      expect(columnCount).toBe(3);
    });
    
    test('should be responsive on mobile', async ({ page }) => {
      await page.setViewportSize({ width: 375, height: 667 });
      
      const pricingTables = page.locator('.pricing-tables');
      await expect(pricingTables).toBeVisible();
      
      // Should have 1 column on mobile
      const gridColumns = await pricingTables.evaluate(el => 
        window.getComputedStyle(el).gridTemplateColumns
      );
      
      const columnCount = gridColumns.split(' ').length;
      expect(columnCount).toBeLessThanOrEqual(1);
    });
  });
  
  test.describe('Team Cards', () => {
    test('should display team members', async ({ page }) => {
      const teamCards = page.locator('.team-cards');
      await expect(teamCards).toBeVisible();
      
      const cards = page.locator('.team-card');
      const count = await cards.count();
      
      expect(count).toBeGreaterThan(0);
    });
    
    test('should have proper grid layout', async ({ page }) => {
      await page.setViewportSize({ width: 1920, height: 1080 });
      
      const teamCards = page.locator('.team-cards');
      const gridColumns = await teamCards.evaluate(el => 
        window.getComputedStyle(el).gridTemplateColumns
      );
      
      const columnCount = gridColumns.split(' ').length;
      expect(columnCount).toBeGreaterThanOrEqual(2);
    });
  });
  
  test.describe('Stats', () => {
    test('should display stats', async ({ page }) => {
      const stats = page.locator('.stats');
      await expect(stats).toBeVisible();
      
      const statItems = page.locator('.stat');
      const count = await statItems.count();
      
      expect(count).toBeGreaterThan(0);
    });
    
    test('should show numbers', async ({ page }) => {
      const firstStat = page.locator('.stat').first();
      const number = firstStat.locator('.stat__value');
      
      await expect(number).toBeVisible();
      
      const value = await number.textContent();
      expect(value).toBeTruthy();
    });
  });
});