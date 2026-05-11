<?php
declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $photos = $this->images->pluck('url');

        return [
            "id" => $this->source_id,
            "name" => $this->title,
            "price" => [
                "min" => $this->price,
                "max" => $this->price_max ?? $this->price 
            ],
            "rooms" => $this->bedrooms ? [$this->bedrooms] : [],
            "category" => [
                "main" => $this->type_main,
                "sub" => $this->type_sub,
            ],
            "location" => [
                "city" => ["name" => $this->location->city ?? null],
                "community" => ["name" => $this->location->community ?? null],
                "sub_community" => ["name" => $this->location->sub_community ?? null],
                "coordinates" => [
                    "lat" => $this->location->lat ?? null,
                    "lng" => $this->location->lng ?? null
                ]
            ],
            "developer" => $this->whenLoaded('developer', fn () => [
                "id" => $this->developer->source_id,
                "name" => $this->developer->name,
                "logo_url" => $this->developer->logo
            ]),
            
            "media" => [
                // 🚀 Optimized: Using the pre-extracted collection
                "cover_photo" => $photos->first(),
                "photos" => $photos->values()
            ],
            
            "amenities" => $this->amenities ?? [],
            "payment_plan" => $this->payment_plans ?? [],
            "completion_status" => $this->purpose ?? "under-construction",
        ];
    }
}