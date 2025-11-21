# TMGMT Automated Tests

This directory contains Playwright end-to-end tests for the TMGMT plugin.

## Setup

1. Install dependencies:
   ```bash
   npm install
   ```

2. Install Playwright browsers:
   ```bash
   npx playwright install
   ```

3. Configure Environment:
   - Open `playwright.config.js` and set `baseURL` to your local WordPress URL (e.g., `http://localhost:8888`).
   - Open `tests/iteration-1.spec.js` and update `USERNAME` and `PASSWORD` if they are not `admin`/`password`.

## Running Tests

Run all tests:
```bash
npx playwright test
```

Run specific test:
```bash
npx playwright test tests/iteration-1.spec.js
```

## Debugging

Run with UI mode to see what's happening:
```bash
npx playwright test --ui
```
