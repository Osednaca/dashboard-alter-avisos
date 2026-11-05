import type { EnumValue } from '@/Types';

export interface CityEntity {
    id: number;
    name: string;
    state: string;
    country: string;
    timezone: string;
    status: EnumValue;
    businesses: Array<{ id: number; name: string }>;
    devices_count: number;
    online_devices_count: number;
}
