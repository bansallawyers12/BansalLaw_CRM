<?php

namespace App\Services;

use App\Models\AnzscoOccupation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AnzscoImportService
{
    protected $errors = [];
    protected $warnings = [];
    protected $stats = [
        'total' => 0,
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0
    ];

    /**
     * Import data from CSV or Excel file
     *
     * @param string $filePath
     * @param array $columnMapping
     * @param bool $updateExisting
     * @return array
     */
    public function import($filePath, $columnMapping, $updateExisting = true)
    {
        $this->resetStats();
        
        try {
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            
            if (in_array(strtolower($extension), ['csv'])) {
                $data = $this->parseCsv($filePath);
            } elseif (in_array(strtolower($extension), ['xlsx', 'xls'])) {
                $data = $this->parseExcel($filePath);
            } else {
                throw new \Exception("Unsupported file format: {$extension}");
            }

            return $this->processData($data, $columnMapping, $updateExisting);

        } catch (\Exception $e) {
            Log::error('ANZSCO Import Error: ' . $e->getMessage());
            $this->errors[] = $e->getMessage();
            return $this->getResults();
        }
    }

    /**
     * Parse CSV file
     */
    protected function parseCsv($filePath)
    {
        $data = [];
        $handle = fopen($filePath, 'r');
        
        if ($handle !== false) {
            // Get headers from first row
            $headers = fgetcsv($handle);
            
            // Read data rows
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) === count($headers)) {
                    $data[] = array_combine($headers, $row);
                }
            }
            fclose($handle);
        }
        
        return $data;
    }

    /**
     * Parse Excel file
     */
    protected function parseExcel($filePath)
    {
        $data = [];
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (count($rows) > 0) {
                $headers = array_shift($rows); // First row as headers
                
                foreach ($rows as $row) {
                    if (count($row) === count($headers)) {
                        $data[] = array_combine($headers, $row);
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \Exception("Error parsing Excel file: " . $e->getMessage());
        }
        
        return $data;
    }

    /**
     * Process imported data
     */
    protected function processData($data, $columnMapping, $updateExisting)
    {
        $this->stats['total'] = count($data);
        
        DB::beginTransaction();
        
        try {
            foreach ($data as $index => $row) {
                $rowNumber = $index + 2; // +2 because index starts at 0 and we skip header
                
                // Map columns according to user's mapping
                $mappedData = $this->mapRowData($row, $columnMapping);
                
                // Validate the data
                $validation = $this->validateRow($mappedData, $rowNumber);
                
                if (!$validation['valid']) {
                    $this->stats['errors']++;
                    continue;
                }
                
                // Check if occupation exists
                $existing = AnzscoOccupation::where('anzsco_code', $mappedData['anzsco_code'])->first();
                
                if ($existing) {
                    if ($updateExisting) {
                        // MERGE list flags instead of replacing them
                        // If a flag is already TRUE, keep it TRUE (don't overwrite with FALSE)
                        $mergedData = $mappedData;
                        $mergedData['is_on_mltssl'] = $existing->is_on_mltssl || $mappedData['is_on_mltssl'];
                        $mergedData['is_on_stsol'] = $existing->is_on_stsol || $mappedData['is_on_stsol'];
                        $mergedData['is_on_rol'] = $existing->is_on_rol || $mappedData['is_on_rol'];
                        $mergedData['is_on_csol'] = $existing->is_on_csol || $mappedData['is_on_csol'];
                        
                        $existing->update($mergedData);
                        $this->stats['updated']++;
                    } else {
                        $this->warnings[] = "Row {$rowNumber}: ANZSCO code {$mappedData['anzsco_code']} already exists. Skipped.";
                        $this->stats['skipped']++;
                    }
                } else {
                    AnzscoOccupation::create($mappedData);
                    $this->stats['inserted']++;
                }
            }
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->errors[] = "Database error: " . $e->getMessage();
            Log::error('ANZSCO Import DB Error: ' . $e->getMessage());
        }
        
        return $this->getResults();
    }

    /**
     * Map row data according to column mapping
     */
    protected function mapRowData($row, $columnMapping)
    {
        $mapped = [
            'is_on_mltssl' => false,
            'is_on_stsol' => false,
            'is_on_rol' => false,
            'is_on_csol' => false,
            'assessment_validity_years' => 3, // Default
            'is_active' => true
        ];
        
        foreach ($columnMapping as $dbColumn => $fileColumn) {
            if (isset($row[$fileColumn])) {
                $value = $row[$fileColumn];
                
                // Handle boolean fields
                if (in_array($dbColumn, ['is_on_mltssl', 'is_on_stsol', 'is_on_rol', 'is_on_csol', 'is_active'])) {
                    $mapped[$dbColumn] = $this->parseBoolean($value);
                }
                // Handle integer fields
                elseif (in_array($dbColumn, ['skill_level'])) {
                    $mapped[$dbColumn] = !empty($value) ? (int)$value : null;
                }
                // Handle assessment_validity_years with default
                elseif ($dbColumn === 'assessment_validity_years') {
                    $mapped[$dbColumn] = !empty($value) ? (int)$value : 3; // Default to 3 years
                }
                // Handle string fields
                else {
                    $mapped[$dbColumn] = !empty($value) ? trim($value) : null;
                }
            }
        }
        
        return $mapped;
    }

    /**
     * Parse boolean values from various formats
     */
    protected function parseBoolean($value)
    {
        if (is_bool($value)) return $value;
        
        $value = strtolower(trim($value));
        return in_array($value, ['1', 'yes', 'true', 'y', 'on', 'x']);
    }

    /**
     * Validate row data
     */
    protected function validateRow($data, $rowNumber)
    {
        $validator = Validator::make($data, [
            'anzsco_code' => 'required|string|max:10',
            'occupation_title' => 'required|string|max:255',
            'skill_level' => 'nullable|integer|between:1,5',
            'assessing_authority' => 'nullable|string|max:255',
            'assessment_validity_years' => 'nullable|integer|min:1|max:10',
        ]);
        
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->errors[] = "Row {$rowNumber}: {$error}";
            }
            return ['valid' => false];
        }
        
        return ['valid' => true];
    }

    /**
     * Generate CSV template for import
     */
    public function generateTemplate()
    {
        $headers = [
            'anzsco_code',
            'occupation_title',
            'skill_level',
            'mltssl',
            'stsol',
            'rol',
            'csol',
            'assessing_authority',
            'validity_years',
            'additional_info',
            'alternate_titles'
        ];
        
        $sampleData = [
            [
                '261313',
                'Software Engineer',
                '1',
                'Yes',
                'No',
                'Yes',
                'No',
                'ACS',
                '3',
                'ICT Professional',
                'Developer, Programmer'
            ],
            [
                '351311',
                'Chef',
                '3',
                'Yes',
                'Yes',
                'Yes',
                'No',
                'TRA',
                '3',
                'Trade qualification required',
                'Cook, Head Chef'
            ]
        ];
        
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, $headers);
        
        foreach ($sampleData as $row) {
            fputcsv($csv, $row);
        }
        
        rewind($csv);
        $output = stream_get_contents($csv);
        fclose($csv);
        
        return $output;
    }

    /**
     * Reset statistics
     */
    protected function resetStats()
    {
        $this->errors = [];
        $this->warnings = [];
        $this->stats = [
            'total' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        ];
    }

    /**
     * Get import results
     */
    protected function getResults()
    {
        return [
            'success' => $this->stats['errors'] < $this->stats['total'],
            'stats' => $this->stats,
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }

    /**
     * Get errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get warnings
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Get statistics
     */
    public function getStats()
    {
        return $this->stats;
    }
}

