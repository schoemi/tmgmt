const { test, expect } = require('@playwright/test');

test.describe('Iteration 4: Logging Workflow', () => {
  
  const WP_ADMIN_URL = '/wp-admin';
  const USERNAME = 'admin';
  const PASSWORD = 'password';

  test.beforeEach(async ({ page }) => {
    await page.goto(`${WP_ADMIN_URL}/`);
    if (await page.locator('#user_login').isVisible()) {
        await page.fill('#user_login', USERNAME);
        await page.fill('#user_pass', PASSWORD);
        await page.click('#wp-submit');
    }
    await expect(page.locator('#wpadminbar')).toBeVisible();
  });

  test('Create Event, Change Status, Verify Log', async ({ page }) => {
    // 1. Setup: Create a specific Status Definition to ensure test reliability
    // We use a unique name to avoid conflicts with existing manual data
    const statusName = 'Termin Prüfung AutoTest ' + Date.now();
    const logTemplate = 'Status wurde auf Termin Prüfung geändert.';
    
    await page.goto(`${WP_ADMIN_URL}/post-new.php?post_type=tmgmt_status_def`);
    await page.fill('#title', statusName);
    await page.fill('#tmgmt_log_template', logTemplate);
    await page.click('#publish');
    await expect(page.locator('#message')).toBeVisible();

    // 2. Create New Event
    await page.goto(`${WP_ADMIN_URL}/post-new.php?post_type=event`);
    await page.fill('#title', 'Log Test Event');
    
    // 3. Change Status
    // We need to reload or wait? No, we just navigated to post-new.php, 
    // so the new status should be in the dropdown (fetched from DB).
    
    // Select by Label (the title we just created)
    await page.locator('#tmgmt_status').selectOption({ label: statusName });

    // 4. Save Event
    await page.click('#publish');
    await page.waitForLoadState('networkidle');

    // 5. Verify Log Entry
    // The log table is in #tmgmt-log-table
    const logTable = page.locator('#tmgmt-log-table');
    await expect(logTable).toBeVisible();
    
    // Check for the specific log message
    await expect(logTable).toContainText(logTemplate);
    // Check for the type 'status_change'
    await expect(logTable).toContainText('status_change');

    // 6. Cleanup Event
    await page.click('#delete-action a.submitdelete');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#message')).toContainText(/Papierkorb/);

    // 7. Cleanup Status (Optional but good practice)
    // Navigate to Status list
    await page.goto(`${WP_ADMIN_URL}/edit.php?post_type=tmgmt_status_def`);
    // Find the row with our status name and click Trash
    // This is a bit complex in a list table, but we can try to find the link.
    // For simplicity in this iteration, we might skip deleting the status definition 
    // or try to delete the one we just created if we are still on its edit page?
    // No, we are on the Event list page now.
    
    // Let's go back to the status edit page? We don't have the ID easily.
    // We'll skip deleting the status definition to keep the test simple, 
    // or we can search for it.
    
    // Search for the status to delete it
    await page.goto(`${WP_ADMIN_URL}/edit.php?post_type=tmgmt_status_def&s=${encodeURIComponent(statusName)}`);
    // If found, delete
    if (await page.locator('.row-actions .trash a').count() > 0) {
        // Hover to show actions
        await page.hover('td.title a.row-title');
        await page.click('.row-actions .trash a');
    }
  });
});
