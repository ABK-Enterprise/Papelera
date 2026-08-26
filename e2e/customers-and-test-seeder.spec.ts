import { test, expect, type Page } from '@playwright/test';

test.describe.serial('Directorio de Clientes y Gestión', () => {
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

    test('1. Directorio de Clientes: Renderizado de Wide Cards y Filtros', async () => {
        await page.goto('/tenant/customers');
        await expect(page.locator('text=Directorio de Clientes').first()).toBeVisible();

        // Verificar Wide Cards de clientes cargados por TestSeeder
        await expect(page.locator('h3:has-text("Librería El Ateneo Central")').first()).toBeVisible();
        await expect(page.locator('h3:has-text("Gráfica Central SRL")').first()).toBeVisible();

        // Verificar Badges fiscales y de morosidad
        await expect(page.locator('span:has-text("Resp. Inscripto")').first()).toBeVisible();
        await expect(page.locator('text=Moroso / Saldo Pendiente').first()).toBeVisible();

        // Probar Filtro de búsqueda
        const searchInput = page.locator('input[placeholder*="Buscar por nombre"]');
        await searchInput.fill('Ateneo');
        await searchInput.press('Enter');

        await expect(page.locator('h3:has-text("Librería El Ateneo Central")').first()).toBeVisible();
        await expect(page.locator('h3:has-text("Gráfica Central SRL")')).not.toBeVisible();

        // Limpiar filtro
        await page.click('button:has-text("Limpiar")');
        await expect(page.locator('h3:has-text("Gráfica Central SRL")').first()).toBeVisible();
    });

    test('2. Clientes: Alta y Modificación vía Modales Minimalistas', async () => {
        await page.goto('/tenant/customers');

        const uniqueSuffix = Date.now().toString().slice(-6);
        const uniqueCustName = `Librería E2E ${uniqueSuffix}`;
        const uniqueTaxId = `20-${uniqueSuffix}11-4`;

        // Abrir modal de nuevo cliente
        await page.click('button:has-text("+ Añadir Cliente")');
        const createModal = page.locator('div[role="dialog"]');
        await expect(createModal).toBeVisible();
        await expect(createModal.locator('text=Añadir Nuevo Cliente')).toBeVisible();

        // Rellenar formulario de alta
        await page.fill('#create-customer-name', uniqueCustName);
        await page.fill('#create-customer-tax-id', uniqueTaxId);
        await page.selectOption('#create-customer-tax-condition', 'monotributo');
        await page.fill('#create-customer-phone', '+54 11 5544-3322');
        await page.fill('#create-customer-address', 'Calle Falsa 123');

        await page.click('button[type="submit"]:has-text("Registrar Cliente")');

        // Esperar a que el modal de creación se cierre
        await expect(createModal).not.toBeVisible();

        // Verificar que aparezca en la lista de clientes
        const newCustomerCard = page.locator(`.bento-card:has-text("${uniqueCustName}")`).first();
        await expect(newCustomerCard).toBeVisible();
        await expect(newCustomerCard.locator(`text=${uniqueTaxId}`)).toBeVisible();

        // Abrir Modal de Gestión de Cliente
        await newCustomerCard.click();
        const manageModal = page.locator('div[role="dialog"]');
        await expect(manageModal).toBeVisible();
        await expect(manageModal.locator('text=Gestión de Cliente')).toBeVisible();

        // Modificar notas y guardar cambios
        await page.fill('#manage-customer-notes', 'Actualizado durante prueba automatizada E2E');
        await page.click('button[type="submit"]:has-text("Guardar cambios")');

        await expect(manageModal).not.toBeVisible();
    });

    test('3. Pedidos: Autocomplete flotante de Clientes y Autocompletado reactivo', async () => {
        await page.goto('/tenant/orders');
        await expect(page.locator('text=Pedidos de Clientes').first()).toBeVisible();

        // Abrir modal de creación de pedido
        await page.click('button:has-text("Nuevo Pedido")');
        const orderModal = page.locator('div[role="dialog"]');
        await expect(orderModal).toBeVisible();
        await expect(orderModal.locator('h3:has-text("Datos del Cliente")')).toBeVisible();

        // Buscar cliente en el autocomplete
        const custSearchInput = page.locator('#cust-search');
        await custSearchInput.fill('Ateneo');

        // Seleccionar cliente de la lista flotante
        const suggestionBtn = page.locator('button:has-text("Librería El Ateneo Central")');
        await expect(suggestionBtn).toBeVisible();
        await suggestionBtn.click();

        // Verificar autocompletado en los campos de cliente
        await expect(page.locator('#cust-name')).toHaveValue('Librería El Ateneo Central');
        await expect(page.locator('#cust-phone')).toHaveValue('+54 11 4321-9988');
        await expect(page.locator('#cust-address')).toHaveValue('Av. Santa Fe 1860, CABA');

        // Probar selección de cliente moroso y verificar alerta
        await custSearchInput.fill('Norte');
        const defaulterBtn = page.locator('button:has-text("Distribuidora Mayorista Norte")');
        await expect(defaulterBtn).toBeVisible();
        await defaulterBtn.click();

        // Verificar que aparezca la alerta de morosidad
        await expect(page.locator('text=Este cliente posee condición de moroso')).toBeVisible();
    });
});
