const { test, expect } = require('@playwright/test');

test.describe('Iteration 7: REST API', () => {
    
    const WP_API_URL = '/wp-json/tmgmt/v1';
    const USERNAME = 'admin';
    const PASSWORD = 'password';
    let authHeader = '';

    test.beforeAll(async ({ request }) => {
        // We need to authenticate. Since we are running locally, we can try Basic Auth if enabled, 
        // or just rely on cookie auth if we were in a browser context.
        // But for API testing via 'request' fixture, we need credentials.
        // Playwright's request context can store cookies if we login first?
        // Or we can use Application Passwords if available.
        // Let's try Basic Auth with the default admin/password.
        const token = Buffer.from(`${USERNAME}:${PASSWORD}`).toString('base64');
        authHeader = `Basic ${token}`;
    });

    test('should update event via API', async ({ request }) => {
        // 1. Create an Event first (we can use the WP REST API for this or assume ID 1 exists, but better to create one)
        // Using standard WP API to create a post
        const createRes = await request.post('/wp-json/wp/v2/event', {
            headers: { 'Authorization': authHeader },
            data: {
                title: 'API Test Event',
                status: 'publish'
            }
        });
        
        // If Basic Auth fails (often disabled by default), we might need another way.
        // Assuming the test environment allows it or we use a plugin for it.
        // If this fails, we might need to skip auth or use cookie.
        
        // Note: In many local setups, Basic Auth works if configured.
        // If not, we can try to use the browser context to get a nonce.
        
        if (!createRes.ok()) {
            console.log('Failed to create event via WP API. Status:', createRes.status());
            // Fallback: Try to find an existing event? Or fail.
            // Let's assume for this test that we have an event with ID 1 or we can't proceed easily without auth setup.
            // But wait, we are in a test environment.
        }
        
        const event = await createRes.json();
        const eventId = event.id;

        // 2. Update Event via TMGMT API
        const updateRes = await request.post(`${WP_API_URL}/events/${eventId}`, {
            headers: { 'Authorization': authHeader },
            data: {
                venue_city: 'API City',
                status: 'confirmed' // Assuming 'confirmed' is a valid status slug
            }
        });

        expect(updateRes.ok()).toBeTruthy();
        const updateData = await updateRes.json();
        expect(updateData.success).toBe(true);

        // 3. Verify Update (Fetch via WP API or TMGMT API if we had GET)
        // We can check the response or fetch again.
        // Let's add a Log Entry
        const logRes = await request.post(`${WP_API_URL}/events/${eventId}/log`, {
            headers: { 'Authorization': authHeader },
            data: {
                message: 'Log from API test',
                type: 'api_test'
            }
        });
        
        expect(logRes.ok()).toBeTruthy();
        const logData = await logRes.json();
        expect(logData.success).toBe(true);
    });
});
