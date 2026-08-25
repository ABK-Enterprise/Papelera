import { test, expect } from '@playwright/test';

async function loginUser(page: any, email: string, pass: string, targetUrlRegex: RegExp) {
    await page.goto('/login');
    await page.fill('#email', email);
    await page.fill('#password', pass);
    await Promise.all([
        page.waitForURL(targetUrlRegex),
        page.click('button[type="submit"]'),
    ]);
}

test.describe('E2E: Refactors de Arquitectura, UX y Wide Cards', () => {
    test('1. Superadmin Dashboard: Separación de Contextos & KPIs Globales', async ({ page }) => {
        await loginUser(page, 'admin@admin.com', 'password123', /.*platform\/dashboard.*/);

        await expect(page.locator('text=Panel de Control de Plataforma').first()).toBeVisible();
        await expect(page.locator('text=Plataforma Global').first()).toBeVisible();
        await expect(page.locator('text=Comercios').first()).toBeVisible();
        await expect(page.locator('text=Usuarios Totales').first()).toBeVisible();
        await expect(page.locator('text=Catálogo Global').first()).toBeVisible();
    });

    test('2. Pedidos a Clientes: Autocomplete Flotante & Stepper con Límite de Stock', async ({ page }) => {
        await loginUser(page, 'axel@axel.com', 'password123', /.*(dashboard|tenant).*/);

        await page.goto('/tenant/orders');
        await expect(page.locator('h1:has-text("Pedidos de Clientes")')).toBeVisible();

        // Abrir modal de nuevo pedido
        await page.click('button:has-text("Nuevo Pedido")');
        const searchInput = page.locator('input[placeholder*="Escribe el nombre o SKU"]');
        await expect(searchInput).toBeVisible();

        // Probar autocomplete interactivo
        await searchInput.fill('Resma');
        await expect(page.locator('button:has-text("Resma")').first()).toBeVisible();

        // Seleccionar producto del dropdown
        await page.locator('button:has-text("Resma")').first().click();

        // Click en agregar al pedido
        await page.click('button:has-text("Agregar al Pedido")');

        // Verificar que aparezca en la lista de items con su nombre y stepper
        await expect(page.locator('h4:has-text("Resma")').first()).toBeVisible();
        await expect(page.locator('text=Stock actual:').first()).toBeVisible();
    });

    test('3. Ingresos de Stock: Wide Cards & Layout Expandido', async ({ page }) => {
        await loginUser(page, 'axel@axel.com', 'password123', /.*(dashboard|tenant).*/);

        await page.goto('/tenant/stock/ingresos');
        await expect(page.locator('h1:has-text("Ingresos de Stock")')).toBeVisible();
        await expect(page.locator('button:has-text("Todos")').first()).toBeVisible();
    });

    test('4. Egresos de Stock: Wide Cards & Layout Expandido', async ({ page }) => {
        await loginUser(page, 'axel@axel.com', 'password123', /.*(dashboard|tenant).*/);

        await page.goto('/tenant/stock/egresos');
        await expect(page.locator('h1:has-text("Egresos de Stock")')).toBeVisible();
        await expect(page.locator('button:has-text("Todos")').first()).toBeVisible();
    });
});
