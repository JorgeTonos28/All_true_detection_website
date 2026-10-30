# Identidad manual y automatizada con Didit

## Decisión y alcance

ATD eligió Didit. Se implementa **sesión API v3 + captura alojada + webhook**, sin SDK adicional en Laravel. La misma sesión admite iframe/SDK en la app integradora. La persona siempre participa en la captura/prueba de vida: automatizar no significa verificarla sin su intervención.

- [Sesión alojada](https://docs.didit.me/integration/api-full-flow): ATD crea la sesión y devuelve su enlace privado. Es la opción implementada en el portal, con menos componentes que mantener.
- [Iframe](https://docs.didit.me/integration/web-sdks/incontext-iframe) o [SDK JavaScript](https://docs.didit.me/integration/web-sdks/javascript-sdk): el cliente integra esa sesión en su app. Debe gestionar HTTPS, cámara, CSP y mensajes de ventana; el servidor confirma la decisión. ATD no incrusta un iframe ni abre permisos globales de cámara.
- APIs independientes de [documento](https://docs.didit.me/standalone-apis/id-verification), [vida pasiva](https://docs.didit.me/standalone-apis/passive-liveness) y comparación facial: permiten servidor a servidor con imágenes de una UI propia. Exigen asegurar captura, coordinar comprobaciones y manejar sus fallos. No se implementan en esta fase ni equivalen al desafío activo 3D del workflow inspeccionado.

El conector permitió publicar, con autorización, dos workflows nuevos de pruebas en «All true detection (Sandbox)»: inicial con documento DOM + **ACTIVE_3D** + Face Match, recurrente con ACTIVE_3D + Face Match sin documento. Durante esa publicación no se modificaron los workflows anteriores ni se crearon capturas. Posteriormente se prepararon sesiones de Sandbox a través del [piloto HTTP aislado](api-testing.md), usando la clave runtime aportada por el usuario. ATD verifica la configuración antes de cada creación y exige comprobaciones activas confirmadas para declarar éxito: no basta Approved. La clave necesita leer el workflow además de crear/consultar sesiones. Un fallo de preflight pausa sin POST. El conector **no entrega a Laravel las claves secretas** ni forma parte del runtime desplegado.

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
6. Didit llama `POST /api/providers/didit/webhook`. ATD verifica firma, frescura, aplicación, entorno, workflow, referencia local y sesión; persiste recibo mínimo deduplicado y solicita conciliación, sin guardar el payload biométrico. Un evento ya aceptado recibe 200 sin volver a encolar trabajo. Si falla el primer despacho, `sync_requested` queda pendiente y el scheduler recupera la conciliación; no se depende del replay para recuperarla.
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

## Uso recurrente implementado — contrato 1.6.0

[Create Session de Didit](https://docs.didit.me/sessions-api/create-session) documenta Biometric Authentication: puede usar una referencia facial de un usuario previamente aprobado, o `portrait_image` para determinados workflows. `expected_details` son valores de contraste, no una cédula preenviada ni una orden para omitir documento. Reenviar cualquier foto elegida por el consumidor no ofrece una vinculación fiable a la identidad inicial.

La primera verificación requiere documento + ACTIVE_3D + Face Match y cédula DOM coincidente. Después de recibirla, el cliente autoriza expresamente su reutilización desde el expediente web o `POST /orders/{order}/people/{person}/reusable-identity`. Una fuente web necesita pago/completado/publicación; por API se exige partner vigente y `identity:verify` + `identity:read`. Se confirma nuevamente la fuente por GET al proveedor antes de registrar o usar la referencia. No sirve una revisión manual, solo presencia, una sesión rechazada ni una reverificación para renovar el ancla.

Didit conserva el documento y la referencia facial según la retención de su aplicación; resuelve el rostro aprobado del usuario enlazado por `vendor_data`. ATD guarda una referencia opaca por cuenta, fuente, aplicación y entorno; perfil básico cifrado (incluida la cédula escrita), consentimiento y caducidad. No descarga ese retrato, vídeo ni documento Didit. Los adjuntos que un cliente haya cargado antes en ATD son archivos privados independientes. No se activó conservación adicional de plantillas tras borrar imágenes ni se cambió retención en Didit. Los 90 días son una **vigencia de uso en ATD**, no una promesa de borrado del proveedor: ver [retención](https://docs.didit.me/console/data-retention).

La pantalla «Identidades reutilizables» permite consultar referencias, solicitar nueva captura y revocar el uso futuro. La API ofrece GET `/identities`, POST `/identities/{identity}/verifications` y POST `/identities/{identity}/revoke`; contrato/ejemplos en [API](api.md). Cada solicitud genera una orden/persona nueva con el perfil snapshot y consentimiento para esa finalidad. API mantiene crédito únicamente para partners autorizados; web requiere nueva factura pagada y publicación. Una referencia ya registrada por API puede usarse en una nueva orden web pagada; esto no hace visible en web la conclusión original exclusiva de crédito. Suspender el acceso API bloquea sus endpoints actuales, sin convertir esa suspensión en revocación de una referencia ya consentida.

La vigencia se cuenta desde la captura inicial (`consented_at + 90 días`), nunca desde el registro ni desde la última selfie. Vencida/revocada/sin fuente aprobada exige nueva verificación completa. Revocar en ATD no elimina medios de Didit ni cancela enlaces ya entregados; coordinar supresión remota de forma autorizada. No invalida una comprobación histórica hecha cuando la referencia era válida; una corrección adversa de la fuente sí bloquea la conclusión dependiente. `document_reverified=false` distingue la autenticación recurrente de una nueva validación documental.

ATD envía al workflow recurrente el `vendor_data` opaco de la fuente y un `metadata.atd_session_id` único, nunca cédula, archivos o `portrait_image`. Rechaza sustitución de referencia/decisión/workflow por el consumidor. Didit deduplica creaciones pendientes por workflow + vendor; ATD admite una sola captura remota pendiente por referencia, incluso si se canceló la orden pero la creación quedó incierta. La metadata del POST, GET y callback debe corresponder al intento local. Solo terminalidad confirmada o cancelación antes del POST libera la reserva. Consulta de fuente fallida bloquea el uso sin crear otra sesión.

## Activación

Requiere clave runtime independiente del conector. Nunca pegar secretos en Git, issues, logs ni chat. Introducirlos en `.env` local o gestor del hosting. Este bloque es contenido de archivo, no comandos:

```dotenv
DIDIT_API_KEY=
DIDIT_WEBHOOK_SECRET=
DIDIT_APPLICATION_ID=
DIDIT_WORKFLOW_ID=
DIDIT_AUTHENTICATION_WORKFLOW_ID=
DIDIT_ENVIRONMENT=sandbox
DIDIT_PRIVACY_APPROVED=false
DIDIT_REUSE_PRIVACY_APPROVED=false
DIDIT_LOCAL_POLLING=false
DIDIT_PRODUCTION_APPROVED=false
DIDIT_DAILY_SESSION_LIMIT=200
DIDIT_ACCOUNT_DAILY_SESSION_LIMIT=20
```

1. Elegir aplicación por su entorno real, no por nombre. Claves separadas sandbox/live y permisos mínimos de sesiones/workflows necesarios. Una clave live puede facturar incluso desde local.
2. Publicar/seleccionar workflow KYC con documento, **ACTIVE_3D** y comparación facial. Probar cédula DOM específica, idioma, calidad, accesibilidad y el campo `liveness_checks[].method` del informe. La clave debe leer `/v3/workflows/{id}/`; si no tiene permiso el preflight bloquea la captura. Autorizar los campos mínimos para vincular identidad; sin ellos ATD no declara verified. No usar un UniLink genérico para sustituir sesiones locales.
3. Producción: destino HTTPS a `/api/providers/didit/webhook`, eventos `status.updated` y `data.updated`, versión v3. Secreto del destino, distinto de API key. Local: `DIDIT_LOCAL_POLLING=true` permite consultar por API sin túnel ni secreto webhook **solo APP_ENV=local + sandbox**. No acepta callbacks sin firma; fuera de esa combinación vuelve a exigir secreto. Scheduler cada 30 s, activas elegibles tras 20 s; recibidas siguen reconfirmándose tras 24 h. Esta tarea no creó túneles ni modificó destinos.
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

## Pruebas locales preparadas

Aplicación seleccionada por el usuario: `145cffa4-94fe-4494-8442-872e7f63c928` («All true detection (Sandbox)»). Workflows publicados y releídos con el conector:

| Uso | Workflow |
| --- | --- |
| Inicial · OCR DOM → ACTIVE_3D → Face Match | `b80f4753-2dbc-46f3-b207-227e19ff735b` |
| Recurrente · ACTIVE_3D → Face Match | `ffa07f01-d206-494d-be95-9c5dd8fa86aa` |

Sin búsqueda facial 1:N ni grabación de audio adicional. No se cambió la retención. El conector actual no expuso el campo de modo real de la aplicación ni claves sin ocultar: confirmar **modo Sandbox** en consola y usar su clave con lectura de workflows y creación/lectura de sesiones. No basta con el nombre. ATD manda `sandbox_scenario=approve` en sesiones sandbox; Didit lo rechaza en una aplicación live antes de crear. El selector alojado permite ensayar otros escenarios.

El `.env` local tiene los IDs anteriores, polling y avisos autorizados; aprobación de producción permanece false. Introducir la clave en `DIDIT_API_KEY`, nunca en chat/Git. El 29 de octubre se comprobó que la clave proporcionada permite leer ambos workflows; las sesiones del piloto devolvieron `environment=sandbox` por la API del proveedor. Las aprobaciones locales corresponden al ensayo y al aviso autorizado, no acreditan revisión contractual para producción. Después de modificar configuración, ejecutar cada comando en su propio bloque:

```bat
php artisan config:clear
```

```bat
php artisan portal:identity-status --check-provider
```

```bat
composer run dev
```

El diagnóstico hace solo GET de ambos workflows y no muestra secretos ni crea sesiones. Tras confirmarlo, activar Didit en Mi negocio. Crear verificación inicial ficticia, cotizar/registrar pago ficticio, iniciar captura y esperar conciliación; completar/publicar el informe, autorizar la referencia y crear otra solicitud desde Identidades reutilizables. Cotizar/pagar esa nueva orden habilita su captura sin documento. Para API, autorizar la cuenta partner y emitir scopes mínimos; el resultado técnico se devuelve automáticamente al finalizar.

El enlace de captura es HTTPS de Didit y puede abrirse directamente en un celular sin publicar Laravel en Internet. En modo polling local se omite el callback al localhost del desarrollador: Didit conserva su pantalla final, y ATD confirma por API desde la computadora. Producción mantiene su callback normal. Los enlaces son privados y caducan con la sesión; no incluirlos en Git, reportes públicos, logs o capturas de QA.

**Límite confirmado en el piloto del 29 de octubre:** [Sandbox simula los datos extraídos](https://docs.didit.me/integration/sandbox-testing), aunque la captura activa se realiza y sus medios se conservan. El proveedor devolvió documento DOM, vida ACTIVE_3D y Face Match Approved, pero el número documental ficticio no tiene once dígitos normalizados ni coincide con la solicitud. ATD mantuvo correctamente `inconclusive` y bloqueó el alta de una referencia reutilizable. No se debilita ese control para lograr una demo. Las pruebas HTTP con proveedor sintético vinculado cubren el recorrido recurrente completo; para completarlo externamente se necesita una muestra DOM compatible en Didit Sandbox o una prueba live controlada y autorizada por separado. No subir cédulas reales para resolver un mock, ni tomar simulaciones como prueba de precisión biométrica.

## Validación y límites

Evidencias anteriores: [integración inicial](qa/didit.md) y [reutilización](qa/reusable-identity.md). La QA actual incorpora el [laboratorio HTTP y piloto móvil](api-testing.md): los ensayos sintéticos no salen al proveedor, mientras las capturas de Sandbox las completa el titular en Didit. Los diagnósticos solo muestran estados e indicadores de coincidencia; no descargan medios ni publican números de documento. No se desplegó producción. El negocio habitual conserva modo manual; el piloto usa otra DB con modo Didit.
