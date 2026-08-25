import { test, expect, type Page, type BrowserContext } from '@playwright/test';

test.describe('E2E: Refactors de Arquitectura, UX y Wide Cards', () => {
    test('1. Superadmin Dashboard: Separación de Contextos & KPIs Globales', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        await page.goto('/login');
        await page.fill('#email', 'admin@admin.com');
        await page.fill('#password', 'password123');
        await page.click('button[type="submit"]');

        await expect(page).toHaveURL(/.*platform\/dashboard.*/);
        await expect(page.locator('text=Panel de Control de Plataforma').first()).toBeVisible();
        await expect(page.locator('text=Plataforma Global').first()).toBeVisible();
        await expect(page.locator('text=Comercios').first()).toBeVisible();
        await expect(page.locator('text=Usuarios Totales').first()).toBeVisible();
        await expect(page.locator('text=Catálogo Global').first()).toBeVisible();

        await context.close();
    });

    test('2. Tenant User: Pedidos, Modal de Bolsón, Ingresos y Egresos', async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        // 1. Iniciar sesión como tenant admin
        await page.goto('/login');
        await page.fill('#email', 'axel@axel.com');
        await page.fill('#password', 'password123');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*(dashboard|tenant).*/);

        // 2. Navegar a Pedidos de Clientes
        await page.goto('/tenant/orders');
        await expect(page.locator('h1:has-text("Pedidos de Clientes")')).toBeVisible();

        // 3. Abrir modal de nuevo pedido y verificar autocomplete interactivo
        await page.click('button:has-text("Nuevo Pedido")');
        const searchInput = page.locator('input[placeholder*="Escribe el nombre o SKU del producto para buscar..."]');
        await expect(searchInput).toBeVisible();

        await searchInput.fill('Resma');
        await expect(page.locator('button:has-text("Resma")').first()).toBeVisible();
        await page.locator('button:has-text("Resma")').first().click();

        await page.click('button:has-text("Agregar al Pedido")');
        await expect(page.locator('h4:has-text("Resma")').first()).toBeVisible();
        await expect(page.locator('text=Stock actual:').first()).toBeVisible();

        // 4. Modal de Bolsón Personalizado
        await page.click('button:has-text("Crear Bolsón Personalizado")');
        await expect(page.locator('h2:has-text("Nuevo Bolsón Personalizado")')).toBeVisible();

        const packageSearchInput = page.locator('input[placeholder*="Buscar producto por nombre o SKU..."]');
        await expect(packageSearchInput).toBeVisible();

        await packageSearchInput.fill('Resma');
        await expect(page.locator('button:has-text("Resma")').first()).toBeVisible();
        await page.locator('button:has-text("Resma")').first().click();

        // Agregar al bolsón con botón explícito y descriptivo
        await page.click('button:has-text("Agregar al Bolsón")');
        await expect(page.locator('h4:has-text("Resma")').first()).toBeVisible();
        await expect(page.locator('text=Total del Bolsón:').first()).toBeVisible();

        // Confirmar bolsón e insertar en el pedido padre
        await page.click('button:has-text("Agregar Bolsón al Pedido")');
        await expect(page.locator('span:has-text("BOLSÓN COMBO")').first()).toBeVisible();

        // 5. Verificar Ingresos de Stock (Wide Cards & Layout)
        await page.goto('/tenant/stock/ingresos');
        await expect(page.locator('h1:has-text("Ingresos de Stock")')).toBeVisible();
        await expect(page.locator('button:has-text("Todos")').first()).toBeVisible();

        // 6. Verificar Egresos de Stock (Wide Cards & Layout)
        await page.goto('/tenant/stock/egresos');
        await expect(page.locator('h1:has-text("Egresos de Stock")')).toBeVisible();
        await expect(page.locator('button:has-text("Todos")').first()).toBeVisible();

        await context.close();
    });
});
