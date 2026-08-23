<?php

namespace App\Enums;

enum SystemModule: string
{
    case InventarioStock = 'inventario_stock';
    case FacturacionArca = 'facturacion_arca';
    case Proveedores = 'proveedores';

    /**
     * Get the human-readable name for the module.
     */
    public function label(): string
    {
        return match ($this) {
            self::InventarioStock => 'Inventario y Stock',
            self::FacturacionArca => 'Facturación ARCA',
            self::Proveedores => 'Proveedores',
        };
    }
}
