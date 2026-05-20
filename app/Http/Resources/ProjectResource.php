<?php
declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $photos = $this->images->pluck('url')->values();
        $raw = $this->raw_payload ?? [];

        return [
            'id' => $this->id,
            'source' => $this->source,
            'source_id' => $this->source_id,
            'reference_number' => $this->reference_number,
            'name' => $this->title,
            'title' => $this->title,
            'title_ar' => $this->title_ar,
            'description' => $this->description,
            'purpose' => $this->purpose,

            'type' => [
                'main' => $this->type_main,
                'sub' => $this->type_sub,
                'main_ar' => data_get($raw, 'type.main_ar'),
                'sub_ar' => data_get($raw, 'type.sub_ar'),
            ],
            'category' => [
                'main' => $this->type_main,
                'sub' => $this->type_sub,
            ],

            'price' => [
                'min' => $this->price !== null ? (float) $this->price : null,
                'max' => $this->price_max !== null ? (float) $this->price_max : ($this->price !== null ? (float) $this->price : null),
                'value' => $this->price !== null ? (float) $this->price : null,
            ],
            'area' => [
                'min' => $this->area_min,
                'max' => $this->area_max,
                'built_up' => $this->area_built_up,
                'plot' => data_get($raw, 'area.plot'),
                'unit' => $this->area_unit ?: 'sqft',
            ],

            'details' => [
                'bedrooms' => $this->bedrooms,
                'bathrooms' => $this->bathrooms,
                'is_furnished' => $this->is_furnished,
                'completion_status' => $this->completion_status,
            ],
            'rooms' => $this->rooms ?? ($this->bedrooms !== null ? [$this->bedrooms] : []),
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'units_count' => $this->units_count,

            'completion_details' => [
                'percentage' => data_get($raw, 'details.completion_details.percentage', data_get($raw, 'completion_details.percentage')),
                'start_date' => data_get($raw, 'details.completion_details.start_date', data_get($raw, 'completion_details.start_date')),
                'completion_date' => data_get($raw, 'details.completion_details.completion_date')
                    ?: data_get($raw, 'completion_details.completion_date')
                    ?: $this->completion_date?->toDateString(),
            ],
            'status' => [
                'completion_status' => $this->completion_status ?? $this->purpose,
                'completion_date' => $this->completion_date?->format('Y-m-d'),
            ],
            'completion_status' => $this->completion_status ?? $this->purpose,

            'location' => [
                'country' => [
                    'name' => $this->location->country ?? data_get($raw, 'location.country.name'),
                    'name_ar' => data_get($raw, 'location.country.name_ar'),
                    'id' => data_get($raw, 'location.country.id'),
                ],
                'city' => [
                    'name' => $this->location->city ?? data_get($raw, 'location.city.name'),
                    'name_ar' => data_get($raw, 'location.city.name_ar'),
                    'id' => data_get($raw, 'location.city.id'),
                ],
                'community' => [
                    'name' => $this->location->community ?? data_get($raw, 'location.community.name'),
                    'name_ar' => data_get($raw, 'location.community.name_ar'),
                    'id' => data_get($raw, 'location.community.id'),
                ],
                'sub_community' => [
                    'name' => $this->location->sub_community ?? data_get($raw, 'location.sub_community.name'),
                    'name_ar' => data_get($raw, 'location.sub_community.name_ar'),
                    'id' => data_get($raw, 'location.sub_community.id'),
                ],
                'cluster' => data_get($raw, 'location.cluster'),
                'coordinates' => [
                    'lat' => $this->location->lat ?? data_get($raw, 'location.coordinates.lat'),
                    'lng' => $this->location->lng ?? data_get($raw, 'location.coordinates.lng'),
                ],
            ],

            'developer' => $this->whenLoaded('developer', fn() => [
                'id' => $this->developer?->source_id,
                'name' => $this->developer?->name,
                'logo_url' => $this->developer?->logo,
            ]),
            'agency' => $this->agency_payload,
            'agent' => $this->agent_payload,

            'amenities' => $this->amenities ?? [],
            'amenities_ar' => $this->amenities_ar ?? [],
            'keywords' => $this->keywords ?? [],
            'keywords_ar' => $this->keywords_ar ?? [],
            'media' => [
                'cover_photo' => data_get($raw, 'media.cover_photo') ?: $photos->first(),
                'photo_count' => data_get($raw, 'media.photo_count', $photos->count()),
                'photos' => $photos,
                'video_count' => data_get($raw, 'media.video_count', 0),
                'panorama_count' => data_get($raw, 'media.panorama_count', 0),
                'cover_video' => data_get($raw, 'media.cover_video'),
            ],

            'verification' => $this->verification_payload,
            'legal' => $this->legal_payload ?: [
                'permit_number' => $this->permit_number,
            ],
            'project' => [
                'id' => data_get($raw, 'project.id'),
                'completion_status' => data_get($raw, 'project.completion_status', $this->completion_status),
                'developer' => data_get($raw, 'project.developer'),
                'payment_plans' => $this->payment_plans ?? data_get($raw, 'payment_plans', []),
            ],
            'payment_plan' => $this->payment_plans ?? [],
            'payment_plans' => $this->payment_plans ?? [],
            'documents' => $this->documents ?? [],
            'contact_methods' => data_get($raw, 'contact_methods', []),
            'offplan_details' => $this->offplan_payload,
            'meta' => [
                'created_at' => data_get($raw, 'meta.created_at'),
                'updated_at' => data_get($raw, 'meta.updated_at'),
                'reactivated_at' => data_get($raw, 'meta.reactivated_at'),
                'product' => data_get($raw, 'meta.product'),
                'url' => $this->bayut_url ?: data_get($raw, 'meta.url'),
                'product_label' => data_get($raw, 'meta.product_label'),
                'source_id' => data_get($raw, 'meta.source_id'),
            ],

            'analytics' => [
                'score' => $this->investment_score ?? data_get($raw, 'score'),
                'indy_score' => data_get($raw, 'indy_score'),
                'city_level_score' => data_get($raw, 'city_level_score'),
                'location_purpose_tier' => data_get($raw, 'location_purpose_tier'),
                'dldAvgPriceSqft' => $this->dld_avg_price_sqft,
                'dldTransactionsCount' => $this->dld_transactions_count,
                'estimatedYield' => $this->estimated_yield,
            ],

            'provider_payload' => $raw,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
