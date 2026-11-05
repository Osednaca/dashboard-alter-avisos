# Conservar imágenes y videos al desplegar en EasyPanel

La ruta `/` del dominio o del código no es almacenamiento persistente. Con `MEDIA_DISK=public`, Laravel guarda originales y portadas en `/var/www/html/storage/app/public`. `public/storage` solo es el enlace público. La base de datos guarda rutas, no los archivos. Una nueva imagen Docker no incluye los archivos subidos.

## Configuración de EasyPanel (servicio App)

1. **Antes de desplegar o agregar el montaje**, conserva una copia externa de los archivos del contenedor actual y de cualquier volumen existente. Si aún están en `public/storage` como carpeta real, conserva también esa carpeta. No elimines/reemplaces el contenedor que contiene la única copia.
2. En el servicio del dashboard abre **Mounts / Montajes → Add / Agregar → Volume / Volumen**. Usa un nombre estable, por ejemplo `signage-media`, y la ruta de montaje **`/var/www/html/storage/app/public`**. Conserva exactamente ese volumen en los siguientes despliegues. No montes `/`, `public/` ni `public/build/`.
3. Restaura en ese volumen los archivos respaldados, conservando las rutas relativas `media/...`. Un volumen vacío oculta el contenido anterior de la carpeta; agregarlo sin trasladar los archivos existentes no los recupera. Para un respaldo desde el host con Docker: `docker cp <contenedor-actual>:/var/www/html/storage/app/public/. <carpeta-de-respaldo>/`. Sustituye los marcadores por nombres verificados. Guarda el respaldo fuera del contenedor y no lo borres hasta completar la verificación.
4. Mantén `MEDIA_DISK=public`, `FILESYSTEM_DISK=public` y `APP_URL=https://signage.finespublicidad.com`. Despliega el código. El arranque prepara permisos y el enlace `public/storage` sin borrar carpetas con archivos.
5. En la consola ejecuta `php artisan signage:media-check`. Este comando es de lectura: revisa cada original y portada registrada, muestra IDs/rutas ausentes y termina con error si falta algo. No borra registros ni intenta sustituir archivos perdidos.
6. Abre una imagen y un video existentes desde el panel. Sube un archivo pequeño de prueba, anota su URL, vuelve a desplegar y confirma que los tres siguen disponibles. Esta es la comprobación real de persistencia del volumen.

Si `public/storage` es una carpeta real, el nuevo arranque se detiene y explica el motivo para conservarla. Primero respáldala y combina sus archivos con el volumen sin sobrescribir copias distintas; solo después reemplaza esa carpeta por el enlace. Si el contenedor anterior ya se eliminó sin volumen ni respaldo, la base de datos no puede reconstruir el contenido: recupera un respaldo o vuelve a subir los originales.

## Cambios en el repositorio

- El arranque ya no ejecuta `rm -rf public/storage`.
- `.dockerignore` excluye archivos subidos y el enlace público, para no mezclar contenido de desarrollo con el volumen de producción.
- `compose.yaml` conecta un volumen con nombre estable para quienes desplieguen con Docker Compose; un servicio App de EasyPanel debe configurar su montaje en el panel, no aplica ese archivo automáticamente.
- Las vistas previas de imágenes usan sus originales; las de video usan la portada cuando existe y, si no, un fotograma del video, sin reproducción automática ni audio.
- No se cambia el disco ni las rutas guardadas en la base de datos y no se ejecuta limpieza de archivos durante un despliegue.

Fuentes: [Mounts de EasyPanel](https://easypanel.io/docs/services/app) y [persistencia de volúmenes Docker](https://docs.docker.com/engine/storage/volumes/).
