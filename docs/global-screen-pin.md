# PIN global para las pantallas

Todas las pantallas de todos los negocios validan el mismo PIN al entrar a Configuración. El backend conserva el contrato `POST /api/v1/device/admin/verify-pin` y la respuesta `{"authorized":true}` para los APK existentes que verifican el PIN en línea. No se modifica Android en este cambio; las versiones antiguas que no solicitan PIN conservan ese comportamiento.

Al desplegar, ejecutar las migraciones habituales y abrir **Pantallas → PIN global** (`/admin/devices/global-pin`) con una cuenta de personal que tenga `devices.manage`. Configurar y confirmar seis dígitos. El cambio aplica inmediatamente a todas las pantallas, incluidas las de nuevos negocios. La página también se enlaza desde el detalle de cada pantalla.

Todos los PIN anteriores por pantalla quedan invalidados desde que se activa este backend. Hasta configurar el nuevo PIN global, el servidor responde 409 y ninguna pantalla puede autorizarse mediante el endpoint de verificación. No se copia ni se promueve ningún PIN anterior. El hash se guarda en la fila 1 de `global_screen_pins`, oculto en el modelo; nunca se envía el PIN o su hash a Inertia ni al registro de auditoría. La configuración genérica del sistema no puede editarlo.

Los intentos incorrectos se limitan durante cinco minutos: cinco por dispositivo, treinta por IP y un límite amplio de trescientos entre toda la red. El límite compartido evita eludir el bloqueo cambiando de pantalla o de IP; un acceso correcto no lo reinicia. La respuesta 429 incluye `Retry-After`. Cambiar el PIN inicia contadores nuevos. En producción, usar un almacén de caché compartido y persistente entre procesos (por ejemplo Redis o el almacén de base de datos configurado), no `array`. Revisar la confianza de proxies en la infraestructura para que la IP reportada corresponda al cliente.

Se conserva la autorización existente de los dispositivos por token. El endpoint de guardado de ajustes sigue su contrato actual y no introduce un nuevo requisito de PIN en este cambio. Los roles de negocio no pueden administrar el PIN global. La ruta de cambio por pantalla se elimina.

Para revertir, restaurar conjuntamente el código anterior y gestionar la migración deliberadamente; no ejecutar resets sobre datos reales. Los hashes anteriores siguen físicamente en `devices.admin_pin_hash` para una reversión, pero el código nuevo jamás los consulta. Restaurar el código antiguo vuelve a habilitar esos hashes, lo cual requiere una decisión operativa explícita. Eliminar la tabla global destruye el PIN global; no hay recuperación del PIN en claro.

Las pruebas de regresión usan SQLite en memoria y cubren distintos negocios, cambio del PIN, ausencia de configuración, rechazo de PIN anteriores, permisos, secretos, límites compartidos y migración reversible. La sincronización exacta del preview y los cambios del próximo APK quedan fuera de este trabajo.
