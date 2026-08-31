import { test, expect, type Page } from '@playwright/test';

async function loginAsSuperadmin(page: Page) {
    await page.goto('/login');
    await page.fill('#email', 'admin@admin.com');
    await page.fill('#password', 'admin');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*platform.*/);
}

test('1. Catalogo Visual de Modulos: Renderizado Minimalista, Permisos y Dialog de Detalle', async ({ page }) => {
    await loginAsSuperadmin(page);

    await page.goto('/platform/modules');
    await expect(page.locator('h1:has-text("Módulos del sistema")').first()).toBeVisible();

    // Validar que NO existan los cards KPI eliminados
    await expect(page.locator('text=Adopción en Comercios')).not.toBeVisible();

    // Validar presencia de los modulos del sistema en cards limpias
    await expect(page.locator('h3:has-text("Productos")').first()).toBeVisible();
    await expect(page.locator('h3:has-text("Stock")').first()).toBeVisible();
    await expect(page.locator('h3:has-text("Proveedores")').first()).toBeVisible();
    await expect(page.locator('h3:has-text("Clientes")').first()).toBeVisible();
    await expect(page.locator('h3:has-text("Pedidos")').first()).toBeVisible();

    // Validar boton de "Ver detalle" que abre el dialog descriptivo
    const detailBtn = page.locator('button:has-text("Ver detalle")').first();
    await expect(detailBtn).toBeVisible();
    await detailBtn.click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await expect(page.getByRole('dialog').locator('button:has-text("Cerrar")').last()).toBeVisible();
    await page.getByRole('dialog').locator('button:has-text("Cerrar")').last().click();

    // Validar que no hay botones de crear/editar o desactivar
    await expect(page.locator('button:has-text("+ Nuevo Módulo")')).not.toBeVisible();
    await expect(page.locator('button:has-text("Desactivar")')).not.toBeVisible();
});

test('2. Filtrado y Busqueda Reactiva en Modulos', async ({ page }) => {
    await loginAsSuperadmin(page);

    await page.goto('/platform/modules');

    // Filtrar por termino 'arca'
    const searchInput = page.locator('input[placeholder*="Buscar por módulo o permiso"]');
    await searchInput.fill('arca');

    await expect(page.locator('h3:has-text("Facturación ARCA")').first()).toBeVisible();
    await expect(page.locator('h3:has-text("Productos")').first()).not.toBeVisible();

    // Limpiar buscador
    await searchInput.fill('');
    await expect(page.locator('h3:has-text("Productos")').first()).toBeVisible();
    await expect(page.locator('h3:has-text("Clientes")').first()).toBeVisible();
});

test('3. Visualizacion de Roles Seeded en el Panel de Roles del Tenant', async ({ page }) => {
    await page.goto('/login');
    await page.fill('#email', 'axel@axel.com');
    await page.fill('#password', 'password');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*(dashboard|tenant).*/);

    await page.goto('/tenant/roles');
    await expect(page.locator('h1:has-text("Roles y Permisos")').first()).toBeVisible();

    // Validar que los roles operativos creados por TestSeeder aparezcan en la vista del tenant y admin este excluido
    await expect(page.locator('h3:has-text("Gestor de Stock")').first()).toBeVisible();
    await expect(page.locator('h3:has-text("Vendedor / Cajero")').first()).toBeVisible();
    await expect(page.locator('h3:has-text("Administrador")')).not.toBeVisible();
    await expect(page.locator('h3:has-text("admin")')).not.toBeVisible();
});
