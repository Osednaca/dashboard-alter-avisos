import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import ts from 'typescript';
import * as icons from 'lucide-react';

const require = createRequire(import.meta.url);
const source = readFileSync(new URL('../../resources/js/Components/app/QuickPlayPreview.tsx', import.meta.url), 'utf8');
const { outputText } = ts.transpileModule(source, { compilerOptions: {
    module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX, esModuleInterop: true,
} });
const module = { exports: {} };
new Function('require', 'module', 'exports', outputText)(name => {
    if (name === 'lucide-react') return icons;
    if (name === '@/Utils/cn') return { cn: (...classes) => classes.filter(Boolean).join(' ') };
    return require(name);
}, module, module.exports);
const { QuickPlayPreview } = module.exports;
const image = { url: '/image.jpg', filename: 'Anuncio', formatted_duration: '10 s', type: { value: 'image' } };
const video = { ...image, url: '/video.mp4', type: { value: 'video' } };
const render = (mode, media = image) => renderToStaticMarkup(React.createElement(QuickPlayPreview, { displayMode: mode, media }));

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
    for (const mode of ['business', 'advertising', 'unknown', '']) {
        for (const media of [image, video]) {
            const html = render(mode, media);
            assert.match(html, /Contenido histórico: no es posible verificar cómo se mostró/);
            assert.doesNotMatch(html, /<img|<video|\/image\.jpg|\/video\.mp4|Pantalla completa|70%|30%|Contenido propio/);
        }
    }
});
