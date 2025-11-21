const { test, expect } = require('@playwright/test');

test.describe('Iteration 6: Kanban Dashboard', () => {
    
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

    test('should display kanban board with columns and events', async ({ page }) => {
        // 1. Ensure we have a Status Definition
        await page.goto('/wp-admin/post-new.php?post_type=tmgmt_status_def');
        await page.fill('#title', 'Kanban Status A');
        await page.click('#publish');
        
        // 2. Ensure we have a Kanban Column mapped to this status
        await page.goto('/wp-admin/post-new.php?post_type=tmgmt_kanban_col');
        await page.fill('#title', 'Column A');
        await page.fill('#tmgmt_kanban_order', '1');
        const statusCheckbox = page.locator('label', { hasText: 'Kanban Status A' }).locator('input[type="checkbox"]');
        await statusCheckbox.check();
        await page.click('#publish');

        // 3. Create an Event with this status
        await page.goto('/wp-admin/post-new.php?post_type=event');
        await page.fill('#title', 'Event In Column A');
        await page.reload(); // Reload to populate status dropdown
        await page.selectOption('#tmgmt_status', { label: 'Kanban Status A' });
        await page.click('#publish');

        // 4. Visit Dashboard
        await page.goto('/wp-admin/admin.php?page=tmgmt-dashboard');
        
        // 5. Verify Board Structure
        await expect(page.locator('.tmgmt-kanban-board')).toBeVisible();
        await expect(page.locator('.tmgmt-kanban-column h2', { hasText: 'Column A' })).toBeVisible();
        
        // 6. Verify Event Card
        const card = page.locator('.tmgmt-event-card', { hasText: 'Event In Column A' });
        await expect(card).toBeVisible();
        await expect(card).toContainText('Kanban Status A');
        
        // 7. Verify Click Navigation (Optional check)
        // await card.click();
        // await expect(page).toHaveURL(/post.php/);
    });
});
