<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Notifications\PortalActivity;
use Illuminate\Support\Str;

class OrderEvents
{
    public static function record(Order $order, string $type): void
    {
        [$title, $copy, $action] = match ($type) {
            'order.created' => ['Recibimos su solicitud', 'Su solicitud fue registrada. ATD revisará los servicios y la documentación para preparar la cotización.', 'Seguir mi solicitud'],
            'order.updated' => ['Su solicitud avanzó de etapa', 'El equipo actualizó el seguimiento de su caso. Consulte el estado y los próximos pasos en el portal.', 'Ver seguimiento'],
            'invoice.issued' => ['Su factura ATD está disponible', 'La revisión inicial ha finalizado y su factura está disponible. Consulte el importe, vencimiento y las indicaciones para coordinar el pago.', 'Consultar factura'],
            'payment.recorded' => ['Registramos un abono a su factura', 'ATD confirmó un pago parcial. Puede consultar el abono y el saldo restante de su solicitud.', 'Ver pagos y saldo'],
            'invoice.paid' => ['Pago total confirmado', 'ATD confirmó el pago completo de su factura. Los resultados se habilitarán cuando el trabajo esté completado y la entrega publicada.', 'Ver factura pagada'],
            'result.published' => ['Su entrega ATD está lista', 'El equipo publicó una entrega de su solicitud. Entre al portal para revisar el informe y descargar los documentos disponibles.', 'Consultar mi entrega'],
            default => ['Novedad en el seguimiento ATD', 'Hay un nuevo paso registrado en su solicitud. Consulte el seguimiento de su caso.', 'Ver solicitud'],
        };
        $path = '/portal/orders/'.$order->public_id;
        $invoice = str_starts_with($type, 'invoice.') || $type === 'payment.recorded' ? $order->invoice()->first() : null;
        if ($invoice) {
            $path = '/portal/invoices/'.$invoice->public_id;
        }
        foreach (User::where('customer_account_id', $order->customer_account_id)->where('role', 'client')->where('is_active', true)->whereNotNull('email_verified_at')->get() as $client) {
            $client->notify(new PortalActivity($title, $path, $copy, $action, $order->public_id));
        }
        $staffTitle = match ($type) {
            'order.created' => 'Nueva solicitud por revisar', 'result.published' => 'Entrega publicada al cliente',
            'invoice.issued' => 'Factura emitida', 'invoice.paid' => 'Factura pagada', 'payment.recorded' => 'Abono registrado',
            default => 'Seguimiento de solicitud actualizado',
        };
        foreach (User::where('is_active', true)->whereNotNull('email_verified_at')->where(fn ($query) => $query->where('role', 'admin')->orWhere(fn ($q) => $q->where('role', 'collaborator')->where('id', $order->assigned_to ?? 0)))->get() as $staff) {
            if ($staff->id !== auth()->id()) {
                $staff->notify(new PortalActivity($staffTitle, '/portal/orders/'.$order->public_id, 'Revise el caso en su bandeja de trabajo para consultar el estado, los documentos y las acciones pendientes.', 'Abrir caso', $order->public_id));
            }
        }
        // Outbox records participate in the caller's transaction. Payloads never contain results or PII.
        foreach (WebhookEndpoint::where('customer_account_id', $order->customer_account_id)->where('is_active', true)->get() as $endpoint) {
            if (is_array($endpoint->events) && ! in_array($type, $endpoint->events, true)) {
                continue;
            }
            $eventId = (string) Str::uuid();
            WebhookDelivery::create([
                'event_id' => $eventId, 'webhook_endpoint_id' => $endpoint->id, 'order_id' => $order->id, 'event_type' => $type,
                'payload' => ['id' => $eventId, 'type' => $type, 'created_at' => now()->toIso8601String(), 'data' => ['order_id' => $order->public_id, 'status' => $order->status->value, 'links' => ['order' => '/api/v1/orders/'.$order->public_id, 'results' => '/api/v1/orders/'.$order->public_id.'/results', 'deliveries' => '/api/v1/orders/'.$order->public_id.'/deliveries'], 'invoice_id' => $invoice?->public_id]],
                'next_attempt_at' => now(),
            ]);
        }
    }
}
