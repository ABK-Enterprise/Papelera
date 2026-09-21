export interface ProvinceGeographicData {
    province: string;
    cities: string[];
}

export const ARGENTINA_GEOGRAPHY: Record<string, string[]> = {
    'Buenos Aires': [
        'La Plata',
        'Mar del Plata',
        'Bahía Blanca',
        'Tandil',
        'Olavarría',
        'San Nicolás',
        'Pergamino',
        'Junín',
        'Zárate',
        'Campana',
        'Quilmes',
        'Lomas de Zamora',
        'San Isidro',
        'Vicente López',
        'Morón',
        'Avellaneda',
        'Lanús',
        'Pilar',
        'Tigre',
        'Moreno',
        'Mercedes',
        'Chivilcoy',
        'Necochea',
        'Azul',
        'San Pedro',
    ],
    'Entre Ríos': [
        'Paraná',
        'Concordia',
        'Gualeguaychú',
        'Concepción del Uruguay',
        'Gualeguay',
        'Villaguay',
        'Chajarí',
        'Victoria',
        'La Paz',
        'Nogoyá',
        'Colón',
        'Diamante',
        'Santa Elena',
        'Crespo',
        'Federación',
        'Basavilbaso',
        'San José',
        'Viale',
    ],
};

export const PROVINCES_LIST = Object.keys(ARGENTINA_GEOGRAPHY);
