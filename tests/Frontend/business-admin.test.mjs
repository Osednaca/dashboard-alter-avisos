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
const root = resolve(import.meta.dirname, '../../resources/js');
let granted = [];
const wrap = ({ children }) => React.createElement('div', null, children);
const fake = new Proxy({}, { get: (_, key) => key === '__esModule' ? true : wrap });
const custom = {
    '@/Hooks/usePermissions': { usePermissions: () => ({ can: p => granted.includes(p), canAny: (...p) => p.some(item => granted.includes(item)) }) },
    '@/Layouts/AdminLayout': { AdminLayout: wrap },
    '@/Components/app/ActionMenu': { ActionMenu: ({ items }) => React.createElement('div', null, items.filter(item => !item.hidden).map(item => React.createElement('span', { key: item.label }, item.label))) },
    '@/Components/ui/dialog': { ...fake, Dialog: ({ open, children }) => open ? React.createElement('div', null, children) : null },
    '@/Components/app/PageHeader': { PageHeader: ({ title, description, actions }) => React.createElement('header', null, title, description, actions) },
    '@/Components/app/DataTable': { DataTable: ({ columns, rows, empty }) => React.createElement('div', null, rows.length ? rows.map(row => React.createElement('div', { key: row.id }, columns.map(column => React.createElement('span', { key: column.key }, column.cell(row))))) : empty) },
    '@/Components/app/SidebarShell': { SidebarShell: ({ sections }) => React.createElement('nav', null, sections.flatMap(section => section.items.map(item => React.createElement('a', { key: item.href, href: item.href }, item.label)))) },
    '@/Components/app/StatusBadge': { StatusBadge: ({ value }) => React.createElement('span', null, value.label) },
    '@/Components/app/EmptyState': { EmptyState: ({ title, description }) => React.createElement('div', null, title, description) },
    '@/Utils/format': { formatDate: v => v ?? '—', formatRelative: v => v ?? '—', formatNumber: v => String(v) },
};
const inertia = {
    Head: () => null,
    Link: ({ href, children, ...props }) => React.createElement('a', { href, ...props }, children),
    useForm: data => ({ data, errors: {}, processing: false, setData() {}, put() {} }),
};
function load(file) {
    const source = readFileSync(resolve(root, file), 'utf8');
    const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX, esModuleInterop: true } });
    const module = { exports: {} };
    new Function('require', 'module', 'exports', outputText)(name => {
        if (name === '@inertiajs/react') return inertia;
        if (name === 'lucide-react') return icons;
        if (custom[name]) return custom[name];
        if (name.startsWith('@/')) return fake;
        return require(name);
    }, module, module.exports);
    return module.exports;
}
const { default: BusinessesIndex } = load('Pages/Admin/Businesses/Index.tsx');
const { default: BusinessShow } = load('Pages/Admin/Businesses/Show.tsx');
const { AppSidebar } = load('Components/app/AppSidebar.tsx');
const business = { id: 1, name: 'Comercio A', category: { label: 'Comercio', value: 'retail' }, status: { label: 'Activo', value: 'active' }, timezone: 'America/Bogota', devices_count: 1, online_devices_count: 1, created_at: null };
const devices = [{ id: 2, name: 'Aviso principal', cities: [{ id: 3, name: 'Bogotá' }], status: { label: 'En línea', value: 'online' }, app_version: '1', last_seen_at: null }];
const renderShow = () => renderToStaticMarkup(React.createElement(BusinessShow, { business, devices, options: { categories: [], statuses: [] } }));

test('business detail shows assigned notices and cities without retired portal sections', () => {
    granted = ['businesses.view', 'devices.view'];
    const html = renderShow();
    assert.match(html, /Comercio A/);
    assert.match(html, /Aviso principal/);
    assert.match(html, /Bogotá/);
    assert.match(html, /href="\/admin\/devices\/2"/);
    assert.doesNotMatch(html, /Guardar cambios|Listas de reproducción|Programación|Usuarios|Layout/);
});

test('business edit and navigation respect their separate permissions', () => {
    granted = ['businesses.view'];
    assert.doesNotMatch(renderShow(), /href="\/admin\/devices\/2"|Guardar cambios/);
    assert.match(renderToStaticMarkup(React.createElement(AppSidebar, { collapsed: false })), /href="\/admin\/businesses"/);
    granted = ['businesses.edit'];
    assert.match(renderShow(), /Guardar cambios/);
    assert.doesNotMatch(renderToStaticMarkup(React.createElement(AppSidebar, { collapsed: false })), /href="\/admin\/businesses"/);
});

test('business list offers only permitted management actions', () => {
    const props = { businesses: { data: [business] }, filters: {}, options: { categories: [], statuses: [], cities: [] } };
    const render = () => renderToStaticMarkup(React.createElement(BusinessesIndex, props));
    granted = ['businesses.view'];
    let html = render();
    assert.match(html, /Comercio A/);
    assert.doesNotMatch(html, /Nuevo negocio|>Editar<|>Eliminar</);
    granted = ['businesses.view', 'businesses.create', 'businesses.edit', 'businesses.delete'];
    html = render();
    assert.match(html, /Nuevo negocio/);
    assert.match(html, />Editar</);
    assert.match(html, />Eliminar</);
    assert.doesNotMatch(html, /Layout actual/);
});
