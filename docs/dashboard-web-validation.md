# Dashboard web: entrega y validación

El dashboard corrige el cambio de cuenta en un mismo navegador, permite mostrar/ocultar la contraseña, representa el contenido confirmado y la orientación de cada TV, usa un PIN global para todas las pantallas y ofrece instalación como PWA. La sincronización real de reproducción espera los próximos requisitos Android del usuario; este trabajo no modifica Android ni genera un APK.

## Despliegue en EasyPanel

El usuario realiza el despliegue. Los cambios están publicados en [codex/dashboard-web-reliability](https://github.com/Osednaca/dashboard-androidtv/tree/codex/dashboard-web-reliability), basada en `4ccea73` e incluyendo los valores verticales iniciales de las TV nuevas. El push con la autenticación Git configurada fue autorizado explícitamente y su primer commit remoto `0be7412` se verificó idéntico al local. No se hizo PR, merge ni despliegue en EasyPanel.

1. Publicar el backend y sus assets mediante el proceso habitual de EasyPanel. El `Dockerfile` ejecuta `npm run build`; `docker/entrypoint.sh` prepara almacenamiento, pero **no ejecuta migraciones**.
2. En la consola de la aplicación desplegada, con las variables de producción correctas, ejecutar `php artisan migrate --force`. No se requiere volver a cargar seeders ni crear cuentas. No ejecutar resets de la base de datos.
3. Entrar con personal autorizado a **Pantallas → PIN global** (`/admin/devices/global-pin`) y configurar/confirmar seis dígitos. Todos los PIN anteriores por pantalla dejan de funcionar; hasta guardar el nuevo PIN, la verificación devuelve 409. Cambiarlo afecta inmediatamente a todas las pantallas de todos los negocios. [Detalles y reversión del PIN](global-screen-pin.md).
4. Comprobar el panel por HTTPS y la instalación en el navegador objetivo. El servidor real, los proveedores de vídeo y los TV físicos requieren esta comprobación posterior. [Uso y caché de la PWA](pwa-dashboard.md).

## Resultado y límites

| Punto | Resultado observado |
|---|---|
| Cuenta y contraseña | Tras cerrar la sesión de administrador y conservar una URL `/admin/campaigns`, el acceso como negocio llega a `/business/dashboard`. Enter muestra la contraseña y Espacio la oculta; el login posterior funciona. Volver al historial administrativo consulta nuevamente el servidor, obtiene 403 y ofrece el enlace correcto al dashboard del negocio. |
| Correo | Crear el negocio y su usuario con el mismo correo de contacto está permitido. El usuario confirmó que el rechazo correspondía a una cuenta ya existente; se conserva la unicidad de correo entre cuentas. |
| Preview | Usa el manifiesto confirmado de la TV, respeta rotación, división y orden. Se observó vertical 90°, publicidad arriba 30% y negocio abajo 70%, en móvil y escritorio 1280×900. Indica explícitamente que es aproximado. No conoce el elemento/cursor actual, Instant Play ni el estado real de un proveedor en la TV. [Estado del preview](preview-status.md). |
| PIN | La página global mostró alcance de todos los negocios, estado sin configurar y campos de seis dígitos/confirmación. No se configuró un PIN real durante la prueba de navegador. Las pruebas automatizadas verifican cambio global, invalidación, permisos y límites de intentos. |
| PWA sin conexión | Con el service worker activo, detener el servidor y recargar la URL autenticada del PIN devolvió el aviso público sin datos de cuenta. No se ejercitó el diálogo de instalación del sistema operativo. |

La comprobación de navegador usó exclusivamente SQLite local aislado (`dashboard-web-browser.sqlite`) y `localhost:8127`, sin sesiones de producción ni proveedores remotos. El tab se cerró, el viewport se restableció y el servidor se detuvo. Evidencia local: `storage/app/dashboard-web-preview.jpg`, `storage/app/dashboard-web-global-pin.jpg` y `storage/app/dashboard-web-offline.jpg`.

## Checks y unidades de revisión

| Check | Resultado |
|---|---|
| PHPUnit completo | 214 pruebas, 2120 aserciones, cero fallos/errores/omitidas; 53.065 s. Informe `storage/logs/dashboard-web-full-phpunit.xml`. SQLite en memoria y configuración de pruebas aislada. |
| Frontend | 29/29 pruebas aprobadas, 19.798 s. |
| TypeScript / build | `npm run typecheck` y `npm run build` aprobados; build 28.19 s. Permanece el aviso existente del bundle HLS mayor de 500 kB. |
| Formato | Pint en los PHP modificados y `git diff --check` aprobados. |

Commits locales: `1943757` autenticación/correo (386 líneas), `955c773` PIN global (426), `34de90f` preview (962) y `8625fda` PWA (387, excluidos tres PNG generados). Son límites coherentes para la cadena sobre rama de función elegida por el usuario; no se crearon PR. RDD permanece desactivado. El documento de recuperación es `odd/tasks/dashboard-web-reliability.md`; la copia Engram sigue pendiente por falta de herramientas disponibles.
