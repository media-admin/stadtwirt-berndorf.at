# Testing Guide

## 🧪 Test Suite

### E2E Tests (Playwright)
- Homepage functionality
- Hero Slider interactions
- Grid layouts (Pricing, Team, Stats)
- Back to Top button
- Navigation

### Accessibility Tests (Axe)
- WCAG 2.1 AA compliance
- Keyboard navigation
- Screen reader compatibility
- Alt text presence

### Performance Tests
- Page load times
- Core Web Vitals (FCP, LCP, CLS, TBT)
- Memory leak detection

### Visual Regression Tests
- Screenshot comparisons
- Layout consistency
- Component rendering

## 🚀 Running Tests

### All Tests
```bash
npm test
```

### Specific Test Suites
```bash
# E2E only
npm run test:e2e

# Accessibility only
npm run test:accessibility

# Performance only
npm run test:performance

# Visual regression only
npm run test:visual
```

### Interactive Mode
```bash
# UI Mode (recommended for development)
npm run test:ui

# Headed mode (see browser)
npm run test:headed

# Debug mode (step through)
npm run test:debug
```

### Single Test File
```bash
npx playwright test tests/e2e/homepage.spec.js
```

### Single Test
```bash
npx playwright test -g "should load successfully"
```

## 📊 Test Reports

### HTML Report
```bash
npm run test:report
```

Opens interactive HTML report in browser.

### CI Reports
Automatically generated on GitHub Actions.
View in: Actions → Workflow Run → Artifacts

## 🔧 Writing Tests

### Test Structure
```javascript
import { test, expect } from '@playwright/test';

test.describe('Feature Name', () => {
  test.beforeEach(async ({ page }) => {
    // Setup
  });
  
  test('should do something', async ({ page }) => {
    // Test
  });
});
```

### Common Patterns
```javascript
// Navigate
await page.goto('/path');

// Wait for element
await page.waitForSelector('.my-class');

// Click
await page.click('.button');

// Check visibility
await expect(page.locator('.element')).toBeVisible();

// Check text
await expect(page.locator('.element')).toHaveText('Expected text');

// Screenshot
await page.screenshot({ path: 'screenshot.png' });
```

## 🎯 Test Coverage Goals

- **E2E Coverage:** 80%+ of critical user flows
- **Accessibility:** 100% WCAG 2.1 AA compliance
- **Performance:** All pages < 3s load time
- **Visual:** Key pages have snapshot tests

## 🐛 Debugging Failed Tests

### 1. View Screenshots
```bash
open test-results/screenshots/
```

### 2. View Videos
```bash
open test-results/videos/
```

### 3. Run in Debug Mode
```bash
npm run test:debug
```

### 4. Use Trace Viewer
```bash
npx playwright show-trace test-results/trace.zip
```

## 📅 CI/CD Integration

Tests run automatically on:
- Every push to `develop`
- Every push to `main`
- Every pull request

### Preventing Deployments
Deployments are blocked if tests fail.

## 🔄 Updating Snapshots

When intentionally changing UI:
```bash
# Update all snapshots
npx playwright test --update-snapshots

# Update specific test
npx playwright test visual-regression.spec.js --update-snapshots
```

## 📊 Performance Budgets

Current thresholds:
- FCP: < 2000ms
- LCP: < 2500ms
- CLS: < 0.1
- TBT: < 300ms

Update in `lighthouserc.js` if needed.