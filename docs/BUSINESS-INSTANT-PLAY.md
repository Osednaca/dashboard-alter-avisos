# Instant Play del negocio

El portal del negocio permite seleccionar una o varias pantallas del negocio activo y reproducir únicamente en su zona del layout.

- El formulario no ofrece negocios, ubicaciones, toda la red, zona publicitaria ni pantalla completa.
- El servidor valida la propiedad de cada pantalla y del archivo. Rechaza destinos o modos no permitidos incluso en solicitudes manipuladas.
- Los reintentos conservan el filtro del negocio actual. Los envíos antiguos del negocio a publicidad o pantalla completa no se pueden reintentar; se debe crear un envío nuevo en la zona del negocio.
- El administrador conserva sus destinos y modos disponibles.

## Validación

29 pruebas de Instant Play y aislamiento de bibliotecas pasan (323 aserciones). Incluyen varias pantallas propias, destinos mezclados entre negocios, cambio de negocio activo, solicitudes malformadas, reintentos y opciones del administrador. TypeScript también pasa.

## Despliegue

Desplegar el dashboard con sus assets compilados. No requiere migraciones ni actualizar el APK. La restricción aplica a nuevos envíos y reintentos; no cancela comandos enviados anteriormente.
