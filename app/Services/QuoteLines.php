<?php

namespace App\Services;

use App\Models\Order;

class QuoteLines
{
    /** Build priceable lines from the stored order and per-person assignments. */
    public static function build(Order $order): array
    {
        $order->loadMissing(['items.service.optionPrices', 'people.services']);
        $enabled = BusinessBillingSettings::pricingEnabled();
        $lines = [];
        foreach ($order->items->sortBy('id') as $item) {
            $service = $item->service;
            $labels = $service->optionLabels();
            $counts = array_fill_keys(array_keys($labels), 0) + ['' => 0];
            if ($labels) {
                foreach ($order->people as $person) {
                    if (! $person->services->contains('id', $item->service_id)) {
                        continue;
                    }
                    $option = $person->profile['service_options'][$service->code] ?? null;
                    $counts[is_string($option) && isset($labels[$option]) ? $option : '']++;
                }
            }
            $unassigned = $item->quantity - array_sum($counts);
            abort_if($unassigned < 0, 409, 'La cantidad del servicio no coincide con las personas asignadas.');
            $counts[''] += $unassigned;
            foreach ($counts as $option => $quantity) {
                if ($quantity === 0) {
                    continue;
                }
                $key = $option === '' ? $item->id : $item->id.':'.$option;
                $lines[$key] = [
                    'item_id' => $item->id, 'service_code' => $service->code, 'service_name' => $item->service_name,
                    'option_code' => $option === '' ? null : $option, 'option_name' => $labels[$option] ?? null,
                    'has_options' => (bool) $labels, 'quantity' => $quantity,
                    'suggested_price' => BusinessBillingSettings::suggestedPrice($service, $option === '' ? null : $option, $enabled),
                ];
            }
        }

        return $lines;
    }
}
