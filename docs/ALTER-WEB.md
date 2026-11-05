# Alter en el dashboard web

El panel web usa el nuevo PNG suministrado y la marca **Alter** en acceso, navegación de administrador y negocio, pie de página, título y favicon. La app Android también presenta el nombre Alter y recursos de marca propios para el launcher, la pantalla inicial y las notificaciones.

## Qué cambia para el negocio

- Un solo menú **Programación** mantiene el formulario unificado de contenido y horarios.
- Se retiran los accesos **Contenido** y **Reportes** del menú; no se borran archivos ni reportes ni se modifican permisos.
- Configuración deja de mostrar audio/notificaciones, marca y zona horaria. Guardar los datos de contacto conserva los valores ocultos existentes.
- Se conservan los logos y nombres propios de los negocios, la campana de notificaciones y los controles del administrador.

## Publicación posterior

1. Publicar el código y los assets compilados del dashboard mediante el procedimiento habitual, incluyendo `public/brand/alter-logo-20261003.png`, los iconos PWA terminados en `-20261003.png` y `public/sw.js`.
2. En el contenedor del dashboard y desde `/var/www/html`, ejecutar `php artisan migrate --force` y después `php artisan config:cache` mediante el procedimiento de despliegue autorizado. El `docker/entrypoint.sh` actual prepara carpetas/permisos y el enlace de almacenamiento, pero **no** ejecuta estas dos operaciones automáticamente. No ejecutar seeders sobre producción para cambiar la marca.
3. Comprobar acceso, menú móvil/escritorio, footer y el guardado de datos de contacto de un negocio. El cambio de marca web es independiente del APK nuevo, que actualiza el nombre visible y los recursos Android.

Si el despliegue personaliza `branding.logo_url` con configuración propia o mantiene una caché de configuración antigua, usar `/brand/alter-logo-20261003.png` o retirar esa personalización y regenerar la caché. El código de esta entrega fija la ruta en `config/branding.php`; no introduce una variable de entorno nueva. Mantener `APP_NAME` y los identificadores técnicos existentes.

Esta entrega solo prepara cambios locales: no ejecuta despliegues ni migraciones en producción.

## Identidad y datos preservados

| Elemento | Comportamiento |
| --- | --- |
| `config/branding.php` | Nombre y ruta de logo públicos, compartidos con Inertia. |
| `APP_NAME` / `VITE_APP_NAME` antiguos | No deciden la marca visible. **No cambiar `APP_NAME` solo por esta entrega**: también puede determinar cookies, caché y Redis. |
| Logo | Copia exacta del PNG `alter_TV.png`, 1064×1064 con transparencia; SHA256 `f80febb0b718a6ebb15c3b14697b79adb9ece633afeb0b96728ef322df9bd07d`. Sin recortar, recolorear ni reinterpretar. |
| `network.name` | La migración cambia únicamente `Red Signage TV Colombia` por `Red Alter Colombia`; conserva nombres personalizados y otros campos JSON. |
| Reversión | `down()` no revierte datos: no puede distinguir un nombre Alter personalizado posterior. Restaurar el nombre solo de forma explícita si se necesita. |
| Identidades técnicas | Rutas, hosts, almacenamiento local, cuentas, cookies, API y puentes Android no se renombran. |

## Verificación y límites

Regresiones PHP comprueban el hash y formato del PNG canónico, la marca con `APP_NAME` antiguo, la preservación de identidad técnica y la migración condicionada. Pruebas de renderizado estático React verifican logo, acceso en ambos contenedores responsive, menú colapsado y footer. La URL nueva invalida el logo/favicon anterior; el service worker usa la caché `alter-public-pwa-v2`, retira las versiones propias anteriores y deja fuera los datos privados.

Los iconos PWA derivan del PNG canónico con reducción proporcional; el maskable conserva el logo dentro del 75% central sobre fondo cyan. El icono adaptive Android usa ese mismo PNG canónico con margen seguro y fondo cyan. El icono legacy y el banner TV se prepararon con la herramienta integrada `image_gen`, referenciada al original, y se redujeron proporcionalmente para el empaquetado. El banner consume 320×180 píxeles xhdpi con texto Alter; la notificación usa una silueta blanca separada. Los namespaces, el package ID y el certificado no se cambian.

Fuentes de generación conservadas en el proyecto Android: `artifacts/alter-brand-assets/alter-launcher-generated.png` y `artifacts/alter-brand-assets/alter-tv-banner-generated.png`. Los archivos consumidos son `app/src/main/res/mipmap-xhdpi/alter_launcher.png` y `app/src/main/res/drawable-xhdpi/alter_tv_banner.png`; el resource `drawable-nodpi/alter_brand.png` mantiene byte a byte el original.

Prompts exactos enviados a la herramienta integrada `image_gen`, referenciada a `D:/Desktop/alter_TV.png`:

```text
Use case: precise-object-edit. Asset type: Android TV launcher icon. Edit target: provided Alter TV logo. Create a production app icon from this exact logo, preserving its cyan circle, white geometric symbol and ALTER TV lettering faithfully, with crisp original geometry, flat color and no decoration. Square transparent canvas, keep full circular logo centered, with generous transparent safety margin around the logo (logo occupies 66% of width and height) for adaptive Android masks. Do not redraw into a different brand; do not add shadows, gradients, 3D, background, border or new text. Preserve transparency outside circle.
```

```text
Use case: compositing. Asset type: Android TV app launcher banner, exact 16:9 landscape composition. Input image: provided Alter TV circular logo, insert reference preserving its cyan and white symbol and ALTER TV lettering. Simple dark navy solid background #07182e. Place the complete circular logo at left, occupying about 60% of banner height with generous margin, and a single large white word Alter at right in clean bold sans-serif type. Center group vertically and horizontally. Crisp flat brand graphic readable when reduced to 320x180. No other text, no gradients, shadows, texture, mockups, extra symbols, outlines or effects. Must preserve supplied logo identity and original straight geometric edges.
```

La comprobación visual de esta marca nueva y del paquete APK corresponde a la verificación integrada de la entrega; el renderizado estático no certifica un launcher real ni la instalación PWA. [Iconos Android TV](https://developer.android.com/design/ui/tv/guides/system/tv-app-icon-guidelines), [margen de iconos adaptive](https://developer.android.com/develop/ui/compose/system/icon_design_adaptive).
