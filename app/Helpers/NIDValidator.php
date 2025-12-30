<?php

namespace App\Helpers;

use App\Models\Worker;

class NIDValidator
{
    /**
     * Validate NID format
     * Supports: 10-digit (old), 13-digit (new), or 17-digit (new) Bangladesh NID
     * 
     * @param string $nid
     * @return array ['valid' => bool, 'message' => string, 'type' => string|null, 'length' => int|null]
     */
    public static function validate(string $nid): array
    {
        if (empty($nid)) {
            return [
                'valid' => false,
                'message' => 'NID is required',
                'type' => null,
                'length' => null
            ];
        }

        // Remove whitespace
        $cleaned = preg_replace('/\s+/', '', $nid);

        // Check if contains only digits
        if (!preg_match('/^\d+$/', $cleaned)) {
            return [
                'valid' => false,
                'message' => 'NID must contain only digits',
                'type' => null,
                'length' => null
            ];
        }

        $length = strlen($cleaned);

        // Validate length
        if (!in_array($length, [10, 13, 17])) {
            return [
                'valid' => false,
                'message' => 'NID must be 10, 13, or 17 digits long',
                'type' => null,
                'length' => $length
            ];
        }

        $type = $length === 10 ? 'old' : 'new';

        return [
            'valid' => true,
            'message' => 'Valid NID format',
            'type' => $type,
            'length' => $length
        ];
    }

    /**
     * Check if NID is unique
     * 
     * @param string $nid
     * @param int|null $excludeWorkerId Worker ID to exclude from check (for updates)
     * @return bool
     */
    public static function isUnique(string $nid, ?int $excludeWorkerId = null): bool
    {
        $cleaned = preg_replace('/\s+/', '', $nid);
        
        $query = Worker::where('nid', $cleaned);
        
        if ($excludeWorkerId) {
            $query->where('id', '!=', $excludeWorkerId);
        }
        
        return !$query->exists();
    }

    /**
     * Extract birth year from new NID format (13 or 17 digits)
     * 
     * @param string $nid
     * @return int|null Birth year or null if unable to extract
     */
    public static function extractBirthYear(string $nid): ?int
    {
        $cleaned = preg_replace('/\s+/', '', $nid);
        $length = strlen($cleaned);

        if (in_array($length, [13, 17])) {
            // First 4 digits represent birth year
            $birthYear = (int) substr($cleaned, 0, 4);
            
            // Validate year range (reasonable birth years)
            if ($birthYear >= 1900 && $birthYear <= date('Y')) {
                return $birthYear;
            }
        }

        return null;
    }

    /**
     * Validate age consistency with NID
     * 
     * @param string $nid
     * @param int $age
     * @param int $tolerance Allowable difference in years (default: 3)
     * @return array ['valid' => bool, 'message' => string, 'calculated_age' => int|null]
     */
    public static function validateAgeConsistency(string $nid, int $age, int $tolerance = 2): array
    {
        $birthYear = self::extractBirthYear($nid);

        if (!$birthYear) {
            return [
                'valid' => true,
                'message' => 'Cannot extract birth year from old NID format',
                'calculated_age' => null
            ];
        }

        $currentYear = (int) date('Y');
        $calculatedAge = $currentYear - $birthYear;
        
        // Account for whether birthday has passed this year
        // NID birth year doesn't include month/day, so we allow a range
        // Minimum age (if birthday hasn't happened yet this year) - subtract 1 for tolerance
        $minAge = $calculatedAge - 1 - $tolerance;
        // Maximum age (if birthday already happened this year) - add tolerance
        $maxAge = $calculatedAge + $tolerance;
        
        // Check if provided age falls within the valid range (with tolerance)
        $isValid = ($age >= $minAge) && ($age <= $maxAge);
        $difference = min(abs($age - ($calculatedAge - 1)), abs($age - $calculatedAge));

        if (!$isValid) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'Age mismatch: NID (birth year %d) suggests age between %d-%d years, but %d was provided',
                    $birthYear,
                    $minAge - $tolerance,
                    $maxAge + $tolerance,
                    $age
                ),
                'calculated_age' => $calculatedAge
            ];
        }

        return [
            'valid' => true,
            'message' => 'Age is consistent with NID',
            'calculated_age' => $calculatedAge
        ];
    }
}

