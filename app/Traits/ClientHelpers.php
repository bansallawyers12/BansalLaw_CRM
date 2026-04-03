<?php

namespace App\Traits;

trait ClientHelpers
{
    /**
     * Get next counter for client ID generation
     *
     * @param string $currentCounter
     * @return string
     */
    public function getNextCounter($currentCounter) {
        // Convert current counter to an integer
        $counter = intval($currentCounter);

        // Increment the counter
        $counter++;

        // If the counter exceeds 99999, reset it to 1
        if ($counter > 99999) {
            $counter = 1;
        }

        // Format the counter as a 5-digit number with leading zeros
        return str_pad($counter, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Format phone number for display
     *
     * @param string $countryCode
     * @param string $phone
     * @return string
     */
    protected function formatPhoneNumber($countryCode, $phone)
    {
        if (empty($phone)) {
            return '';
        }
        
        return !empty($countryCode) ? $countryCode . $phone : $phone;
    }

    /**
     * Build full name from first and last name
     *
     * @param string $firstName
     * @param string $lastName
     * @return string
     */
    protected function buildFullName($firstName, $lastName)
    {
        return trim($firstName . ' ' . $lastName);
    }
}

