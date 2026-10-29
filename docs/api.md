# Integrar con All True Detection

La API v1 permite crear solicitudes de servicios, adjuntar documentos escritos, seguir casos, conversar con ATD, consultar facturas y recuperar entregas publicadas. La cuenta de cliente define el perímetro de datos. Solo los partners expresamente autorizados pueden iniciar identidad Didit a crédito y recibir automáticamente su conclusión técnica. La API no permite confirmar pagos ni publicar informes de otros servicios.

## Entornos y contrato

Base de producción: `https://SU_DOMINIO/api/v1`. Base local predeterminada: `http://127.0.0.1:8000/api/v1`. En producción se exige HTTPS; HTTP devuelve 426. Registre por separado los tokens, endpoints y secretos de cada instalación. No mezcle datos o credenciales de pruebas con producción.

La versión de protocolo está en `/v1`; el contrato OpenAPI 3.0.3 describe rutas, parámetros y esquemas. Los identificadores de negocio son ULID opacos, nunca credenciales. Fechas de API en ISO 8601 UTC y vencimientos como `YYYY-MM-DD`. Dinero en unidades menores enteras: `1050` equivale a `10.50` de la moneda indicada. No use flotantes para conciliar importes.

El límite general es de 60 peticiones/minuto por usuario y 600/minuto por IP antes de autenticar. Además, las cargas de documentos admiten 10 intentos/minuto y las operaciones costosas de identidad (iniciar sesión, registrar referencia o crear reverificación) comparten otra cuota de 10 intentos/minuto por usuario. Estos dos contadores son independientes; usan el mismo usuario aunque se rote el token. Los intentos con validación fallida también cuentan. Los cupos diarios de sesiones Didit por cuenta/global siguen aplicándose aparte. Ante 429 respete `Retry-After`; no cree otra clave idempotente para evadir el bloqueo.

## Autenticación y permisos

Cree un usuario cliente, confirme su email e ingrese al portal. En Integraciones API puede emitir tokens temporales de 1 a 90 días (opción predeterminada), o elegir explícitamente un token persistente para una automatización servidor a servidor. Este último tiene `expires_at=null`, no caduca por fecha y no necesita renovación automática. No se amplía la vigencia de tokens existentes. Todos mantienen scopes mínimos y revocación inmediata; se exige confirmación reciente de identidad al emitir o revocar. Cada usuario admite hasta 10 tokens; el token completo se muestra una sola vez y el servidor conserva su hash.

Un token persistente robado también conserva su validez hasta revocarlo. Guárdelo exclusivamente en un gestor de secretos del backend, nunca en una app distribuida, navegador, URL o repositorio. Revise su último uso, rote la credencial según su política operativa y revóquela ante cualquier sospecha. Para rotar sin interrumpir trabajo: emita otro token con los mismos scopes, actualice su aplicación, compruebe una llamada y revoque el anterior. Cambiar/restablecer contraseña, desactivar la cuenta o cambiar sus permisos sigue invalidando el acceso; la integración debe manejar 401/403 y alertar al responsable, no reintentar indefinidamente.

Envíe `Authorization: Bearer TOKEN` en cada llamada. No se aceptan tokens en la URL ni sesiones web como sustituto. No existe un token administrativo de API. Un usuario desactivado, un token revocado/vencido o un cambio de acceso revocado deja de autorizar solicitudes. Cambiar/restablecer contraseña o modificar roles revoca tokens. Cada recurso comprueba pertenencia a cuenta, además del scope.

| Permiso | Uso |
| --- | --- |
| `orders:read` | Catálogo, solicitudes y documentos aportados por el cliente. |
| `orders:write` | Crear solicitudes y subir documentos a casos abiertos. |
| `results:read` | Manifiestos, resultados y descargas después de pago y publicación. |
| `invoices:read` | Facturas, pagos ya registrados, vencimientos y saldos. |
| `messages:read` | Conversación de una solicitud. |
| `messages:write` | Enviar seguimiento a una solicitud. |
| `identity:verify` | Iniciar sesiones Didit a crédito; requiere aprobación comercial vigente de la cuenta. |
| `identity:read` | Consultar enlace, estado y conclusión técnica de esas sesiones; requiere la misma aprobación vigente. |

Los permisos de identidad no se emiten para cuentas sin aprobación y no sustituyen la comprobación de autorización por cuenta en cada llamada. Un token anterior con `orders:write` ya no autoriza estos endpoints: tras la aprobación, emita uno con los permisos específicos. Suspender el acceso bloquea también los tokens existentes. Los usuarios de una cuenta comercial comparten su autorización y cupo; no se concede acceso global al registrarse.

Guardar el token en un gestor de secretos del backend. No colocarlo en JavaScript público, repositorios, logs o herramientas de analítica. Una integración servidor a servidor no necesita CORS. Para un consumidor de navegador, ATD debe autorizar orígenes exactos; no se habilitan cookies ni comodines de origen. Se recomienda un backend propio para no exponer credenciales a los usuarios del navegador.

## Crear una solicitud

