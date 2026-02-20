import { test, expect } from '@playwright/test';
import { BasePage } from '../helpers/base';

test.describe('Homepage', () => {
  let basePage;
  
  test.beforeEach(async ({ page }) => {
    basePage = new BasePage(page);
    await basePage.goto('/');
  });
  
  test('should load successfully', async ({ page }) => {
    // Check page title
    await expect(page).toHaveTitle(/Custom Theme/);
    
    // Check main content is visible
    await expect(page.locator('body')).toBeVisible();
  });
  
  test('should have no console errors', async ({ page }) => {
    const errors = await basePage.checkConsoleErrors();
    
    // Filter out known warnings
    const criticalErrors = errors.filter(error => 
      !error.includes('Swiper') && 
      !error.includes('favicon')
    );
    
    expect(criticalErrors).toHaveLength(0);
  });
  
  test('should have working navigation', async ({ page }) => {
    // Check if navigation exists
    const nav = page.locator('nav');
    await expect(nav).toBeVisible();
    
    // Check navigation links
    const links = await nav.locator('a').count();
    expect(links).toBeGreaterThan(0);
  });
  
  test('should load all images', async ({ page }) => {
    // Wait for images to load
    await page.waitForLoadState('networkidle');
    
    // Check all images have loaded
    const images = await page.locator('img').all();
    
    for (const img of images) {
      const isLoaded = await img.evaluate(el => el.complete && el.naturalHeight > 0);
      expect(isLoaded).toBeTruthy();
    }
  });
  
  test('should be responsive', async ({ page }) => {
    // Desktop
    await page.setViewportSize({ width: 1920, height: 1080 });
    await basePage.waitForLoad();
    await expect(page.locator('body')).toBeVisible();
    
    // Tablet
    await page.setViewportSize({ width: 768, height: 1024 });
    await basePage.waitForLoad();
    await expect(page.locator('body')).toBeVisible();
    
    // Mobile
    await page.setViewportSize({ width: 375, height: 667 });
    await basePage.waitForLoad();
    await expect(page.locator('body')).toBeVisible();
  });
});