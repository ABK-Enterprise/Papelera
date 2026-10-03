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

export const ARGENTINE_PROVINCES_FALLBACK: GeoProvince[] = [
    { id: '02', name: 'Ciudad Autónoma de Buenos Aires', iso_code: 'AR-C' },
    { id: '06', name: 'Buenos Aires', iso_code: 'AR-B' },
    { id: '10', name: 'Catamarca', iso_code: 'AR-K' },
    { id: '14', name: 'Córdoba', iso_code: 'AR-X' },
    { id: '18', name: 'Corrientes', iso_code: 'AR-W' },
    { id: '22', name: 'Chaco', iso_code: 'AR-H' },
    { id: '26', name: 'Chubut', iso_code: 'AR-U' },
    { id: '30', name: 'Entre Ríos', iso_code: 'AR-E' },
    { id: '34', name: 'Formosa', iso_code: 'AR-P' },
    { id: '38', name: 'Jujuy', iso_code: 'AR-Y' },
    { id: '42', name: 'La Pampa', iso_code: 'AR-L' },
    { id: '46', name: 'La Rioja', iso_code: 'AR-F' },
    { id: '50', name: 'Mendoza', iso_code: 'AR-M' },
    { id: '54', name: 'Misiones', iso_code: 'AR-N' },
    { id: '58', name: 'Neuquén', iso_code: 'AR-Q' },
    { id: '62', name: 'Río Negro', iso_code: 'AR-R' },
    { id: '66', name: 'Salta', iso_code: 'AR-A' },
    { id: '70', name: 'San Juan', iso_code: 'AR-J' },
    { id: '74', name: 'San Luis', iso_code: 'AR-D' },
    { id: '78', name: 'Santa Cruz', iso_code: 'AR-Z' },
    { id: '82', name: 'Santa Fe', iso_code: 'AR-S' },
    { id: '86', name: 'Santiago del Estero', iso_code: 'AR-G' },
    { id: '90', name: 'Tucumán', iso_code: 'AR-T' },
    { id: '94', name: 'Tierra del Fuego, Antártida e Islas del Atlántico Sur', iso_code: 'AR-V' },
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
            const data = await res.json();
            if (Array.isArray(data) && data.length > 0) {
                provincesCache = data;
                return provincesCache;
            }
        }
    } catch (e) {
        console.error('Error fetching provinces:', e);
    }
    return ARGENTINE_PROVINCES_FALLBACK;
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

export async function fetchLocalitiesByProvince(provinceId: string): Promise<GeoLocality[]> {
    if (!provinceId) return [];
    const cacheKey = `prov_${provinceId}`;
    if (localitiesCache.has(cacheKey)) {
        return localitiesCache.get(cacheKey)!;
    }
    try {
        const res = await fetch(`/tenant/geo/localidades?provincia_id=${encodeURIComponent(provinceId)}`);
        if (res.ok) {
            const data: GeoLocality[] = await res.json();
            localitiesCache.set(cacheKey, data);
            return data;
        }
    } catch (e) {
        console.error('Error fetching localities by province:', e);
    }
    return [];
}
