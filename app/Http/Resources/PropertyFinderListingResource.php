<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyFinderListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        // FIX: was checking $request->user()->is_admin which doesn't exist
        // Use hasRole() from Spatie Permission instead
        $isAdmin = $user?->hasRole(['admin', 'superadmin']) ?? false;

        return [
            // ── Core Identifiers ─────────────────────────────────────────────────
            'id'              => $this->id,
            'pf_id'           => $this->pf_id,
            'pf_reference'    => $this->pf_reference,
            'pf_listing_url'  => $this->pf_listing_url,

            // ── Location & Emirates ──────────────────────────────────────────────
            'emirate_id'      => $this->emirate_id,
            'emirate'         => $this->emirate,
            'location_id'     => $this->location_id,
            'pf_location_name' => $this->pf_location_name,
            'pf_city'         => $this->pf_city,
            'pf_community'    => $this->pf_community,
            'pf_subcommunity' => $this->pf_subcommunity,
            'pf_building'     => $this->pf_building,
            'uae_emirate'     => $this->uae_emirate,
            'latitude'        => $this->latitude,
            'longitude'       => $this->longitude,

            // ── Permits & Compliance ─────────────────────────────────────────────
            'permit_number'       => $this->permit_number,
            'license_number'      => $this->license_number,
            'building_name'       => $this->building_name,
            'dld_permit_number'   => $this->when($isAdmin, $this->dld_permit_number),

            // ── Classification ───────────────────────────────────────────────────
            'listing_type'    => $this->listing_type,
            'category'        => $this->category,
            'property_type'   => $this->property_type,
            'project_status'  => $this->project_status,

            // ── Pricing ──────────────────────────────────────────────────────────
            'price' => [
                'value'           => (float) $this->price,
                'currency'        => $this->price_currency ?? 'AED',
                'formatted'       => ($this->price_currency ?? 'AED') . ' ' . number_format((float) $this->price, 2),
                'on_request'      => (bool) $this->price_on_request,
            ],
            'price_currency'  => $this->price_currency ?? 'AED',
            'price_on_request' => (bool) $this->price_on_request,
            'ownership_type'  => $this->ownership_type,

            // ── Content ──────────────────────────────────────────────────────────
            'title'           => $this->title_en,
            'title_ar'        => $this->title_ar,
            'description'     => $this->description_en,
            'description_ar'  => $this->description_ar,

            // ── Property Specifications ──────────────────────────────────────────
            'specifications' => [
                'bedrooms'        => $this->bedrooms,
                'bathrooms'       => $this->bathrooms,
                'size_sqft'       => $this->size ? (float) $this->size : null,
                'plot_size_sqft'  => $this->plot_size_sqft ? (float) $this->plot_size_sqft : null,
                'floor_number'    => $this->floor_number,
                'number_of_floors' => $this->number_of_floors,
                'private_pool'    => (bool) $this->private_pool,
                'hotel_name'      => $this->hotel_name,
                'parking'         => $this->parking,
                'furnished'       => $this->furnished,
                'fitted'          => $this->fitted,
                'zoning_type'     => $this->zoning_type,
            ],

            // ── Rental fields (only shown for rent listings) ─────────────────────
            'rental' => $this->when($this->listing_type === 'rent' || $this->purpose === 'rent', [
                'rent_frequency'  => $this->rent_frequency,
                'cheques'         => $this->cheques,
                'available_from'  => $this->available_from?->toDateString(),
            ]),

            // ── Off-Plan fields (only shown for off_plan category) ───────────────
            'off_plan' => $this->when($this->category === 'off_plan', [
                'developer_name'  => $this->developer_name,
                'project_name'    => $this->project_name,
                'completion_date' => $this->completion_date?->toDateString(),
                'payment_plan'    => $this->payment_plan,
            ]),

            // ── Media ────────────────────────────────────────────────────────────
            'images'          => $this->images ?? [],
            'amenities'       => $this->amenities ?? [],
            'virtual_tour'    => $this->virtual_tour,
            'floor_plan'      => $this->floor_plan,

            // ── Status & Compliance ──────────────────────────────────────────────
            'status'          => $this->status,
            'is_compliant'    => $this->isCompliant(),
            'can_publish'     => $this->canPublish(),
            'unpublish_reason' => $this->unpublish_reason,
            'validation_diffs' => $this->validation_diffs ?? [],

            // ── Portals ──────────────────────────────────────────────────────────
            'portals' => [
                'pf'       => (bool) ($this->portal_pf ?? true), // Default true for legacy rows
                'bayut'    => (bool) $this->portal_bayut,
                'dubizzle' => (bool) $this->portal_dubizzle,
                'website'  => (bool) $this->portal_website,
            ],

            // Compliance snapshot only visible to admins (contains permit details)
            'compliance_snapshot' => $this->when($isAdmin, $this->compliance_snapshot),

            // ── Timestamps ────────────────────────────────────────────────────────
            'published_at'             => $this->published_at?->toIso8601String(),
            'last_compliance_check_at' => $this->last_compliance_check_at?->toIso8601String(),
            'created_at'               => $this->created_at->toIso8601String(),
            'updated_at'               => $this->updated_at->toIso8601String(),
            'agent_id'                 => $this->agent_id,

            // ── Relationships ──────────────────────────────────────────────────
            'agent' => $this->whenLoaded('agent', fn () => [
                'id'          => $this->agent->id,
                'name'        => $this->agent->name,
                'email'       => $this->agent->email,
                'pf_agent_id' => $this->agent->pf_agent_id,
                'is_active'   => (bool) $this->agent->is_active,
            ]),

            'company' => $this->whenLoaded('company', fn () => [
                'id'   => $this->company->id,
                'name' => $this->company->name,
            ]),
        ];
    }
}