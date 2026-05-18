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
            // ── Identity ──────────────────────────────────────────────────────
            'id'    => $this->id,
            'name'  => $this->title,
            'title' => $this->title,

            // ── Developer ─────────────────────────────────────────────────────
            'developer' => $this->whenLoaded('developer', fn() => [
                'id'       => $this->developer->source_id,
                'name'     => $this->developer->name,
                'logo_url' => $this->developer->logo,
            ]),

            // ── Price ─────────────────────────────────────────────────────────
            'price' => [
                'min' => (float) $this->price,
                'max' => (float) ($this->price_max ?? $this->price),
            ],

            // ── Type / Category ───────────────────────────────────────────────
            'category' => [
                'main' => $this->type_main,
                'sub'  => $this->type_sub,
            ],
            'type' => [
                'main' => $this->type_main,
                'sub'  => $this->type_sub,
            ],

            // ── Area ─────────────────────────────────────────────────────────
            'area' => [
                'min'      => $this->area_min,
                'max'      => $this->area_max,
                'built_up' => $this->area_built_up,
                'unit'     => 'sqft',
            ],

            // ── Bedrooms / Rooms ──────────────────────────────────────────────
            'rooms'    => $this->rooms ?? ($this->bedrooms !== null ? [$this->bedrooms] : []),
            'bedrooms' => $this->bedrooms,

            // ── Units ────────────────────────────────────────────────────────
            'units_count' => $this->units_count,

            // ── Location ─────────────────────────────────────────────────────
            'location' => [
                'city'          => ['name' => $this->location->city ?? null],
                'community'     => ['name' => $this->location->community ?? null],
                'sub_community' => ['name' => $this->location->sub_community ?? null],
                'coordinates'   => [
                    'lat' => $this->location->lat ?? null,
                    'lng' => $this->location->lng ?? null,
                ],
            ],

            // ── Media ────────────────────────────────────────────────────────
            'media' => [
                'cover_photo' => $photos->first(),
                'photos'      => $photos->values(),
            ],

            // ── Documents ────────────────────────────────────────────────────
            'documents' => $this->documents ?? [],

            // ── Status ───────────────────────────────────────────────────────
            'status' => [
                'completion_status' => $this->completion_status ?? $this->purpose,
                'completion_date'   => $this->completion_date?->format('Y-m-d'),
            ],
            'completion_status' => $this->completion_status ?? $this->purpose,

            // ── Payment Plan ─────────────────────────────────────────────────
            'payment_plan' => $this->payment_plans ?? [],

            // ── Amenities ────────────────────────────────────────────────────
            'amenities' => $this->amenities ?? [],

            // ── Analytics ────────────────────────────────────────────────────
            'analytics' => [
                'score'                 => $this->investment_score,
                'dldAvgPriceSqft'       => $this->dld_avg_price_sqft,
                'dldTransactionsCount'  => $this->dld_transactions_count,
                'estimatedYield'        => $this->estimated_yield,
            ],

            // ── Timestamps ───────────────────────────────────────────────────
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}