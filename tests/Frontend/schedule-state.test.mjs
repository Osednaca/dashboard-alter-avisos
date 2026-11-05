import assert from 'node:assert/strict';
import test from 'node:test';
import {
    appendScheduleItems, applyScheduleMediaStatus, hasUnreadyScheduleItems, hydrateSchedule,
    moveScheduleItem, removeScheduleItem, schedulePayload,
} from '../../resources/js/Utils/schedule-state.ts';

const asset = (id, status = 'ready', type = 'image') => ({ id, filename: `item-${id}`, type: { value: type }, processing_status: { value: status } });
const row = (id, duration = 10, transition = 'fade') => ({ id, media: asset(id), duration, transition });
const schedule = { name: 'Almuerzo', priority: 37, status: 'inactive', location: { id: 4 }, daily_start_time: null, daily_end_time: null, days_of_week: [1, 5], items: [row(11, 22), row(12, 44, 'slide_left')] };

test('new library draft preserves selected order and safe durations without persisting a schedule', () => {
    const selected = [{ ...asset(8, 'ready', 'video'), duration: 34 }, asset(2), { ...asset(5), duration: 900 }];
    const draft = hydrateSchedule(null, undefined, selected);
    assert.deepEqual(draft.items.map((item) => item.media_asset_id), [8, 2, 5]);
    assert.deepEqual(draft.items.map((item) => item.duration_seconds), [34, 10, 600]);
    assert.equal(draft.name, '');
    assert.equal(draft.location_id, '');
    assert.deepEqual(hydrateSchedule(schedule, undefined, selected).items.map((item) => item.media_asset_id), [11, 12]);
});

test('hydration preserves all-day, priority, location, days, ordered durations and transitions', () => {
    const draft = hydrateSchedule(schedule);
    assert.equal(draft.daily_start_time, '');
    assert.equal(draft.daily_end_time, '');
    assert.equal(draft.priority, 37);
    assert.equal(draft.location_id, '4');
    assert.equal(draft.status, 'inactive');
    assert.deepEqual(draft.days_of_week, [1, 5]);
    assert.deepEqual(draft.items, [
        { key: 0, media_asset_id: 11, duration_seconds: 22, transition: 'fade' },
        { key: 1, media_asset_id: 12, duration_seconds: 44, transition: 'slide_left' },
    ]);
    draft.days_of_week.push(3);
    assert.deepEqual(schedule.days_of_week, [1, 5]);
    assert.equal(hydrateSchedule({ ...schedule, daily_start_time: '08:15:00' }).daily_start_time, '08:15');
});

test('uploads append in request order without replacing unsaved form fields or previous rows', () => {
    const draft = { ...hydrateSchedule(schedule), name: 'Edición pendiente', daily_start_time: '10:30' };
    const uploaded = [21, 22].map((id, index) => ({ key: index + 2, media_asset_id: id, duration_seconds: 30, transition: 'none' }));
    const next = uploaded.reduce((current, item) => appendScheduleItems(current, [item]), draft);
    assert.deepEqual(next.items.map((item) => item.media_asset_id), [11, 12, 21, 22]);
    assert.equal(next.name, 'Edición pendiente');
    assert.equal(next.daily_start_time, '10:30');
    assert.equal(next.priority, 37);
    assert.equal(draft.items.length, 2);
});

test('move and removal retain row settings, identity and original draft', () => {
    const draft = hydrateSchedule(schedule);
    const moved = moveScheduleItem(draft, 1, -1);
    assert.deepEqual(moved.items.map((item) => [item.key, item.duration_seconds, item.transition]), [[1, 44, 'slide_left'], [0, 22, 'fade']]);
    const removed = removeScheduleItem(moved, 1);
    assert.deepEqual(removed.items, [draft.items[0]]);
    assert.equal(moveScheduleItem(draft, 0, -1), draft);
    assert.equal(moveScheduleItem(draft, 1, 1), draft);
    assert.equal(draft.items.length, 2);
});

test('payload strips client-only keys while preserving schedule fields and order', () => {
    const draft = hydrateSchedule(schedule);
    const payload = schedulePayload(draft);
    assert.deepEqual(Object.keys(payload.items[0]).sort(), ['duration_seconds', 'media_asset_id', 'transition']);
    assert.equal(payload.priority, 37);
    assert.equal(payload.daily_end_time, '');
    assert.equal(payload.items[1].media_asset_id, 12);
    assert.equal(draft.items[0].key, 0);
});

test('readiness blocks missing, pending, failed and unsupported media but accepts ready images/videos', () => {
    const items = hydrateSchedule(schedule).items;
    assert.equal(hasUnreadyScheduleItems(items, { 11: asset(11), 12: asset(12, 'ready', 'video') }), false);
    for (const status of ['pending', 'processing', 'failed']) {
        assert.equal(hasUnreadyScheduleItems(items, { 11: asset(11), 12: asset(12, status) }), true);
    }
    assert.equal(hasUnreadyScheduleItems(items, { 11: asset(11) }), true);
    assert.equal(hasUnreadyScheduleItems(items, { 11: asset(11), 12: asset(12, 'ready', 'live_stream') }), true);
});

test('successful status refresh marks missing IDs unavailable and preserves unrelated assets', () => {
    const assets = { 11: asset(11, 'pending'), 12: asset(12, 'pending'), 99: asset(99) };
    const updated = applyScheduleMediaStatus(assets, [11, 12], [asset(11), asset(888)]);
    assert.equal(updated[11].processing_status.value, 'ready');
    assert.equal(updated[12], undefined);
    assert.equal(updated[99], assets[99]);
    assert.equal(updated[888], undefined);
    assert.equal(assets[12].processing_status.value, 'pending');
    assert.equal(hasUnreadyScheduleItems(hydrateSchedule(schedule).items, updated), true);
});

test('legacy import creates independent draft rows and missing media remains blocked', () => {
    const imported = { name: 'Anterior', items: [row(11, 55), { ...row(12), media: null }] };
    const draft = hydrateSchedule(null, imported);
    draft.items[0].duration_seconds = 99;
    assert.equal(imported.items[0].duration, 55);
    assert.equal(draft.items[1].media_asset_id, 0);
    assert.equal(hasUnreadyScheduleItems(draft.items, { 11: asset(11) }), true);
});
