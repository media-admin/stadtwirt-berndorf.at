/**
 * Base Test Helpers
 */

export class BasePage {
  constructor(page) {
    this.page = page;
  }
  
  /**
   * Navigate to page
   */
  async goto(path = '/') {
    await this.page.goto(path);
    await this.waitForLoad();
  }
  
  /**
   * Wait for page to be fully loaded
   */
  async waitForLoad() {
    await this.page.waitForLoadState('networkidle');
    await this.page.waitForLoadState('domcontentloaded');
  }
  
  /**
   * Take screenshot
   */
  async screenshot(name) {
    await this.page.screenshot({ 
      path: `test-results/screenshots/${name}.png`,
      fullPage: true 
    });
  }
  
  /**
   * Check for JavaScript errors
   */
  async checkConsoleErrors() {
    const errors = [];
    
    this.page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });
    
    this.page.on('pageerror', error => {
      errors.push(error.message);
    });
    
    return errors;
  }
  
  /**
   * Wait for element to be visible
   */
  async waitForElement(selector, timeout = 5000) {
    await this.page.waitForSelector(selector, { 
      state: 'visible',
      timeout 
    });
  }
}