import { test, expect } from '@playwright/test';

test.describe('E2E: Verificación Visual de Pedido Cancelado con Nota de Crédito Pendiente', () => {
    test('1. Apertura de modal de detalle de pedido cancelado con factura activa, validando pill "Pendiente NC" y botón "Emitir Nota de Crédito ARCA"', async ({ page }) => {
        // 1. Iniciar sesión como administrador del comercio
        await page.goto('/login');
        await page.fill('#email', 'axelberin@gmail.com');
        await page.fill('#password', 'axel123#');
        await page.click('button[type="submit"]');

        await expect(page).toHaveURL(/.*(dashboard|tenant).*/, { timeout: 15000 });

        // 2. Navegar al módulo de Pedidos de Clientes
        await page.goto('/tenant/pedidos');
        await page.waitForLoadState('networkidle');

        await expect(page.locator('text=Pedidos de Clientes').first()).toBeVisible({ timeout: 10000 });

        // 3. Filtrar o buscar el pedido cancelado con factura pendiente de NC (PED-0008)
        const searchInput = page.locator('input[placeholder*="Buscar por cliente"], input[placeholder*="Buscar"]').first();
        if (await searchInput.isVisible()) {
            await searchInput.fill('PED-0008');
            await page.waitForTimeout(400);
        }

        // Localizar y hacer clic en la fila / tarjeta del pedido
        const orderCard = page.locator('text=PED-0008').first();
        await expect(orderCard).toBeVisible({ timeout: 8000 });
        await orderCard.click();

        // 4. Verificar apertura del modal de detalle de pedido
        const detailModal = page.locator('div[role="dialog"]').first();
        await expect(detailModal).toBeVisible({ timeout: 5000 });

        // Validar que el modal contenga el número de pedido
        await expect(detailModal.locator('text=PED-0008').first()).toBeVisible();

        // Validar que muestre el badge de estado "Cancelado"
        await expect(
            detailModal.locator('span:has-text("Cancelado")').first()
        ).toBeVisible();

        // 5. Comprobar la presencia del pill "Pendiente NC"
        const pendingNcPill = detailModal.locator('span:has-text("Pendiente NC")').first();
        await expect(pendingNcPill).toBeVisible();

        // 6. Comprobar la presencia del botón de acción "Emitir Nota de Crédito ARCA"
        const emitNcButton = detailModal.locator('button:has-text("Emitir Nota de Crédito ARCA")').first();
        await expect(emitNcButton).toBeVisible();

        // 7. Cerrar el modal de detalle
        const closeButton = detailModal.locator('button[aria-label="Cerrar"], button:has-text("Cerrar")').first();
        if (await closeButton.isVisible()) {
            await closeButton.click();
        } else {
            await page.keyboard.press('Escape');
        }

        await page.waitForTimeout(300);
    });
});
