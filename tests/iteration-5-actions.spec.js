const { test, expect } = require('@playwright/test');
const path = require('path');

test.describe('Iteration 5: Actions & Webhooks', () => {
    // test.use({ storageState: 'playwright/.auth/admin.json' });
    
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
    });

    test('should configure status with action and execute it in event', async ({ page }) => {
        // 1. Create a Webhook
        await page.goto('/wp-admin/post-new.php?post_type=tmgmt_webhook');
        await page.fill('#title', 'Test Webhook Action');
        await page.fill('#tmgmt_webhook_url', 'https://httpbin.org/get'); // Safe test endpoint
        await page.selectOption('#tmgmt_webhook_method', 'GET');
        await page.click('#publish');
        await expect(page.locator('.notice.updated')).toBeVisible();
        
        // Get Webhook ID from URL
        const webhookUrl = page.url();
        const webhookId = new URL(webhookUrl).searchParams.get('post');

        // 2. Create Status Definition with Action
        await page.goto('/wp-admin/post-new.php?post_type=tmgmt_status_def');
        await page.fill('#title', 'Status With Action');
        
        // Add Action
        await page.click('#tmgmt-add-action');
        await page.fill('input[name*="[label]"]', 'Run Webhook');
        await page.selectOption('select[name*="[type]"]', 'webhook');
        await page.selectOption('select[name*="[webhook_id]"]', webhookId);
        
        await page.click('#publish');
        await expect(page.locator('.notice.updated')).toBeVisible();
        
        // Get Status Slug (derived from title usually, but let's check)
        // For simplicity, we assume slug is 'status-with-action'
        
        // 3. Create Event and assign Status
        await page.goto('/wp-admin/post-new.php?post_type=event');
        await page.fill('#title', 'Event for Action Test');
        
        // Select the new status
        // We need to reload the page or ensure the status list is updated? 
        // Statuses are loaded from DB.
        await page.reload(); 
        await page.selectOption('#tmgmt_status', { label: 'Status With Action' });
        await page.click('#publish');
        await expect(page.locator('.notice.updated')).toBeVisible();

        // 4. Verify Action Button appears
        // The meta box should be visible.
        await expect(page.locator('#tmgmt_event_actions')).toBeVisible();
        const actionBtn = page.locator('.tmgmt-trigger-action', { hasText: 'Run Webhook' });
        await expect(actionBtn).toBeVisible();

        // 5. Execute Action
        // Handle confirm dialog
        page.on('dialog', dialog => dialog.accept());
        
        await actionBtn.click();
        
        // Wait for alert (success message)
        // Note: The implementation uses alert() for success. Playwright handles alerts via dialog event too.
        // We need to handle the second dialog (the alert)
        
        // Actually, the first dialog is confirm(), the second is alert().
        // We can set up a handler that accepts all.
        
        // Wait for page reload (the script reloads on success)
        await page.waitForLoadState('networkidle');

        // 6. Verify Log
        await page.click('#tmgmt_event_logs .handle-actions'); // Open logs box if closed? usually open.
        // Check for log entry
        await expect(page.locator('#tmgmt-log-table')).toContainText('Aktion ausgeführt: Run Webhook');
        await expect(page.locator('#tmgmt-log-table')).toContainText('Webhook Response (200)');
    });
});
