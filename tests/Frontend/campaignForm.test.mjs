import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import ts from 'typescript';

// Execute the same initializer used by the editor without requiring a browser or a new test dependency.
const source = readFileSync(new URL('../../resources/js/Utils/campaignForm.ts', import.meta.url), 'utf8');
const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext } });
const { initialCampaignValues } = await import(`data:text/javascript;base64,${Buffer.from(outputText).toString('base64')}`);

test('editing retains advertiser, schedule, goals, budget, ordered creatives and exclusions', () => {
    const campaign = {
        advertiser: { id: 17, name: 'Anunciante existente' }, name: 'Campaña', description: 'Guardada',
        starts_at: '2026-09-01', ends_at: '2026-09-30', daily_start_time: '09:15:00', daily_end_time: '21:45:00',
        days_of_week: [2, 4, 7], priority: 8, playback_goal: 1234, impressions_goal: 5678, budget: '32100.50',
        creatives: [{ media_asset_id: 9, duration: 900, weight: 30 }, { media_asset_id: 3, duration: 600, weight: 20 }],
        targets: [{ target_type: 'device', target_id: 8, target_value: null, is_exclusion: true }],
    };
    const form = initialCampaignValues(campaign);
    assert.equal(form.advertiser_id, '17');
    for (const key of ['name', 'description', 'starts_at', 'ends_at', 'days_of_week', 'priority', 'playback_goal', 'impressions_goal', 'budget', 'creatives']) {
        assert.deepEqual(form[key], campaign[key], key);
    }
    assert.equal(form.daily_start_time, '09:15');
    assert.equal(form.daily_end_time, '21:45');
    assert.deepEqual(form.targets, [{ target_type: 'device', target_id: 8, target_value: '', is_exclusion: true }]);
    assert.equal(form.publish, false);
});

test('editing unrestricted or empty fields does not replace them with new-campaign defaults', () => {
    for (const days of [null, []]) {
        const form = initialCampaignValues({ days_of_week: days, daily_start_time: null, daily_end_time: null,
            starts_at: null, ends_at: null, budget: '0.00', playback_goal: null, impressions_goal: null });
        for (const key of ['starts_at', 'ends_at', 'daily_start_time', 'daily_end_time']) assert.equal(form[key], '', key);
        assert.deepEqual(form.days_of_week, []);
        assert.equal(form.playback_goal, null);
        assert.equal(form.impressions_goal, null);
        assert.equal(form.budget, '0.00');
    }
});

test('new campaigns retain their default dates and schedule', () => {
    const form = initialCampaignValues(null);
    assert.equal(form.advertiser_id, '');
    assert.match(form.starts_at, /^\d{4}-\d{2}-\d{2}$/);
    assert.ok(form.ends_at > form.starts_at);
    assert.equal(form.daily_start_time, '07:00');
    assert.equal(form.daily_end_time, '22:00');
    assert.deepEqual(form.days_of_week, [1, 2, 3, 4, 5, 6]);
    assert.equal(form.impressions_goal, null);
});

test('library preselection initializes ordered creatives only for a new campaign and never publishes', () => {
    const selected = [{ id: 8, duration: 40 }, { id: 2, duration: null }];
    const form = initialCampaignValues(null, selected);
    assert.deepEqual(form.creatives, [{ media_asset_id: 8, duration: 40, weight: 10 }, { media_asset_id: 2, duration: 10, weight: 10 }]);
    assert.equal(form.publish, false);
    assert.equal(form.advertiser_id, '');
    assert.deepEqual(form.targets, []);
    assert.deepEqual(initialCampaignValues({ creatives: [{ media_asset_id: 4, duration: 15, weight: 20 }] }, selected).creatives, [{ media_asset_id: 4, duration: 15, weight: 20 }]);
});
