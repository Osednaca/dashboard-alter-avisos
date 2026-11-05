# Eliminar una pantalla

El personal con permiso `devices.manage` puede eliminar una pantalla en **Pantallas** (`/admin/devices`), desde su menú de acciones o desde el detalle. El diálogo identifica la pantalla y permite cancelar antes de enviar el borrado.

Al confirmar, el servidor elimina la pantalla y vuelve al listado. Se borran sus latidos, manifiestos, comandos, eventos de reproducción, estado de reproducción y entregas de reproducción inmediata. Las otras pantallas, negocios, archivos multimedia y registros generales de reproducción inmediata se conservan.

El token anterior deja de autenticar y los códigos de activación asociados quedan revocados dentro de la misma transacción. Un código reclamado anteriormente no puede recrear la pantalla. Para volver a usarla, solicitar un código nuevo y asignarlo nuevamente a un negocio.

Los permisos también se validan en el servidor: soporte, usuarios de negocio y visitantes no pueden borrar mediante la URL. Una pantalla ya eliminada devuelve 404.

Las pruebas `DeviceDeletionTest` verifican autorización, cascadas, conservación de otros datos, rechazo del token/código anterior y solicitud de una nueva activación con SQLite en memoria. `DeviceActivationTest` y `DeviceCommandTest` cubren los flujos existentes.

Revertir el código retira la opción y la revocación adicional; no recupera las filas borradas ni los códigos revocados. Recuperar datos requiere un respaldo o una nueva activación. El borrado en servidor tampoco elimina los archivos que el APK ya descargó: su limpieza local depende del dispositivo.
