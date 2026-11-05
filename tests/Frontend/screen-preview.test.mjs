import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import test from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import ts from 'typescript';
import * as icons from 'lucide-react';

const require = createRequire(import.meta.url);
const root = resolve(import.meta.dirname, '../../resources/js');
const modules = new Map();
function load(file) {
    if (modules.has(file)) return modules.get(file).exports;
    const source = readFileSync(file, 'utf8');
    const module = { exports: {} };
    modules.set(file, module);
    const { outputText } = ts.transpileModule(source, { compilerOptions: {
        module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX, esModuleInterop: true,
    } });
    const localRequire = name => {
        if (name === 'lucide-react') return icons;
        if (name.startsWith('@/') || name.startsWith('.')) {
            const base = name.startsWith('@/') ? resolve(root, name.slice(2)) : resolve(dirname(file), name);
            for (const ext of ['.tsx', '.ts']) {
                try { return load(base + ext); } catch (error) { if (error.code !== 'ENOENT') throw error; }
            }
        }
        return require(name);
    };
    new Function('require', 'module', 'exports', outputText)(localRequire, module, module.exports);
    return module.exports;
}
const { ScreenPreview } = load(resolve(root, 'Components/app/ScreenPreview.tsx'));
const image = { id: 1, type: 'image', url: '/confirmed.jpg' };
const video = { id: 2, type: 'video', url: '/confirmed.mp4' };
const preview = {
    device: { id: 1, name: 'TV', is_online: true, business: { name: 'Negocio' }, location: null },
    layout: { name: 'Confirmado', rotation: 90, split: 'top_bottom', business_first: false,
        business_percentage: 70, advertising_percentage: 30, ratio: '70/30' },
    business_media: video, advertising: { campaign_name: 'Pauta', media: image },
    playlist: null, manifest_version: '100', pending_manifest_version: '200', status: 'approximate',
    last_sync_at: null, checked_at: null,
};
const render = data => renderToStaticMarkup(React.createElement(ScreenPreview, { preview: data }));

test('portrait preview has upright aspect, top/bottom split, configured zone order and video', () => {
    const html = render(preview);
    assert.match(html, /aspect-ratio:9 \/ 16/);
    assert.match(html, /Vertical · 90°/);
    assert.match(html, /Arriba \/ abajo/);
    assert.ok(html.indexOf('data-preview-zone="advertising"') < html.indexOf('data-preview-zone="business"'));
    assert.match(html, /<video[^>]*src="\/confirmed.mp4"/);
    assert.match(html, /<img[^>]*src="\/confirmed.jpg"/);
    assert.match(html, /no está sincronizada/);
    assert.match(html, /cambios pendientes de confirmar/);
});

test('explicit zero and 180 remain horizontal while 270 is vertical', () => {
    for (const rotation of [0, 90, 180, 270]) {
        const html = render({ ...preview, layout: { ...preview.layout, rotation, split: 'side_by_side', business_first: true } });
        assert.match(html, new RegExp(`>${rotation % 180 ? 'Vertical' : 'Horizontal'} · ${rotation}°<`));
        assert.match(html, /Izquierda \/ derecha/);
        assert.match(html, rotation % 180 ? /aspect-ratio:9 \/ 16/ : /aspect-ratio:16 \/ 9/);
        assert.ok(html.indexOf('data-preview-zone="business"') < html.indexOf('data-preview-zone="advertising"'));
    }
});

test('unconfirmed content is visibly unavailable and does not claim playback', () => {
    const html = render({ ...preview, layout: null, status: 'unconfirmed', business_media: null, advertising: null });
    assert.match(html, /Orientación sin confirmar/);
    assert.match(html, /todavía no ha confirmado un manifiesto/);
    assert.doesNotMatch(html, /<video|<img|Reproduciendo/);
});

test('live sources use the embedded player rather than an image URL', () => {
    const live = { id: 3, type: 'live_stream', url: 'https://www.youtube.com/watch?v=abcdefghijk',
        live: { provider: 'youtube', original_url: 'https://www.youtube.com/watch?v=abcdefghijk', source_id: 'abcdefghijk', embed_url: '/live/embed/3?signature=test' } };
    const html = render({ ...preview, business_media: null, advertising: { campaign_name: 'Directo', media: live } });
    assert.match(html, /<iframe[^>]*src="\/live\/embed\/3\?signature=test"/);
    assert.doesNotMatch(html, /<img/);
    assert.match(html, /Vista aproximada/);
});

