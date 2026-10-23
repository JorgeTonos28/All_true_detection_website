@extends('portal.layout')
@section('title','Guía de integración API')
@section('subtitle','API v1 · Solicitudes, seguimiento y resultados para su sistema.')
@section('content')
<section class="panel stack"><h2>Conectar su sistema</h2><p>Genere un token desde Seguridad e incluya <code>Authorization: Bearer TOKEN</code> en cada solicitud. Base: <code>{{ url('/api/v1') }}</code>. Use HTTPS en producción y almacene el token como secreto.</p><a href="{{ route('portal.api-contract') }}">Descargar contrato OpenAPI JSON</a>
<h3>Endpoints y permisos</h3><div class="table-wrap"><table><thead><tr><th>Operación</th><th>Permiso</th></tr></thead><tbody><tr><td>GET /services · GET /orders · GET /orders/{id}</td><td>orders:read</td></tr><tr><td>POST /orders</td><td>orders:write</td></tr><tr><td>GET /orders/{id}/results y descarga</td><td>results:read</td></tr><tr><td>GET /orders/{id}/messages</td><td>messages:read</td></tr><tr><td>POST /orders/{id}/messages</td><td>messages:write</td></tr></tbody></table></div>
<h3>Crear solicitudes sin duplicarlas</h3><p>Envíe una clave única en <code>Idempotency-Key</code> y un cuerpo JSON como el siguiente. Reutilice la misma clave y datos al reintentar; cambiar el contenido devuelve 409.</p><pre><code>{
  "subject": "Validación de credenciales",
  "details": "Solicitamos verificar las credenciales autorizadas.",
  "external_reference": "RRHH-001",
  "consent": true,
  "items": [{"service_code": "university-degree", "quantity": 1}]
}</code></pre>
<h3>Resultados protegidos</h3><p>El listado de resultados devuelve 409 hasta completar la orden y confirmar el pago total. Solo aparecen las entregas publicadas por ATD. Las descargas requieren el mismo token autorizado y vuelven a comprobar el acceso.</p>
<h3>Límites y errores</h3><p>60 solicitudes por minuto por usuario. Listados paginados, con <code>per_page</code> de 1 a 100 para órdenes. Respete <code>Retry-After</code> en errores 429. Los errores incluyen <code>error.code</code>, <code>error.message</code>, <code>error.fields</code> y <code>meta.request_id</code>.</p>
<h3>Webhooks firmados</h3><p>Solicite a ATD la autorización de su dominio y registre su endpoint HTTPS desde Seguridad. Verifique <code>X-ATD-Signature</code> como <code>v1=HMAC-SHA256(timestamp + "." + cuerpo original, secreto)</code>, usando comparación en tiempo constante. Rechace timestamps fuera de una ventana de 5 minutos y deduplique por <code>X-ATD-Event-ID</code>. Responda 2xx rápidamente.</p><p>Eventos: order.created, order.updated, invoice.issued, payment.recorded, invoice.paid y result.published. Los avisos contienen identificadores y estado; consulte la API para obtener detalles. Hay hasta 8 intentos; las entregas pueden repetirse o llegar fuera de orden.</p>
</section>
@endsection