Primero consulte `GET /services` para obtener los códigos activos y sus requisitos: `per_person`, `requires_identity_number` y `required_evidence`. Luego envíe `POST /orders` con JSON, consentimiento e `Idempotency-Key`. `details` es opcional: se admite omitirlo, enviarlo como `null` o usar un texto de hasta 10.000 caracteres, sin longitud mínima.

```json
{"subject":"Consulta de capacitación","external_reference":"RRHH-2026-001","consent":true,"items":[{"service_code":"training-equipment","quantity":1}]}
```

La clave debe contener entre 8 y 128 caracteres ASCII alfanuméricos o `._:-`. Se aísla por usuario y se conserva durante la vida de la solicitud. Reutilice la misma clave y el mismo contenido tras timeout o error de red: recibirá la misma orden (201), sin crear una segunda. Reutilizar la clave con otros datos produce 409. Cree una clave nueva para otra solicitud de negocio. No genere claves nuevas indiscriminadamente al reintentar.

Cuenta, rol, precio, asignación y estado se determinan en el servidor. La referencia externa facilita conciliar su sistema; no es una clave de idempotencia ni se impone como única. Se aceptan hasta 12 servicios distintos y cantidades enteras entre 1 y 100. El consentimiento debe representar autorización real para tratar la información aportada.

Ejemplo para CMD de Windows, en una sola línea. Sustituya `SU_TOKEN` antes de ejecutarlo:

```bat
curl.exe --request POST "http://127.0.0.1:8000/api/v1/orders" --header "Authorization: Bearer SU_TOKEN" --header "Content-Type: application/json" --header "Idempotency-Key: solicitud-rrhh-2026-001" --data "{\"subject\":\"Consulta de capacitacion\",\"external_reference\":\"RRHH-2026-001\",\"consent\":true,\"items\":[{\"service_code\":\"training-equipment\",\"quantity\":1}]}"
```

## Verificaciones por persona

Todos los servicios son por persona salvo `training-equipment` (Capacitación y Equipos). El portal y la API comparten el formato `people`: entre 1 y 10 fichas por orden, con una o varias entradas `service_codes` por persona. Cada servicio asignado debe figurar también en `items`; su `quantity` debe coincidir exactamente con el número de personas que lo reciben. Una misma persona puede combinar varios servicios sin duplicar su ficha. La orden, conversación y factura son únicas; las partidas facturan la cantidad correspondiente a cada servicio.

No existe un registro global de personas ni búsqueda de cédulas entre cuentas. ATD genera un identificador público por ficha al crear la orden; no acepte ni envíe identificadores internos de base de datos. Dos fichas con la misma cédula normalizada se rechazan: combine sus servicios en una sola ficha. Si un servicio no necesita cédula, no aporte este dato; el servidor lo rechaza.

Compatibilidad: `person` sigue disponible para una sola persona con título, exequátur o identidad, cantidad 1; no se combina con `people`. La creación API anterior sin ficha para los otros servicios se conserva para integraciones existentes. Las integraciones nuevas deben usar `people` para servicios personales. Las solicitudes históricas conservan sus datos y requisitos de evidencia; no se inventan personas para órdenes antiguas.

| Campo de cada entrada de `people` | Requisito |
| --- | --- |
| `first_name`, `last_name` | Obligatorios, hasta 100 caracteres cada uno. |
| `service_codes` | Lista no vacía, sin códigos repetidos; servicios de esta orden, nunca `training-equipment`. |
| `identity_number` | Obligatoria solo para título, exequátur o identidad. Cédula dominicana de 11 dígitos con o sin guiones; se guarda normalizada. El formato no acredita autenticidad. Prohibida en los demás servicios. |
| `address` | Obligatoria para título, exequátur, identidad y visitas domiciliarias; opcional para los demás, hasta 1.000 caracteres. |
| `purpose` | Finalidad autorizada obligatoria para título, exequátur e identidad, 5-1.000 caracteres; opcional en los otros servicios. No sustituye a `details`, que sigue siendo opcional. |
| `authorization_confirmed` | Debe ser `true` y representar autorización real de la persona. |
| `birth_date`, `phone`, `email` | Opcionales; fecha `YYYY-MM-DD` no futura. |
| `degree.institution`, `degree.degree_name` | Obligatorios solo para título universitario. |
| `degree.graduation_year`, `degree.registration_number` | Opcionales; año no futuro. |
| `exequatur.profession` | Obligatorio solo para exequátur. |
| `exequatur.number`, `exequatur.issuing_authority`, `exequatur.issued_on` | Opcionales; fecha no futura. |

Ejemplo completamente ficticio (la cédula es solo un marcador de pruebas):

