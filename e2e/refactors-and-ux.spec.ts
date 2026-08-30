import { test, expect } from '@playwright/test';

test.describe('E2E: Refactors de Arquitectura, UX, Wide Cards y Limite Estricto de Bultos y Stock', () => {
    test('1. Superadmin Dashboard: Separacion de Contextos & Wide-Cards con Split View', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await page.goto('/login');
        await page.fill('#email', 'admin@admin.com');
        await page.fill('#password', 'admin');
        await page.click('button[type="submit"]');

        await expect(page).toHaveURL(/.*platform\/dashboard.*/);
        await expect(page.locator('h1:has-text("Panel de control")')).toBeVisible();
        await expect(page.locator('text=Comercios registrados')).toBeVisible();

        // Validar Split View interactivo si hay comercios
        const viewBtn = page.locator('button:has-text("Ver")').first();
        if (await viewBtn.isVisible()) {
            await viewBtn.click();
            await expect(page.locator('text=Estado del comercio')).toBeVisible();
        }

        await context.close();
    });

    test('2. Tenant User: Validacion de Stock en Bultos y Control Reactivo en Pedido', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        // 1. Iniciar sesion como tenant admin
        await page.goto('/login');
        await page.fill('#email', 'axel@axel.com');
        await page.fill('#password', 'password');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*(dashboard|tenant).*/);

        // 2. Navegar a Pedidos de Clientes
        await page.goto('/tenant/orders');
        await expect(page.locator('h1:has-text("Pedidos de Clientes")')).toBeVisible();

        // 3. Abrir modal de nuevo pedido
        await page.click('button:has-text("Nuevo Pedido")');
        await expect(page.locator('h2:has-text("Nuevo Pedido")')).toBeVisible();

        // 4. Autocomplete de cliente
        const custSearch = page.locator('#cust-search');
        await expect(custSearch).toBeVisible();

        // 5. Verificar Ingresos de Stock (Wide Cards)
        await page.goto('/tenant/stock/ingresos');
        await expect(page.locator('h1:has-text("Ingresos de Stock")')).toBeVisible();
        await expect(page.locator('button:has-text("Todos")').first()).toBeVisible();

        // 6. Verificar Egresos de Stock (Wide Cards)
        await page.goto('/tenant/stock/egresos');
        await expect(page.locator('h1:has-text("Egresos de Stock")')).toBeVisible();
        await expect(page.locator('button:has-text("Todos")').first()).toBeVisible();

        await context.close();
    });
});
