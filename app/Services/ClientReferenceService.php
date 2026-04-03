<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Centralized service for generating unique client references
 * Fixes race condition by using database-level locking
 */
class ClientReferenceService
{
    /**
     * Generate a unique client reference with proper locking to prevent duplicates
     * 
     * Format: [FIRST_4_LETTERS][YEAR][COUNTER]
     * Example: JOHN2500337
     *
     * @param string $firstName Client's first name
     * @return array ['client_id' => 'JOHN2500337', 'client_counter' => '00337']
     * @throws \Exception if unable to generate reference after retries
     */
    public function generateClientReference(string $firstName): array
    {
        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                return $this->generateReferenceWithLock($firstName);
            } catch (\Exception $e) {
                $attempt++;
                
                if ($attempt >= $maxRetries) {
                    Log::error('Failed to generate client reference after ' . $maxRetries . ' attempts', [
                        'first_name' => $firstName,
                        'error' => $e->getMessage()
                    ]);
                    throw new \Exception('Unable to generate client reference. Please try again.');
                }
                
                // Brief wait before retry (exponential backoff)
                usleep(100000 * $attempt); // 100ms, 200ms, 300ms
            }
        }
    }

    /**
     * Generate reference with database-level pessimistic locking
     * 
     * Note: This method relies on the calling code to save the generated
     * counter to the database within the same transaction. The lockForUpdate()
     * ensures that concurrent requests wait until the counter is committed.
     * 
     * @param string $firstName
     * @return array
     */
    protected function generateReferenceWithLock(string $firstName): array
    {
        // Use FOR UPDATE lock to prevent race conditions
        // This blocks other transactions from reading the same row until we commit
        // IMPORTANT: The caller MUST be in a transaction and MUST save the Admin
        // record with this counter before committing
        
        $latestClient = DB::table('admins')
            ->select('client_counter')
            ->whereIn('type', ['client', 'lead'])
            ->whereNotNull('client_counter')
            ->orderBy('client_counter', 'desc') // Order by counter, not timestamp
            ->lockForUpdate() // CRITICAL: Locks the row until transaction commits
            ->first();

        // Get the latest counter value
        $currentCounter = $latestClient ? $latestClient->client_counter : '00000';

        // Increment to next counter
        $nextCounter = $this->incrementCounter($currentCounter);

        // Generate the prefix from first name
        $prefix = $this->generatePrefix($firstName);

        // Generate the year component
        $year = date('y'); // 25 for 2025

        // Combine to create client_id
        $clientId = $prefix . $year . $nextCounter;

        Log::info('Generated client reference', [
            'client_id' => $clientId,
            'counter' => $nextCounter,
            'prefix' => $prefix
        ]);

        return [
            'client_id' => $clientId,
            'client_counter' => $nextCounter
        ];
    }

    /**
     * Increment counter with proper zero-padding
     * 
     * @param string $currentCounter
     * @return string
     */
    protected function incrementCounter(string $currentCounter): string
    {
        // Convert to integer and increment
        $counter = intval($currentCounter) + 1;

        // Reset to 1 if exceeds 99999
        if ($counter > 99999) {
            $counter = 1;
            Log::warning('Client counter exceeded 99999, resetting to 1');
        }

        // Return as 5-digit zero-padded string
        return str_pad($counter, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Generate prefix from first name (first 4 letters, uppercase)
     * 
     * @param string $firstName
     * @return string
     */
    protected function generatePrefix(string $firstName): string
    {
        // Remove any non-alphabetic characters
        $cleaned = preg_replace('/[^A-Za-z]/', '', $firstName);
        
        // Take first 4 characters (or less if name is shorter)
        $prefix = strlen($cleaned) >= 4 
            ? substr($cleaned, 0, 4) 
            : $cleaned;

        return strtoupper($prefix);
    }

    /**
     * Validate if a client_id already exists
     * 
     * @param string $clientId
     * @return bool
     */
    public function clientIdExists(string $clientId): bool
    {
        return DB::table('admins')
            ->where('client_id', $clientId)
            ->exists();
    }

    /**
     * Get current counter without locking (for display/reporting only)
     * 
     * @return string
     */
    public function getCurrentCounter(): string
    {
        $latestClient = DB::table('admins')
            ->select('client_counter')
            ->whereIn('type', ['client', 'lead'])
            ->whereNotNull('client_counter')
            ->orderBy('client_counter', 'desc')
            ->first();

        return $latestClient ? $latestClient->client_counter : '00000';
    }
}