```json
{
  "subject": "Verificación de credenciales",
  "consent": true,
  "items": [
    {
      "service_code": "university-degree",
      "quantity": 1
    },
    {
      "service_code": "exequatur",
      "quantity": 1
    },
    {
      "service_code": "psychometric",
      "quantity": 1
    }
  ],
  "people": [{
    "service_codes": ["university-degree", "exequatur"],
    "first_name": "Persona",
    "last_name": "Ficticia",
    "identity_number": "00000000000",
    "address": "Dirección ficticia",
    "purpose": "Verificación autorizada de credenciales",
    "authorization_confirmed": true,
    "degree": {
      "institution": "Institución ficticia",
      "degree_name": "Título de prueba"
    },
    "exequatur": {
      "profession": "Profesión de prueba"
    }
  }, {
    "first_name": "Otra persona",
    "last_name": "Ficticia",
    "service_codes": ["psychometric"],
    "authorization_confirmed": true
  }]
}
```

La creación devuelve `people[].id`. Después suba cada evidencia a `/orders/{order}/attachments` mediante `file`, `purpose` y `person_id` de la ficha correspondiente. Si hay varias personas, `person_id` es obligatorio para evidencia clasificada. Con una sola persona se puede inferir por compatibilidad. Un identificador de otra orden devuelve 404, aunque ambas órdenes pertenezcan a la misma cuenta.

- `identity_front`: frente de la cédula, obligatorio para título/exequátur; una copia sirve para ambos. PDF, JPG, PNG o WebP, escaneada o fotografiada.
- `identity_back`: reverso opcional, en los mismos formatos.
- `degree_document`: título universitario escaneado en PDF o fotografiado, obligatorio para `university-degree`.
- `exequatur_document`: exequátur escaneado en PDF o fotografiado, obligatorio para `exequatur`.
- `selfie`: selfie frontal obligatoria para identidad manual. En modo Didit se captura directamente con el proveedor. Solo JPG, PNG o WebP; no se aceptan PDF/Word como selfie.
- `selfie_extra`: segunda selfie opcional, solo para identidad.
- `supporting`: autorizaciones y otros documentos de apoyo; no sustituye al título, exequátur, cédula ni selfie obligatorios. Puede ser general o vincularse a una persona. Admite DOCX además de PDF e imágenes.

```bat
curl.exe --request POST "http://127.0.0.1:8000/api/v1/orders/ID_ORDEN/attachments" --header "Authorization: Bearer SU_TOKEN" --header "Idempotency-Key: cedula-frente-001" --form "person_id=ID_PERSONA_DEVUELTO_POR_ATD" --form "purpose=identity_front" --form "file=@C:/ruta/cedula-frente.jpg"
```

`GET /orders/{order}` devuelve `people` y `verification` con `method=manual` o `mixed_or_didit`, `ready_for_review`, `required`, `missing` y el desglose por `person_id` en `verification.people`, con su método e indicador operativo `identity_pending`. `person` es el perfil anterior si existe una sola persona; con varias es `null`. El listado general omite fichas y estado documental para no exportar datos personales en bloque. `ready_for_review` solo indica documentación completa: NO significa identidad verificada ni que Didit haya terminado. No se cotiza ni se inicia la revisión/trabajo con evidencia obligatoria pendiente.

La clave idempotente protege el orden de las personas, sus datos y las asignaciones de servicios/documentos. Un reintento debe conservar el orden de `people` y el contenido original. Cambiar la persona, el contenido o la categoría de un documento con la misma clave devuelve 409. Una clave nueva no permite duplicar un archivo ya guardado: el contenido repetido se rechaza con 422, incluso si cambia el nombre. La comprobación compara bytes (SHA-256), no garantiza detectar recortes o imágenes reexportadas, ni prueba que una foto corresponda realmente al frente/reverso. La revisión humana sigue siendo necesaria.

El portal permite adjuntar evidencia al crear la orden, de forma atómica, con el límite conjunto de 20 archivos/20 MB entre todas las personas, incluidos documentos de apoyo. Si no caben los documentos obligatorios, divida las personas en solicitudes más pequeñas. La API conserva el flujo JSON + cargas individuales. Los límites, antivirus, almacenamiento privado, scopes y aislamiento existentes se aplican a todos los documentos. Una única copia PDF con ambas caras de la cédula puede aportarse como `identity_front`; no vuelva a cargar el mismo PDF como reverso.

ATD registra una conclusión por persona y servicio: `verified`, `not_verified` o `inconclusive`, con notas. Los servicios manuales requieren criterio profesional; identidad Didit incorpora la comprobación del proveedor. No es una decisión automática de contratación ni una validación por acceso a registros oficiales. Después de pago total, finalización y publicación, `/deliveries` devuelve `verification_findings` con `method=manual`, `didit` o `mixed`, `reviewed_at` y `checks[]` (`person_id`, `service_code`, `outcome`, `notes`). Las comprobaciones Didit incluyen su referencia ATD y `environment`; sandbox no es una verificación real y no se publica en producción. Las entregas antiguas pueden no incluir `person_id`. No se filtra esa conclusión por estado, notificaciones, listados ni webhooks antes de la entrega ATD.

