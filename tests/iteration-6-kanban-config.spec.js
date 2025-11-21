const { test, expect } = require('@playwright/test');

test.describe('Iteration 6: Kanban Configuration', () => {
    
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

    test('should create a kanban column and assign statuses', async ({ page }) => {
        // 1. Create a Status Definition first
        await page.goto('/wp-admin/post-new.php?post_type=tmgmt_status_def');
        await page.fill('#title', 'Kanban Status Test');
        await page.click('#publish');
        await expect(page.locator('.notice.updated')).toBeVisible();
        
        // 2. Create a Kanban Column
        await page.goto('/wp-admin/post-new.php?post_type=tmgmt_kanban_col');
        await page.fill('#title', 'Test Column');
        
        // Set Order
        await page.fill('#tmgmt_kanban_order', '10');
        
        // Set Color (Color input handling can be tricky, fill usually works)
        await page.fill('#tmgmt_kanban_color', '#ff0000');
        
        // Select Status (Checkbox)
        // We need to find the checkbox for 'Kanban Status Test'
        // The label contains the text.
        const statusCheckbox = page.locator('label', { hasText: 'Kanban Status Test' }).locator('input[type="checkbox"]');
        await statusCheckbox.check();
        
        await page.click('#publish');
        await expect(page.locator('.notice.updated')).toBeVisible();
        
        // 3. Verify Persistence
        await page.reload();
        await expect(page.locator('#tmgmt_kanban_order')).toHaveValue('10');
        await expect(page.locator('#tmgmt_kanban_color')).toHaveValue('#ff0000');
        await expect(statusCheckbox).toBeChecked();
    });
});
