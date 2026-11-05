# Reproducción y edición de campañas

## Correcciones

- El editor carga la relación del anunciante, que antes llegaba como `null`. Conserva el formato simple de segmentación del arreglo anterior.
- Los elementos se cargan y se envían al TV por su posición guardada, con ID como desempate. Entre campañas se mantiene prioridad descendente e ID ascendente.
- El formulario conserva horarios vacíos, días sin restricciones, presupuesto y metas. La meta de impresiones ahora se carga y tiene su propio campo; antes se omitía del envío y podía borrarse al guardar. Los valores predeterminados de fechas y horario solo se aplican al crear una campaña.
- Android TV 0.1.13 agrupa los elementos por campaña después de filtrar horario y prioridad. Antes se ordenaban globalmente las posiciones locales y se intercalaban A1, B1, A2, B2. Ahora se reproduce A1, A2, B1, B2 y se repite el ciclo.

## Validación

35 pruebas Laravel aprobadas (289 aserciones), incluidas edición con anunciante preseleccionado, campos guardados, orden de creatividades, segmentación, publicación, cancelación y horarios.

Tres pruebas del inicializador real del formulario: `node --test tests/Frontend/campaignForm.test.mjs`. Cubren valores existentes, campos opcionales vacíos y valores predeterminados al crear. Pint, revisión del diff, TypeScript (`npm run typecheck`) y compilación Vite (`npm run build`) aprobados.

Los casos de mezcla de campañas, anunciante ausente y posiciones de elementos fallaron antes de aplicar las correcciones y pasaron después.

## Aplicación en producción

Desplegar el dashboard con el frontend recompilado y recargar el editor. Instalar el APK firmado 0.1.13 para corregir el orden en el TV; desplegar únicamente el dashboard no corrige el ordenamiento global del APK anterior. No se requieren migraciones nuevas ni borrar los datos del TV. Mantener el volumen existente `signage-media`.

No se desplegó el servidor ni se instaló el APK en un TV físico desde esta tarea.
