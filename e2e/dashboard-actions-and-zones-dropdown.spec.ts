import { test, expect, type Page } from '@playwright/test';

test.describe.serial('Dashboard Actions, Config Dropdown & Order Payment UI/UX', () => {
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

    test('1. Dropdown de Configuración: Enlace visible a Zonas de Envío', async () => {
        await page.goto('/dashboard');
        // Abrir dropdown de usuario
        const avatarTrigger = page.locator('button[aria-haspopup="menu"]').first();
        if (await avatarTrigger.isVisible()) {
            await avatarTrigger.click();
            await expect(page.locator('a[href="/tenant/configuracion/zonas"]').first()).toBeVisible();
        }
    });

    test('2. Dashboard: Redirección diferida (300ms) a Nuevo Pedido y limpieza de URL', async () => {
        await page.goto('/dashboard');
        const newOrderBtn = page.getByRole('link', { name: 'Nuevo Pedido' });
        await expect(newOrderBtn).toBeVisible();
        await newOrderBtn.click();

        // Verificar que abre el modal de creación tras el timeout
        await expect(page.locator('div[role="dialog"] h2:has-text("Nuevo Pedido")')).toBeVisible({ timeout: 5000 });

        // Verificar que la URL se limpió y ya no tiene ?crear=1
        await page.waitForTimeout(400);
        expect(page.url()).not.toContain('crear=1');
    });

    test('3. Modal de Pedido: Bloque de Condición de Cobro (Total, Parcial, Cta Cte)', async () => {
        await page.goto('/tenant/pedidos');
        await page.waitForLoadState('networkidle');
        await page.click('button:has-text("Nuevo Pedido")');

        const modal = page.locator('div[role="dialog"]');
        await expect(modal).toBeVisible();

        // Verificar opciones de condición de cobro
        await expect(modal.locator('text=Cuenta Corriente').first()).toBeVisible();
        await expect(modal.locator('text=Cobro Total').first()).toBeVisible();
        await expect(modal.locator('text=Pago Parcial / Seña').first()).toBeVisible();
    });
});
