# Campañas por ciudad y varios negocios

En **Ubicaciones**, el administrador gestiona ciudades y asigna varios negocios a cada una. Una campaña dirigida a **Ubicación (ciudad)** llega a todas las pantallas habilitadas de esos negocios, incluso a las que no tienen sucursal asignada. Las pantallas deshabilitadas o pendientes de activación no reciben la campaña.

## Gestión

1. Abre **Ubicaciones**, pulsa **Nueva ciudad** y completa ciudad, departamento, país y zona horaria.
2. Marca los negocios que pertenecen a esa ciudad y guarda. Debes asignar al menos uno.
3. En la segmentación de la campaña, elige **Ubicación (ciudad)** y selecciona la ciudad. La vista previa muestra el alcance antes de publicar.

Editar los negocios asignados actualiza el alcance de las campañas de esa ciudad: las pantallas retiradas dejan de recibirlas y las nuevas las reciben en su siguiente sincronización. Desactivar o eliminar una ciudad retira su alcance; eliminarla conserva sus negocios, sucursales y pantallas.

La identidad de una ciudad eliminada queda reservada para impedir que las campañas anteriores vuelvan a reproducirse accidentalmente al crear otra ciudad con el mismo nombre, departamento y país. El formulario devuelve un mensaje claro si esa combinación ya existe o fue eliminada. No hay restauración automática.

## Campañas y sucursales existentes

Las sucursales siguen disponibles en ajustes, activación de pantallas y segmentación por **Sucursal**. Se conservan sus identificadores y las asignaciones de las pantallas.

Las campañas nuevas usan el identificador estable del catálogo de ciudades. Las anteriores que guardan el nombre de la ciudad resuelven los negocios asignados al catálogo; si hay ciudades homónimas, incluyen la unión de sus negocios. Cuando no existe una entrada del catálogo, mantienen la segmentación anterior por el texto de ciudad de las sucursales. Una entrada inactiva, eliminada o sin negocios no activa ese respaldo. Renombrar una ciudad cuyo nombre es único convierte sus reglas anteriores al identificador estable para conservar el alcance.

## Requisito de despliegue

Aplicar `2026_10_07_120000_create_cities_tables` mediante el procedimiento habitual antes de habilitar este código. La migración es aditiva: crea `cities` y `city_business`, agrupa las sucursales existentes por ciudad/departamento/país y asigna sus negocios. No cambia los identificadores de sucursales ni pantallas. Recalcula el alcance guardado de las campañas por ciudad y marca los manifestos para su siguiente sincronización. No requiere una APK nueva.

La migración se verificó en SQLite aislada, incluyendo su reversión y conservación de sucursales; no se ejecutó sobre la base de aplicación. Una reversión real debe evaluar los datos del catálogo y las reglas convertidas a identificadores antes de retirar tablas o código.
