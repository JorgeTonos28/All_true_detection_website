# All True Detection — guía de ingeniería

Referencia: https://github.com/JorgeTonos28/Vamo_al_game. Adaptar sus principios al dominio ATD; este proyecto no usa Auth0 ni mantiene una aplicación móvil paralela.

- Mantener Laravel y las convenciones existentes. El sitio público y el portal web deben ser responsive.
- Construir backend reusable: migraciones → modelos → FormRequests → policies → actions/services → resources → API `/api/v1` → UI. Los controladores web y API comparten reglas de negocio.
- Autorización obligatoria en servidor. Clientes aislados por cuenta; colaboradores solo acceden a órdenes asignadas; solo administradores generales administran usuarios, marca, facturas y pagos.
- Nunca entregar resultados, archivos ni contenido sensible en mensajes, notificaciones o webhooks antes de confirmar el pago y publicar la entrega. Esta regla se verifica también al descargar.
- Archivos de clientes y resultados exclusivamente en almacenamiento privado. No incluir secretos, datos personales, tokens o credenciales en Git, logs o respuestas de error.
- Validar entradas, proteger asignación masiva, CSRF, OAuth state, sesiones, 2FA, límites de uso y tokens con permisos mínimos, expiración y revocación.
- Persistencia con relaciones, restricciones, índices y transacciones. Dinero en unidades menores enteras; operaciones financieras auditadas; nunca usar flotantes.
- Cambios de esquema requieren migración, seeder/factory cuando corresponda y pruebas de integridad. Nunca ejecutar migraciones destructivas sobre datos existentes.
- Mantener contrato API y documentación al cambiar endpoints. La integración de correo y Google se configura con secretos de entorno.
- Probar escenarios de abuso, aislamiento, permisos, pago/entrega, reintentos y autenticación. Revisar vistas, errores, estados vacíos, accesibilidad y responsive.
- Actualizar README y documentación operativa en cada entrega funcional.
- Trabajar en unidades coherentes, validar y crear commits pequeños, lógicos y atómicos antes de continuar. Separar esquema, dominio, seguridad, API, UI y documentación cuando tengan propósito independiente. No hacer squash sin petición.
