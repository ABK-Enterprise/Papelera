import { test, expect } from '@playwright/test';

test.describe('E2E: Gestión de Logo del Comercio y Modal Accesible', () => {
    test('Apertura de modal de logo desde Company.svelte, verificación de UI y cierre accesible', async ({ page }) => {
        // 1. Iniciar sesión con admin del comercio
        await page.goto('/login');
        await page.fill('#email', 'axelberin@gmail.com');
        await page.fill('#password', 'axel123#');
        await page.click('button[type="submit"]');

        await expect(page).toHaveURL(/.*(dashboard|tenant).*/, { timeout: 15000 });

        // 2. Navegar a datos del comercio
        await page.goto('/tenant/configuracion/comercio');
        await page.waitForLoadState('networkidle');

        // 3. Validar presencia del control interactivo de logo con botón lápiz
        const logoAvatar = page.locator('div[aria-label="Editar logo del comercio"]').first();
        await expect(logoAvatar).toBeVisible({ timeout: 10000 });

        // 4. Hacer clic para abrir el diálogo
        await logoAvatar.click();

        // 5. Validar encabezado conciso y elementos del modal
        await expect(page.locator('text=Editar logo').first()).toBeVisible({ timeout: 5000 });
        await expect(page.locator('button:has-text("Cerrar")').first()).toBeVisible();
        await expect(page.locator('button:has-text("Guardar")').first()).toBeVisible();

        // 6. Cerrar el modal limpiamente
        await page.locator('button:has-text("Cerrar")').first().click();
        await expect(page.locator('text=Editar logo')).not.toBeVisible({ timeout: 5000 });
    });
});
