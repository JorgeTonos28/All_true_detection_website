# Identidad manual y automatizada con Didit

## Decisión y alcance

ATD eligió Didit. Se implementa **sesión API v3 + captura alojada + webhook**, sin SDK adicional en Laravel. La misma sesión admite iframe/SDK en la app integradora. La persona siempre participa en la captura/prueba de vida: automatizar no significa verificarla sin su intervención.

- [Sesión alojada](https://docs.didit.me/integration/api-full-flow): ATD crea la sesión y devuelve su enlace privado. Es la opción implementada en el portal, con menos componentes que mantener.
- [Iframe](https://docs.didit.me/integration/web-sdks/incontext-iframe) o [SDK JavaScript](https://docs.didit.me/integration/web-sdks/javascript-sdk): el cliente integra esa sesión en su app. Debe gestionar HTTPS, cámara, CSP y mensajes de ventana; el servidor confirma la decisión. ATD no incrusta un iframe ni abre permisos globales de cámara.
- APIs independientes de [documento](https://docs.didit.me/standalone-apis/id-verification), [vida pasiva](https://docs.didit.me/standalone-apis/passive-liveness) y comparación facial: permiten servidor a servidor con imágenes de una UI propia. Exigen asegurar captura, coordinar comprobaciones y manejar sus fallos. No se implementan en esta fase ni equivalen al desafío activo 3D del workflow inspeccionado.

El conector autorizado permitió inspeccionar aplicaciones/workflows sin modificarlos ni crear sesiones. El workflow publicado consultado incluía OCR, vida **ACTIVE_3D** y comparación facial, con documentos DOM. ATD verifica esa configuración antes de cada creación y exige el mismo método en las comprobaciones para declarar éxito: no basta con que el estado global diga Approved. La clave runtime necesita leer el workflow además de crear/consultar sesiones. Un fallo de preflight pausa sin POST, no usa vida pasiva como alternativa. El conector sirve para gestión: **no entrega a Laravel las claves secretas** ni forma parte del runtime desplegado.

## Modo y expedientes existentes

Mi negocio incluye un switch Manual/Didit protegido por administrador general, sesión activa, correo verificado, 2FA y reconfirmación de contraseña. No admite claves en la UI. Predeterminado manual; si faltan configuración o aprobaciones, la oferta efectiva vuelve a manual para casos nuevos y el switch queda bloqueado.

Cada `order_person` conserva su `identity_mode`. Cambiar el switch no reescribe personas/evidencias históricas ni transmite imágenes antiguas. Las sesiones iniciadas siguen conciliándose con sus credenciales de aplicación/entorno. Cambiar workflow dentro de la misma aplicación permite seguir consultando sesiones anteriores; cambiar aplicación/entorno requiere conciliar sus pendientes antes de retirar las credenciales previas.

Web, catálogo API y formulario usan el modo efectivo. Manual exige selfie, sin afirmar biometría/prueba de vida. Didit no exige esa selfie de entrada: la captura se realiza con el proveedor tras confirmar el aviso versionado. Título/exequátur y sus documentos mantienen su flujo independiente.

## Flujo integrado

1. Crear orden con `people` y servicio `identity-verification`; ATD devuelve ULID de persona y modo snapshot.
2. **Web:** ATD cotiza y confirma pago total antes de habilitar captura, tanto en live como en sandbox (pago ficticio en pruebas). **API:** solo partners aprobados a crédito, orden exclusiva de identidad sin factura individual. No mezclar canales en una orden.
3. Mostrar el aviso y obtener autorización. Desde la web, iniciar en el expediente; desde API, `POST /api/v1/orders/{order}/people/{person}/identity-session`, con `consent=true`, versión `didit-2026-10-v1` y `identity:verify`. Web/API comparten acción con políticas explícitas por canal; se registra versión/fecha y el integrador conserva evidencia del consentimiento.
4. Respuesta 202; el worker crea la sesión. GET en la misma ruta devuelve estado y, cuando corresponde, `verification_url`. Repetir devuelve la misma sesión: una por persona.
5. Presentar el enlace al titular, por navegación alojada o SDK/iframe. El Bearer ATD queda en el backend cliente. El enlace es una capacidad privada de captura, no un resultado ni URL pública.
6. Didit llama `POST /api/providers/didit/webhook`. ATD verifica firma, frescura, aplicación, entorno, workflow, referencia local y sesión; persiste recibo mínimo deduplicado y solicita conciliación, sin guardar el payload biométrico.
7. El worker consulta la decisión actual con la clave de servidor. Los eventos tardíos no sustituyen ese estado. Redirect, query strings y postMessage nunca aprueban ni alteran un caso.
8. Para `verified` se exige Approved y comprobaciones no vacías de documento/vida/comparación aprobadas, vida con `method=ACTIVE_3D`, documento DOM y cédula normalizada coincidente. Aprobación sandbox vacía, vida pasiva, falta de campos o cédula distinta queda no concluyente. Alcance actual: cédula dominicana, no todos los documentos que ofrece Didit. El reto visual concreto lo determina el proveedor según su flujo/dispositivo.
9. **Web:** identidad Didit prepara entrega privada; lotes mixtos incorporan la conclusión a las demás comprobaciones profesionales. Finalización/pago/publicación explícitos; evento `result.published` y `/deliveries` con `results:read`.
10. **API crédito:** `identity.verification.updated` solo avisa con metadatos; `GET .../identity-session/result` con `identity:read` devuelve automáticamente conclusión técnica confirmada, sin pago ni publicación manual. Orden completada al tener conclusión por cada persona; una retirada vuelve a en proceso. No hay informe/archivo en esta excepción. In Review/discrepancias pueden necesitar humanos: `inconclusive` no significa éxito ni decisión de elegibilidad.

## Seguridad, consumo y conciliación

- HTTPS fijo `verification.didit.me`, certificado verificado, sin redirects ni URLs de API suministradas por clientes. Conexión 3 s / operación 12 s; sin reintentar automáticamente POST.
- Referencias opacas enlazan sesión/persona/orden; no usar cédula como `vendor_data` ni agrupar identidades entre clientes. Perfil esperado mínimo; sin invitaciones Didit por email automáticas ni transferencia de adjuntos ATD.
- URL de captura, estado del proveedor y conclusión resumida cifrados. No se descargan informes, documentos, selfies o vídeos automáticamente. Errores de transporte sin claves, cuerpos o respuesta remota en logs.
- [Firma Didit](https://docs.didit.me/integration/webhooks): V2 HMAC-SHA256, comparación constante y canonicalización preservando objetos vacíos, orden lexicográfico, Unicode/barras. Comprobar tanto header X-Timestamp como timestamp firmado, dentro de 300 s. Sin Simple ni confianza en redirects. V2-Previous permite la ventana de compatibilidad durante rotación de secreto.
- Recibo por `event_id`, sin datos personales. Colas contienen identificador local. Si falla despacho, `sync_requested` persiste para el scheduler.
- `portal:reconcile-identity` cada minuto: hasta 100 elegibles por lote; activas tras 10 min y recibidas tras 24 h sin conciliación, además de eventos que solicitan sync. Intervalos de elegibilidad, no SLA bajo saturación. No procesa otras aplicaciones/entornos con la clave actual.
- POST incierto: `create_uncertain`, sin repetir ni crear una segunda sesión. Un webhook vinculado recupera el identificador. Sin evento, operaciones localiza la sesión en consola por `vendor_data` y reenvía un evento firmado; no pedir otra orden a ciegas ni borrar registros para reintentar.
- Cuotas de nuevas sesiones: 200/día UTC globales; web 20/cuenta/canal, configurables. API usa el cupo de cada partner aprobado (1–1.000). Serializadas en DB; cero en el límite global/web bloquea nuevas. Repetir una persona existente no consume otra reserva. No son un presupuesto monetario ni sustituyen alertas/límites del proveedor.
- Una corrección/caducidad invalida publicaciones web anteriores; prepara nueva versión cuando corresponde y exige publicación actualizada. En crédito incrementa la revisión y emite aviso para consultar la conclusión actual; si se retiró, devuelve 409 y el consumidor debe invalidarla. Consultas repetidas no cambian revisión/fecha de conclusión. Producción bloquea simulaciones en ambos canales.

El estado público `received` no revela aprobación/rechazo. La propia UI/SDK Didit puede mostrar su estado al titular: el prepago web evita exposición antes del pago, pero no se promete controlar mensajes de un producto externo. Esto no significa que Didit cobre al cliente de ATD ni actúe como pasarela. El informe **ATD** web conserva publicación administrativa; el resultado técnico API crédito no espera cobro. Revisar pantallas/mails Didit antes de producción.

## Partners y corte de consumo

En Integraciones API → Identidad y crédito, solo un administrador con 2FA y contraseña reciente autoriza/suspende una cuenta comercial y su cupo. Se audita actor/fecha. Por defecto ninguna cuenta está autorizada. Los scopes `identity:verify`/`identity:read` no se emiten sin aprobación; cada llamada revalida cuenta además del token. Suspender impide iniciar pendientes y leer resultados; se comprueba también después del preflight. No cancela llamadas ya en curso o enlaces entregados ni evita conciliar consumo aceptado. No se generan nuevos avisos de resultados mientras está suspendida; al reautorizar, el consumidor reconcilia por GET.

Cada sesión guarda canal inmutable, aprobación snapshot, fecha única de aceptación remota y revisión. El registro operativo por período usa aceptación UTC (o creación para reservas aún no confirmadas), separa live/sandbox y no incluye conclusiones ni enlaces secretos. Reintentos/polls/callbacks no suman otra sesión. No equiparar el conteo a los cargos del proveedor: podrían variar por comprobaciones o reintentos de captura. Las creaciones inciertas deben conciliarse antes del corte; no asumir que no costaron.

No se inventaron tarifas, impuestos, fechas de corte o saldo financiero. La emisión/cobro de factura consolidada queda pendiente de definir el acuerdo comercial. Las órdenes a crédito no admiten factura individual para evitar doble cobro. No hay pasarela automática ni se registran como pagadas para saltar las reglas de otros servicios.

## Uso recurrente: recomendación, no implementación actual

[Create Session de Didit](https://docs.didit.me/sessions-api/create-session) documenta Biometric Authentication: puede usar una referencia facial de un usuario previamente aprobado, o `portrait_image` para determinados workflows. `expected_details` son valores de contraste, no una cédula preenviada ni una orden para omitir documento. Reenviar cualquier foto elegida por el consumidor no ofrece una vinculación fiable a la identidad inicial.

Recomendación: primera verificación completa documento + ACTIVE_3D + Face Match; posteriores reautenticaciones con vida activa y comparación contra la referencia **aprobada** del mismo sujeto, sin documento cada vez. Un identificador opaco estable y aislado por partner vincularía ese sujeto con Didit; ATD hoy usa la referencia de cada sesión y aún no tiene ese registro reutilizable. Renovación del documento, discrepancias, caducidad y riesgo requieren reverificación completa. Reautenticar no valida otra vez la vigencia del documento.

Antes de implementar: workflow de autenticación independiente, soporte ACTIVE_3D comprobado en ese workflow, vinculación inicial, protección contra sustitución de retrato, consentimiento específico, retención/supresión y aislamiento. No se ha activado reutilización de rostros ni almacenamiento biométrico adicional en ATD; la API actual rechaza campos de retrato/referencia/workflow/decisión arbitrarios y cada captura sigue pidiendo documento.

## Activación

Requiere clave runtime independiente del conector. Nunca pegar secretos en Git, issues, logs ni chat. Introducirlos en `.env` local o gestor del hosting. Este bloque es contenido de archivo, no comandos:

```dotenv
DIDIT_API_KEY=
DIDIT_WEBHOOK_SECRET=
DIDIT_APPLICATION_ID=
DIDIT_WORKFLOW_ID=
DIDIT_ENVIRONMENT=sandbox
DIDIT_PRIVACY_APPROVED=false
DIDIT_PRODUCTION_APPROVED=false
DIDIT_DAILY_SESSION_LIMIT=200
DIDIT_ACCOUNT_DAILY_SESSION_LIMIT=20
```

1. Elegir aplicación por su entorno real, no por nombre. Claves separadas sandbox/live y permisos mínimos de sesiones/workflows necesarios. Una clave live puede facturar incluso desde local.
2. Publicar/seleccionar workflow KYC con documento, **ACTIVE_3D** y comparación facial. Probar cédula DOM específica, idioma, calidad, accesibilidad y el campo `liveness_checks[].method` del informe. La clave debe leer `/v3/workflows/{id}/`; si no tiene permiso el preflight bloquea la captura. Autorizar los campos mínimos para vincular identidad; sin ellos ATD no declara verified. No usar un UniLink genérico para sustituir sesiones locales.
3. Crear destino HTTPS de la aplicación a `/api/providers/didit/webhook`, eventos `status.updated` y `data.updated`, versión v3. Usar el secreto de ese destino, distinto de la API key. En local se necesita túnel HTTPS de pruebas elegido por operaciones o conciliación por API: Didit no alcanza 127.0.0.1. Esta tarea no creó túneles ni modificó destinos.
4. Revisar condiciones para verificar clientes, privacidad/DPA, ubicación, subencargados, conservación/supresión y preferencias de entrenamiento. El aviso aprobado no acredita revisión contractual/legal. `DIDIT_PRIVACY_APPROVED=true` solo tras completar esta revisión y definir evidencia del consentimiento en la app integradora.
5. Respaldar DB/APP_KEY, aplicar migraciones aditivas, limpiar/cachear configuración y reiniciar workers. Cola/scheduler supervisados; en local `composer run dev` reúne los procesos en una terminal.
6. Probar sandbox completo: callback, firma, duplicados, discrepancias, errores y recuperación. Approved simulado sin comprobaciones produce inconclusive deliberadamente. Mocks locales no confirman una llamada real a Didit.
7. Producción requiere entorno live, aplicación/workflow/secretos live y `DIDIT_PRODUCTION_APPROVED=true`; revisar pantallas/SDK y hacer prueba autorizada antes de abrir el servicio. El switch exige configuración completa. Web mantiene pago/publicación. Para API, aprobar únicamente partners con acuerdo de crédito y cupo; probar entrega automática, suspensión y corrección sin relajar otras entregas.

Tras editar `.env`, ejecutar por separado:

```bat
php artisan config:clear
```

Reiniciar el proceso local y ejecutar en esa misma terminal:

```bat
composer run dev
```

Conciliación manual del entorno configurado (encola trabajo; requiere worker):

```bat
php artisan portal:reconcile-identity
```

Apagar el switch afecta casos nuevos, no cancela capturas iniciadas. Ante incidente, operaciones puede pausar procesamiento/configuración preservando recibos y conciliación. No hay botón para borrar/reintentar, ni supresión automática en Didit: requiere procedimiento autorizado con el proveedor.

## Validación y límites

Pruebas HTTP simuladas y QA visual aislado: [evidencias](qa/didit.md). No se transmitieron biometría/documentos ni crearon sesiones reales. No se usaron claves runtime reales, modificaron workflows ni desplegó producción. La instalación local conserva manual hasta completar configuración y prueba integral con Didit.
