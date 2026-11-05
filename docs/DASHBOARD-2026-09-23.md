# Dashboard y campañas — 23 de septiembre de 2026

## Cambios

- Bibliotecas de administrador y negocio, galería del anunciante e Instant Play permiten seleccionar o arrastrar varios archivos. La cola envía un archivo por solicitud, muestra progreso y errores individuales, continúa después de un error y reintenta únicamente los fallidos. Los límites de tamaño y tipo siguen aplicándose por archivo.
- El administrador ve archivos publicitarios sin propietario o pertenecientes a anunciantes. Los archivos de negocios se excluyen de su biblioteca, selectores y del historial de Instant Play. Las comprobaciones también están en el servidor; enviar manualmente el ID de un archivo ajeno no permite seleccionarlo ni eliminarlo desde el portal administrativo.
- Publicar, pausar, reanudar, archivar, editar y eliminar campañas invalidan los manifiestos de las pantallas afectadas. Al cambiar destinos se actualizan tanto los antiguos como los nuevos.
- Los trabajos diferidos reconstruyen el contenido desde la base de datos vigente. La sincronización del TV reconstruye un manifiesto invalidado aunque el trabajador de la cola esté retrasado. Las fechas de inicio y finalización se concilian mediante el programador y también al sincronizar.
- Una confirmación fallida de una versión antigua ya no borra una actualización más reciente.
- Android TV 0.1.11 comprueba de nuevo la versión del servidor después de descargar archivos y antes de activarlos. Si la campaña fue cancelada durante la descarga, conserva la lista anterior y vuelve a sincronizar. Si no hay conexión, sigue reproduciendo el contenido local ya instalado.

## Auditoría responsive

Revisión acotada al flujo de campañas, creatividades, anunciantes, Instant Play, pantallas y playlists. Datos de prueba en SQLite aislado, sin conexiones ni escrituras en producción. Chrome a 320, 390, 768 y 1440 píxeles; 52 combinaciones de pantalla/estado en cada pasada completa. Se midieron ancho del documento y posición de controles e iconos, además de revisar capturas móviles. Las tablas de escritorio conservan su desplazamiento interno; no deben ampliar el documento.

Resultado de la pasada de confirmación: **52/52 sin desbordamiento del documento ni controles fuera del viewport; cero excepciones JavaScript**. Los archivos ficticios de la semilla muestran placeholders cuando no existe su medio; la comprobación mide la distribución y no certifica la disponibilidad de archivos en producción.

Hallazgos corregidos:

| Severidad | Hallazgo | Corrección |
|---|---|---|
| Alta | Guardar y publicar llegaba a x=343 en una pantalla de 320 px | Grupos de acciones con salto de línea |
| Alta | Duración y eliminación se superponían en creatividades seleccionadas | Distribución en cuadrícula con columnas adaptables |
| Alta | Pestañas y grupos de controles no cabían en móvil | Salto de línea, límites de ancho y tamaños mínimos flexibles |
| Media | Filas de playlists y acciones de listas competían por espacio | Controles redistribuidos y nombres truncados dentro de su columna |
| Media | Galería demasiado densa en teléfonos estrechos | Una columna por debajo de 400 px |
| Media | Botones compartidos pequeños para uso táctil | Objetivos de 44 px en móvil, manteniendo tamaños de escritorio |

Evaluación técnica limitada a lo inspeccionado (0–4; no es una certificación WCAG):

| Dimensión | Puntuación | Evidencia y límites |
|---|---:|---|
| Accesibilidad | 2 | Etiquetas en selector de archivos y quitar creatividad; progreso anunciado. Falta auditoría integral de contraste y lector de pantalla. |
| Rendimiento | 3 | Cargas secuenciales y miniaturas con carga diferida; sin perfil de rendimiento en dispositivo de gama baja. |
| Responsive | 3 | Comprobación en cuatro anchos; algunos controles específicos conservan tamaños menores que los botones compartidos. |
| Tema visual | 3 | Se conserva el sistema de colores y componentes existente; las gráficas aún tienen colores literales. |
| Integridad de implementación | 4 | Un componente común de carga, separación en servidor y cliente, estilos del producto conservados. Detector de Impeccable sin hallazgos en los archivos UI modificados. |
| **Total** | **15/20** | Evaluación acotada, pendiente de una auditoría completa de accesibilidad. |

## Despliegue

1. Desplegar el dashboard con las dependencias y el frontend compilado.
2. Ejecutar `php artisan migrate --force`: añade `devices.manifest_dirty`. Debe aplicarse antes de servir peticiones con el código nuevo.
3. Reiniciar los trabajadores de la cola (`php artisan queue:restart`) y mantener activo el programador de Laravel. La conciliación de fechas se ejecuta cada minuto y tiene respaldo en las consultas del TV.
4. Instalar Android TV **0.1.11** para proteger también el caso de descargas ya iniciadas cuando se cancela una campaña.

Los cambios de campañas requieren conexión del TV. El cliente activo consulta aproximadamente cada diez segundos, además del tiempo necesario para descargar; ante errores utiliza reintentos. Un aparato sin Internet no puede recibir una cancelación remota hasta reconectarse. La comprobación previa a activar reduce la ventana entre una descarga y una cancelación; no constituye una cancelación remota instantánea.

El volumen persistente de EasyPanel explicado en `MEDIA-PERSISTENCE.md` sigue siendo necesario para conservar originales y vistas previas entre despliegues. Estos cambios no reemplazan esa configuración.

## Validación

- 39 pruebas Laravel cubiertas por las suites CampaignDelivery, Campaign, MediaLibraryIsolation, BusinessQuickPlay, QuickPlay, DeviceSettings y SchedulePlayback. Tras corregir el alcance del historial del negocio, las 15 pruebas de medios e Instant Play se volvieron a ejecutar y pasaron; las otras 24 ya habían pasado.
- TypeScript, compilación de producción, Pint y `git diff --check` aprobados.
- Chrome: 52 comprobaciones responsive aprobadas. Carga real en ambos portales con una selección de tres archivos: respuestas `201, 422, 201`; al reintentar, una sola solicitud adicional `422` para el archivo inválido. Los dos válidos permanecen y no se vuelven a enviar.
- Evidencia local: `storage/logs/responsive-audit-confirmation-final.log`, `storage/logs/upload-audit-final.log`, `storage/logs/tests-dashboard-final.log` y `storage/logs/tests-media-final.log`. Las pruebas de navegador utilizaron almacenamiento temporal aislado.
- No se desplegó en EasyPanel ni se probó esta entrega en el TV físico.
