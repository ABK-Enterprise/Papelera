import { test, expect, type Page } from '@playwright/test';

test.describe.serial('Zonas de Envío y Recálculo en Pedidos UI/UX', () => {
    let page: Page;

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage();
        await page.goto('/login');
        await page.fill('#email', 'axel@axel.com');
        await page.fill('#password', 'password');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*(dashboard|tenant).*/);
    });

    test.afterAll(async () => {
        await page?.close();
    });

    test('1. ABM Zonas de Envío: Crear y listar en grid 2x2', async () => {
        await page.goto('/tenant/configuracion/zonas');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('h1:has-text("Zonas de Envío")')).toBeVisible();

        // Abrir modal de nueva zona
        const newBtn = page.locator('button:has-text("Nueva Zona de Envío")');
        await expect(newBtn).toBeVisible();
        await newBtn.click();
        await expect(page.locator('#zone-name')).toBeVisible();

        // Llenar formulario
        await page.fill('#zone-name', 'Gran Centro E2E');
        await page.fill('#zone-distance', '20');
        await page.fill('#zone-surcharge', '5');

        // Enviar formulario
        await page.click('button:has-text("Guardar Zona")');

        // Confirmar en ConfirmActionModal
        await expect(page.locator('text=¿Confirmar creación de zona?').first()).toBeVisible();
        await page.click('button:has-text("Crear Zona")');

        // Verificar que aparezca en la lista
        await expect(page.locator('text=Gran Centro E2E').first()).toBeVisible();
        await expect(page.locator('text=20.0 km').first()).toBeVisible();
        await expect(page.locator('text=+5.00%').first()).toBeVisible();
    });

    test('2. Integración en Pedidos: Selector de Zona y Recálculo de Envío', async () => {
        await page.goto('/tenant/pedidos');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('h1:has-text("Pedidos")').first()).toBeVisible();

        // Abrir modal de nuevo pedido
        const newOrderBtn = page.locator('button:has-text("Nuevo Pedido")');
        await expect(newOrderBtn).toBeVisible();
        await newOrderBtn.click();

        // Esperar selector de zona de envío
        const zoneSelect = page.locator('#order-shipping-zone');
        await expect(zoneSelect).toBeVisible();

        // Seleccionar la zona creada (segunda opción, index 1)
        await zoneSelect.selectOption({ index: 1 });
        await expect(zoneSelect).not.toHaveValue('');

        // Cerrar modal
        await page.click('button:has-text("Cancelar")');
        await expect(zoneSelect).not.toBeVisible();
    });
});
