# Integración API v1

Base: `https://SU_DOMINIO/api/v1`. Contrato: [`openapi.json`](../packages/contracts/openapi.json). Autenticación mediante `Authorization: Bearer TOKEN`. Crear tokens desde Seguridad → Integraciones, con usuario cliente activo y correo verificado. El portal exige autenticación reciente para emitir credenciales. El secreto se muestra una sola vez; los tokens vencen a los 90 días y pueden revocarse. No usar credenciales personales en código ni registrar el encabezado Authorization.

Permisos disponibles: `orders:read`, `orders:write`, `results:read`, `messages:read`, `messages:write`. Los permisos del token complementan el aislamiento de cuenta; nunca lo sustituyen. No hay tokens de administración, pagos ni publicación.

## Crear una solicitud

Enviar `POST /orders` con JSON y `Idempotency-Key: identificador-unico-123`:

```json
{
  "subject": "Validación de credenciales",
  "details": "Solicitamos verificar las credenciales con la información autorizada.",
  "external_reference": "RRHH-2026-001",
  "consent": true,
  "items": [{"service_code": "university-degree", "quantity": 1}]
}
```

Consultar códigos en `GET /services`. La misma clave y contenido devuelve la misma orden (201); cambiar el contenido produce 409. Claves de 8–128 caracteres ASCII alfanuméricos o `._:-`, aisladas por usuario y conservadas durante toda la vida de la orden. Utilizar una clave distinta para cada solicitud de negocio y reutilizarla al reintentar tras un timeout. Precios, roles, cuenta y estados se deciden en servidor.

`GET /orders` permite `status`, `external_reference` y `per_page` (1–100, predeterminado 25). Las colecciones incluyen paginación Laravel (`data`, `links`, `meta`). `GET /orders/{id}` devuelve servicios, estado y factura. Los identificadores de negocio son ULID opacos; no son credenciales.

`GET /orders/{id}/results` solo devuelve entregas publicadas con orden completada y pago total confirmado; en otro caso devuelve 409. `download_url` requiere el mismo token y vuelve a validar permisos y pago. `GET` y `POST /orders/{id}/messages` permiten leer y enviar seguimiento con `{ "body": "Consulta sobre el avance" }`; los resultados finales se entregan exclusivamente desde resultados.

Los documentos de entrada usan `GET/POST /orders/{id}/attachments` y `GET /orders/{id}/attachments/{attachment}/download`. Lectura requiere `orders:read`; carga requiere `orders:write`. Para cargar, enviar `multipart/form-data` con campo `file`. Solo PDF/JPG/PNG/WebP de hasta 10 MB, 30 documentos por orden, 10 cargas por minuto y únicamente en órdenes abiertas. Se valida contenido MIME y extensión; en producción también se requiere escaneo antivirus. Estos adjuntos solo los aporta el cliente; el personal entrega archivos mediante resultados. Una carga repetida no es idempotente: consultar el listado después de un timeout antes de reintentar.

## Errores y límites

400: JSON malformado; 401: token inválido o vencido; 403: permisos o cuenta; 404: recurso inexistente; 409: conflicto/idempotencia/entrega no disponible; 413: tamaño excedido; 415: tipo de contenido no compatible; 422: validación; 429: límite; 500/503: error interno o servicio temporalmente no disponible. Los errores usan `{ "error": { "code": "http_422", "message": "...", "fields": {} }, "meta": { "request_id": "..." } }`. Guardar `X-Request-ID` para soporte. Respetar `Retry-After` en 429. Límite: 60 solicitudes/minuto por usuario, compartido entre sus tokens. No consultar en bucle rápido: preferir webhooks y consultar únicamente la orden afectada. Los cuerpos JSON tienen un límite de 1 MB. Toda respuesta de API usa JSON incluso si el cliente omite Accept. CORS está cerrado por defecto para navegadores; autorizar orígenes exactos con `API_ALLOWED_ORIGINS` si corresponde, sin cookies.

## Webhooks

Configurar endpoints desde Integraciones. Operaciones debe autorizar previamente cada hostname exacto en `WEBHOOK_ALLOWED_HOSTS`. Solo HTTPS en puerto 443, sin credenciales/fragmentos, con resolución pública; se verifica de nuevo y fija la dirección IP en cada intento. No se siguen redirecciones ni proxies de entorno.

Eventos: `order.created`, `order.updated`, `invoice.issued`, `payment.recorded`, `invoice.paid`, `result.published`. Payload mínimo: `{id,type,created_at,data:{order_id,status}}`; no contiene resultados, mensajes, nombres ni documentos. Consultar resultados con un token autorizado después de `result.published`.

Verificar `X-ATD-Signature: v1=HEX` como HMAC-SHA256 del texto `X-ATD-Timestamp + "." + cuerpo_HTTP_original` usando el secreto del endpoint. Comparar en tiempo constante y rechazar timestamps con más de cinco minutos de diferencia. Procesar cada `X-ATD-Event-ID` una sola vez y responder 2xx rápidamente; la entrega es al menos una vez y puede llegar fuera de orden. Al recibir, consultar el estado actual de la orden.

Ocho intentos con esperas de 1, 5, 15, 60, 360, 1440 y 1440 minutos. Otros estados HTTP y redirecciones se reintentan; los errores finales quedan visibles en Integraciones. Desactivar un endpoint detiene nuevas entregas y descarta las pendientes. Para cambiar URL o secreto, desactivar el anterior y crear uno nuevo. No se ofrece replay manual de eventos fallidos en esta versión.

Requiere cron de `php artisan schedule:run` cada minuto y worker de `php artisan queue:work --tries=5 --timeout=60` (retry_after de la cola > 60 s). La bandeja se guarda en la misma transacción de negocio, por lo que los eventos sobreviven a reinicios del worker. Probar firma, idempotencia, retries y revocación en un entorno de prueba antes de conectar producción.
