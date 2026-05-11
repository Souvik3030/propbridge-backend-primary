<?php

declare(strict_types=1);

namespace App\Http\Requests\PropertyFinder;

use Illuminate\Validation\Rule;

/**
 * Validation for updating an existing PropertyFinder listing.
 *
 * All fields are optional (PATCH = partial update per PF API docs).
 *
 * IMPORTANT: The images field is a FULL REPLACE when provided.
 * Always include ALL images (existing + new) in the array.
 */
class UpdateListingRequest extends StoreListingRequest
{
    public function authorize(): bool
    {
        $listing = $this->route('listing');
        return $this->user()->can('update', $listing);
    }

    public function rules(): array
    {
        // Get parent rules (all the conditional required_if rules)
        $rules = parent::rules();

        // Make ALL rules "sometimes" for partial updates
        // This preserves the conditional rules (required_if, etc.) but
        // only triggers them if the field is actually present in the request
        foreach ($rules as $field => $fieldRules) {
            if (!is_array($fieldRules)) {
                $rules[$field] = ['sometimes', $fieldRules];
                continue;
            }

            // Don't prepend 'sometimes' to wildcard rules (images.*, amenities.*)
            if (str_contains($field, '*')) {
                continue;
            }

            // Only add 'sometimes' if not already there
            if (!in_array('sometimes', $fieldRules, true)) {
                array_unshift($rules[$field], 'sometimes');
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'images.min' => 'When updating images, you must provide the complete list (all images including existing ones). This field is a full replace.',
        ]);
    }
}