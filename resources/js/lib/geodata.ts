export interface GeoProvince {
    id: string;
    name: string;
    iso_code?: string | null;
}

export interface GeoDepartment {
    id: string;
    province_id: string;
    name: string;
}

export interface GeoLocality {
    id: string;
    department_id: string;
    province_id: string;
    name: string;
}

export const ARGENTINE_PROVINCES_FALLBACK: string[] = [
    'Buenos Aires',
    'Catamarca',
    'Chaco',
    'Chubut',
    'Ciudad Autónoma de Buenos Aires',
    'Corrientes',
    'Córdoba',
    'Entre Ríos',
    'Formosa',
    'Jujuy',
    'La Pampa',
    'La Rioja',
    'Mendoza',
    'Misiones',
    'Neuquén',
    'Río Negro',
    'Salta',
    'San Juan',
    'San Luis',
    'Santa Cruz',
    'Santa Fe',
    'Santiago del Estero',
    'Tierra del Fuego, Antártida e Islas del Atlántico Sur',
    'Tucumán',
];

let provincesCache: GeoProvince[] | null = null;
const departmentsCache = new Map<string, GeoDepartment[]>();
const localitiesCache = new Map<string, GeoLocality[]>();

export async function fetchProvinces(): Promise<GeoProvince[]> {
    if (provincesCache && provincesCache.length > 0) {
        return provincesCache;
    }
    try {
        const res = await fetch('/tenant/geo/provincias');
        if (res.ok) {
            provincesCache = await res.json();
            return provincesCache || [];
        }
    } catch (e) {
        console.error('Error fetching provinces:', e);
    }
    return ARGENTINE_PROVINCES_FALLBACK.map((name, index) => ({
        id: String(index + 1).padStart(2, '0'),
        name,
        iso_code: null,
    }));
}

export async function fetchDepartments(provinceId: string): Promise<GeoDepartment[]> {
    if (!provinceId) return [];
    if (departmentsCache.has(provinceId)) {
        return departmentsCache.get(provinceId)!;
    }
    try {
        const res = await fetch(`/tenant/geo/departamentos?provincia_id=${encodeURIComponent(provinceId)}`);
        if (res.ok) {
            const data: GeoDepartment[] = await res.json();
            departmentsCache.set(provinceId, data);
            return data;
        }
    } catch (e) {
        console.error('Error fetching departments:', e);
    }
    return [];
}

export async function fetchLocalities(departmentId: string): Promise<GeoLocality[]> {
    if (!departmentId) return [];
    if (localitiesCache.has(departmentId)) {
        return localitiesCache.get(departmentId)!;
    }
    try {
        const res = await fetch(`/tenant/geo/localidades?departamento_id=${encodeURIComponent(departmentId)}`);
        if (res.ok) {
            const data: GeoLocality[] = await res.json();
            localitiesCache.set(departmentId, data);
            return data;
        }
    } catch (e) {
        console.error('Error fetching localities:', e);
    }
    return [];
}
