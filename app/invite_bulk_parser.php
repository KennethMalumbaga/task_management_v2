<?php

require_once __DIR__ . "/invite_helpers.php";

if (!function_exists('bulk_invite_parse_upload')) {
    function bulk_invite_parse_upload($tmpPath, $originalName)
    {
        $ext = strtolower(pathinfo((string)$originalName, PATHINFO_EXTENSION));
        if ($ext === '') {
            throw new RuntimeException("Uploaded file must have an extension (.csv, .xlsx, or .pdf).");
        }

        if ($ext === 'csv') {
            $rows = bulk_invite_parse_csv($tmpPath);
            return bulk_invite_extract_records($rows, 'csv');
        }

        if ($ext === 'xlsx') {
            $rows = bulk_invite_parse_xlsx($tmpPath);
            return bulk_invite_extract_records($rows, 'xlsx');
        }

        if ($ext === 'pdf') {
            return bulk_invite_parse_pdf($tmpPath);
        }

        if ($ext === 'xls') {
            throw new RuntimeException("Legacy .xls is not supported here. Save it as .xlsx or .csv, then upload again.");
        }

        throw new RuntimeException("Unsupported file type. Use .csv, .xlsx, or text-based .pdf.");
    }
}

if (!function_exists('bulk_invite_parse_csv')) {
    function bulk_invite_parse_csv($path)
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Failed to read CSV file.");
        }

        $firstLine = (string)fgets($handle);
        rewind($handle);

        $delims = [',', ';', "\t", '|'];
        $chosen = ',';
        $bestCount = -1;
        foreach ($delims as $delim) {
            $count = substr_count($firstLine, $delim);
            if ($count > $bestCount) {
                $bestCount = $count;
                $chosen = $delim;
            }
        }

        $rows = [];
        while (($cols = fgetcsv($handle, 0, $chosen)) !== false) {
            $rows[] = array_map('bulk_invite_clean_text', $cols);
        }
        fclose($handle);

        return $rows;
    }
}

if (!function_exists('bulk_invite_parse_xlsx')) {
    function bulk_invite_parse_xlsx($path)
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException("ZIP extension is required to parse .xlsx files.");
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Failed to open .xlsx file.");
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $shared = @simplexml_load_string($sharedXml);
            if ($shared && isset($shared->si)) {
                foreach ($shared->si as $si) {
                    $sharedStrings[] = bulk_invite_shared_string_to_text($si);
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException("No worksheet found in .xlsx file.");
        }

        $sheet = @simplexml_load_string($sheetXml);
        if (!$sheet || !isset($sheet->sheetData)) {
            $zip->close();
            throw new RuntimeException("Worksheet is unreadable.");
        }

        $rowsMap = [];
        foreach ($sheet->sheetData->row as $row) {
            $rowIdx = (int)$row['r'];
            $rowData = [];

            foreach ($row->c as $cell) {
                $ref = (string)$cell['r'];
                $colLetters = preg_replace('/\d+/', '', $ref);
                $colIdx = bulk_invite_column_letters_to_index($colLetters);
                $type = (string)$cell['t'];
                $value = '';

                if ($type === 's') {
                    $sharedIdx = (int)($cell->v ?? 0);
                    $value = $sharedStrings[$sharedIdx] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $value = (string)$cell->is->t;
                } else {
                    $value = (string)($cell->v ?? '');
                }

                $rowData[$colIdx] = bulk_invite_clean_text($value);
            }

            if (!empty($rowData)) {
                ksort($rowData);
                $rowsMap[$rowIdx] = array_values($rowData);
            }
        }

        $zip->close();
        ksort($rowsMap);
        return array_values($rowsMap);
    }
}

if (!function_exists('bulk_invite_parse_pdf')) {
    function bulk_invite_parse_pdf($path)
    {
        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            throw new RuntimeException("Failed to read PDF file.");
        }

        $text = bulk_invite_extract_pdf_text($content);
        if (trim($text) === '') {
            throw new RuntimeException("PDF text could not be extracted. Use .xlsx or .csv for best results.");
        }

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $records = [];
        $seen = [];

        foreach ($lines as $i => $lineRaw) {
            $line = bulk_invite_clean_text($lineRaw);
            if ($line === '') {
                continue;
            }

            if (!preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $line, $matches)) {
                continue;
            }

            foreach ($matches[0] as $emailRaw) {
                $email = strtolower(trim($emailRaw));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                if (isset($seen[$email])) {
                    continue;
                }
                $seen[$email] = true;

                $name = trim(str_ireplace($emailRaw, '', $line));
                $name = trim(preg_replace('/[\|\-,:;]+/', ' ', $name));
                if ($name === '' && isset($lines[$i - 1])) {
                    $prev = bulk_invite_clean_text((string)$lines[$i - 1]);
                    if ($prev !== '' && !preg_match('/@/', $prev)) {
                        $name = $prev;
                    }
                }

                $records[] = [
                    'full_name' => $name,
                    'email' => $email,
                ];
            }
        }

        if (empty($records)) {
            throw new RuntimeException("No valid employee emails were found in PDF. Use .xlsx or .csv if needed.");
        }

        return $records;
    }
}

