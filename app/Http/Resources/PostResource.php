<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'content' => $this->content,
            'user'    => $this->whenLoaded('user', fn() => [
                'id'       => $this->user->id,
                'username' => $this->user->username,
            ]),
            'media'   => $this->whenLoaded('media', function () {
                return $this->media->map(fn($m) => [
                    'id'         => $m->id,
                    'media_type' => $m->media_type,
                    'media_url'  => Storage::url($m->media_url), // dari PATH ke URL
                    'sort_order' => $m->sort_order,
                ]);
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
