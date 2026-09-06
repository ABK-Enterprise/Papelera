import { test, expect, type Page } from '@playwright/test';

test.describe.serial('Pedidos y Flujos de Stock UI', () => {
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

    test('1. Pedidos: Modal Bento & Flujo Crear Pedido con Bulto y Autocomplete', async () => {
        await page.goto('/tenant/pedidos');
        await expect(page.locator('text=Pedidos de Clientes').first()).toBeVisible();

        // Abrir modal de nuevo pedido
        await page.waitForTimeout(300);
        await page.click('button:has-text("Nuevo Pedido")');
        await expect(page.locator('h2:has-text("Nuevo Pedido")').first()).toBeVisible();
        await expect(page.locator('text=Datos del Cliente').first()).toBeVisible();
        await expect(page.locator('text=Productos del Pedido').first()).toBeVisible();

        // Verificar buscador autocomplete de clientes
        const productSearch = page.locator('#cust-search');
        await expect(productSearch).toBeVisible();

        // Cerrar modal
        await page.click('button[aria-label="Cerrar modal"]');
        await expect(page.locator('#modal-title')).not.toBeVisible();
    });

    test('2. Catálogo de Productos: Layout 2 Columnas Stitch & Cálculo de Bulto', async () => {
        await page.goto('/tenant/productos/crear');
        await expect(page.locator('text=Alta de Producto').first()).toBeVisible();

        // Columna Izquierda: Empaque & Multimedia
        await expect(page.locator('text=Imagen y empaque').first()).toBeVisible();
        await expect(page.locator('text=Cálculo de bulto').first()).toBeVisible();

        // Columna Derecha: Formulario
        await expect(page.locator('text=1. Información básica').first()).toBeVisible();
        await expect(page.locator('text=2. Precios y facturación').first()).toBeVisible();
        await expect(page.locator('text=3. Inventario y empaque').first()).toBeVisible();

        // Interactuar con campos y verificar cálculo reactivo
        await page.fill('#create-prod-price', '1000');
        await page.fill('#create-prod-units-pack', '5');

        // El cálculo del bulto debe reflejar el precio con el descuento global por bulto del tenant (10%)
        await expect(page.locator('text=$ 4.500,00').first()).toBeVisible();
    });

    test('3. Ingresos de Stock: Tabla Minimalista & Pills de Filtros', async () => {
        await page.goto('/tenant/stock/ingresos');
        await expect(page.locator('h1:has-text("Ingresos de Stock")')).toBeVisible();

        // Verificar presencia de Pills de Filtro Rápido
        await expect(page.locator('button:has-text("Todos")').first()).toBeVisible();
        await expect(page.locator('button:has-text("Esta semana")').first()).toBeVisible();
        await expect(page.locator('button:has-text("Mes actual")').first()).toBeVisible();

        // Verificar botón Registrar Ingreso
        await expect(page.locator('button:has-text("Registrar Ingreso")')).toBeVisible();
    });

    test('4. Kardex: Bento Grid de Métricas & Auditoría Cronológica', async () => {
        await page.goto('/tenant/stock/kardex');
        await expect(page.locator('h1:has-text("Kardex")')).toBeVisible();

        // Verificar Bento Grid de Métricas
        await expect(page.locator('text=Total Ingresos de Stock').first()).toBeVisible();
        await expect(page.locator('text=Total Salidas / Devoluciones').first()).toBeVisible();
        await expect(page.locator('text=Balance Neto del Período').first()).toBeVisible();

        // Verificar Pills de Tipos
        await expect(page.locator('button:has-text("Todos")').first()).toBeVisible();
        await expect(page.locator('button:has-text("Solo Ingresos")').first()).toBeVisible();
        await expect(page.locator('button:has-text("Solo Egresos")').first()).toBeVisible();
    });
});
