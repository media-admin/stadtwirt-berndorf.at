import { test, expect } from '@playwright/test';
import { BasePage } from '../helpers/base';

test.describe('Hero Slider', () => {
  let basePage;
  
  test.beforeEach(async ({ page }) => {
    basePage = new BasePage(page);
    await basePage.goto('/beispiel-seite');
  });
  
  test('should be visible', async ({ page }) => {
    const slider = page.locator('.hero-slider');
    await expect(slider).toBeVisible();
  });
  
  test('should have slides', async ({ page }) => {
    const slides = page.locator('.swiper-slide');
    const count = await slides.count();
    
    expect(count).toBeGreaterThan(0);
  });
  
  test('should have navigation buttons', async ({ page }) => {
    const prevButton = page.locator('.swiper-button-prev');
    const nextButton = page.locator('.swiper-button-next');
    
    await expect(prevButton).toBeVisible();
    await expect(nextButton).toBeVisible();
  });
  
  test('should navigate between slides', async ({ page }) => {
    const nextButton = page.locator('.swiper-button-next');
    
    // Get initial active slide
    const initialSlide = await page.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
    
    // Click next
    await nextButton.click();
    
    // Wait for transition
    await page.waitForTimeout(1000);
    
    // Check that active slide changed
    const newSlide = await page.locator('.swiper-slide-active').getAttribute('data-swiper-slide-index');
    
    expect(newSlide).not.toBe(initialSlide);
  });
  
  test('should autoplay (if enabled)', async ({ page }) => {
    // Get initial active slide
    const initialSlide = await page.locator('.swiper-slide-active').textContent();
    
    // Wait for autoplay (5 seconds + transition)
    await page.waitForTimeout(6000);
    
    // Check if slide changed
    const newSlide = await page.locator('.swiper-slide-active').textContent();
    
    // If autoplay is enabled, slides should be different
    // This test might need adjustment based on your autoplay settings
  });
});