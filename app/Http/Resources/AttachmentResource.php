<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'purpose' => $this->purpose, 'file_name' => $this->file_name, 'mime_type' => $this->mime_type, 'file_size' => $this->file_size, 'sha256' => $this->sha256, 'created_at' => $this->created_at->toIso8601String(), 'download_url' => url('/api/v1/orders/'.$this->order->public_id.'/attachments/'.$this->public_id.'/download')];
    }
}