if (!function_exists('bulk_invite_extract_records')) {
    function bulk_invite_extract_records(array $rows, $source)
    {
        if (empty($rows)) {
            throw new RuntimeException("No rows found in uploaded {$source} file.");
        }

        $rows = array_values(array_filter($rows, static function ($row) {
            if (!is_array($row)) {
                return false;
            }
            foreach ($row as $col) {
                if (trim((string)$col) !== '') {
                    return true;
                }
            }
            return false;
        }));

        if (empty($rows)) {
            throw new RuntimeException("Uploaded file is empty.");
        }

        $header = array_map('bulk_invite_normalize_header', $rows[0]);
        $hasHeader = in_array('email', $header, true) || in_array('full_name', $header, true) || in_array('name', $header, true);
        $startIdx = $hasHeader ? 1 : 0;

        $emailIdx = bulk_invite_find_header_index($header, ['email', 'email_address', 'employee_email']);
        $nameIdx = bulk_invite_find_header_index($header, ['full_name', 'name', 'employee_name']);

        $records = [];
        $seen = [];
        for ($i = $startIdx; $i < count($rows); $i++) {
            $row = $rows[$i];
            $email = '';
            $name = '';

            if ($emailIdx !== null && isset($row[$emailIdx])) {
                $email = strtolower(trim((string)$row[$emailIdx]));
            } else {
                foreach ($row as $cell) {
                    $candidate = strtolower(trim((string)$cell));
                    if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                        $email = $candidate;
                        break;
                    }
                }
            }

            if ($nameIdx !== null && isset($row[$nameIdx])) {
                $name = trim((string)$row[$nameIdx]);
            } else {
                foreach ($row as $cell) {
                    $candidate = trim((string)$cell);
                    if ($candidate === '' || filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }
                    $name = $candidate;
                    break;
                }
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if (isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;

            $records[] = [
                'full_name' => $name,
                'email' => $email,
            ];
        }

        if (empty($records)) {
            throw new RuntimeException("No valid rows found. Include at least email and name columns.");
        }

        return $records;
    }
}

if (!function_exists('bulk_invite_shared_string_to_text')) {
    function bulk_invite_shared_string_to_text($si)
    {
        if (isset($si->t)) {
            return bulk_invite_clean_text((string)$si->t);
        }

        $parts = [];
        if (isset($si->r)) {
            foreach ($si->r as $r) {
                $parts[] = (string)($r->t ?? '');
            }
        }

        return bulk_invite_clean_text(implode('', $parts));
    }
}

if (!function_exists('bulk_invite_column_letters_to_index')) {
    function bulk_invite_column_letters_to_index($letters)
    {
        $letters = strtoupper((string)$letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return max(0, $index - 1);
    }
}

if (!function_exists('bulk_invite_normalize_header')) {
    function bulk_invite_normalize_header($value)
    {
        $value = strtolower(trim((string)$value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        return trim($value, '_');
    }
}

if (!function_exists('bulk_invite_find_header_index')) {
    function bulk_invite_find_header_index(array $headers, array $candidates)
    {
        foreach ($headers as $idx => $header) {
            if (in_array($header, $candidates, true)) {
                return $idx;
            }
        }
        return null;
    }
}

if (!function_exists('bulk_invite_clean_text')) {
    function bulk_invite_clean_text($value)
    {
        $value = (string)$value;
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value));
        return $value;
    }
}

if (!function_exists('bulk_invite_extract_pdf_text')) {
    function bulk_invite_extract_pdf_text($content)
    {
        $chunks = [];
        if (preg_match_all('/stream[\r\n]+(.*?)endstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = $stream;
                $candidate = @gzuncompress($stream);
                if ($candidate !== false) {
                    $decoded = $candidate;
                } else {
                    $candidate = @gzdecode($stream);
                    if ($candidate !== false) {
                        $decoded = $candidate;
                    }
                }

                $chunks[] = bulk_invite_decode_pdf_text_operators($decoded);
            }
        }

        $chunks[] = bulk_invite_decode_pdf_text_operators($content);
        return trim(implode("\n", array_filter($chunks)));
    }
}

if (!function_exists('bulk_invite_decode_pdf_text_operators')) {
    function bulk_invite_decode_pdf_text_operators($data)
    {
        $parts = [];

        if (preg_match_all('/\((.*?)\)\s*Tj/s', $data, $m1)) {
            foreach ($m1[1] as $txt) {
                $parts[] = bulk_invite_unescape_pdf_text($txt);
            }
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $data, $m2)) {
            foreach ($m2[1] as $segment) {
                if (preg_match_all('/\((.*?)\)/s', $segment, $inner)) {
                    $line = '';
                    foreach ($inner[1] as $txt) {
                        $line .= bulk_invite_unescape_pdf_text($txt);
                    }
                    $parts[] = $line;
                }
            }
        }

        return implode("\n", $parts);
    }
}

if (!function_exists('bulk_invite_unescape_pdf_text')) {
    function bulk_invite_unescape_pdf_text($text)
    {
        $text = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], (string)$text);
        $text = preg_replace_callback('/\\\\([0-7]{1,3})/', static function ($m) {
            return chr(octdec($m[1]));
        }, $text);
        return bulk_invite_clean_text($text);
    }
}
