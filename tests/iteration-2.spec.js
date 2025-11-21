const { test, expect } = require('@playwright/test');

test.describe('Iteration 2: Event Meta Data Persistence', () => {
  
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

  test('Create Event and save Meta Data', async ({ page }) => {
    // 1. Navigate to Add New Event
    await page.goto(`${WP_ADMIN_URL}/post-new.php?post_type=event`);

    // 2. Fill Title
    const testTitle = 'Meta Data Test ' + Date.now();
    await page.fill('#title', testTitle);

    // 3. Fill Event Details
    await page.fill('#tmgmt_event_date', '2025-12-24');
    await page.fill('#tmgmt_event_start_time', '20:00');
    await page.fill('#tmgmt_venue_city', 'Köln');
    await page.fill('#tmgmt_venue_street', 'Domplatte 1');

    // 4. Fill Contact Details
    await page.fill('#tmgmt_contact_firstname', 'Hans');
    await page.fill('#tmgmt_contact_lastname', 'Müller');
    await page.fill('#tmgmt_contact_email_contract', 'hans@example.com');

    // 5. Fill Contract Details (Side)
    await page.fill('#tmgmt_fee', '1500.50');

    // 6. Save
    await page.click('#publish');
    
    // Wait for reload
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#message')).toBeVisible();

    // 7. Verify Persistence
    await expect(page.locator('#tmgmt_event_date')).toHaveValue('2025-12-24');
    await expect(page.locator('#tmgmt_event_start_time')).toHaveValue('20:00');
    await expect(page.locator('#tmgmt_venue_city')).toHaveValue('Köln');
    await expect(page.locator('#tmgmt_contact_firstname')).toHaveValue('Hans');
    await expect(page.locator('#tmgmt_contact_email_contract')).toHaveValue('hans@example.com');
    await expect(page.locator('#tmgmt_fee')).toHaveValue('1500.50');

    // 8. Clean up
    const deleteLink = page.locator('#delete-action a.submitdelete');
    await deleteLink.click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#message')).toContainText(/Papierkorb/);
  });
});
