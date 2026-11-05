# Transmisiones en vivo

## Arquitectura y plan de integración

1. Extender `media_assets` con el tipo `live_stream` y metadatos validados; conservar biblioteca, permisos y campañas.
2. Añadir páginas Blade firmadas y aisladas para los reproductores oficiales.
3. Ampliar el formulario de campañas y el manifiesto existente con horario absoluto, zona, audio y respaldo.
4. Añadir renderizadores Media3/HLS y WebView en Android, usando las capas y cursores de reproducción existentes.
5. Incorporar recuperación, pruebas, diagnóstico y prueba de reproducción en las colas actuales.

Las fuentes se crean desde **Creatividades → Agregar directo** o desde el paso **Creatividades** de una campaña. El administrador pega una URL, ve el proveedor detectado y pulsa **Probar directo**. Después configura horario del evento, calendario de campaña, zona, audio y respaldo. El directo ocupa su zona durante la ventana del evento, no durante la duración de una diapositiva. La ventana debe coincidir con los días y horas habilitados en la campaña.

`media_assets.metadata.live` conserva proveedor, URL normalizada, identificador y estado de configuración; `metadata.created_by` conserva al creador. `campaign_creatives.configuration` contiene la configuración particular de la asignación. Los estados de disponibilidad son del reproductor, no una garantía permanente del registro de la biblioteca.

El manifiesto incluye campañas futuras con directos, fechas de campaña y referencias al respaldo. Los archivos del respaldo se descargan mediante el caché actual. Las URLs HLS y los reproductores web nunca se descargan al caché multimedia.

## Despliegue en EasyPanel

1. Desplegar dashboard con `composer install` y `npm ci && npm run build`, como en la imagen Docker actual.
2. Ejecutar `php artisan migrate --force`. La nueva migración agrega dos columnas JSON opcionales; no modifica archivos ni volúmenes.
3. Configurar `APP_URL` con el origen HTTPS público real. Para Twitch, configurar `LIVE_TWITCH_PARENTS=signage.finespublicidad.com` (sin esquema ni ruta). Incluir también cualquier dominio propio adicional que contenga la vista previa.
4. Para HLS, configurar `LIVE_HLS_HOSTS=video.ejemplo.com,segmentos.ejemplo.com`: hosts exactos aprobados para la URL inicial. Android permite también sus subdominios para segmentos y claves; configura hosts específicos controlados por tu proveedor. Sin esta variable no se aceptan fuentes HLS. Las URLs deben ser HTTPS directas; no se siguen redirecciones en Android. La prueba en navegador necesita CORS habilitado por el servidor HLS.
5. Reconstruir configuración con `php artisan config:cache` y reiniciar los workers según el despliegue habitual.
6. Instalar APK **0.1.15 o posterior** en las pantallas destino **antes de publicar campañas con directos**. Las versiones anteriores a 0.1.14 no reconocen `live_stream`; 0.1.15 corrige la retirada de campañas durante descargas y reproducciones temporales.

El volumen `signage-media` permanece en `/var/www/html/storage/app/public`. Esta función no cambia su ubicación. No se ha desplegado automáticamente al servidor.

## Prioridades, audio y recuperación

Se conserva Instant Play explícito de pantalla completa como máxima prioridad. Después se selecciona un directo por prioridad de campaña, desempate por campaña y orden del elemento. El directo ocupa solo su zona y pausa su contenido subyacente; los Instant Plays de esa zona esperan. Al terminar el horario o retirar la campaña del manifiesto, vuelve el contenido habitual. Los cursores se conservan durante la ocupación temporal.

Desde Android 0.1.15, las campañas retiradas se eliminan de la programación local en cuanto se recibe y verifica la vigencia del nuevo manifiesto, antes de descargar archivos de reemplazo. Una descarga fallida no restaura la campaña retirada. La versión nueva solo se confirma al completar la instalación; el contenido del negocio se conserva. Las descargas comprueban cada 10 segundos si su manifiesto fue reemplazado y se cancelan si quedó obsoleto. La TV necesita conexión para recibir la pausa del servidor.

Solo la zona autorizada por el modo de audio del TV puede emitir sonido. Pantalla completa puede emitir audio si el dispositivo no está silenciado y el administrador lo habilitó. El respaldo se reproduce silenciado.

