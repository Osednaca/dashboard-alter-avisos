import assert from 'node:assert/strict';
import test from 'node:test';
import { canSelectLibraryMedia, toggleLibrarySelection } from '../../resources/js/Utils/library-selection.ts';

test('selection preserves user order, removal and readdition while enforcing the destination limit', () => {
    let ids = [];
    for (const id of [8, 2, 5]) ids = toggleLibrarySelection(ids, id, 3);
    assert.deepEqual(ids, [8, 2, 5]);
    assert.deepEqual(toggleLibrarySelection(ids, 9, 3), ids);
    ids = toggleLibrarySelection(ids, 2, 3);
    assert.deepEqual(toggleLibrarySelection(ids, 2, 3), [8, 5, 2]);
});

test('only ready images and videos can be selected for the normal programming flows', () => {
    for (const type of ['image', 'video', 'live_stream']) {
        for (const status of ['ready', 'pending', 'processing', 'failed']) {
            assert.equal(canSelectLibraryMedia({ type: { value: type }, processing_status: { value: status } }), status === 'ready' && type !== 'live_stream');
        }
    }
});
