import test from 'node:test';
import assert from 'node:assert/strict';
import { findVillageOption, initialiseVillageFilter, syncVillageFilterLabel } from './village-filter.js';

class FakeControl extends EventTarget {
    constructor() {
        super();
        this.value = '';
        this.dataset = {};
    }
}

const makeSelect = () => {
    const select = new FakeControl();
    select.options = [
        { value: '', textContent: 'सभी गाँव' },
        { value: '480058', textContent: 'लोटिया, सारंगपुर, राजगढ़' },
        { value: '480059', textContent: 'ब्यावरा, ब्यावरा, राजगढ़' },
    ];
    return select;
};

test('finds a village by its complete Hindi-first label', () => {
    const select = makeSelect();

    assert.equal(findVillageOption(select.options, 'लोटिया, सारंगपुर, राजगढ़')?.value, '480058');
    assert.equal(findVillageOption(select.options, 'लोटिया') , null);
    assert.equal(findVillageOption(select.options, '  '), null);
});

test('typing an exact datalist label selects its village code and dispatches change', () => {
    const input = new FakeControl();
    const select = makeSelect();
    let changes = 0;
    select.addEventListener('change', () => { changes += 1; });

    initialiseVillageFilter(input, select);
    input.value = 'लोटिया, सारंगपुर, राजगढ़';
    input.dispatchEvent(new Event('input'));

    assert.equal(select.value, '480058');
    assert.equal(changes, 1);
});

test('typing a non-matching label clears the selected village code', () => {
    const input = new FakeControl();
    const select = makeSelect();
    select.value = '480058';
    let changes = 0;
    select.addEventListener('change', () => { changes += 1; });

    initialiseVillageFilter(input, select);
    input.value = 'अनजान गाँव';
    input.dispatchEvent(new Event('input'));

    assert.equal(select.value, '');
    assert.equal(changes, 1);
    assert.equal(input.value, 'अनजान गाँव');
});

test('restores the visible label when the village code changes externally', () => {
    const input = new FakeControl();
    const select = makeSelect();

    initialiseVillageFilter(input, select);
    select.value = '480059';
    select.dispatchEvent(new Event('change'));

    assert.equal(input.value, 'ब्यावरा, ब्यावरा, राजगढ़');
    syncVillageFilterLabel(input, select);
    select.value = '';
    syncVillageFilterLabel(input, select);
    assert.equal(input.value, '');
});
