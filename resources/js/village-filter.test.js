import test from 'node:test';
import assert from 'node:assert/strict';
import { filterVillageOptions, findVillageOption, syncVillageFilterLabel } from './village-filter.js';

const makeOptions = () => [
    { value: '', textContent: 'सभी गाँव' },
    { value: '480058', textContent: 'लोटिया, सारंगपुर, राजगढ़' },
    { value: '480059', textContent: 'ब्यावरा, ब्यावरा, राजगढ़' },
    { value: '480060', textContent: 'नरसिंहगढ़, नरसिंहगढ़, राजगढ़' },
];

test('filters village options by partial Hindi and English labels', () => {
    const options = makeOptions();

    assert.deepEqual(filterVillageOptions(options, 'लोट'), [options[1]]);
    assert.deepEqual(filterVillageOptions(options, 'Lot'), []);
    assert.deepEqual(filterVillageOptions(options, 'सारंगपुर'), [options[1]]);
    assert.deepEqual(filterVillageOptions(options, 'राजगढ़'), options.slice(1));
});

test('returns all villages for an empty search and excludes the all-villages option', () => {
    const options = makeOptions();

    assert.deepEqual(filterVillageOptions(options, ''), options.slice(1));
});

test('finds an exact label without treating partial text as a selection', () => {
    const options = makeOptions();

    assert.equal(findVillageOption(options, 'लोटिया, सारंगपुर, राजगढ़')?.value, '480058');
    assert.equal(findVillageOption(options, 'लोटिया'), null);
});

test('syncs the visible label from the selected village code', () => {
    const input = { value: '' };
    const select = { value: '480058', options: makeOptions() };

    syncVillageFilterLabel(input, select);
    assert.equal(input.value, 'लोटिया, सारंगपुर, राजगढ़');

    select.value = '';
    syncVillageFilterLabel(input, select);
    assert.equal(input.value, '');
});
