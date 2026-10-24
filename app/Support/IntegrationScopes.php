<?php

namespace App\Support;

class IntegrationScopes
{
    public const ALL = ['orders:read', 'orders:write', 'results:read', 'invoices:read', 'messages:read', 'messages:write'];

    public const LABELS = [
        'orders:read' => 'Consultar solicitudes y documentos de entrada', 'orders:write' => 'Crear solicitudes y adjuntar documentos',
        'results:read' => 'Consultar y descargar entregas publicadas', 'invoices:read' => 'Consultar facturas, pagos y saldos',
        'messages:read' => 'Leer conversaciones de solicitudes', 'messages:write' => 'Enviar mensajes de seguimiento',
    ];
}
