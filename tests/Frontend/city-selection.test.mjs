import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/Utils/citySelection.ts', import.meta.url), 'utf8');
const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext } });
const { countCityDevices } = await import(`data:text/javascript;base64,${Buffer.from(outputText).toString('base64')}`);

test('city selection counts each eligible device once even when cities overlap', () => {
    const cities = [{ id: 1, device_ids: [1, 2, 4] }, { id: 2, device_ids: [2, 3] }, { id: 3, device_ids: [9] }];
    assert.equal(countCityDevices(cities, [1, 2], [1, 2, 3]), 3);
    assert.equal(countCityDevices(cities, [1], [1, 2, 3]), 2);
    assert.equal(countCityDevices(cities, [3], [1, 2, 3]), 0);
    assert.equal(countCityDevices(cities, [], [1, 2, 3]), 0);
});