Por defecto, conexión o buffering sin recuperación durante 30 segundos activa el respaldo. Los reintentos esperan 30 segundos y aumentan hasta 300 segundos. Sin conexión no se reintenta agresivamente. Al confirmar reproducción vuelve el directo. Si no existe respaldo explícito se usa la lista publicitaria normal; si está vacía se intenta contenido local del negocio. Sin contenido disponible se muestra un aviso y se sigue reintentando.

Parámetros: `LIVE_CONNECT_TIMEOUT_SECONDS`, `LIVE_RETRY_SECONDS`, `LIVE_MAX_RETRY_SECONDS`, `LIVE_UNVERIFIED_SECONDS`. Android acota valores para impedir intervalos peligrosos. Los dominios de recursos de proveedores se centralizan en `config/live.php`; no se aceptan desde formularios.

## Proveedores y límites verificados

- [YouTube IFrame API](https://developers.google.com/youtube/iframe_api_reference): estados de reproducción, errores y bloqueo de autoplay. [Parámetros y dimensiones](https://developers.google.com/youtube/player_parameters): mínimo 200×200 px. Se conserva el Referer y se configura `origin` para evitar el error de identificación del cliente.
- [Twitch Video & Clips](https://dev.twitch.tv/docs/embed/video-and-clips/): HTTPS, `parent`, mínimo 400×300 px; eventos PLAYING, OFFLINE y PLAYBACK_BLOCKED. El autoplay puede depender del dispositivo.
- [Kick embed oficial](https://help.kick.com/en/articles/8010826-how-to-embed-your-kick-livestream): `player.kick.com`, autoplay, muted y allowfullscreen. No documenta una API de confirmación de reproducción o desconexión. Por ello se informa **sin confirmación**, nunca se considera que cargar el iframe demuestra reproducción. Después del periodo configurable de 300 segundos se pasa al respaldo y se reintenta; esto puede interrumpir un directo Kick sano. Es una protección conservadora contra pantallas indefinidamente vacías, no detección fiable de disponibilidad.

La página comprueba el tamaño real para YouTube/Twitch y reporta error si no cumple. El formulario requiere revisar el tamaño y recomienda una zona mayor o pantalla completa; nunca cambia el layout automáticamente. El backend no conoce las dimensiones reales de cada viewport y no puede certificar el tamaño antes de probar en ese TV.

No se extraen URLs internas, no se usa scraping ni yt-dlp. Los proveedores pueden bloquear videos no insertables, restringidos, con consentimiento obligatorio o autoplay. Probar el enlace en el TV real sigue siendo necesario.

## Seguridad y prueba de reproducción

Las páginas de reproducción requieren firmas del servidor y no aceptan HTML/iframes del usuario. CSP con nonce, `frame-src` por proveedor, sin formularios ni objetos; Referer conservado. WebView solo admite el embed propio como documento principal, HTTPS y recursos de dominios autorizados. No expone puentes nativos a JavaScript, archivos, ventanas nuevas, descargas ni depuración.

La prueba de reproducción usa la misma base local y el endpoint por lotes. Cada segmento conserva `metadata.started_event` (`stream_started`/`fallback_started`) y su evento final (`stream_completed`, `stream_failed` o `fallback_completed`), proveedor, fuente y verificación. Inicio y fin comparten registro para no duplicar solicitudes ni contar dos veces el mismo intervalo. Los errores de conexión anteriores al inicio también se reportan. El heartbeat añade proveedor y estado al diagnóstico de pantalla.

## Verificación

Pruebas backend cubren detección/normalización, rechazo de URLs y manipulación de firmas, autorización, configuración de campaña, respaldo, edición, manifiestos futuros, retirada de campañas y metadatos de reproducción. Android cubre selección de renderizador, programación, prioridades, audio, seguridad de URLs, recuperación y exclusión del caché. Hay una prueba de Compose para restaurar las zonas sin remontar sus reproductores.

Resultado local del 23/09/2026: 150 pruebas backend (1116 aserciones), 72 pruebas JVM Android, 2 pruebas Compose en Android TV API 34 y 3 pruebas frontend aprobadas. TypeScript, Vite, Pint y APK firmado de producción correctos. La prueba adicional del manifiesto cubre lectura de los campos nuevos y rechazo de referencias de respaldo inexistentes.

La inspección del navegador confirmó creación/detección y configuración de una fuente, y visualización del error 150 de YouTube en la vista previa. No confirma reproducción efectiva de todos los proveedores en televisores físicos. Consultar `E:/programacion/signage-app/artifacts/VALIDATION-0.1.14.md` para límites y evidencias de la validación.
