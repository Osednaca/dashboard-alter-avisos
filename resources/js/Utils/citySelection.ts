export function countCityDevices(
    cities: Array<{ id: number; device_ids: number[] }>,
    selectedCityIds: number[],
    eligibleDeviceIds: number[],
): number {
    const eligible = new Set(eligibleDeviceIds);
    return new Set(cities.filter((city) => selectedCityIds.includes(city.id))
        .flatMap((city) => city.device_ids.filter((id) => eligible.has(id)))).size;
}
