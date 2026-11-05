# Programación del negocio en un solo lugar

El negocio prepara el contenido y su horario desde **Programación**, con un solo menú y formulario. Cada programación tiene su propio contenido: editarla no cambia otra programación, aunque ambas antes compartieran una playlist.

## Crear o editar

1. Abre **Programación** y crea una entrada o edita una existente.
2. Elige archivos de la biblioteca o súbelos directamente desde el formulario. Puedes subir varios sin salir del editor.
3. Organiza el orden, la duración y la transición de cada elemento.
4. Define el nombre, la ubicación, los días y el horario. Una programación existente de todo el día conserva ese comportamiento al editarla.
5. Guarda una sola vez para persistir el contenido y la programación juntos.

La subida de archivos es independiente de guardar la programación. **Cancelar conserva los archivos subidos en la biblioteca, pero no publica una programación nueva.** Espera a que los archivos estén listos; los estados pendientes y errores no deben convertirse silenciosamente en contenido publicado. Si un archivo falla, corrígelo o retíralo sin perder los demás archivos.

En **Biblioteca**, cada archivo muestra un botón de tres puntos visible también en pantallas táctiles. Se puede abrir con teclado para previsualizar; renombrar y eliminar aparecen según los permisos del usuario. En la biblioteca de creatividades del administrador, el mismo menú permite previsualizar imágenes/videos y eliminar con confirmación, tanto en cuadrícula como en lista.

También puedes marcar varias imágenes/videos listos en **Biblioteca** y pulsar **Crear programación**. La selección conserva el orden de los clics y permanece al buscar, filtrar o cambiar de página; admite hasta 100 elementos. Abre una programación nueva con esos archivos, incluso si no están entre los primeros 100 del selector. Completa el nombre y horario y guarda para publicarla: abrir o cancelar el formulario no crea registros.

En **Creatividades** del administrador, la selección está disponible en cuadrícula y lista y **Crear campaña** abre el formulario nuevo con hasta 30 creatividades, en el orden elegido. Aún debes completar anunciante, segmentación y horario. Abrirlo no guarda ni publica una campaña. Cada destino vuelve a comprobar permisos, propiedad y estado de los archivos; un archivo que dejó de estar disponible produce un error de selección.

## Contenido anterior

- Ya no hace falta entrar a un segundo editor de playlists. Los enlaces antiguos de playlists llevan a la misma superficie de Programación.
- Importar una lista anterior copia su contenido al formulario; guardar crea contenido independiente, no un enlace compartido editable.
- Al editar por primera vez una programación con una lista antigua compartida, se copia su contenido. Las otras programaciones y la lista anterior se conservan.
- Las listas antiguas usadas como contenido de respaldo no se eliminan automáticamente. Las listas nuevas gestionadas por Programación se excluyen del respaldo fuera de horario en el servidor.

## Despliegue y reversión

Este cambio requiere desplegar el dashboard y aplicar su nueva migración `2026_09_26_230000_add_schedule_management_to_playlists` mediante el procedimiento habitual. Añade `playlists.is_schedule_managed`, con valor predeterminado falso para los registros anteriores. **No requiere una APK nueva ni ejecutar los datos de prueba locales en el servidor.** No se realizó ningún despliegue remoto como parte de la verificación.

No reviertas únicamente el código del selector de respaldo ni elimines la columna sin revisar las listas gestionadas creadas: el selector antiguo podría tratarlas como respaldo y reproducirlas fuera de horario. Una reversión debe conservar los datos, identificar esas listas y mantener su exclusión hasta completar una transición compatible. No borres listas compartidas ni archivos como parte de una reversión automática.

## Evidencia y límites

- Suite completa del dashboard: **167 pruebas y 1289 aserciones aprobadas**. Siete pruebas del estado real del formulario también pasaron: hidratación, subida sin perder cambios, orden/eliminación, payload y archivos no disponibles. TypeScript y compilación de producción correctos; permanece la advertencia previa de tamaño del chunk HLS.
- Integración local sin servidor HTTP externo: **1 prueba, 60 aserciones aprobadas** sobre una SQLite nueva y almacenamiento aislado. Se ejercitaron rutas reales, autenticación de pruebas y CSRF válido, dos subidas PNG con procesamiento real, rechazo de archivo inválido, estado listo, guardado con orden/duración/transición, edición independiente de dos programaciones antiguas compartidas, hidratación, enlaces antiguos y aislamiento entre negocios.
- La prueba comprobó que subir archivos sin guardar no crea una programación. No sustituye una prueba interactiva del botón Cancelar ni del estado visual de subida.
- La verificación interactiva y responsive en navegador quedó **pendiente**: el arranque del servidor local fue bloqueado por la política de ejecución. No se intentó evitar ese bloqueo y no se generaron capturas que aparenten una verificación visual.
- No se verificaron producción ni una TV física. Los cambios de franja en una TV sin conexión son un límite previo del reproductor y no se verificaron con este cambio; la exclusión de respaldo descrita corresponde al servidor.

Antes de dar por cerrada la aceptación visual, comprobar en móvil y escritorio: un solo menú/formulario, varias subidas con éxito parcial, conservación del borrador y del orden, creación/edición y ausencia de desplazamiento horizontal.
