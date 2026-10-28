# Identidad manual y automatizada con Didit

## Decisión y alcance

ATD eligió Didit. Se implementa **sesión API v3 + captura alojada + webhook**, sin SDK adicional en Laravel. La misma sesión admite iframe/SDK en la app integradora. La persona siempre participa en la captura/prueba de vida: automatizar no significa verificarla sin su intervención.

- [Sesión alojada](https://docs.didit.me/integration/api-full-flow): ATD crea la sesión y devuelve su enlace privado. Es la opción implementada en el portal, con menos componentes que mantener.
- [Iframe](https://docs.didit.me/integration/web-sdks/incontext-iframe) o [SDK JavaScript](https://docs.didit.me/integration/web-sdks/javascript-sdk): el cliente integra esa sesión en su app. Debe gestionar HTTPS, cámara, CSP y mensajes de ventana; el servidor confirma la decisión. ATD no incrusta un iframe ni abre permisos globales de cámara.
- APIs independientes de [documento](https://docs.didit.me/standalone-apis/id-verification), [vida pasiva](https://docs.didit.me/standalone-apis/passive-liveness) y comparación facial: permiten servidor a servidor con imágenes de una UI propia. Exigen asegurar captura, coordinar comprobaciones y manejar sus fallos. No se implementan en esta fase ni equivalen al desafío activo 3D del workflow inspeccionado.

El conector autorizado permitió inspeccionar aplicaciones/workflows sin modificarlos ni crear sesiones. El workflow publicado consultado incluía OCR, vida activa 3D y comparación facial, con documentos DOM. Validar de nuevo su configuración antes de activar. El conector sirve para gestión: **no entrega a Laravel las claves secretas** ni forma parte del runtime desplegado.

## Modo y expedientes existentes

Mi negocio incluye un switch Manual/Didit protegido por administrador general, sesión activa, correo verificado, 2FA y reconfirmación de contraseña. No admite claves en la UI. Predeterminado manual; si faltan configuración o aprobaciones, la oferta efectiva vuelve a manual para casos nuevos y el switch queda bloqueado.

Cada `order_person` conserva su `identity_mode`. Cambiar el switch no reescribe personas/evidencias históricas ni transmite imágenes antiguas. Las sesiones iniciadas siguen conciliándose con sus credenciales de aplicación/entorno. Cambiar workflow dentro de la misma aplicación permite seguir consultando sesiones anteriores; cambiar aplicación/entorno requiere conciliar sus pendientes antes de retirar las credenciales previas.

Web, catálogo API y formulario usan el modo efectivo. Manual exige selfie, sin afirmar biometría/prueba de vida. Didit no exige esa selfie de entrada: la captura se realiza con el proveedor tras confirmar el aviso versionado. Título/exequátur y sus documentos mantienen su flujo independiente.

## Flujo integrado

1. Crear orden con `people` y servicio `identity-verification`; ATD devuelve ULID de persona y modo snapshot.
2. En **live**, ATD cotiza y confirma pago total antes de habilitar captura. Evita consumo no pagado y adelantar el veredicto desde la pantalla/SDK Didit. Sandbox permite pruebas sin pago.
3. Mostrar el aviso, obtener autorización e iniciar `POST /api/v1/orders/{order}/people/{person}/identity-session`, con `consent=true`, versión `didit-2026-10-v1` y `orders:write`. Web/API comparten acción; se registra versión/fecha y el integrador conserva su evidencia de consentimiento.
4. Respuesta 202; el worker crea la sesión. GET en la misma ruta devuelve estado y, cuando corresponde, `verification_url`. Repetir devuelve la misma sesión: una por persona.
5. Presentar el enlace al titular, por navegación alojada o SDK/iframe. El Bearer ATD queda en el backend cliente. El enlace es una capacidad privada de captura, no un resultado ni URL pública.
6. Didit llama `POST /api/providers/didit/webhook`. ATD verifica firma, frescura, aplicación, entorno, workflow, referencia local y sesión; persiste recibo mínimo deduplicado y solicita conciliación, sin guardar el payload biométrico.
7. El worker consulta la decisión actual con la clave de servidor. Los eventos tardíos no sustituyen ese estado. Redirect, query strings y postMessage nunca aprueban ni alteran un caso.
8. Para `verified` se exige Approved y comprobaciones no vacías de documento/vida/comparación facial aprobadas, documento DOM y cédula normalizada coincidente. Una aprobación sandbox vacía, falta de campos o cédula distinta queda no concluyente. Alcance actual: cédula dominicana, no todos los documentos que ofrece Didit.
9. Orden solo de identidad Didit: entrega privada preparada sin transcripción humana. Lotes mixtos: conclusión Didit incorporada a las demás conclusiones profesionales, sin permitir sobrescribirla desde formularios.
10. Finalización y publicación siguen siendo explícitas después del pago. El cliente recibe `result.published` y recupera `/deliveries` con `results:read`. Automatización técnica no autoriza confirmar pagos o publicar sin administrador. In Review/discrepancias pueden necesitar humanos; no se promete automatización sin excepciones ni decisiones de elegibilidad.

## Seguridad, consumo y conciliación

- HTTPS fijo `verification.didit.me`, certificado verificado, sin redirects ni URLs de API suministradas por clientes. Conexión 3 s / operación 12 s; sin reintentar automáticamente POST.
- Referencias opacas enlazan sesión/persona/orden; no usar cédula como `vendor_data` ni agrupar identidades entre clientes. Perfil esperado mínimo; sin invitaciones Didit por email automáticas ni transferencia de adjuntos ATD.
- URL de captura, estado del proveedor y conclusión resumida cifrados. No se descargan informes, documentos, selfies o vídeos automáticamente. Errores de transporte sin claves, cuerpos o respuesta remota en logs.
- [Firma Didit](https://docs.didit.me/integration/webhooks): V2 HMAC-SHA256, comparación constante y canonicalización preservando objetos vacíos, orden lexicográfico, Unicode/barras. Comprobar tanto header X-Timestamp como timestamp firmado, dentro de 300 s. Sin Simple ni confianza en redirects. V2-Previous permite la ventana de compatibilidad durante rotación de secreto.
- Recibo por `event_id`, sin datos personales. Colas contienen identificador local. Si falla despacho, `sync_requested` persiste para el scheduler.
- `portal:reconcile-identity` cada minuto: hasta 100 elegibles por lote; activas tras 10 min y recibidas tras 24 h sin conciliación, además de eventos que solicitan sync. Intervalos de elegibilidad, no SLA bajo saturación. No procesa otras aplicaciones/entornos con la clave actual.
- POST incierto: `create_uncertain`, sin repetir ni crear una segunda sesión. Un webhook vinculado recupera el identificador. Sin evento, operaciones localiza la sesión en consola por `vendor_data` y reenvía un evento firmado; no pedir otra orden a ciegas ni borrar registros para reintentar.
- Cuotas de nuevas sesiones: 200/día UTC globales y 20/cuenta, configurables y serializadas en DB; cero bloquea nuevas. Repetir una persona existente no consume otra cuota. No son un presupuesto monetario ni sustituyen alertas/límites del proveedor.
- Una corrección/caducidad invalida publicaciones anteriores; prepara nueva versión cuando corresponde y exige publicación actualizada. La publicación comprueba que el snapshot sigue vigente y bloquea simulaciones en producción.

El estado público `received` no revela aprobación/rechazo. La propia UI/SDK Didit puede mostrar su estado al titular: pago previo live evita exposición antes del pago, pero no se promete controlar mensajes de un producto externo. El informe **ATD** conserva publicación administrativa. Revisar este límite y las pantallas/mails Didit antes de autorizar producción.

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
2. Publicar/seleccionar workflow KYC con documento, vida y comparación facial. Probar cédula DOM específica, idioma, calidad y accesibilidad. Autorizar los campos mínimos necesarios para vincular identidad; sin ellos ATD no declara verified. No usar un UniLink genérico para sustituir sesiones locales.
3. Crear destino HTTPS de la aplicación a `/api/providers/didit/webhook`, eventos `status.updated` y `data.updated`, versión v3. Usar el secreto de ese destino, distinto de la API key. En local se necesita túnel HTTPS de pruebas elegido por operaciones o conciliación por API: Didit no alcanza 127.0.0.1. Esta tarea no creó túneles ni modificó destinos.
4. Revisar condiciones para verificar clientes, privacidad/DPA, ubicación, subencargados, conservación/supresión y preferencias de entrenamiento. El aviso aprobado no acredita revisión contractual/legal. `DIDIT_PRIVACY_APPROVED=true` solo tras completar esta revisión y definir evidencia del consentimiento en la app integradora.
5. Respaldar DB/APP_KEY, aplicar migraciones aditivas, limpiar/cachear configuración y reiniciar workers. Cola/scheduler supervisados; en local `composer run dev` reúne los procesos en una terminal.
6. Probar sandbox completo: callback, firma, duplicados, discrepancias, errores y recuperación. Approved simulado sin comprobaciones produce inconclusive deliberadamente. Mocks locales no confirman una llamada real a Didit.
7. Producción requiere entorno live, aplicación/workflow/secretos live y `DIDIT_PRODUCTION_APPROVED=true`; revisar pantallas/SDK y hacer prueba autorizada antes de abrir el servicio. El switch exige configuración completa. No automatizar pago/publicación como parte de activación.

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
