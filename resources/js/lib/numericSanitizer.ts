/**
 * Utilidad unificada para blindaje y saneamiento de entradas numéricas en la interfaz de usuario.
 * Garantiza que valores porcentuales no excedan 100% y valores fijos/enteros no superen $100.000.000.
 */

export const CEILING_LIMITS = {
    PERCENTAGE_MIN: 0,
    PERCENTAGE_MAX: 100,
    FIXED_MIN: 0.01,
    FIXED_MAX: 100_000_000,
} as const;

/**
 * Previene el tipeo por teclado de caracteres no válidos como notación científica ('e', 'E', '+')
 * y opcionalmente signos negativos para campos estrictamente positivos.
 */
export function preventInvalidNumericKeys(e: KeyboardEvent, allowNegative = false) {
    if (['e', 'E', '+'].includes(e.key) || (!allowNegative && e.key === '-')) {
        e.preventDefault();
    }
}

/**
 * Sanea y acota en tiempo real el valor ingresado en un input numérico.
 * Evita desbordes sin romper la escritura de decimales en curso (ej. '12.').
 */
export function sanitizeNumericInput(
    e: Event,
    min: number,
    max: number,
    onValueUpdate: (val: number | string) => void
) {
    const input = e.target as HTMLInputElement;
    const raw = input.value;

    if (raw === '') {
        onValueUpdate('');
        return;
    }

    const num = parseFloat(raw);
    if (isNaN(num)) {
        input.value = '';
        onValueUpdate('');
        return;
    }

    // Recorte al techo si excede el máximo permitido
    if (num > max) {
        input.value = String(max);
        onValueUpdate(max);
        return;
    }

    // Recorte al piso si el valor es menor al mínimo (excepto si apenas está empezando a escribir)
    if (num < min && !raw.startsWith('-') && raw !== '0' && !raw.startsWith('0.')) {
        input.value = String(min);
        onValueUpdate(min);
        return;
    }

    // Dentro de rango: se conserva el valor tipiado para no destruir puntos decimales ('12.')
    onValueUpdate(raw);
}
