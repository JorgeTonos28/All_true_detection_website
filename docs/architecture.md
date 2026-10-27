# Portal ATD

Referencia de arquitectura: Vamo al Game, revisada el 23 de octubre de 2026. Se adoptan Laravel, Fortify, Socialite, Sanctum, policies, actions y API versionada. Se conserva Blade para integrar el sitio existente sin una migración innecesaria a otro frontend. Sin Auth0 ni app móvil.

## Límites y permisos

Una cuenta de cliente agrupa usuarios y órdenes. El registro público crea cuenta y usuario cliente, nunca personal interno. General admin gestiona negocio, usuarios, cotización, facturación, pagos y publicación. Colaborador ve únicamente órdenes asignadas y sus conversaciones. Cliente ve únicamente recursos de su cuenta. Tokens de integración pertenecen a usuarios cliente verificados y activos; no existen tokens administrativos.

## Ciclo de una solicitud

Solicitud y líneas de servicios → revisión/cotización → factura comercial inmutable → trabajo → resultado privado → pago confirmado por administrador general → publicación explícita → descarga privada. Pago y trabajo tienen estados independientes. Los resultados pueden prepararse antes del pago, pero su contenido solo sale del servidor cuando la factura está pagada y el administrador publica la entrega. Los correos y eventos contienen referencias, nunca resultados.

Importes en centavos, moneda configurable (DOP por defecto), impuestos explícitos en puntos básicos. Sin procesador de pagos automático. Las facturas comerciales no se presentan como comprobantes fiscales/NCF. Cambios de alcance después de facturar requieren una nueva orden; no se reescribe una factura emitida. Pagos parciales se acumulan con referencia única por factura. No se ofrecen reembolsos ni conciliación bancaria automática en esta versión.

## Personas y evidencia

`orders` agrupa hasta 10 `order_people`; cada perfil es un snapshot cifrado de esa solicitud, no una identidad reutilizable entre clientes. `order_person_services` vincula servicios a personas y las cantidades de las partidas deben coincidir con esas asignaciones. Capacitación y Equipos no requiere persona. La orden y factura permanecen únicas para el lote.

Los documentos clasificados pertenecen a su persona mediante `order_person_id`; una clave foránea compuesta impide relacionarlos con otra orden. Título y exequátur tienen categorías obligatorias propias. SHA-256 detecta contenido repetido bajo bloqueo de orden, no equivalencia visual ni autenticidad documental. La evidencia se comprueba por persona antes de avanzar/cotizar/preparar conclusiones. Las fichas anteriores conservan sus requisitos originales mediante `evidence_version`.

Las vistas previas raster son endpoints autorizados con `no-store`, no rutas de almacenamiento público. Los resultados mantienen las mismas condiciones de pago/publicación al previsualizar y descargar. Las miniaturas previas al envío usan URLs blob locales, revocadas al retirar archivos o abandonar la página. No se incrustan documentos activos ni se habilitan cámara o servicios biométricos.

## API e integración

`/api/v1` usa tokens Sanctum, scopes, expiración, revocación, paginación y límites por identidad. La creación requiere Idempotency-Key y conserva la respuesta lógica. Las mismas acciones autorizan operaciones web y API. Webhooks firmados se guardan en una bandeja de salida transaccional y se entregan con reintentos mediante cola; las URLs externas se restringen a HTTPS y hosts autorizados explícitamente por operaciones.

## Operación

SMTP y OAuth requieren configuración externa. La interfaz muestra los mensajes persistidos; la notificación por correo se procesa en cola. Las respuestas por correo entrante no se importan automáticamente: el destinatario responde en el portal. Publicar resultados es una acción independiente de completar el trabajo o confirmar un pago. No desplegar ni ejecutar migraciones sobre producción sin el procedimiento operativo.
