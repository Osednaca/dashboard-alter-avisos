# Instalar el panel Alter

El panel se puede instalar como aplicación desde un navegador compatible. Necesita conexión para consultar o cambiar datos; cuando no hay red, muestra una página pública de aviso y no recupera información de otra sesión.

## Uso

1. Abre el panel por HTTPS y entra con tu cuenta.
2. Usa «Instalar aplicación» en el menú del navegador cuando esté disponible. En iPhone/iPad, usa Safari → Compartir → Añadir a pantalla de inicio.
3. Abre Alter desde su icono. El inicio continúa usando la autenticación y los permisos del panel.

La opción y los criterios de instalación dependen del navegador. El manifiesto ofrece iconos PNG de 192 y 512 píxeles y un icono maskable con margen seguro, derivados del nuevo PNG canónico `alter_TV.png`. No requiere una dependencia adicional ni un botón de instalación propio.

## Datos y actualización

El service worker `/sw.js` se registra únicamente en compilaciones de producción y contextos seguros. `localhost` permite una comprobación local; una instalación real necesita HTTPS, el build de Vite y los archivos públicos de esta unidad.

Solo guarda la página pública `/offline.html`, el logotipo, los iconos y recursos públicos con hash de `/build/assets/`. Rechaza respuestas `private`/`no-store`, redirecciones y contenido HTML/JSON fuera del aviso público. Las peticiones Inertia, de API, medios privados, almacenamiento, autenticación y cambios de datos quedan fuera de la caché. Las navegaciones consultan la red con `cache: no-store`, evitando también la caché HTTP del navegador; incluso sin conexión no se reproducen páginas autenticadas.

Al actualizar el aviso o los iconos, incrementa `CACHE_NAME` en `public/sw.js`. La activación elimina únicamente versiones anteriores con el prefijo `alter-public-pwa-`; conserva las cachés de otros componentes. Los assets de Vite cambian de URL con su hash.

Esta actualización utiliza `alter-public-pwa-v2`, logo `/brand/alter-logo-20261003.png` e iconos terminados en `-20261003.png`, incluyendo el icono de pantalla de inicio de Apple. Las rutas nuevas evitan que la caché HTTP reutilice los iconos de la marca anterior.

## Comprobación local

Ejecuta `node --test tests/Frontend/pwa*.test.mjs`, `npm run typecheck` y `npm run build`. Las pruebas ejecutan el service worker y la lógica real de registro en un entorno simulado, validan dimensiones de PNG y prueban límites de caché, cierre de sesión y navegación sin red. No certifican el diálogo de instalación de un navegador ni un despliegue HTTPS.

En un perfil local aislado, comprueba el manifiesto en DevTools → Application, el registro del worker y el aviso al navegar sin conexión. La publicación y la prueba en el servidor real requieren una acción posterior autorizada.

Referencias: [criterios de instalación](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable), [Cache y cabeceras HTTP](https://developer.mozilla.org/en-US/docs/Web/API/Cache).
