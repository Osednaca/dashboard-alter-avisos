# Publicar una actualización Android

En Administración → Actualizaciones Android (`/admin/android-updates`), un administrador con permiso `system.settings` selecciona un APK de producción, añade las notas y decide si la actualización es obligatoria. Al publicar, el servidor extrae paquete, `versionCode` y `versionName` del `AndroidManifest.xml` binario y calcula SHA-256. No hay campos manuales para esos metadatos.

El APK debe tener el paquete `tv.signage.player`, ser una aplicación base sin splits y pesar como máximo 128 MiB. Las notas admiten hasta 16 KiB de UTF-8. `forceUpdate` es falso por defecto. Firma cada nueva versión con la misma identidad usada por la aplicación instalada y aumenta `versionCode`; el servidor no realiza verificación criptográfica de firmas. Android valida checksum, identidad y firma antes de instalar mediante el cliente OTA.

La publicación genera estas rutas públicas HTTPS, sin requerir sesión del dashboard:

- `https://signage.finespublicidad.com/updates/android/signage-<versionName>.apk`
- `https://signage.finespublicidad.com/updates/android/latest.json`

El JSON contiene exclusivamente `versionCode`, `versionName`, `apkUrl`, `sha256`, `forceUpdate` y `changelog`. Por ejemplo, la primera versión es `signage-0.1.23.apk`; sus valores reales proceden del APK subido. El JSON usa `no-store` y los APK usan caché inmutable.

Los archivos quedan en `storage/app/public/updates/android`, dentro del volumen de medios existente. Conserva ese volumen y sus permisos al desplegar. El contenedor actual ya incluye `unzip`, necesario para leer el manifiesto con un proceso acotado; no necesita Android SDK ni extensión PHP ZIP. El directorio privado `storage/app/private/android-update-staging` necesita escritura temporal y no se publica.

Un bloqueo de archivo coordina las publicaciones que comparten el volumen. Primero se guarda y sincroniza un temporal dentro del directorio público y se renombra al nombre definitivo; finalmente se sustituye `latest.json` mediante renombrado en el mismo sistema de archivos. Un fallo conserva el manifiesto anterior. Puede quedar un APK completo sin anunciar si falla el último renombrado; una nueva carga idéntica permite terminar la publicación.

No se permiten versiones inferiores, reutilizar el mismo `versionCode` con otro APK ni sobrescribir un nombre de versión con bytes diferentes. Subir exactamente el mismo APK permite cambiar únicamente notas y política obligatoria. Los APK anteriores se conservan para evitar romper descargas en curso. La acción queda registrada en auditoría.

Este cambio requiere desplegar el dashboard por el procedimiento habitual; no realiza despliegues ni transferencias remotas. Después del despliegue, usa esta pantalla para publicar el APK firmado. Las instalaciones anteriores que tenían OTA desactivado necesitan instalar manualmente una primera versión que incluya el endpoint configurado.

## Comprobaciones locales nuevas

`php vendor/bin/phpunit tests/Unit/AndroidBinaryManifestTest.php tests/Feature/AndroidUpdatePublicationTest.php` verifica exclusivamente la nueva funcionalidad, con SQLite en memoria y directorios temporales propios. Si `unzip` no está en PATH, indica su ruta con `OTA_TEST_UNZIP`. No utiliza el volumen de producción ni repite las suites anteriores.

El lector AXML usa las estructuras de [AOSP ResourceTypes.h](https://android.googlesource.com/platform/frameworks/base/+/refs/heads/main/libs/androidfw/include/androidfw/ResourceTypes.h), límites de tamaño y validación de índices, tipos y recursos Android; no interpreta XML mediante expresiones regulares.