Las fichas y conclusiones se cifran en la base de datos. La huella de idempotencia de solicitudes con persona usa HMAC con una clave del servidor, no un hash simple de los datos personales. No use nombres/cédulas en asunto, referencia externa, nombres de archivos, claves de idempotencia, URLs o logs. Use referencias opacas. No envíe fotos en base64 dentro del JSON ni por correo. Aplique en su sistema acceso mínimo, cifrado y retención definida para estos datos sensibles. Las órdenes anteriores sin ficha siguen operando; no se inventan ni migran datos de personas.


## Documentos de entrada

En API se crea la orden con JSON y luego se cargan los documentos por `POST /orders/{order}/attachments`, con `multipart/form-data` y un campo `file` y la categoría opcional `purpose`. Este diseño evita reintentar una solicitud entera al fallar la transferencia de un documento. Use una `Idempotency-Key` distinta por documento: el servidor devuelve el mismo adjunto si contenido/nombre/categoría coinciden; 409 si cambian. El header es opcional por compatibilidad, pero se recomienda en toda integración automática.

PDF, Word DOCX sin macros/objetos incrustados/cifrado y JPG, PNG o WebP. Máximo 10 MB por archivo, 30 documentos de entrada y 100 MB totales por solicitud, 1 GB por cuenta. Imágenes limitadas a 40 megapíxeles y 10.000 píxeles por lado. Audio, video, SVG y ZIP independientes no se admiten. Convertir `.doc` antiguo a DOCX o PDF. La extensión y el MIME deben coincidir; DOCX se inspecciona como contenedor y el antivirus es obligatorio en producción. No se acepta un archivo por cambiar su nombre/extensión. Error o timeout del escáner bloquea la carga. No se adjunta a casos completados/cancelados.

```bat
curl.exe --request POST "http://127.0.0.1:8000/api/v1/orders/ID_ORDEN/attachments" --header "Authorization: Bearer SU_TOKEN" --header "Idempotency-Key: documento-rrhh-2026-001" --form "file=@C:/ruta/evidencia.pdf"
```

`GET /orders/{order}/attachments` lista los adjuntos y devuelve sus metadatos y `person_id` cuando corresponde. `download_url` requiere Bearer y `orders:read`; nunca es una URL pública. Los nuevos archivos incluyen `file_size` y `sha256`. Comparar la huella con el contenido descargado permite comprobar integridad. La web puede crear una solicitud junto con hasta 20 documentos / 20 MB, con persistencia atómica. Los límites del proxy/PHP también se aplican: ante 413 reduzca el cuerpo, no repita la misma carga indefinidamente.

Para JPG/PNG/WebP se devuelve también `preview_url`: `GET /orders/{order}/attachments/{attachment}/preview`, con Bearer `orders:read`, comprobación de cuenta, MIME de imagen, disposición inline y caché privada deshabilitada. No use el enlace como URL pública ni coloque el token en el query string. Un backend consumidor puede descargar la imagen autenticada y servirla bajo sus propias autorizaciones. Para PDF/Word, `preview_url=null`; no se incrustan como imágenes y el endpoint de vista previa devuelve 415.

La UI muestra miniaturas y ampliación en modal. Antes de enviar, las miniaturas de los archivos seleccionados se generan localmente en el navegador, sin subirlos ni enviarlos a terceros. Una vista previa no implica validación del contenido ni análisis antivirus; este se ejecuta al cargar al servidor.

## Seguimiento y conversación

`GET /orders` admite `status`, `external_reference` y `per_page` (1–100, por defecto 25). `GET /orders/{order}` devuelve el estado y los servicios. La propiedad `invoice` contiene información financiera solo si el token también tiene `invoices:read`; con `orders:read` únicamente es `null`.

Estados: `submitted` → `reviewing` → `quoted` → `in_progress` → `completed`; una cancelación puede cerrar el caso según las reglas de ATD. Completar el trabajo no equivale a publicar una entrega. El estado del pago es independiente.

`GET /orders/{order}/messages` consulta el historial; `POST` al mismo recurso envía JSON `{"body":"Consulta sobre el avance del caso"}` (1–10.000 caracteres). Los mensajes de seguimiento no sustituyen la entrega final. No envíe informes finales por mensajes ni espere que una respuesta al email se importe al portal: las respuestas se escriben en la UI o API.

## Facturas, pagos y saldos

Con `invoices:read`, consulte `GET /invoices`, con filtros `status=unpaid`, `status=paid` o `status=overdue`; `order_id` permite limitar a una solicitud. Las colecciones paginan con `per_page` entre 1 y 100. `GET /invoices/{invoice}` agrega partidas y los pagos que ATD ha registrado.

Se devuelven subtotal, impuesto, total, `paid_amount`, `outstanding`, moneda, vencimiento y snapshots del emisor/receptor. `unpaid` incluye facturas sin abonos y parcialmente pagadas; use importes para distinguirlas. `overdue` significa no pagada y vencimiento anterior a hoy. Las facturas emitidas conservan sus datos y precios originales. Los eventos de factura y pago sirven para actualizar su sistema; vuelva a consultar para conocer el saldo vigente.

