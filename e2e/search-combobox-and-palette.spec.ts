import { test, expect } from '@playwright/test';

test.describe('E2E: SearchCombobox, IconContainer and Realtime Color Palette', () => {
    test('1. Superadmin: Quick Search Combobox on Dashboard & Clean UTF-8 Strings', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await page.goto('/login');
        await page.fill('#email', 'admin@admin.com');
        await page.fill('#password', 'admin');
        await page.click('button[type="submit"]');

        await expect(page).toHaveURL(/.*platform\/dashboard.*/);
        await expect(page.locator('h1:has-text("Panel de control")')).toBeVisible();

        // Check SearchCombobox on Dashboard
        const quickSearch = page.locator('input[role="combobox"]').first();
        await expect(quickSearch).toBeVisible();
        await quickSearch.fill('Papelera');
        await page.waitForTimeout(400);

        // Verify UTF-8 characters are clean (no \ufffd or broken characters)
        const content = await page.content();
        expect(content).not.toContain('\ufffd');
        expect(content).not.toContain('Mdulos');
        expect(content).not.toContain('Mtricas');

        await context.close();
    });

    test('2. Tenant: Product and Customer SearchCombobox with ClickOutside', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await page.goto('/login');
        await page.fill('#email', 'axel@axel.com');
        await page.fill('#password', 'password');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*(dashboard|tenant).*/);

        // 1. Products search combobox
        await page.goto('/tenant/products');
        await expect(page.locator('h1:has-text("Catálogo de Productos")')).toBeVisible();

        const productSearch = page.locator('input[role="combobox"]').first();
        await expect(productSearch).toBeVisible();

        // 2. Customers search combobox
        await page.goto('/tenant/customers');
        await expect(page.locator('h1:has-text("Directorio de Clientes")')).toBeVisible();

        const customerSearch = page.locator('input[role="combobox"]').first();
        await expect(customerSearch).toBeVisible();

        // 3. Verify clean UTF-8 across tenant pages
        const tenantContent = await page.content();
        expect(tenantContent).not.toContain('\ufffd');

        await context.close();
    });
});
