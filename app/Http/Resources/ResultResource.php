<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'order_id' => $this->order->public_id, 'summary' => $this->summary, 'published_at' => $this->published_at?->toIso8601String(), 'file_name' => $this->file_name, 'mime_type' => $this->mime_type, 'file_size' => $this->file_size, 'sha256' => $this->sha256, 'download_url' => $this->file_path ? url('/api/v1/orders/'.$this->order->public_id.'/results/'.$this->public_id.'/download') : null];
    }
}
