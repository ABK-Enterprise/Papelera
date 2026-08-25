import { test, expect, type Page } from '@playwright/test';

async function loginAsSuperadmin(page: Page) {
    await page.goto('/login');
    await page.fill('#email', 'admin@admin.com');
    await page.fill('#password', 'admin');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*platform.*/);
}

test('1. Catálogo Visual de Módulos: Renderizado Bento, Indicadores y Permisos Granulares', async ({ page }) => {
    await loginAsSuperadmin(page);

    await page.goto('/platform/modules');
    await expect(page.locator('h1:has-text("Módulos y Permisos")')).toBeVisible();
    await expect(page.locator('text=Estructura Inmutable')).toBeVisible();

    // Validar KPIs de resumen
    await expect(page.locator('text=Módulos del Sistema')).toBeVisible();
    await expect(page.locator('span:has-text("Permisos Granulares")').first()).toBeVisible();
    await expect(page.locator('text=Adopción en Comercios')).toBeVisible();

    // Validar presencia de los 6 módulos fijos del sistema
    await expect(page.locator('.bento-card h3:has-text("Catálogo de Productos")')).toBeVisible();
    await expect(page.locator('.bento-card h3:has-text("Control de Stock y Kardex")')).toBeVisible();
    await expect(page.locator('.bento-card h3:has-text("Proveedores")')).toBeVisible();
    await expect(page.locator('.bento-card h3:has-text("Clientes")')).toBeVisible();
    await expect(page.locator('.bento-card h3:has-text("Pedidos y Ventas")')).toBeVisible();
    await expect(page.locator('.bento-card h3:has-text("Facturación ARCA")')).toBeVisible();

    // Validar estructura fija (sin botones de creación ni edición)
    await expect(page.locator('button:has-text("+ Nuevo Módulo")')).not.toBeVisible();
    await expect(page.locator('button:has-text("Editar")')).not.toBeVisible();
    await expect(page.locator('button:has-text("Eliminar")')).not.toBeVisible();
});

test('2. Filtrado y Búsqueda Reactiva en Módulos y Permisos', async ({ page }) => {
    await loginAsSuperadmin(page);

    await page.goto('/platform/modules');

    // Filtrar por término 'arca'
    const searchInput = page.locator('input[placeholder*="Filtrar por nombre"]');
    await searchInput.fill('arca');

    await expect(page.locator('.bento-card h3:has-text("Facturación ARCA")')).toBeVisible();
    await expect(page.locator('.bento-card h3:has-text("Catálogo de Productos")')).not.toBeVisible();

    // Filtrar por permiso específico 'stock_ingreso'
    await searchInput.fill('stock_ingreso');
    await expect(page.locator('.bento-card h3:has-text("Control de Stock y Kardex")')).toBeVisible();
    await expect(page.locator('.bento-card h3:has-text("Facturación ARCA")')).not.toBeVisible();

    // Limpiar buscador
    await searchInput.fill('');
    await expect(page.locator('.bento-card h3:has-text("Catálogo de Productos")')).toBeVisible();
    await expect(page.locator('.bento-card h3:has-text("Clientes")')).toBeVisible();
});
