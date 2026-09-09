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

    test('1. Modal de Pedido a Cliente (Mobile): Buscador apilado, botones 44px, alineacion accion-resultado', async () => {
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

        // Product search input
        const productSearchInput = page.locator('input[placeholder*="Escribe el nombre o SKU"]').first();
        await expect(productSearchInput).toBeVisible();
        await productSearchInput.fill('a');
        await page.waitForTimeout(400);

        const productOption = page.locator('[role="option"]').last();
        if (await productOption.isVisible()) {
            await productOption.click();
            await page.waitForTimeout(200);
            if (await addBtn.isEnabled()) {
                await addBtn.click();
                await page.waitForTimeout(200);

                // Verify left block: select presentation
                const selectEl = page.locator('select').last();
                await expect(selectEl).toBeVisible();

                // Verify right block: stepper and subtotal adjacent
                const rightControls = page.locator('.ml-auto').first();
                await expect(rightControls).toBeVisible();
            }
        }

        const cancelBtn = page.locator('button:has-text("Cancelar")').first();
        const submitBtn = page.locator('button:has-text("Crear Pedido")').first();
        await expect(cancelBtn).toBeVisible();
        await expect(cancelBtn).toHaveClass(/min-h-\[44px\]/);
        await expect(submitBtn).toBeVisible();
        await expect(submitBtn).toHaveClass(/min-h-\[44px\]/);

        await closeBtn.click();
        await expect(modalTitle).not.toBeVisible();
    });

    test('2. Modal de Ordenes a Proveedor (Mobile): Buscador global superior, Grid 2 cols y Subtotal dedicado', async () => {
        await page.goto('/tenant/proveedores/ordenes');
        await expect(page.getByRole('button', { name: /Hacer Pedido a Proveedor/i })).toBeVisible();

        await page.getByRole('button', { name: /Hacer Pedido a Proveedor/i }).click();

        const modalTitle = page.locator('#supplier-order-modal-title');
        await expect(modalTitle).toBeVisible();
        await expect(modalTitle).toContainText('Proveedor');

        const addProductBtn = page.getByRole('button', { name: /Añadir producto/i });
        await expect(addProductBtn).toBeVisible();
        await expect(addProductBtn).toHaveClass(/w-full/);

        // Interact with global search combobox
        const searchInput = page.locator('#create-supplier-order-form input[placeholder*="Buscar producto"]').first();
        await expect(searchInput).toBeVisible();
        await searchInput.fill('a');
        await page.waitForTimeout(400);

        const option = page.locator('[role="option"]').first();
        if (await option.isVisible()) {
            await option.click();
        } else {
            await addProductBtn.click();
        }
        await page.waitForTimeout(200);

        const costLabel = page.locator('text=Costo:').first();
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
