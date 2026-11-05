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
const { MediaActionsMenu } = load(resolve(root, 'Components/app/MediaActionsMenu.tsx'));

test('library menu is visible, keyboard reachable and labelled with the filename', () => {
    const html = renderToStaticMarkup(React.createElement(MediaActionsMenu, { filename: 'Imagen local.jpg', onPreview() {}, onDelete() {} }));
    assert.match(html, /<button[^>]*aria-label="Opciones de Imagen local.jpg"/);
    assert.match(html, /aria-haspopup="menu"/);
    assert.match(html, /aria-expanded="false"/);
    assert.match(html, /focus-visible:ring-2/);
    assert.doesNotMatch(html, /opacity-0|group-hover|tabindex="-1"/);
});
