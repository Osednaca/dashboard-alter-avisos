# Avisos LED y publicidad a pantalla completa

Objetivo: adaptar esta copia de signage a avisos LED para publicidad, eliminando el acceso y las referencias a restaurantes y orientando campañas y reproducción a pantalla completa.

Alcance autorizado: interfaz y flujos locales, rutas y lógica del servidor, contrato de reproducción compatible cuando sea posible y pruebas. No se autorizan operaciones remotas ni cambios en un repositorio Android externo. Conservar datos existentes y claves técnicas necesarias para compatibilidad.

Restricciones y evidencia: el reproductor Android no está en esta copia; el contrato actual contempla dos zonas y la activación depende de `devices.business_id`. No se puede garantizar el comportamiento físico sin verificar o modificar el reproductor. TDD: sin configuración on/off en proyecto o sesión; aplicar pruebas de regresión ordinarias, sin atribuir un modo. Runner backend: `C:/xampp/php/php.exe vendor/bin/phpunit` con SQLite en memoria y entorno aislado; frontend: `node --test tests/Frontend/*.test.mjs`, `npm run typecheck`, `npm run build`. Dependencias inicialmente ausentes. RDD desactivado globalmente: disabled/unmanaged. Engram no disponible en herramientas: espejo pendiente.

Entrega: estrategia ask-on-risk; previsión 800–1500 líneas de cambios propios, sin generados. El usuario eligió stacked-to-main; límites de slices y commits se registrarán según el trabajo real. No se autorizó publicar PR ni push.

## Tareas

- [x] LED-1: Retirar acceso al portal de restaurantes y adaptar los flujos de gestión/activación sin perder datos ni impedir el alta. Ruta delegada: exploración de más de cuatro archivos y edición de múltiples archivos no triviales. Comprobar rutas, autenticación y activación.
- [x] LED-2: Hacer que campañas, reproducción rápida y manifiestos soliciten publicidad a pantalla completa, preservando el contrato compatible. Ruta delegada: múltiples archivos no triviales. Comprobar validación, manifiestos y reproducción; documentar límites del reproductor externo.
- [ ] LED-3: Adaptar nomenclatura visible del dashboard a avisos LED y eliminar referencias a restaurantes. Ruta delegada: múltiples archivos no triviales. Comprobar interfaz, búsquedas y compilación.
- [ ] LED-4: Ejecutar verificaciones aplicables, revisar el resultado y registrar evidencia y limitaciones. Ruta delegada si requiere cambios en múltiples archivos; inline para comprobaciones.

Criterios de aceptación: no existe acceso funcional al dashboard de restaurantes; las operaciones de avisos siguen siendo utilizables; las opciones visibles no ofrecen zonas parciales; el servidor solicita la publicidad a pantalla completa; textos relevantes dicen aviso LED; verificaciones y límites del reproductor quedan explícitos.

Progreso: exploración inicial completada. Siguiente paso: implementar por unidades con pruebas y commits convencionales. Conteo de líneas y límites de slices pendientes.

LED-2 verificado: PHPUnit focal 64 pruebas/490 aserciones (entorno SQLite aislado, APP_URL HTTPS); frontend 41 pruebas; typecheck y build correctos. Primer intento falló por APP_KEY/build ausentes y otro por APP_URL HTTP; corregidos solo en entorno de prueba. RDD disabled/unmanaged. Compatibilidad física Android no verificada. Commit: 722c449; slice 1, 388 líneas propias (230 altas + 158 bajas). La comprobación del diff detectó una línea vacía final en este documento, corregida a continuación. Rollback: archivos del manifiesto, reproducción, formularios y sus pruebas de este commit.
Pint detectó formato en el manifiesto y su prueba; corregido y verificado. PHPUnit FullscreenAdvertisingTest: 3 pruebas, 23 aserciones. Commit correctivo pendiente de registrar.

- [ ] LED-5: Retirar gestión y segmentación visibles de negocios/restaurantes y asociar avisos directamente a ciudades sin alterar datos históricos. Ruta delegada: múltiples archivos no triviales; la segmentación actual usa city_business y compartir un negocio técnico entre ciudades causaría destinos incorrectos. Migrar relaciones conservando originales y verificar inclusión/exclusión, activación y entrega. Esta tarea concreta el alcance original de eliminar acceso a restaurantes.
LED-2 commit correctivo de formato: 086ff3d. La suite inicial completa: 260 pruebas, 2516 aserciones, 9 fallos y 1 error; causas observadas: rutas retiradas, usuario sin rol de personal y unzip no disponible. En corrección.
LED-1 verificado: pruebas de acceso, usuarios y activación incluidas en lote focal de 64 pruebas/490 aserciones; pruebas históricas adaptadas aparte. RDD disabled/unmanaged. Slice 2: unidad cohesiva supera 400 líneas por adaptación de pruebas junto al cierre del portal; se recomienda size:exception, sin reducir cobertura para ahorrar líneas. Rollback: rutas de acceso, autenticación, activación, usuarios y pruebas del commit. Commit adbb4a6; slice 2: 462 líneas (127 altas + 335 bajas).
AndroidUpdatePublicationTest: 5 pruebas y 69 aserciones correctas usando unzip local. Pint LED-1 detectó formato, pendiente de corregir en LED-5 para evitar conflictos con las ediciones en curso.
LED-4 parcial: pruebas históricas adaptadas, 29 pruebas/361 aserciones y QuickPlayManagementTest 10 pruebas/103 aserciones correctas. La eliminación de casos exclusivos del portal retirado hace que esta unidad supere 400 líneas; se recomienda size:exception y se conservan comprobaciones de aislamiento y entrega aplicables. Verificación global aún pendiente. Rollback: pruebas históricas adaptadas, sin afectar datos de aplicación. Commit 7be17e4; slice 3: 1233 líneas (164 altas + 1069 bajas).
LED-3 parcial: terminología de avisos LED en mensajes y vistas; Pint y suite global (261 pruebas/2632 aserciones) correctos. Otros textos se integran con LED-5. Rollback: etiquetas, mensajes y test de eliminación de este commit. RDD disabled/unmanaged; commit pendiente de registrar.
