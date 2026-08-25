import { test, expect, type Page } from '@playwright/test';

async function loginAsSuperadmin(page: Page) {
    await page.goto('/login');
    await page.fill('#email', 'admin@admin.com');
    await page.fill('#password', 'admin');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*platform.*/);
}

test('1. Catálogo Visual de Módulos: Renderizado Minimalista, Permisos y Acción Desactivar/Activar', async ({ page }) => {
    await loginAsSuperadmin(page);

    await page.goto('/platform/modules');
    await expect(page.locator('h1:has-text("Gestión de Módulos")')).toBeVisible();

    // Validar que NO existan los cards KPI eliminados
    await expect(page.locator('text=Módulos del Sistema')).not.toBeVisible();
    await expect(page.locator('text=Adopción en Comercios')).not.toBeVisible();

    // Validar presencia de los 6 módulos fijos del sistema en cards limpias
    await expect(page.locator('h3:has-text("Catálogo de Productos")')).toBeVisible();
    await expect(page.locator('h3:has-text("Control de Stock y Kardex")')).toBeVisible();
    await expect(page.locator('h3:has-text("Proveedores")')).toBeVisible();
    await expect(page.locator('h3:has-text("Clientes")')).toBeVisible();
    await expect(page.locator('h3:has-text("Pedidos y Ventas")')).toBeVisible();
    await expect(page.locator('h3:has-text("Facturación ARCA")')).toBeVisible();

    // Validar botón de Desactivar / Activar en las cards
    const toggleBtn = page.locator('button:has-text("Desactivar")').first();
    await expect(toggleBtn).toBeVisible();

    // Validar que no hay botones de crear/editar
    await expect(page.locator('button:has-text("+ Nuevo Módulo")')).not.toBeVisible();
    await expect(page.locator('button:has-text("Editar")')).not.toBeVisible();
});

test('2. Filtrado y Búsqueda Reactiva en Módulos', async ({ page }) => {
    await loginAsSuperadmin(page);

    await page.goto('/platform/modules');

    // Filtrar por término 'arca'
    const searchInput = page.locator('input[placeholder*="Buscar módulo o permiso"]');
    await searchInput.fill('arca');

    await expect(page.locator('h3:has-text("Facturación ARCA")')).toBeVisible();
    await expect(page.locator('h3:has-text("Catálogo de Productos")')).not.toBeVisible();

    // Limpiar buscador
    await searchInput.fill('');
    await expect(page.locator('h3:has-text("Catálogo de Productos")')).toBeVisible();
    await expect(page.locator('h3:has-text("Clientes")')).toBeVisible();
});

test('3. Visualización de Roles Seeded en el Panel de Roles del Tenant', async ({ page }) => {
    await page.goto('/login');
    await page.fill('#email', 'axel@axel.com');
    await page.fill('#password', 'password');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*(dashboard|tenant).*/);

    await page.goto('/tenant/roles');
    await expect(page.locator('h1:has-text("Roles y Permisos")')).toBeVisible();

    // Validar que los roles creados por TestSeeder aparezcan en la vista del tenant
    await expect(page.locator('h3:has-text("Administrador de Sucursal")')).toBeVisible();
    await expect(page.locator('h3:has-text("Gestor de Stock")')).toBeVisible();
    await expect(page.locator('h3:has-text("Vendedor / Cajero")')).toBeVisible();
});
