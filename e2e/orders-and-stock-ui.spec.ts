import { test, expect } from '@playwright/test';

test('1. Pedidos: Modal Bento & Flujo Crear Bolsón Secundario', async ({ page }) => {
    await page.goto('/login');
    await page.fill('#email', 'axel@axel.com');
    await page.fill('#password', 'password123');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*(dashboard|tenant).*/);

    await page.goto('/tenant/orders');
    await expect(page.locator('text=Pedidos de Clientes').first()).toBeVisible();

    // Abrir modal de nuevo pedido
    await page.click('button:has-text("Nuevo Pedido")');
    await expect(page.locator('text=Nuevo Pedido').first()).toBeVisible();
    await expect(page.locator('text=Datos del Cliente').first()).toBeVisible();
    await expect(page.locator('text=Productos del Pedido').first()).toBeVisible();

    // Verificar botón Crear Bolsón
    const createPackageBtn = page.locator('button:has-text("Crear Bolsón")');
    await expect(createPackageBtn).toBeVisible();
    await createPackageBtn.click();

    // Modal Secundario Crear Bolsón
    const packageModal = page.locator('div[aria-label="Cerrar modal de bolsón"]');
    await expect(packageModal).toBeVisible();
    await expect(packageModal.locator('text=Contenido del Bolsón')).toBeVisible();

    // Cerrar modal secundario
    await packageModal.locator('button:has-text("Cancelar")').click();
    await expect(packageModal).not.toBeVisible();
});

test('2. Catálogo de Productos: Layout 2 Columnas Stitch & Cálculo de Bolsón', async ({ page }) => {
    await page.goto('/login');
    await page.fill('#email', 'axel@axel.com');
    await page.fill('#password', 'password123');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*(dashboard|tenant).*/);

    await page.goto('/tenant/products/create');
    await expect(page.locator('text=Alta de Producto').first()).toBeVisible();

    // Columna Izquierda: Empaque & Multimedia
    await expect(page.locator('text=Imagen & Empaque')).toBeVisible();
    await expect(page.locator('text=Cálculo de Bolsón')).toBeVisible();

    // Columna Derecha: Formulario
    await expect(page.locator('text=1. Información Básica')).toBeVisible();
    await expect(page.locator('text=2. Precios & Facturación (ARCA)')).toBeVisible();
    await expect(page.locator('text=3. Inventario & Empaque')).toBeVisible();

    // Interactuar con campos y verificar cálculo reactivo
    await page.fill('#create-prod-price', '1000');
    await page.fill('#create-prod-units-pack', '5');

    // El cálculo del bolsón debe reflejar el precio multiplicado
    await expect(page.locator('text=$ 5.000,00').first()).toBeVisible();
});

test('3. Ingresos de Stock: Tabla Minimalista & Pills de Filtros', async ({ page }) => {
    await page.goto('/login');
    await page.fill('#email', 'axel@axel.com');
    await page.fill('#password', 'password123');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*(dashboard|tenant).*/);

    await page.goto('/tenant/stock/ingresos');
    await expect(page.locator('text=Ingresos de Stock').first()).toBeVisible();

    // Verificar presencia de Pills de Filtro Rápido
    await expect(page.locator('button:has-text("Todos")').first()).toBeVisible();
    await expect(page.locator('button:has-text("Esta semana")').first()).toBeVisible();
    await expect(page.locator('button:has-text("Mes actual")').first()).toBeVisible();

    // Verificar botón Registrar Ingreso
    await expect(page.locator('button:has-text("Registrar Ingreso")')).toBeVisible();
});

test('4. Kardex: Bento Grid de Métricas & Auditoría Cronológica', async ({ page }) => {
    await page.goto('/login');
    await page.fill('#email', 'axel@axel.com');
    await page.fill('#password', 'password123');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/.*(dashboard|tenant).*/);

    await page.goto('/tenant/stock/kardex');
    await expect(page.locator('text=Kardex / Historial').first()).toBeVisible();

    // Verificar Bento Grid de Métricas
    await expect(page.locator('text=Total Ingresos de Stock').first()).toBeVisible();
    await expect(page.locator('text=Total Salidas / Devoluciones').first()).toBeVisible();
    await expect(page.locator('text=Balance Neto del Período').first()).toBeVisible();

    // Verificar Pills de Tipos
    await expect(page.locator('button:has-text("Todos")').first()).toBeVisible();
    await expect(page.locator('button:has-text("Solo Ingresos")').first()).toBeVisible();
    await expect(page.locator('button:has-text("Solo Egresos")').first()).toBeVisible();
});
