<?php

namespace App\Enums;

enum SystemModule: string
{
    case Productos = 'productos';
    case Stock = 'stock';
    case Proveedores = 'proveedores';
    case Clientes = 'clientes';
    case Pedidos = 'pedidos';
    case FacturacionArca = 'facturacion_arca';
    case InventarioStock = 'inventario_stock';

    /**
     * Get the human-readable name for the module.
     */
    public function label(): string
    {
        return match ($this) {
            self::Productos => 'Catálogo de Productos',
            self::Stock => 'Control de Stock y Kardex',
            self::Proveedores => 'Proveedores',
            self::Clientes => 'Clientes',
            self::Pedidos => 'Pedidos y Ventas',
            self::FacturacionArca => 'Facturación ARCA',
            self::InventarioStock => 'Inventario y Stock (Legacy)',
        };
    }
}