`GET /invoices/{invoice}/document` descarga una representación HTML imprimible de la factura comercial. No es PDF ni un NCF/documento fiscal homologado. La API no confirma fondos, no cobra, no permite reembolsos y no puede marcar una factura pagada: esa confirmación corresponde al administrador general de ATD.

## Recepción automática de entregas

1. Registrar un webhook y suscribirse a `result.published` (y, si lo necesita, a los eventos de facturación).
2. Validar la firma del evento y persistirlo en su propia cola con deduplicación por `event_id`.
3. Consultar `GET /orders/{order}/deliveries` con `results:read`. Es un manifiesto paginado de entregas publicadas. `/results` conserva la misma colección por compatibilidad.
4. Consultar `GET /orders/{order}/results/{result}` para el detalle individual, o usar el resultado del manifiesto.
5. Descargar `download_url` con el mismo Bearer. Verificar `sha256`, guardar el archivo de forma privada y completar el procesamiento en su sistema.

La orden debe estar completada, la factura totalmente pagada según el registro de pagos y la entrega publicada explícitamente por un administrador. Si falta trabajo/pago se responde 409; una entrega aún no publicada no aparece en el manifiesto. La regla se comprueba de nuevo en cada consulta y descarga. Un aviso recibido no concede acceso ni evita estas verificaciones. Los eventos no contienen el resumen, el archivo, nombres ni datos del expediente. Pueden existir varias entregas; deduplicar por el ULID de resultado y mantener su fecha de publicación. Una entrega solo con resumen tendrá `download_url=null`.

Las entregas con imagen incluyen `preview_url` en `/orders/{order}/results/{result}/preview`. Exige `results:read` y las mismas comprobaciones de finalización, pago y publicación; no anticipa la entrega ni genera archivos públicos.

Si se pierde un webhook o está temporalmente fuera de servicio, concilie periódicamente solicitudes y entregas con paginación y un intervalo moderado. No dependa exclusivamente del orden de llegada de los eventos.

## Verificación de identidad con Didit

`GET /services` informa el modo vigente. Cada persona de una orden conserva su `identity_mode`: `manual` o `didit`; el cambio administrativo solo afecta solicitudes nuevas. No envíe selfies a ATD para iniciar el modo Didit: la captura se realiza en la sesión del proveedor. Los documentos obligatorios de título/exequátur siguen siendo independientes.

### Acceso autorizado y captura activa

Solicite a ATD aprobación comercial para identidad API a crédito. Un administrador la gestiona en **Integraciones API → Identidad y crédito**, por cuenta y con límite diario. Mientras el modo efectivo sea manual, una persona nueva no admite sesión Didit; la autorización del partner no convierte casos históricos ni activa el proveedor.

Cree con `orders:write` una orden **exclusivamente** de `identity-verification`, sin otros servicios ni factura individual; puede incluir hasta 10 personas. Para mezclar título, exequátur u otros servicios, cree otra orden con el flujo normal de cotización/pago. Después use el ULID de la persona para `POST /orders/{order}/people/{person}/identity-session`, con `identity:verify` y cuerpo `{"consent":true,"notice_version":"didit-2026-10-v1"}`. Debe haber mostrado el aviso y obtenido autorización para documento, captura facial, prueba de vida activa y tratamiento por Didit. ATD registra versión/fecha; el integrador conserva evidencia de consentimiento. Repetir devuelve la misma sesión, sin reservar otra. No requiere prepago. El canal queda fijado al iniciar la primera persona: no se puede convertir una sesión web en crédito ni mezclar ambos canales en la misma orden.

ATD comprueba el workflow publicado antes de crear: documento, **ACTIVE_3D** y comparación facial. Si no puede confirmar esa configuración, detiene la creación para atención operativa, sin sustituirla silenciosamente por vida pasiva. Para declarar `verified`, también exige que el informe confirme `method=ACTIVE_3D`, comprobaciones aprobadas y cédula DOM coincidente. El reto concreto en pantalla lo determina Didit; no se garantiza una misma secuencia de movimientos en cada dispositivo.

La respuesta es 202, con `billing_mode=partner_credit`, entorno y revisión. Consulte `GET` en la misma ruta con `identity:read` hasta `state=awaiting_person`; use su `verification_url` para presentar el flujo al titular. El backend conserva el Bearer ATD y entrega únicamente ese enlace privado a su frontend. No lo registre, almacene públicamente ni envíe por correo/webhooks. El enlace ya entregado no queda revocado automáticamente al suspender la cuenta ATD; operaciones debe coordinar su cierre con Didit si corresponde. No se admiten callbacks arbitrarios del cliente: el retorno alojado vuelve a una página neutra ATD, que no confirma ni modifica decisiones. Puede usar el enlace con iframe/SDK oficial, sujeto a HTTPS, cámara y política de seguridad de su sitio; compruebe origen/ventana de postMessage y nunca lo use como aprobación.

