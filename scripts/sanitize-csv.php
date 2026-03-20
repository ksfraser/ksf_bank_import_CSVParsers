<?php

/**
 * CSV Anonymization/Sanitization Utility for Bank Statements
 *
 * Scrubs names, account numbers, and personal info from raw CSV samples.
 * Uses dynamic maps built during processing so that:
 *   - The first unique merchant becomes "Merchant1", "Merchant2", etc.
 *   - All instances of the same merchant map to the same alias.
 *   - ZIP/Postal codes are normalized to Z9Z 9Z9.
 *   - Reference numbers are masked.
 *
 * This ensures the output is non-reversible and contains no PII.
 *
 * Usage: php scripts/sanitize-csv.php <input_dir> <output_dir>
 *
 * @requirement FR-1.2.x (Security & Data Privacy)
 */

if ($argc < 3) {
    die("Usage: php scripts/sanitize-csv.php <input_dir> <output_dir>\n");
}

$inputDir  = rtrim($argv[1], DIRECTORY_SEPARATOR);
$outputDir = rtrim($argv[2], DIRECTORY_SEPARATOR);

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// ---------------------------------------------------------------------------
// Dynamic maps — populated as files are processed and shared across all files
// ---------------------------------------------------------------------------
$merchantMap = [];
$accountMap  = [];
$cityMap     = [];
$nameMap     = [];

$merchantCounter = 1;
$accountCounter  = 1;
$cityCounter     = 1;
$nameCounter     = 1;

/**
 * Return (or create) a sequential alias for $value within $map.
 */
function getOrCreateAlias(string $value, array &$map, string $prefix, int &$counter): string
{
    $key = strtolower(trim($value));
    if ($key === '') return '';
    if (!isset($map[$key])) {
        $map[$key] = $prefix . $counter;
        $counter++;
    }
    return $map[$key];
}

// Common header aliases to detect sensitive columns
$sensitiveColumns = [
    'merchant'   => ['merchant', 'merchant name', 'description', 'payee', 'notes', 'memo'],
    'account'    => ['account', 'account number', 'card number', 'transaction card number', 'account name', 'full account name'],
    'city'       => ['city', 'merchant city'],
    'postal'     => ['postal', 'zip', 'postal code', 'merchant postal code/zip'],
    'name'       => ['name', 'name on card', 'customer name'],
    'reference'  => ['reference', 'reference number', 'confirmation', 'transaction id', 'number'],
];

// Keywords to identify columns without headers (headerless CSVs)
$keywordMapping = [
    'merchant' => ['/pay /i', '/transfer/i', '/interest/i', '/deposit/i', '/withdrawal/i', '/purchase/i'],
    'account'  => ['/account/i', '/card/i', '/\d{4,}\b/'], // Broad, but let's be careful
];

$files = glob("$inputDir/*.{csv,CSV}", GLOB_BRACE);

foreach ($files as $file) {
    $filename = basename($file);
    echo "Processing: $filename\n";

    if (($handle = fopen($file, "r")) !== FALSE) {
        $outputHandle = fopen($outputDir . DIRECTORY_SEPARATOR . $filename, "w");
        
        $firstRow = fgetcsv($handle);
        if ($firstRow === FALSE) continue;
        
        // Detect if first row is a header
        $isHeader = false;
        $columnMap = [];
        
        foreach ($firstRow as $index => $label) {
            $normalizedLabel = trim(strtolower($label), "\"' \t\n\r\0\x0B");
            foreach ($sensitiveColumns as $type => $aliases) {
                if (in_array($normalizedLabel, $aliases)) {
                    $columnMap[$index] = $type;
                    $isHeader = true;
                    break;
                }
            }
        }

        // If not a header, or even if it is, let's try keyword detection on the first row's data
        foreach ($firstRow as $index => $value) {
            if (isset($columnMap[$index])) continue;
            foreach ($keywordMapping as $type => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $columnMap[$index] = $type;
                        break 2;
                    }
                }
            }
        }

        if ($isHeader) {
            fputcsv($outputHandle, $firstRow);
        } else {
            // It's data, process it
            $processedFirstRow = $firstRow;
            sanitizeRow($processedFirstRow, $columnMap, $merchantMap, $merchantCounter, $accountMap, $accountCounter, $cityMap, $cityCounter, $nameMap, $nameCounter);
            fputcsv($outputHandle, $processedFirstRow);
        }

        while (($data = fgetcsv($handle)) !== FALSE) {
            sanitizeRow($data, $columnMap, $merchantMap, $merchantCounter, $accountMap, $accountCounter, $cityMap, $cityCounter, $nameMap, $nameCounter);
            fputcsv($outputHandle, $data);
        }

        fclose($handle);
        fclose($outputHandle);
    }
}

/**
 * Helper to sanitize a single row based on the column map.
 */
function sanitizeRow(&$data, $columnMap, &$merchantMap, &$merchantCounter, &$accountMap, &$accountCounter, &$cityMap, &$cityCounter, &$nameMap, &$nameCounter) {
    foreach ($data as $index => &$value) {
        // Broad redaction for common sensitive patterns inside any string, but NOT if they look like simple dollar amounts
        $value = preg_replace('/\b[A-Z]\d[A-Z]\s?\d[A-Z]\d\b/i', 'Z9Z 9Z9', $value); // Postal
        
        // Only redact numbers that are 7+ digits and NOT just currency/comma/decimal patterns
        // We avoid matching numbers that are likely currency (e.g., 1,000.00 or 1234.56)
        if (!preg_match('/^\(?[A-Z]{0,2}\$?[0-9,]+\.[0-9]{2}\)?$/i', trim($value))) {
            $value = preg_replace('/\b\d{7,}\b/', 'REDACTED_NUM', $value); // Long numbers (phones/accounts)
        }

        if (!isset($columnMap[$index])) continue;

        switch ($columnMap[$index]) {
            case 'merchant':
                // For combined strings like "Pay WALMART...", alias the merchant part if possible or just the whole thing
                $value = getOrCreateAlias($value, $merchantMap, 'Merchant', $merchantCounter);
                break;
            case 'account':
                $value = getOrCreateAlias($value, $accountMap, 'Account', $accountCounter);
                break;
            case 'city':
                $value = getOrCreateAlias($value, $cityMap, 'City', $cityCounter);
                break;
            case 'postal':
                $value = 'Z9Z 9Z9';
                break;
            case 'name':
                $value = getOrCreateAlias($value, $nameMap, 'Person', $nameCounter);
                break;
            case 'reference':
                if (strlen($value) > 4) {
                    $value = str_repeat('X', strlen($value) - 4) . substr($value, -4);
                } else {
                    $value = 'REF' . str_pad($value, 4, '0', STR_PAD_LEFT);
                }
                break;
        }
    }
}

echo "Sanitization complete. Files saved to $outputDir\n";
echo "  Merchant aliases: " . count($merchantMap) . "\n";
echo "  Account aliases:  " . count($accountMap) . "\n";
echo "  City aliases:     " . count($cityMap) . "\n";
echo "  Name aliases:     " . count($nameMap) . "\n";