const { playbackIsFresh, playbackPositionMs } = load(resolve(root, 'Utils/playback-preview.ts'));
const reportedZone = {
    source: 'manifest', state: 'playing', manifest_version: '99', item_id: 'second-item',
    media_asset_id: 2, quick_play_device_id: null, command_id: null, live_creative_id: null,
    position_ms: 4000, duration_ms: 10000, live_state: null, media: video,
};
const report = {
    fresh: true, age_ms: 1000, received_at: '2026-10-03T12:00:00Z', session_id: 'sample', sequence: 3,
    scene: 'playback', layout: { ...preview.layout, width_px: 1080, height_px: 1920 },
    zones: { business: reportedZone },
};
const actual = { ...preview, playback_reported: true, status: 'reported', playback: report,
    layout: { ...preview.layout, width_px: 1080, height_px: 1920 } };

test('actual report uses logical dimensions, crop and current source without a video loop', () => {
    const html = render(actual);
    assert.match(html, /aspect-ratio:1080 \/ 1920/);
    assert.match(html, /Estado reportado/);
    assert.match(html, /Reproduciendo/);
    assert.match(html, /<video[^>]*object-cover/);
    assert.doesNotMatch(html, /<video[^>]*loop|<video[^>]*autoPlay|\/confirmed.jpg/);
    assert.match(html, /data-preview-zone="business"/);
    assert.doesNotMatch(html, /data-preview-zone="advertising"/);
});

test('fullscreen quick play replaces normal zones and previous playlist content', () => {
    const quick = { ...reportedZone, source: 'quick_play', item_id: null, media: { ...video, id: 9, url: '/quick.mp4' } };
    const html = render({ ...actual, playback: { ...report, zones: { business: reportedZone, fullscreen: quick } } });
    assert.match(html, /data-preview-zone="fullscreen"/);
    assert.match(html, /Reproducción inmediata/);
    assert.match(html, /\/quick.mp4/);
    assert.doesNotMatch(html, /data-preview-zone="business"|data-preview-zone="advertising"|\/confirmed.mp4/);
});

test('all non-playback scenes mask even media left in a malformed response', () => {
    for (const scene of ['settings', 'pin', 'background', 'activation']) {
        const html = render({ ...actual, playback: { ...report, scene } });
        assert.doesNotMatch(html, /<video|<img|<iframe|data-preview-zone=/);
        assert.match(html, /Configuración abierta|Acceso administrativo|segundo plano|TV en activación/);
    }
});

test('stale and server-unverified state hide old media instead of falling back to estimates', () => {
    for (const staleReport of [{ ...report, fresh: false }, { ...report, age_ms: 15001 }]) {
        const html = render({ ...actual, playback: staleReport });
        assert.match(html, /Reporte vencido/);
        assert.doesNotMatch(html, /<video|<img|<iframe|data-preview-zone=/);
        assert.doesNotMatch(html, /Vista aproximada del contenido confirmado/);
    }
});

test('video position extrapolates playing reports only and never crosses item duration', () => {
    assert.equal(playbackPositionMs(reportedZone, 1000), 5000);
    assert.equal(playbackPositionMs(reportedZone, 12000), 10000);
    for (const state of ['paused', 'buffering', 'error']) {
        assert.equal(playbackPositionMs({ ...reportedZone, state }, 12000), 4000);
    }
    assert.equal(playbackPositionMs({ ...reportedZone, position_ms: null }, 1000), null);
    assert.equal(playbackPositionMs({ ...reportedZone, position_ms: undefined }, 1000), null);
    assert.equal(playbackIsFresh(report, 14000), true);
    assert.equal(playbackIsFresh(report, 14001), false);
    assert.equal(playbackIsFresh({ ...report, fresh: false }, 0), false);
});

test('live and fallback identify actual source while disclosing separate browser buffering', () => {
    const live = { id: 3, type: 'live_stream', url: '/live', live: { provider: 'youtube', original_url: 'https://www.youtube.com/watch?v=abcdefghijk', source_id: 'abcdefghijk', embed_url: '/live/embed/3?signature=test' } };
    const html = render({ ...actual, playback: { ...report, zones: { advertising: { ...reportedZone, source: 'live', media: live } } } });
    assert.match(html, /<iframe/);
    assert.match(html, /propio búfer/);
    const fallback = render({ ...actual, playback: { ...report, zones: { advertising: { ...reportedZone, source: 'live_fallback', media: image } } } });
    assert.match(fallback, /Reserva de directo/);
    assert.match(fallback, /<img/);
    assert.doesNotMatch(fallback, /<iframe/);
    const hls = render({ ...actual, playback: { ...report, zones: { advertising: { ...reportedZone, source: 'live', media: { ...live, live: { provider: 'hls', original_url: '/channel.m3u8' } } } } } });
    assert.match(hls, /object-cover bg-black/);
});

test('preview pages poll only their scoped preview every three seconds', () => {
    for (const file of ['Pages/Admin/Dashboard.tsx', 'Pages/Business/Home.tsx', 'Pages/Business/Preview/Index.tsx', 'Pages/Business/Screens/Show.tsx']) {
        const source = readFileSync(resolve(root, file), 'utf8');
        assert.match(source, /usePoll\(3000, \{ only: \[/);
    }
});