Estados públicos: `preparing`, `awaiting_person`, `processing`, `received`, `attention_required`. `received` solo significa que ATD obtuvo una conclusión; no revela si fue aprobada. Los casos con comprobaciones insuficientes, cédula diferente, vencimiento o revisión pueden ser no concluyentes. Un fallo incierto al crear la sesión necesita conciliación operativa, no repetir con nuevos identificadores. Consulte cada 10–30 segundos durante la captura, con backoff; se aplican los límites compartidos de la API.

### Conclusión técnica automática y correcciones

ATD verifica callbacks Didit y reconcilia por API en segundo plano. No acepte decisiones de parámetros URL, navegador o cuerpos enviados por usuarios. Reciba `identity.verification.updated` y consulte `GET /orders/{order}/people/{person}/identity-session/result` con `identity:read`, o siga consultando con backoff. No requiere factura pagada ni publicación manual: es una excepción exclusiva de las sesiones `api_credit` y partners aprobados **en el momento de consultar**. No se devuelve desde el portal web ni desde `/results` o `/deliveries`.

La respuesta privada incluye `id`, `order_id`, `person_id`, `provider`, `environment`, `billing_mode`, `revision`, `outcome`, `reason`, `reviewed_at` y `requires_review`. `outcome` puede ser `verified`, `not_verified` o `inconclusive`; el último requiere revisión. No incluye cédula, fotos, biometría ni informe bruto. No constituye una decisión de elegibilidad ni garantiza ausencia de fraude. `sandbox` es simulación y este endpoint bloquea sus resultados en una instalación de producción. Sin conclusión confirmada, devuelve 409; sin aprobación/scope/cuenta correcta, 403.

`revision` aumenta cuando cambia o se retira una conclusión, no por cada consulta. El evento solo contiene referencias locales, entorno, estado, revisión y `links.result`: no contiene conclusión ni datos personales. Conserve la revisión mayor e ignore avisos obsoletos; consulte siempre el estado actual. Tras un evento de retirada, GET puede volver a 409: invalide su conclusión local anterior. Al reanudar un partner suspendido, reconcilie por GET; no se generan automáticamente avisos retroactivos. Una orden se completa cuando todas sus personas tienen conclusión técnica, incluso no concluyente; si alguna conclusión se retira, vuelve a en proceso.

### Crédito, consumo y flujo web

El panel concilia sesiones aceptadas por Didit por fecha UTC de aceptación, separando sandbox/live. Las reservas sin aceptación confirmada se muestran por fecha de creación. Una fila por sesión/persona; reintentos del mismo expediente, polls y callbacks no agregan consumo. Un POST incierto puede haber tenido coste en Didit: no cree otra orden para reintentar ni lo trate como gratis. Este registro **no es una factura ni un cálculo monetario**; las tarifas, corte, impuestos y cobro posterior se acuerdan con ATD. Las órdenes a crédito no admiten factura individual. No se ha implementado aún la emisión automática de facturas consolidadas por corte.

En la **web**, incluso si el cliente es partner, la captura requiere factura totalmente pagada, tanto en live como en sandbox. En pruebas se registra un pago ficticio para ensayar el mismo flujo; no se procesa dinero. El informe ATD normal conserva finalización/publicación y descarga protegida. La pantalla de Didit puede mostrar su propio estado al titular: eso no es un cobro ni una entrega ATD publicada. No se utiliza Didit como pasarela de pago.

Por defecto se admiten 200 reservas nuevas/día UTC globales y 20 por cuenta/canal. El cupo API es configurable por cuenta aprobada; el de web y el global se configuran por operaciones. Superar el cupo responde 429; repetir una sesión existente no consume otra reserva. La suspensión impide iniciar sesiones remotas pendientes y se vuelve a comprobar tras el preflight. No cancela llamadas ya en curso ni sesiones aceptadas: estas siguen conciliándose, sin entregar resultados mientras la cuenta esté suspendida.

### Identidades reutilizables y reverificación facial

Contrato 1.6.0: la verificación inicial sigue pidiendo documento. Después de una conclusión **documental Didit aprobada** y entregada, puede registrar una referencia consentida y solicitar nuevas capturas activas sin volver a enviar la cédula. Las revisiones manuales y las propias autenticaciones recurrentes no sirven como una nueva referencia documental. El resultado recurrente indica `verification_kind=recurring`, `document_reverified=false` e `identity_id`: acredita el cotejo facial realizado en esa fecha, no una nueva validación documental ni una decisión de elegibilidad.

1. Complete la solicitud inicial normal de identidad. Si fue web, debe estar pagada, completada y publicada; si fue API crédito, mantiene su autorización de partner vigente.
2. Con autorización adicional, llame `POST /orders/{order}/people/{person}/reusable-identity` con `identity:verify` + `identity:read` y cuerpo `{"consent":true,"notice_version":"didit-reuse-2026-10-v1"}`. ATD vuelve a consultar al proveedor, registra consentimiento/vínculo y responde 201 con `data.id`. Repetir devuelve la misma referencia, no otra ni una vigencia renovada.
3. Consulte `GET /identities` con `identity:read`: referencias de su propia cuenta, 20 por página, `q` por ULID exacto. Devuelve nombre, últimos cuatro dígitos, entorno, vigencia y estado (`available`, `expired`, `revoked`, `unavailable`), nunca retratos o número completo. No existe búsqueda de personas entre clientes.
4. Llame `POST /identities/{identity}/verifications` con `identity:verify` + `orders:write`, `Idempotency-Key` y el mismo consentimiento versionado. Puede añadir `subject`, `details` opcionales, `external_reference` y `purpose` (5–1.000 caracteres; si se omite, sigue la finalidad original autorizada). ATD crea orden/persona/sesión recurrente a crédito y devuelve 202 con sus identificadores e `identity_session_url`, sin exportar el perfil. Consulte esa URL con `identity:read` para recuperar el enlace privado; entrega técnica y eventos siguen el flujo de crédito anterior.

