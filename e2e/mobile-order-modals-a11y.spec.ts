import { test, expect, type Page } from '@playwright/test';

test.describe.serial('Mobile UI/UX y Accesibilidad en Modales de Pedidos', () => {
    let page: Page;

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage({
            viewport: { width: 375, height: 667 },
            userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
        });

        await page.goto('/login');
        await page.fill('#email', 'axel@axel.com');
        await page.fill('#password', 'password');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*(dashboard|tenant).*/);
    });

    test.afterAll(async () => {
        await page?.close();
    });

    test('1. Modal de Pedido a Cliente (Mobile): Buscador apilado, botones 44px y header resiliente', async () => {
        await page.goto('/tenant/pedidos');
        await expect(page.getByRole('button', { name: /Nuevo Pedido/i })).toBeVisible();

        await page.getByRole('button', { name: /Nuevo Pedido/i }).click();

        const modalTitle = page.locator('#modal-title');
        await expect(modalTitle).toBeVisible();
        await expect(modalTitle).toContainText('Nuevo Pedido');
        const closeBtn = page.locator('button[aria-label="Cerrar modal"]');
        await expect(closeBtn).toBeVisible();

        const addBtn = page.locator('button:has-text("Agregar")').first();
        await expect(addBtn).toBeVisible();
        await expect(addBtn).toHaveClass(/w-full/);
        await expect(addBtn).toHaveClass(/min-h-\[44px\]/);

        const cancelBtn = page.locator('button:has-text("Cancelar")').first();
        const submitBtn = page.locator('button:has-text("Crear Pedido")').first();
        await expect(cancelBtn).toBeVisible();
        await expect(cancelBtn).toHaveClass(/min-h-\[44px\]/);
        await expect(submitBtn).toBeVisible();
        await expect(submitBtn).toHaveClass(/min-h-\[44px\]/);

        await closeBtn.click();
        await expect(modalTitle).not.toBeVisible();
    });

    test('2. Modal de Ordenes a Proveedor (Mobile): Grid 2 cols para Costo/Cantidad, Subtotal dedicado y Footer apilado', async () => {
        await page.goto('/tenant/proveedores/ordenes');
        await expect(page.getByRole('button', { name: /Hacer Pedido a Proveedor/i })).toBeVisible();

        await page.getByRole('button', { name: /Hacer Pedido a Proveedor/i }).click();

        const modalTitle = page.locator('#supplier-order-modal-title');
        await expect(modalTitle).toBeVisible();
        await expect(modalTitle).toContainText('Proveedor');

        const addProductBtn = page.getByRole('button', { name: /Añadir producto/i });
        await expect(addProductBtn).toBeVisible();
        await expect(addProductBtn).toHaveClass(/w-full/);

        const costLabel = page.locator('text=Costo Unit:').first();
        await expect(costLabel).toBeVisible();

        const mobileSubtotal = page.locator('text=Subtotal Estimado:').first();
        await expect(mobileSubtotal).toBeVisible();

        const cancelBtn = page.locator('button:has-text("Cancelar")').last();
        const submitBtn = page.locator('button:has-text("Crear Orden")');
        await expect(cancelBtn).toBeVisible();
        await expect(cancelBtn).toHaveClass(/min-h-\[44px\]/);
        await expect(submitBtn).toBeVisible();
        await expect(submitBtn).toHaveClass(/min-h-\[44px\]/);

        const closeBtn = page.locator('button[aria-label="Cerrar modal"]').first();
        await closeBtn.click();
        await expect(modalTitle).not.toBeVisible();
    });
});
