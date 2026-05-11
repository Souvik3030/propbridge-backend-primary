<?php

namespace App\Services\PropertyFinder;

class EmiratePermitService
{
    public function requiresPermit(int $emirateId, ?string $locationName = null): bool
    {
        $emirate = config('propertyfinder.emirates.'.$emirateId);

        if (!$emirate || !($emirate['requires_permit'] ?? false)) {
            return false;
        }

        // Check exempt areas for Dubai (DIFC, JAFZA)
        if ($locationName && !empty($emirate['exempt_areas'])) {
            foreach ($emirate['exempt_areas'] as $area) {
                if (stripos($locationName, $area) !== false) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getPermitLabel(int $emirateId): string
    {
        return match($emirateId) {
            1 => 'RERA Permit Number',
            2 => 'ADREC Permit Number',
            default => 'Permit Number',
        };
    }
}