Didit conserva la referencia facial bajo su retención aplicable; ATD conserva perfil básico cifrado —incluido el número escrito de cédula—, identificador opaco vinculado a la cuenta, consentimiento y conclusiones. No descarga ni reenvía retratos o copias de documento para esta función. Los adjuntos aportados directamente a ATD tienen su ciclo independiente. El servidor fija la referencia y el workflow; `portrait_image`, `reference_image`, `identity_number`, `workflow_id` o `decision` en la petición recurrente se rechazan (422). [Biometric Authentication](https://docs.didit.me/core-technology/biometric-auth/overview).

La vigencia operativa es de **90 días desde la verificación original**, no desde el registro ni desde la última selfie. Caducidad o referencia no disponible devuelve 409 y requiere otro KYC completo. La retención de Didit puede diferir: no se configuró borrado automático o conservación de plantillas tras borrar sesiones. El resultado histórico conserva su fecha; caducar una referencia no convierte un informe anterior en una comprobación nueva. Una corrección que invalide la aprobación documental original bloquea inmediatamente las conclusiones recurrentes dependientes (409 mientras se reconcilian); se solicita su conciliación para actualizar revisión y emitir `identity.verification.updated` con metadatos. Consultar después el resultado actual, que puede ser `inconclusive`.

Solo se admite una captura sin cierre confirmado por referencia. Otra clave para crear simultáneamente devuelve 409; un timeout de creación requiere conciliación, no otra orden. ATD distingue el identificador estable de persona de la referencia única de cada sesión en metadatos, y confirma ambos con el proveedor. Usa ACTIVE_3D + Face Match contra referencia; solo presencia no basta. La referencia no se reutiliza entre aplicaciones, entornos o cuentas.

`POST /identities/{identity}/revoke`, con `identity:verify`, devuelve 204 de forma idempotente y bloquea nuevas capturas en ATD. No reactiva la referencia, no borra comprobaciones históricas ni datos de Didit y no cancela enlaces ya entregados. Para supresión o retirada completa de datos, contacte con ATD para coordinar el procedimiento con Didit. Una revocación requiere otra verificación completa si se desea registrar una referencia nueva.

En la web: abra el expediente aprobado/publicado y seleccione «Registrar referencia reutilizable», aceptando el aviso adicional. Desde «Identidades reutilizables» solicite la nueva comprobación, sin volver a escribir la cédula ni adjuntar fotos. Cada solicitud web conserva su factura, prepago y publicación, incluso si la referencia se registró por API. No se muestra por esta vía la conclusión técnica API original sin sus permisos.

## Webhooks y firma

Configure endpoints, eventos y secreto desde Integraciones API. ATD debe autorizar cada hostname exacto en `WEBHOOK_ALLOWED_HOSTS`. Solo HTTPS en puerto 443, sin credenciales, fragmentos o IP literal. El destino se vuelve a resolver y su IP pública se fija en cada intento, sin redirecciones ni proxies de entorno. El endpoint recibe POST JSON y debe responder 2xx rápidamente, después de guardar el evento de forma durable. No realice análisis de documentos dentro de esa respuesta HTTP.

Eventos de negocio: `order.created`, `order.updated`, `invoice.issued`, `payment.recorded`, `invoice.paid`, `result.published` e `identity.verification.updated` (solo crédito autorizado). Un envío de prueba genera `endpoint.test`, sin expediente. Payload: `id`, `type`, `created_at` y `data` con identificadores/estado/enlaces relativos de API. `invoice_id`/`result_id` pueden ser nulos cuando no aplican. Identidad usa el sobre especializado descrito arriba. Las credenciales de API y la clave HMAC son secretos distintos.

Headers: `X-ATD-Event-ID`, `X-ATD-Timestamp` (segundos Unix) y `X-ATD-Signature`. Cada firma `v1=HEX` es HMAC-SHA256 de `timestamp + "." + cuerpo_HTTP_original` con el secreto del endpoint. Verificar los bytes originales antes de parsear JSON, comparar en tiempo constante y rechazar diferencias de más de cinco minutos. Durante la rotación puede haber dos firmas `v1`, separadas por coma; acepte una firma válida para una clave que tenga autorizada.

Núcleo de validación para un backend Node.js (es código de archivo, no un comando para pegar en CMD):

```javascript
import { createHmac, timingSafeEqual } from 'node:crypto';
export function validATDWebhook(rawBody, timestamp, signature, secret) {
  if (!Buffer.isBuffer(rawBody) || rawBody.length > 65536) return false;
  if (!/^\d{10}$/.test(String(timestamp))) return false;
  if (Math.abs(Date.now() / 1000 - Number(timestamp)) > 300) return false;
  const expected = createHmac('sha256', secret).update(String(timestamp) + '.').update(rawBody).digest();
  return String(signature).split(',').slice(0, 2).some(part => {
    const match = /^v1=([a-f0-9]{64})$/.exec(part.trim());
    return match && timingSafeEqual(expected, Buffer.from(match[1], 'hex'));
  });
}
```

Después de validar, parsear JSON, comprobar que `id` coincide con `X-ATD-Event-ID`, deduplicar de manera transaccional y encolar el trabajo. No volver a ejecutar acciones por un duplicado ya aceptado. Si responde 2xx antes de persistir, una caída podría perder el evento sin que ATD lo reintente.

La entrega es al menos una vez y puede llegar fuera de orden. Hay hasta 8 intentos con esperas de 1, 5, 15, 60, 360, 1440 y 1440 minutos. No se siguen 3xx. El timeout HTTP es 15 s y se limita la respuesta a 64 KB. El panel muestra intentos, códigos HTTP, fechas y errores de transporte sin registrar cuerpos remotos. Puede probar el endpoint, desactivarlo, rotar el secreto con 24 h de solapamiento y reencolar una entrega agotada conservando su `event_id` y payload. Desactivar detiene nuevos eventos y descarta pendientes; no borra los registros. Volver a consultar el estado vigente al recibir un replay.

## Errores, límites y reintentos

Enviar `Content-Type: application/json` en escrituras, excepto la carga multipart. Cuerpo JSON de hasta 1 MB y profundidad máxima de 64 niveles. Toda respuesta de error API es JSON, aunque no se envíe Accept. Las colecciones siguen `data`, `links` y `meta`. Los mensajes de error contienen `error.code`, `error.message`, `error.fields` y `meta.request_id`. Las respuestas incluyen `X-Request-ID` para soporte.

| HTTP | Interpretación y acción |
| --- | --- |
| 400 | JSON malformado o profundidad excesiva. Corregir el cuerpo. |
| 401 | Bearer ausente, inválido, revocado o vencido. Renovar credenciales. |
| 403 | Cuenta/rol/permisos insuficientes o recurso de otra cuenta. Revisar acceso. |
| 404 | Identificador o archivo no disponible. Revisar el recurso. |
| 409 | Clave reutilizada con otros datos, caso cerrado o entrega sin habilitar. Conciliar el estado. |
| 413 | Cuerpo demasiado grande; reducirlo. |
| 415 | Tipo de contenido incorrecto. |
| 422 | Datos, documento o cuota inválidos; consultar `error.fields`. |
| 426 | Usar HTTPS en producción. |
| 429 | Respetar `Retry-After`; no abrir más tokens para evadir el límite. |
| 500 / 503 | Error interno o servicio temporalmente no disponible (incluye escáner). Reintentar de forma acotada. |

Límite de 60 solicitudes/minuto por usuario, compartido entre sus tokens; protección adicional de 600/minuto por IP y 10 cargas/minuto. Revisar `X-RateLimit-Limit`, `X-RateLimit-Remaining` y `Retry-After` cuando estén presentes. Para red, 429 o 5xx aplicar espera exponencial con jitter y un máximo de intentos. Reutilizar claves de idempotencia para escrituras; nunca reintentar con nuevas claves sin conciliar. Un error 4xx de validación/permisos no se corrige repitiendo la misma llamada.

## Operación y seguridad

Integraciones API muestra actividad por ruta, códigos, latencia y request ID, tokens y su último uso/vencimiento, endpoints e historial de entregas. El cliente ve su cuenta; ATD administra el conjunto con autorización reforzada. Las métricas se conservan 30 días; no guardan cuerpos, query strings, archivos ni Authorization. El historial de negocio permanece separado. El contador p95 se calcula con una muestra de hasta 1.000 solicitudes recientes y se identifica como tal en el panel.

En el servidor se requieren worker y scheduler: el outbox se guarda con la transacción de negocio y el scheduler encola pendientes/reintentos. Los envíos HTTP no mantienen abierta una transacción de base de datos. Los procesos usan leases para evitar entregar simultáneamente el mismo evento. Configurar escáner, cuotas del servidor web, TLS, respaldos privados y protección de APP_KEY según el runbook de operación.

Antes de activar una integración: probar scopes mínimos, aislamiento entre cuentas, expiración/revocación, clave repetida y conflicto, MIME/tamaño rechazado, pagos parciales, bloqueo de entregas, firma/replay y recuperación ante timeouts. Solicitar a soporte el diagnóstico con `X-Request-ID`, fecha/hora UTC y operación; nunca enviar el token, la clave HMAC ni el contenido del expediente.
