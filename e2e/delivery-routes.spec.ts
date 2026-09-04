import { test, expect, type Page } from '@playwright/test';

test.describe.serial('Logística de Repartos y Última Milla UI/UX', () => {
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

    test('1. Panel de Repartos: Navegación, Widget Caja del Turno y Pestañas', async () => {
        await page.goto('/tenant/repartos');
        await page.waitForLoadState('networkidle');

        // Verificar encabezado de la página
        await expect(page.locator('h1:has-text("Repartos y Última Milla")')).toBeVisible();

        // Verificar Widget Caja del Turno
        await expect(page.locator('text=Efectivo en Mano').first()).toBeVisible();
        await expect(page.locator('text=Transferencias / QR').first()).toBeVisible();

        // Verificar Pestañas operativas
        await expect(page.locator('button:has-text("Mi Ruta en Curso")').first()).toBeVisible();
        await expect(page.locator('button:has-text("Bolsa de Pedidos Disponibles")').first()).toBeVisible();
    });

    test('2. Bolsa de Trabajo: Navegación entre pestañas y selección', async () => {
        await page.goto('/tenant/repartos');
        await page.waitForLoadState('networkidle');

        // Cambiar a Bolsa de Pedidos Disponibles
        const bolsaBtn = page.locator('button:has-text("Bolsa de Pedidos Disponibles")').first();
        await bolsaBtn.click();
        await page.waitForTimeout(300);

        // Verificar que estamos en la bolsa
        const isListVisible = await page.locator('button:has-text("Tomar para Mi Ruta")').count();
        const isEmptyState = await page.locator('text=No hay pedidos disponibles en depósito').isVisible();

        expect(isListVisible > 0 || isEmptyState).toBeTruthy();
    });

    test('3. Modales de Entrega y No Entregado (Happy / Unhappy Path UI)', async () => {
        await page.goto('/tenant/repartos');
        await page.waitForLoadState('networkidle');

        // Si hay pedidos en Mi Ruta
        const deliverBtn = page.locator('button:has-text("Marcar como Entregado")').first();
        if (await deliverBtn.isVisible()) {
            await deliverBtn.click();
            await page.waitForTimeout(300);

            // Verificar diálogo de entrega
            await expect(page.locator('text=Confirmar Entrega de Pedido').first()).toBeVisible();
            await expect(page.locator('text=Modalidad de cobro').first()).toBeVisible();
            await expect(page.locator('#delivery-notes')).toBeVisible();

            // Cerrar modal
            await page.locator('button:has-text("Cancelar")').click();
            await page.waitForTimeout(200);
            await expect(page.locator('text=Confirmar Entrega de Pedido')).not.toBeVisible();
        }

        // Probar modal de No Entregado
        const failBtn = page.locator('button:has-text("No Entregado")').first();
        if (await failBtn.isVisible()) {
            await failBtn.click();
            await page.waitForTimeout(300);

            // Verificar diálogo de no entregado
            await expect(page.locator('text=Registrar Visita Fallida').first()).toBeVisible();
            await expect(page.locator('#failure-reason')).toBeVisible();
            await expect(page.locator('#failure-notes')).toBeVisible();

            // Cerrar modal
            await page.locator('button:has-text("Cancelar")').click();
            await page.waitForTimeout(200);
            await expect(page.locator('text=Registrar Visita Fallida')).not.toBeVisible();
        }
    });
});
