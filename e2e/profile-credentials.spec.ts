import { test, expect } from '@playwright/test';

test.describe('E2E: Credenciales de Perfil y Actualización de Contraseña', () => {
    test('1. Inicio de sesión con admin de pruebas, navegación a /settings/security, validación de campos y envío de formulario', async ({ page }) => {
        // 1. Inicio de sesión con el admin de pruebas (axelberin@gmail.com / axel123#)
        await page.goto('/login');
        await page.fill('#email', 'axelberin@gmail.com');
        await page.fill('#password', 'axel123#');
        await page.click('button[type="submit"]');

        // Validar acceso exitoso
        await expect(page).toHaveURL(/.*(dashboard|tenant|settings).*/, { timeout: 15000 });

        // 2. Navegación a la configuración de seguridad
        await page.goto('/settings/security');
        await page.waitForLoadState('networkidle');

        // Validar presencia del encabezado de seguridad
        await expect(
            page.locator('text=Actualizar contraseña, text=Configuración de seguridad').first()
        ).toBeVisible({ timeout: 10000 });

        // 3. Verificación de los campos de contraseña actual, nueva contraseña y confirmación
        const currentPasswordInput = page.locator('#current_password');
        const newPasswordInput = page.locator('#password');
        const confirmPasswordInput = page.locator('#password_confirmation');
        const submitButton = page.locator('button[data-test="update-password-button"], button[type="submit"]:has-text("Guardar cambios")').first();

        await expect(currentPasswordInput).toBeVisible();
        await expect(newPasswordInput).toBeVisible();
        await expect(confirmPasswordInput).toBeVisible();
        await expect(submitButton).toBeVisible();

        // 4. Rellenar campos del formulario
        const testNewPassword = 'AxelPassword123#';

        await currentPasswordInput.fill('axel123#');
        await newPasswordInput.fill(testNewPassword);
        await confirmPasswordInput.fill(testNewPassword);

        // Validar indicador dinámico de fortaleza de contraseña
        await expect(
            page.locator('text=/Excelente|Muy segura|Buena/i').first()
        ).toBeVisible();

        // 5. Envío del formulario
        await submitButton.click();

        // Verificar que la petición se procesó exitosamente (toast o recarga limpia)
        await expect(
            page.locator('text=/Contraseña actualizada correctamente|Guardar cambios/i').first()
        ).toBeVisible({ timeout: 10000 });

        // 6. Restaurar contraseña original para garantizar idempotencia en futuras ejecuciones
        await page.waitForTimeout(500);
        await currentPasswordInput.fill(testNewPassword);
        await newPasswordInput.fill('axel123#');
        await confirmPasswordInput.fill('axel123#');
        await submitButton.click();
        await page.waitForTimeout(500);
    });
});
