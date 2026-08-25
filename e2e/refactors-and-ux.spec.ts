import { test, expect } from '@playwright/test';

test.describe('E2E: Refactors de Arquitectura, UX, Wide Cards y Límite Estricto de Bolsones', () => {
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

    test('2. Tenant User: Validación de Stock en Bolsón y Bloqueo de Incremento en Pedido Padre', async ({ browser }) => {
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

        // 3. Abrir modal de nuevo pedido
        await page.click('button:has-text("Nuevo Pedido")');
        await expect(page.locator('h2:has-text("Nuevo Pedido")')).toBeVisible();

        // 4. Abrir modal de Bolsón Personalizado
        await page.click('button:has-text("Crear Bolsón Personalizado")');
        await expect(page.locator('h2:has-text("Nuevo Bolsón Personalizado")')).toBeVisible();

        // 5. Buscar producto en el bolsón y asignar cantidad = 16 (sobre stock de 30)
        const packageSearchInput = page.locator('input[placeholder*="Buscar producto por nombre o SKU..."]');
        await expect(packageSearchInput).toBeVisible();

        await packageSearchInput.fill('Resma');
        await expect(page.locator('button:has-text("Resma")').first()).toBeVisible();
        await page.locator('button:has-text("Resma")').first().click();

        // Asignar cantidad = 16 unidades en el bolsón
        const cantInPackInput = page.locator('div.bg-muted\\/20 input[type="number"]');
        await cantInPackInput.fill('16');

        // Agregar al bolsón
        await page.click('button:has-text("Agregar al Bolsón")');
        await expect(page.locator('h4:has-text("Resma")').first()).toBeVisible();
        await expect(page.locator('text=16u').first()).toBeVisible();

        // Verificar que en el modal de bolsón el botón de aumentar bolsones esté deshabilitado (16 * 2 = 32 > 30 stock)
        const increasePackageQtyBtn = page.locator('button[aria-label="Aumentar cantidad de bolsones"]');
        await expect(increasePackageQtyBtn).toBeDisabled();

        // 6. Confirmar e insertar el bolsón (1 unidad) al pedido padre
        await page.click('button:has-text("Agregar Bolsón al Pedido")');

        // 7. En el pedido padre: verificar que el Bolsón Combo muestre el badge de Tope de Stock
        await expect(page.locator('span:has-text("BOLSÓN COMBO")').first()).toBeVisible();
        await expect(page.locator('span:has-text("Tope de stock")').first()).toBeVisible();

        // 8. Verificar que el botón de aumentar cantidad (+) del Bolsón en el modal padre esté DESHABILITADO
        const parentBolsontIncreaseBtn = page.locator('button[aria-label="Aumentar cantidad"]').first();
        await expect(parentBolsontIncreaseBtn).toBeDisabled();

        // Verificar que el span de cantidad del stepper del bolsón contenga exactamente '1'
        const stepperQty = parentBolsontIncreaseBtn.locator('..').locator('span');
        await expect(stepperQty).toHaveText('1');

        // 9. Verificar Ingresos de Stock (Wide Cards)
        await page.goto('/tenant/stock/ingresos');
        await expect(page.locator('h1:has-text("Ingresos de Stock")')).toBeVisible();
        await expect(page.locator('button:has-text("Todos")').first()).toBeVisible();

        // 10. Verificar Egresos de Stock (Wide Cards)
        await page.goto('/tenant/stock/egresos');
        await expect(page.locator('h1:has-text("Egresos de Stock")')).toBeVisible();
        await expect(page.locator('button:has-text("Todos")').first()).toBeVisible();

        await context.close();
    });
});
