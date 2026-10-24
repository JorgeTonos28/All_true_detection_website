# Integrar con All True Detection

La API v1 permite crear solicitudes de servicios, adjuntar documentos escritos, seguir casos, conversar con ATD, consultar facturas y recuperar entregas publicadas. La cuenta de cliente define el perímetro de datos. La API no ejecuta verificaciones por sí sola ni permite confirmar pagos o publicar informes: esas decisiones corresponden a ATD.

## Entornos y contrato

Base de producción: `https://SU_DOMINIO/api/v1`. Base local predeterminada: `http://127.0.0.1:8000/api/v1`. En producción se exige HTTPS; HTTP devuelve 426. Registre por separado los tokens, endpoints y secretos de cada instalación. No mezcle datos o credenciales de pruebas con producción.

La versión de protocolo está en `/v1`; el contrato OpenAPI 3.0.3 describe rutas, parámetros y esquemas. Los identificadores de negocio son ULID opacos, nunca credenciales. Fechas de API en ISO 8601 UTC y vencimientos como `YYYY-MM-DD`. Dinero en unidades menores enteras: `1050` equivale a `10.50` de la moneda indicada. No use flotantes para conciliar importes.

## Autenticación y permisos

Cree un usuario cliente, confirme su email e ingrese al portal. En Integraciones API puede emitir tokens de 1 a 90 días con los permisos mínimos necesarios. Se exige confirmación reciente de identidad para emitir, revocar y rotar credenciales. Cada usuario admite hasta 10 tokens; el token completo se muestra una sola vez y el servidor conserva su hash.

Envíe `Authorization: Bearer TOKEN` en cada llamada. No se aceptan tokens en la URL ni sesiones web como sustituto. No existe un token administrativo de API. Un usuario desactivado, un token revocado/vencido o un cambio de acceso revocado deja de autorizar solicitudes. Cambiar/restablecer contraseña o modificar roles revoca tokens. Cada recurso comprueba pertenencia a cuenta, además del scope.

| Permiso | Uso |
| --- | --- |
| `orders:read` | Catálogo, solicitudes y documentos aportados por el cliente. |
| `orders:write` | Crear solicitudes y subir documentos a casos abiertos. |
| `results:read` | Manifiestos, resultados y descargas después de pago y publicación. |
| `invoices:read` | Facturas, pagos ya registrados, vencimientos y saldos. |
| `messages:read` | Conversación de una solicitud. |
| `messages:write` | Enviar seguimiento a una solicitud. |

Guardar el token en un gestor de secretos del backend. No colocarlo en JavaScript público, repositorios, logs o herramientas de analítica. Una integración servidor a servidor no necesita CORS. Para un consumidor de navegador, ATD debe autorizar orígenes exactos; no se habilitan cookies ni comodines de origen. Se recomienda un backend propio para no exponer credenciales a los usuarios del navegador.

## Crear una solicitud

Primero consulte `GET /services` para obtener los códigos activos. Luego envíe `POST /orders` con JSON, consentimiento e `Idempotency-Key`.

```json
{"subject":"Validación de credenciales","details":"Solicitamos verificar las credenciales con la información autorizada.","external_reference":"RRHH-2026-001","consent":true,"items":[{"service_code":"university-degree","quantity":1}]}
```

La clave debe contener entre 8 y 128 caracteres ASCII alfanuméricos o `._:-`. Se aísla por usuario y se conserva durante la vida de la solicitud. Reutilice la misma clave y el mismo contenido tras timeout o error de red: recibirá la misma orden (201), sin crear una segunda. Reutilizar la clave con otros datos produce 409. Cree una clave nueva para otra solicitud de negocio. No genere claves nuevas indiscriminadamente al reintentar.

Cuenta, rol, precio, asignación y estado se determinan en el servidor. La referencia externa facilita conciliar su sistema; no es una clave de idempotencia ni se impone como única. Se aceptan hasta 11 servicios distintos y cantidades enteras entre 1 y 100. El consentimiento debe representar autorización real para tratar la información aportada.

