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
const page = { url: '/business/dashboard', props: {
    app: { name: 'Alter', logo_url: '/brand/alter-logo-20261003.png', version: '1.0.0' },
    flash: {}, auth: { user: null },
} };
const modules = new Map();

// Render the real TSX, with only the browser/Inertia boundary stubbed. This does
// not claim browser layout or screenshot coverage and installs no test framework.
function load(file) {
    if (modules.has(file)) return modules.get(file).exports;
    const source = readFileSync(file, 'utf8');
    const module = { exports: {} };
    modules.set(file, module);
    const { outputText } = ts.transpileModule(source, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX, esModuleInterop: true },
    });
    const localRequire = (name) => {
        if (name === 'lucide-react') return icons;
        if (name === '@inertiajs/react') return {
            usePage: () => page,
            Head: () => null,
            Link: ({ children, ...props }) => React.createElement('a', props, children),
            useForm: (data) => ({ data, errors: {}, processing: false }),
        };
        if (name.startsWith('@/') || name.startsWith('.')) {
            const base = name.startsWith('@/') ? resolve(root, name.slice(2)) : resolve(dirname(file), name);
            for (const extension of ['.tsx', '.ts']) {
                try { return load(base + extension); } catch (error) {
                    if (error.code !== 'ENOENT') throw error;
                }
            }
        }
        return require(name);
    };
    new Function('require', 'module', 'exports', outputText)(localRequire, module, module.exports);
    return module.exports;
}

test('logo keeps complete image and accessible alternative without cropping', () => {
    const { BrandLogo } = load(resolve(root, 'Components/app/BrandLogo.tsx'));
    const html = renderToStaticMarkup(React.createElement(BrandLogo));
    assert.match(html, /src="\/brand\/alter-logo-20261003.png"/);
    assert.match(html, /alt="Alter"/);
    assert.match(html, /object-contain/);
    assert.match(html, /width="1064" height="1064"/);
    assert.doesNotMatch(html, /object-cover|rounded-full/);
});

test('login has Alter identity in both desktop and small-screen containers', () => {
    const { default: Login } = load(resolve(root, 'Pages/Auth/Login.tsx'));
    const html = renderToStaticMarkup(React.createElement(Login));
    assert.equal((html.match(/<img /g) ?? []).length, 2);
    assert.match(html, /hidden flex-col[^\"]*lg:flex/);
    assert.match(html, /mb-6 flex[^\"]*lg:hidden/);
    assert.equal((html.match(/>Alter</g) ?? []).length, 2);
    assert.doesNotMatch(html, /Signage TV/);
    assert.match(html, /<input(?=[^>]*id="password")(?=[^>]*type="password")[^>]*>/);
    assert.match(html, /autoComplete="current-password"/);
    assert.match(html, /type="button"[^>]*aria-label="Mostrar contraseña"[^>]*aria-pressed="false"[^>]*aria-controls="password"/);
    assert.doesNotMatch(html, /tabindex="-1"/i);
});

test('password visibility keeps the password masked even if a caller supplies type text', () => {
    const { PasswordInput } = load(resolve(root, 'Components/ui/password-input.tsx'));
    const html = renderToStaticMarkup(React.createElement(PasswordInput, {
        id: 'new-password', type: 'text', value: 'secret', readOnly: true, disabled: true,
    }));
    assert.match(html, /<input(?=[^>]*id="new-password")(?=[^>]*type="password")[^>]*>/);
    assert.match(html, /value="secret"/);
    assert.match(html, /aria-controls="new-password"[^>]*disabled=""/);
});

test('forbidden-page recovery returns each account to its permitted dashboard', () => {
    const { default: ErrorPage } = load(resolve(root, 'Pages/Errors/Error.tsx'));
    for (const [permissions, destination] of [
        [['business.dashboard.view'], '/business/dashboard'],
        [['devices.view'], '/admin/dashboard'],
        [['business.dashboard.view', 'devices.view'], '/admin/dashboard'],
    ]) {
        page.props.auth.user = { permissions };
        try {
            const html = renderToStaticMarkup(React.createElement(ErrorPage, { status: 403 }));
            assert.match(html, /Acceso denegado/);
            assert.ok(html.includes(`href="${destination}"`));
            assert.match(html, /Volver al dashboard/);
        } finally {
            page.props.auth.user = null;
        }
    }
    const guestHtml = renderToStaticMarkup(React.createElement(ErrorPage, { status: 419 }));
    assert.match(guestHtml, /Sesión expirada/);
    assert.match(guestHtml, /href="\/login"[^>]*>Iniciar sesión/);
});

test('sidebar retains an accessible Alter link while collapsed and expanded', () => {
    const { SidebarShell } = load(resolve(root, 'Components/app/SidebarShell.tsx'));
    for (const collapsed of [true, false]) {
        const html = renderToStaticMarkup(React.createElement(SidebarShell, {
            brand: { title: 'Alter', subtitle: 'Panel del negocio', href: '/business/dashboard' },
            sections: [], collapsed,
        }));
        assert.match(html, /aria-label="Alter · Panel del negocio"/);
        assert.match(html, /<img [^>]*src="\/brand\/alter-logo-20261003.png"/);
        assert.doesNotMatch(html, /Signage TV/);
    }
});

test('shared dashboard footer includes brand image and visible Alter name', () => {
    const { DashboardLayout } = load(resolve(root, 'Layouts/DashboardLayout.tsx'));
    const html = renderToStaticMarkup(React.createElement(DashboardLayout, {
        sidebar: () => null, topNavigation: () => null, children: 'Dashboard',
    }));
    assert.match(html, /<footer[\s\S]*src="\/brand\/alter-logo-20261003.png"/);
    assert.match(html, /Alter · Plataforma/);
    assert.doesNotMatch(html, /Signage TV/);
});

test('business menu keeps one Programación and hides retired menus even with permissions', () => {
    const { BusinessSidebar } = load(resolve(root, 'Components/app/BusinessSidebar.tsx'));
    page.props.auth.user = { roles: ['business_owner'], permissions: [
        'business.dashboard.view', 'business.media.view', 'business.devices.view',
        'business.schedules.view', 'business.settings.view', 'business.playlists.view', 'business.reports.view',
    ] };
    try {
        const html = renderToStaticMarkup(React.createElement(BusinessSidebar, { collapsed: false }));
        assert.equal((html.match(/href="\/business\/schedule"/g) ?? []).length, 1);
        assert.match(html, />Programación</);
        assert.match(html, /aria-label="Alter · Panel del negocio"/);
        assert.doesNotMatch(html, /href="\/business\/(playlists|reports)"|>Contenido<|>Reportes</);
    } finally {
        page.props.auth.user = null;
    }
});
