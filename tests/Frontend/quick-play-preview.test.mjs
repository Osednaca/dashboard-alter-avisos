import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import test from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import ts from 'typescript';
import * as icons from 'lucide-react';

const require = createRequire(import.meta.url);
function load(file) {
    const source = readFileSync(file, 'utf8');
    const { outputText } = ts.transpileModule(source, { compilerOptions: {
        module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX, esModuleInterop: true,
    } });
    const module = { exports: {} };
    new Function('require', 'module', 'exports', outputText)(name => {
        if (name === 'lucide-react') return icons;
        if (name === '@/Utils/cn') return { cn: (...classes) => classes.filter(Boolean).join(' ') };
        if (name.startsWith('@/')) return load(resolve(import.meta.dirname, '../../resources/js', name.slice(2) + '.tsx'));
        return require(name);
    }, module, module.exports);
    return module.exports;
}
const { QuickPlayPreview } = load(new URL('../../resources/js/Components/app/QuickPlayPreview.tsx', import.meta.url));
const image = { url: '/image.jpg', filename: 'Anuncio', formatted_duration: '10 s', type: { value: 'image' } };
const video = { ...image, url: '/video.mp4', type: { value: 'video' } };
const render = (mode, media = image, presentation = 'screen') => renderToStaticMarkup(React.createElement(QuickPlayPreview, { displayMode: mode, media, presentation }));

test('fullscreen image and video fill the preview with their configured mode', () => {
    const img = render('fullscreen');
    assert.match(img, /<img[^>]*src="\/image\.jpg"/);
    assert.match(img, /Pantalla completa/);
    assert.doesNotMatch(img, /no es posible verificar|70%|30%/);
    const vid = render('fullscreen', video);
    assert.match(vid, /<video[^>]*src="\/video\.mp4"/);
    assert.match(vid, /Pantalla completa/);
});

test('historical and unknown modes use a neutral frame without asserting playback', () => {
    for (const presentation of ['screen', 'led']) {
        for (const mode of ['business', 'advertising', 'unknown', '']) {
            for (const media of [image, video]) {
                const html = render(mode, media, presentation);
                assert.match(html, /Contenido histórico: no es posible verificar cómo se mostró/);
                assert.doesNotMatch(html, /<img|<video|\/image\.jpg|\/video\.mp4|Pantalla completa|70%|30%|Contenido propio/);
            }
        }
    }
});

test('LED preview uses the horizontal dashboard cabinet for images and videos without a mode pill', () => {
    for (const media of [image, video]) {
        const html = render('fullscreen', media, 'led');
        assert.match(html, /data-preview-presentation="led"/);
        assert.match(html, /data-preview-orientation="landscape"/);
        assert.match(html, /aspect-ratio:3 \/ 1/);
        assert.match(html, /data-preview-led-matrix="true"/);
        assert.match(html, media === image ? /<img[^>]*src="\/image\.jpg"/ : /<video[^>]*src="\/video\.mp4"[^>]*muted/);
        assert.match(html, /Anuncio/);
        assert.match(html, /10 s/);
        assert.doesNotMatch(html, /Pantalla completa|aspect-video|h-1 w-16/);
    }
});

test('empty LED preview keeps the cabinet and selection prompt without a mode pill', () => {
    const html = render('fullscreen', null, 'led');
    assert.match(html, /aspect-ratio:3 \/ 1/);
    assert.match(html, /Selecciona un contenido/);
    assert.match(html, /Sin contenido/);
    assert.doesNotMatch(html, /<img|<video|Pantalla completa|h-1 w-16/);
});