Ejemplo para CMD de Windows, en una sola línea. Sustituya `SU_TOKEN` antes de ejecutarlo:

```bat
curl.exe --request POST "http://127.0.0.1:8000/api/v1/orders" --header "Authorization: Bearer SU_TOKEN" --header "Content-Type: application/json" --header "Idempotency-Key: solicitud-rrhh-2026-001" --data "{\"subject\":\"Validacion de credenciales\",\"details\":\"Verificacion de credenciales autorizada por el titular.\",\"external_reference\":\"RRHH-2026-001\",\"consent\":true,\"items\":[{\"service_code\":\"university-degree\",\"quantity\":1}]}"
```

## Documentos de entrada

En API se crea la orden con JSON y luego se cargan los documentos por `POST /orders/{order}/attachments`, con `multipart/form-data` y un único campo `file`. Este diseño evita reintentar una solicitud entera al fallar la transferencia de un documento. Use una `Idempotency-Key` distinta por documento: el servidor devuelve el mismo adjunto si contenido/nombre coinciden; 409 si cambian. El header es opcional por compatibilidad, pero se recomienda en toda integración automática.

PDF, Word DOCX sin macros/objetos incrustados/cifrado y JPG, PNG o WebP. Máximo 10 MB por archivo, 30 documentos de entrada y 100 MB totales por solicitud, 1 GB por cuenta. Imágenes limitadas a 40 megapíxeles y 10.000 píxeles por lado. Audio, video, SVG y ZIP independientes no se admiten. Convertir `.doc` antiguo a DOCX o PDF. La extensión y el MIME deben coincidir; DOCX se inspecciona como contenedor y el antivirus es obligatorio en producción. No se acepta un archivo por cambiar su nombre/extensión. Error o timeout del escáner bloquea la carga. No se adjunta a casos completados/cancelados.

```bat
curl.exe --request POST "http://127.0.0.1:8000/api/v1/orders/ID_ORDEN/attachments" --header "Authorization: Bearer SU_TOKEN" --header "Idempotency-Key: documento-rrhh-2026-001" --form "file=@C:/ruta/evidencia.pdf"
```

`GET /orders/{order}/attachments` lista los adjuntos y devuelve sus metadatos. `download_url` requiere Bearer y `orders:read`; nunca es una URL pública. Los nuevos archivos incluyen `file_size` y `sha256`. Comparar la huella con el contenido descargado permite comprobar integridad. La web puede crear una solicitud junto con hasta 5 documentos / 20 MB, con persistencia atómica. Los límites del proxy/PHP también se aplican: ante 413 reduzca el cuerpo, no repita la misma carga indefinidamente.

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

Si se pierde un webhook o está temporalmente fuera de servicio, concilie periódicamente solicitudes y entregas con paginación y un intervalo moderado. No dependa exclusivamente del orden de llegada de los eventos.

## Webhooks y firma

Configure endpoints, eventos y secreto desde Integraciones API. ATD debe autorizar cada hostname exacto en `WEBHOOK_ALLOWED_HOSTS`. Solo HTTPS en puerto 443, sin credenciales, fragmentos o IP literal. El destino se vuelve a resolver y su IP pública se fija en cada intento, sin redirecciones ni proxies de entorno. El endpoint recibe POST JSON y debe responder 2xx rápidamente, después de guardar el evento de forma durable. No realice análisis de documentos dentro de esa respuesta HTTP.

Eventos de negocio: `order.created`, `order.updated`, `invoice.issued`, `payment.recorded`, `invoice.paid`, `result.published`. Un envío de prueba genera `endpoint.test`, sin expediente. Payload: `id`, `type`, `created_at` y `data` con identificadores/estado/enlaces relativos de API. `invoice_id`/`result_id` pueden ser nulos cuando no aplican. Las credenciales de API y la clave HMAC son secretos distintos.

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
