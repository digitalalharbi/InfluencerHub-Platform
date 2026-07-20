// أدوات مشتركة لسيناريوهات E2E
export async function login(page, email = 'admin@a.test', password = process.env.E2E_PASSWORD) {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL('**/app**');
}
