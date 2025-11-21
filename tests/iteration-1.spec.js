const { test, expect } = require('@playwright/test');

test.describe('Iteration 1: Basic Event CPT & Save Workflow', () => {
  
  // Configuration - Update these if needed
  const WP_ADMIN_URL = '/wp-admin';
  const USERNAME = 'admin';
  const PASSWORD = 'password';

  test.beforeEach(async ({ page }) => {
    // 1. Login
    await page.goto(`${WP_ADMIN_URL}/`);
    
    // Check if we need to log in
    if (await page.locator('#user_login').isVisible()) {
        await page.fill('#user_login', USERNAME);
        await page.fill('#user_pass', PASSWORD);
        await page.click('#wp-submit');
    }
    
    // Verify we are in admin
    await expect(page.locator('#wpadminbar')).toBeVisible();
  });

  test('Create Event with "Save Only" workflow', async ({ page }) => {
    // 2. Navigate to Add New Event
    await page.goto(`${WP_ADMIN_URL}/post-new.php?post_type=event`);

    // 3. Verify UI Simplification (Classic Editor)
    
    // "Save Draft" should be hidden or removed
    const saveDraftBtn = page.locator('#save-post');
    if (await saveDraftBtn.count() > 0) {
        await expect(saveDraftBtn).toBeHidden();
    }

    // "Preview" should be hidden or removed
    const previewBtn = page.locator('#post-preview');
    if (await previewBtn.count() > 0) {
        await expect(previewBtn).toBeHidden();
    }

    // 4. Verify "Speichern" button
    const publishBtn = page.locator('#publish');
    await expect(publishBtn).toBeVisible();
    await expect(publishBtn).toHaveValue('Speichern');

    // 5. Create Event
    const testTitle = 'Automated Test Event ' + Date.now();
    await page.fill('#title', testTitle);
    
    // Click Save
    await publishBtn.click();

    // 6. Verify Persistence
    // Wait for page reload/save
    await page.waitForLoadState('networkidle');

    // Check for success message
    await expect(page.locator('#message')).toBeVisible();
    // Match either "aktualisiert" or "veröffentlicht" to handle both creation and update states
    await expect(page.locator('#message')).toContainText(/aktualisiert|veröffentlicht/);

    // Verify status is 'publish' (by checking the hidden input or the UI)
    // In Classic Editor, the status is usually shown in the submitdiv, but we hid parts of it.
    // We can check the "post_status" hidden input if it exists, or rely on the fact that it saved.
    
    // Double check the button still says "Speichern" (update)
    await expect(publishBtn).toHaveValue('Speichern');

    // 7. Clean up - Delete the event
    const deleteLink = page.locator('#delete-action a.submitdelete');
    await expect(deleteLink).toBeVisible();
    await deleteLink.click();
    
    // Verify deletion (redirects to list table usually)
    await page.waitForLoadState('networkidle');
    // Check for "Post moved to the trash" message (Papierkorb)
    await expect(page.locator('#message')).toContainText(/Papierkorb/);
  });
});
