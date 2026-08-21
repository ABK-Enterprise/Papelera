<?php

namespace App\Enums;

enum SystemModule: string
{
    case FacturacionArca = 'facturacion_arca';
    case InventarioStock = 'inventario_stock';
    case CuentasCorrientes = 'cuentas_corrientes';
    case PuntoDeVenta = 'punto_de_venta';

    /**
     * Get the human-readable name for the module.
     */
    public function label(): string
    {
        return match ($this) {
            self::FacturacionArca => 'Facturación ARCA',
            self::InventarioStock => 'Inventario y Stock',
            self::CuentasCorrientes => 'Cuentas Corrientes',
            self::PuntoDeVenta => 'Punto de Venta (POS)',
        };
    }
}
