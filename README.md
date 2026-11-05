<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Inicialización de producción en EasyPanel

Con `APP_ENV=production` y las variables `DB_*` configuradas, ejecuta en la consola del dashboard:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan signage:admin
```

El seed de producción prepara roles, permisos, layouts y ajustes básicos sin Faker, cuentas demo ni contenido ficticio. Se puede repetir; conserva usuarios, contraseñas y ajustes existentes. El comando `signage:admin` pide nombre, correo nuevo y una contraseña de al menos 12 caracteres (entrada oculta y confirmación). Crea un superadministrador activo; rechaza correos existentes sin modificarlos. No introduzcas la contraseña como argumento del comando.

Si ya ejecutaste el seeder anterior antes del fallo de Faker, pudo haber creado las cuentas demo `admin@signagetv.co`, `operaciones@signagetv.co`, `campanas@signagetv.co` y `soporte@signagetv.co`. Entra con tu administrador propio, revisa Usuarios y suspende esas cuentas de demostración. Este cambio no elimina usuarios existentes ni borra la base de datos.

Los datos demo solo se cargan con `APP_ENV=local` o `testing` y requieren las dependencias de desarrollo. Después de preparar producción, crea un negocio y una ubicación desde el dashboard y asigna el código que muestra tu TV.

## Orientación inicial de las TV nuevas

Al vincular una TV nueva, la API le asigna un layout exclusivo vertical con giro de 90° y división superior/inferior. Conserva la proporción y los demás ajustes del layout predeterminado; si no existe, usa 70/30 con negocio arriba y publicidad abajo. Una zona de negocio izquierda/superior se convierte en superior y una derecha/inferior en inferior. Los layouts compartidos, las TV existentes y sus giros elegidos se conservan, incluidos los reintentos de activación.

Este cambio está preparado y probado localmente; su despliegue del backend está pendiente. No requiere migraciones ni seeders. El APK actualizado debe instalarse también para mostrar la activación y el menú verticales desde el primer inicio.

## Compatibilidad de fechas de activación en Android TV

La API de activación devuelve `expires_at` en UTC con terminación `Z` (por ejemplo, `2026-09-17T20:52:02Z`). Esto conserva el instante de vencimiento y permite que lo interpreten también las implementaciones antiguas de `Instant.parse` de Android que rechazan `+00:00`. La zona horaria del TV puede continuar en Bogotá y la del servidor en UTC. Referencia: [lector de instantes de Android API 30](https://android.googlesource.com/platform/prebuilts/fullsdk/sources/android-30/+/refs/heads/androidx-core-release/java/time/format/DateTimeFormatterBuilder.java#3253).

Si el APK 0.1.3 muestra `Solicitud · RESPUESTA_INVALIDA`, desplegar este cambio permite descartar esa incompatibilidad de fecha sin reinstalar la app ni modificar códigos pendientes. El diagnóstico también puede corresponder a JSON o campos inválidos; hace falta comprobar el TV después del despliegue para confirmar la causa. No requiere migraciones ni volver a ejecutar seeders.

Las fechas de vencimiento de los comandos y del contenido de Instant Play también se entregan con terminación `Z`. La API normaliza las fechas de Instant Play que quedaron en comandos pendientes antes del despliegue, conservando su instante y los datos almacenados. Un envío que el TV ya marcó como fallido no se ejecuta de nuevo: después de desplegar la corrección hay que crear otro Instant Play. Esta corrección del backend funciona con el APK 0.1.3 y no requiere volver a vincular el TV.

## Entrega de comandos sin confirmación

`Enviado` significa que la API ofreció el comando; no confirma que el televisor recibió el cuerpo HTTP ni que inició la reproducción. La API vuelve a ofrecer los comandos enviados sin resultado después de un minuto, conservando ID, contenido y vencimiento. Prioriza comandos nuevos y luego los ofrecidos hace más tiempo. El APK guarda los IDs procesados para que un reintento no repita la reproducción. Los comandos completados, fallidos o vencidos no se vuelven a entregar. Esta corrección funciona con el APK 0.1.3 y no requiere migraciones; un Instant Play vencido necesita un envío nuevo.

## PIN administrativo de Android TV

Todas las pantallas de todos los negocios usan un único PIN de seis dígitos. Tras desplegar y ejecutar `php artisan migrate --force`, un usuario del personal con permiso `devices.manage` debe configurarlo en **Pantallas → PIN global**. Los PIN anteriores por pantalla dejan de funcionar; sin configurar el nuevo PIN global, la verificación devuelve 409. No hay PIN predeterminado ni se muestra el valor guardado.

Se conserva `POST /api/v1/device/admin/verify-pin` con el token de la pantalla para los APK que verifican el PIN en línea. El hash está oculto y se limitan los intentos por dispositivo, IP y red completa. No se requiere volver a vincular las pantallas. [Configuración, límites y reversión](docs/global-screen-pin.md).

## Dashboard web y PWA

El cambio de cuenta respeta los permisos del nuevo usuario, el login permite mostrar/ocultar la contraseña, y el preview representa el contenido confirmado con orientación y división. El preview sigue siendo aproximado hasta implementar los próximos cambios Android. El dashboard ofrece instalación como PWA y un aviso público sin datos privados al perder conexión. [Entrega, comprobaciones y pasos en EasyPanel](docs/dashboard-web-validation.md).

## Ajustes de pantalla desde el TV (APK 0.1.6)

El APK 0.1.7 añade `settings.rotation` (0, 90, 180, 270) y `settings.transition` (`playlist`, `none`, `fade`, `crossfade`, `slide_left`, `slide_right`, `soft_zoom`). Se conservan en la configuración del layout exclusivo de la pantalla y se incluyen en los siguientes manifiestos. `playlist` respeta la transición de cada elemento. Los clientes anteriores que envían `orientation` siguen funcionando y restablecen el giro a 0°/90°. Las confirmaciones tardías de sincronización conservan versiones nuevas pendientes y no revierten una versión más reciente ya confirmada.

Para habilitarlo, desplegar el backend actualizado e instalar el APK 0.1.7; este cambio no incorpora migraciones ni cambios del frontend. Los originales de las imágenes del negocio permanecen en el panel, y el TV guarda una copia persistente para reproducir sin Internet después de sincronizar. Los archivos publicitarios mantienen su almacenamiento en la nube.

La app puede guardar división, proporción, orientación y audio mediante `PATCH /api/v1/device/admin/settings`, con bearer de la pantalla y un objeto `settings` que contiene los campos modificados (`split`, `business_percentage`, `orientation`, `audio_mode`). El contrato actual admite guardar sin enviar PIN; la verificación del PIN para entrar al menú usa el endpoint separado `admin/verify-pin`. No permite elegir otra pantalla ni modificar un diseño compartido: crea una configuración nueva cuando cambia el diseño y la asigna únicamente al dispositivo autenticado. El dashboard refleja su layout asignado; los siguientes manifiestos conservan los cambios.

Devuelve un manifiesto completo para que Android lo instale de inmediato, sin inventar versiones locales. Las versiones se generan bajo bloqueo del dispositivo y crecen aunque se guarden varios cambios en un segundo. La auditoría identifica la pantalla y campos modificados, sin PIN ni token. Los errores de descarga posteriores al guardado dejan el manifiesto pendiente de sincronización, conservando el contenido local anterior.

Desplegar el backend y actualizar al APK 0.1.6. Este cambio no añade migraciones; presupone que ya existe la migración del PIN de 0.1.4. No hace falta regenerar el PIN ni desvincular el dispositivo.

## Instant Play para negocios

El historial y el detalle de Instant Play incluyen **Reintentar fallidas** y **Eliminar**, tanto en administración como en negocios. El reintento está disponible cuando el envío terminó con fallos y crea un envío nuevo únicamente para esas pantallas, conservando archivo, duración y modo. Usa comandos nuevos y un vencimiento renovado, por lo que Android no lo descarta como un comando repetido. Los clics duplicados sobre el mismo envío abren el mismo reintento; si vuelve a fallar, se puede reintentar desde el nuevo envío.

Eliminar pide confirmación, oculta el envío del historial y cancela la entrega/reentrega de sus comandos pendientes. No borra el archivo de la biblioteca ni detiene un contenido que el TV ya recibió. Se conserva el registro interno para aceptar las confirmaciones tardías del dispositivo sin errores ni reapariciones en el historial. Estas acciones requieren `quick_play.send` en administración o `business.devices.view` y `business.playlists.manage` en negocios; siempre respetan el negocio activo.

Esta mejora requiere desplegar el código, ejecutar `php artisan migrate --force` (migración `2026_09_19_180000_add_soft_deletes_to_quick_plays_table`) y `npm run build`. No necesita actualizar el APK.

Disponible en `/business/quick-play`: reutiliza el envío, historial y seguimiento del administrador con el contexto del negocio. Solo permite archivos listos de su biblioteca y pantallas/ubicaciones propias. La selección «Todas las pantallas» se limita al negocio activo; cambiar de negocio también cambia el historial. Ver requiere `business.devices.view`; enviar requiere además `business.playlists.manage`. Los roles de negocio existentes ya tienen estos permisos.

Para desplegar esta modificación:

```powershell
php artisan migrate --force
npm ci
npm run build
```

La migración `2026_09_12_220000_add_business_id_to_quick_plays_table` añade la propiedad del envío sin modificar el historial administrativo existente. En negocios el seguimiento consulta el estado cada seis segundos y se detiene al completar o fallar la entrega.


## Actualización Android TV 0.1.9

- La app abre y guarda ajustes sin PIN. `PATCH /api/v1/device/admin/settings` requiere únicamente el bearer del TV y `settings`; mantiene aislamiento por dispositivo y rechaza tokens revocados y pantallas deshabilitadas. El endpoint y los controles de PIN anteriores quedan disponibles para APK antiguos.
- En **Dispositivos → pantalla → Validación** se muestran diagnóstico de sincronización, imágenes locales, carpeta privada del TV, fallos de reproducción y comandos. Se actualiza cada 15 segundos con los reportes recibidos. Los datos offline llegan al reconectar el TV.
- El heartbeat acepta un objeto opcional `diagnostics`, guardado como JSON en `device_heartbeats`. Los APK anteriores no necesitan enviarlo.
- Las creatividades de campañas permiten 3–86.400 segundos. El formulario muestra errores del servidor, incluidos los campos de duración.

Despliegue: ejecutar `php artisan migrate --force`, compilar/publicar los assets con `npm run build` y desplegar el backend antes de instalar `Signage-TV-production-0.1.9-release.apk`. No se ha ejecutado esta migración sobre producción desde la tarea. La app conserva originales del negocio en el panel y copias verificadas en `/data/user/0/tv.signage.player/files/signage/media/`; los originales publicitarios continúan en la nube.

## Archivos que sobreviven a los despliegues

En EasyPanel agrega un volumen persistente a `/var/www/html/storage/app/public`, respaldando y trasladando antes los archivos existentes. La ruta `/` del dominio no crea este volumen. El arranque ya no borra `public/storage` y `php artisan signage:media-check` detecta originales o portadas ausentes sin modificar datos. [Pasos y recuperación](docs/MEDIA-PERSISTENCE.md).
