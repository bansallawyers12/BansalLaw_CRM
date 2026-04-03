<?php

namespace App\Helpers;

class PhoneValidationHelper
{
    /**
     * Standard phone number validation regex
     * Allows 9-10 digits for Australian numbers (with/without leading 0)
     * Allows up to 15 digits for international numbers
     */
    const PHONE_REGEX = '/^[0-9]{9,15}$/';
    
    /**
     * Placeholder number pattern
     */
    const PLACEHOLDER_PATTERN = '/^4444444444/';
    
    /**
     * Australian number pattern
     */
    const AUSTRALIAN_PATTERN = '/^\+61/';

    /**
     * Validate phone number format
     */
    public static function validatePhoneNumber($phone)
    {
        if (empty($phone)) {
            return [
                'valid' => false,
                'message' => 'Phone number is required'
            ];
        }

        // Remove any non-digit characters for validation
        $cleaned = preg_replace('/[^\d]/', '', $phone);

        // Check if it's a placeholder number (allow it)
        if (self::isPlaceholderNumber($cleaned)) {
            return [
                'valid' => true,
                'message' => 'Placeholder number detected',
                'is_placeholder' => true
            ];
        }

        // Check length - support 9-10 digits for AU numbers
        if (strlen($cleaned) < 9) {
            return [
                'valid' => false,
                'message' => 'Phone number must be at least 9 digits'
            ];
        }

        if (strlen($cleaned) > 15) {
            return [
                'valid' => false,
                'message' => 'Phone number must not exceed 15 digits'
            ];
        }

        // Check if it contains only digits
        if (!preg_match(self::PHONE_REGEX, $cleaned)) {
            return [
                'valid' => false,
                'message' => 'Phone number must contain only digits'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Valid phone number',
            'is_placeholder' => false
        ];
    }

    /**
     * Check if phone number is a placeholder
     */
    public static function isPlaceholderNumber($phone)
    {
        $cleaned = preg_replace('/[^\d]/', '', $phone);
        return preg_match(self::PLACEHOLDER_PATTERN, $cleaned);
    }

    /**
     * Check if phone number is Australian
     */
    public static function isAustralianNumber($phone)
    {
        return preg_match(self::AUSTRALIAN_PATTERN, $phone);
    }

    /**
     * Check if phone number can be verified
     */
    public static function canVerify($phone, $countryCode = null)
    {
        // Can't verify placeholder numbers
        if (self::isPlaceholderNumber($phone)) {
            return false;
        }

        // Can only verify Australian numbers
        if ($countryCode) {
            return $countryCode === '+61';
        }

        return self::isAustralianNumber($phone);
    }

    /**
     * Sanitize phone number for storage
     */
    public static function sanitizePhoneNumber($phone)
    {
        // Remove any non-digit characters except +
        $sanitized = preg_replace('/[^\d+]/', '', $phone);
        
        // Remove leading + if present for storage
        return ltrim($sanitized, '+');
    }

    /**
     * Format phone number for display
     */
    public static function formatForDisplay($phone, $countryCode = null)
    {
        if (self::isPlaceholderNumber($phone)) {
            return $phone; // Keep placeholder as-is
        }

        if ($countryCode) {
            return $countryCode . $phone;
        }

        return $phone;
    }

    /**
     * Format Australian phone number to standard display format
     * Mobile: 04XX XXX XXX
     * Landline: (0X) XXXX XXXX
     * 
     * @param string $phone The phone number
     * @param string|null $countryCode The country code (e.g., '+61')
     * @return string Formatted phone number
     */
    public static function formatAustralianPhone($phone, $countryCode = null)
    {
        // Check if it's a placeholder number - keep as-is
        if (self::isPlaceholderNumber($phone)) {
            return $phone;
        }

        // Remove any non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        
        // Check if it's an Australian number
        $isAustralian = false;
        $digitsOnly = '';
        
        // Handle country code
        if ($countryCode === '+61' || str_starts_with($cleaned, '+61')) {
            $isAustralian = true;
            // Remove +61 and any leading 0
            $digitsOnly = preg_replace('/^\+61/', '', $cleaned);
            $digitsOnly = preg_replace('/^0/', '', $digitsOnly);
        } elseif (preg_match('/^0?[23478]/', $cleaned)) {
            // Check if it starts with Australian area codes (02, 03, 04, 07, 08)
            $isAustralian = true;
            $digitsOnly = preg_replace('/[^\d]/', '', $cleaned);
        }
        
        // If not Australian, return as-is with country code
        if (!$isAustralian) {
            if ($countryCode) {
                return $countryCode . $cleaned;
            }
            return $cleaned;
        }
        
        // Ensure we have digits only for formatting
        if (empty($digitsOnly)) {
            $digitsOnly = preg_replace('/[^\d]/', '', $cleaned);
        }
        
        // Remove leading 0 if present (we'll add it back in the format)
        if (strlen($digitsOnly) > 0 && $digitsOnly[0] === '0') {
            $digitsOnly = substr($digitsOnly, 1);
        }
        
        // Must be 9 digits for Australian numbers
        if (strlen($digitsOnly) !== 9) {
            // If not 9 digits, return formatted with country code
            if ($countryCode) {
                return $countryCode . $digitsOnly;
            }
            return $cleaned;
        }
        
        // Determine if mobile (starts with 4) or landline (starts with 2, 3, 7, 8)
        $firstDigit = $digitsOnly[0];
        
        if ($firstDigit === '4') {
            // Mobile format: 0XXX XXX XXX (e.g., 0435 345 456)
            $firstPart = substr($digitsOnly, 0, 3); // First 3 digits (e.g., 435)
            $secondPart = substr($digitsOnly, 3, 3); // Next 3 digits (e.g., 345)
            $lastPart = substr($digitsOnly, 6, 3); // Last 3 digits (e.g., 456)
            return '0' . $firstPart . ' ' . $secondPart . ' ' . $lastPart;
        } elseif (in_array($firstDigit, ['2', '3', '7', '8'])) {
            // Landline format: (0X) XXXX XXXX
            $areaCode = substr($digitsOnly, 0, 1); // First digit (e.g., 2)
            $firstPart = substr($digitsOnly, 1, 4); // Next 4 digits (e.g., 9876)
            $lastPart = substr($digitsOnly, 5, 4); // Last 4 digits (e.g., 5432)
            return '(0' . $areaCode . ') ' . $firstPart . ' ' . $lastPart;
        }
        
        // Fallback: return with country code if provided
        if ($countryCode) {
            return $countryCode . '0' . $digitsOnly;
        }
        
        return '0' . $digitsOnly;
    }

    /**
     * Format phone number for SMS
     * Handles 9-10 digit Australian numbers:
     * - 0412345678 (10 digits with 0) → +61412345678
     * - 412345678 (9 digits without 0) → +61412345678
     * - 0298765432 (landline with 0) → +61298765432
     * - 298765432 (landline without 0) → +61298765432
     */
    public static function formatForSMS($phone, $countryCode = '+61')
    {
        if (self::isPlaceholderNumber($phone)) {
            return null; // Don't send SMS to placeholders
        }

        // Remove any non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        
        // If already has country code, return as is
        if (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }
        
        // Handle 9-10 digit numbers (assume Australian)
        if (strlen($cleaned) === 9) {
            // 9 digits without leading 0 - add +61
            return '+61' . $cleaned;
        } elseif (strlen($cleaned) === 10 && $cleaned[0] === '0') {
            // 10 digits with leading 0 - remove 0 and add +61
            return '+61' . substr($cleaned, 1);
        } elseif (strlen($cleaned) === 10) {
            // 10 digits without leading 0 - add +61
            return '+61' . $cleaned;
        }
        
        // For other lengths, use country code
        if ($countryCode && !str_starts_with($cleaned, '+')) {
            return $countryCode . $cleaned;
        }

        return $cleaned;
    }
    
    /**
     * Determine SMS provider based on phone number
     * Australian numbers (+61) → Cellcast
     * Other countries → Twilio
     */
    public static function getProviderForNumber($phone)
    {
        $formatted = self::formatForSMS($phone);
        
        if (!$formatted) {
            return null; // Placeholder numbers
        }
        
        return str_starts_with($formatted, '+61') ? 'cellcast' : 'twilio';
    }

    /**
     * Get validation rules for Laravel validation
     */
    public static function getValidationRules($field = 'phone')
    {
        return [
            $field => [
                'required',
                'string',
                'min:9',
                'max:15',
                function ($attribute, $value, $fail) {
                    $validation = self::validatePhoneNumber($value);
                    if (!$validation['valid']) {
                        $fail($validation['message']);
                    }
                },
            ]
        ];
    }

    /**
     * Get validation rules for array of phone numbers
     */
    public static function getArrayValidationRules($field = 'phone')
    {
        return [
            $field . '.*' => [
                'required',
                'string',
                'min:9',
                'max:15',
                function ($attribute, $value, $fail) {
                    $validation = self::validatePhoneNumber($value);
                    if (!$validation['valid']) {
                        $fail($validation['message']);
                    }
                },
            ]
        ];
    }

    /**
     * Validate phone number with custom error messages
     */
    public static function validateWithMessages($phone, $customMessages = [])
    {
        $validation = self::validatePhoneNumber($phone);
        
        if (!$validation['valid']) {
            $message = $customMessages[$validation['message']] ?? $validation['message'];
            return [
                'valid' => false,
                'message' => $message
            ];
        }

        return $validation;
    }

    /**
     * Check if phone number needs verification
     */
    public static function needsVerification($phone, $countryCode = null, $isVerified = false)
    {
        // Already verified
        if ($isVerified) {
            return false;
        }

        // Can't verify placeholder numbers
        if (self::isPlaceholderNumber($phone)) {
            return false;
        }

        // Can only verify Australian numbers
        return self::canVerify($phone, $countryCode);
    }
}
