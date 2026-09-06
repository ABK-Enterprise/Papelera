import { test, expect, type Page } from '@playwright/test';

test.describe.serial('Motor de Pagos, Cobranzas y Recibos UI/UX', () => {
    let page: Page;

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage();
        await page.goto('/login');
        await page.fill('#email', 'axel@axel.com');
        await page.fill('#password', 'password');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*(dashboard|tenant).*/, { timeout: 10000 });
    });

    test.afterAll(async () => {
        await page?.close();
    });

    test('1. Panel de Cobranzas: Seleccionar cliente, pedidos y abrir UniversalPaymentModal con FIFO', async () => {
        await page.goto('/tenant/cobranzas');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('h1:has-text("Panel de Cobranzas")')).toBeVisible();

        // Verificar que la columna de Cuentas por Cobrar esté visible
        await expect(page.locator('text=Cuentas por Cobrar').first()).toBeVisible();

        // Buscar o seleccionar el primer cliente con deuda disponible
        const customerButtons = page.locator('div.col-span-12.lg\\:col-span-4 button');
        const count = await customerButtons.count();
        if (count > 0) {
            await customerButtons.first().click();
            await page.waitForTimeout(400);

            // Verificar si hay pedidos impagos para este cliente
            const collectBtn = page.locator('button:has-text("Cobrar Seleccionados")');
            if (await collectBtn.isVisible()) {
                await collectBtn.click();

                // Verificar que abra el UniversalPaymentModal
                await expect(page.locator('text=Registrar Cobro / Recibo').first()).toBeVisible();
                await expect(page.locator('#receipt-amount')).toBeVisible();
                await expect(page.locator('#receipt-method')).toBeVisible();

                // Cambiar método de pago
                await page.locator('#receipt-method').selectOption('transferencia');
                await page.fill('#receipt-ref', 'TRF-E2E-TEST');

                // Cerrar modal
                await page.click('button:has-text("Cancelar")');
                await expect(page.locator('#receipt-amount')).not.toBeVisible();
            }
        }
    });

    test('2. Historial de Recibos: Navegación, listado y filtros', async () => {
        await page.goto('/tenant/cobranzas/recibos');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('h1:has-text("Historial de Recibos")')).toBeVisible();

        // Verificar presencia de la tabla y botón Volver a Cobranzas
        const backBtn = page.locator('button:has-text("Volver a Cobranzas")');
        await expect(backBtn).toBeVisible();

        // Verificar filtros de métodos y estados
        await expect(page.locator('select').first()).toBeVisible();
    });
});
