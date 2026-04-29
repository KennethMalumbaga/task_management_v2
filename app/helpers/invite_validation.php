<?php

if (!function_exists('invite_normalize_full_name')) {
    function invite_normalize_full_name(string $value): string
    {
        $collapsed = preg_replace('/\s+/', ' ', trim($value));
        return is_string($collapsed) ? $collapsed : trim($value);
    }
}

if (!function_exists('invite_is_valid_full_name')) {
    function invite_is_valid_full_name(string $value): bool
    {
        // Require at least first + last name; allow letters with common punctuation.
        $nameLength = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($nameLength < 3 || $nameLength > 80) {
            return false;
        }
        if (preg_match('/\s+/', $value) !== 1) {
            return false;
        }

        $namePart = "[\\p{L}](?:[\\p{L}\\p{M}'\\-]*[\\p{L}\\p{M}])?\\.?";

        return preg_match(
            "/^(?=.{3,80}$){$namePart}(?:\\s+{$namePart})+$/u",
            $value
        ) === 1;
    }
}

if (!function_exists('invite_normalize_email')) {
    function invite_normalize_email(string $value): string
    {
        return strtolower(trim($value));
    }
}

if (!function_exists('invite_is_valid_email')) {
    function invite_is_valid_email(string $value): bool
    {
        if ($value === '' || strlen($value) > 254) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('invite_bulk_allowed_extensions')) {
    function invite_bulk_allowed_extensions(): array
    {
        return ['xlsx', 'csv', 'pdf'];
    }
}

if (!function_exists('invite_bulk_detect_extension')) {
    function invite_bulk_detect_extension(string $originalName): string
    {
        return strtolower(trim((string)pathinfo($originalName, PATHINFO_EXTENSION)));
    }
}

if (!function_exists('invite_bulk_file_prefix')) {
    function invite_bulk_file_prefix(string $path, int $bytes = 8): string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }
        $data = (string)fread($handle, max(1, $bytes));
        fclose($handle);
        return $data;
    }
}

if (!function_exists('invite_bulk_has_pdf_signature')) {
    function invite_bulk_has_pdf_signature(string $path): bool
    {
        return strncmp(invite_bulk_file_prefix($path, 4), '%PDF', 4) === 0;
    }
}

if (!function_exists('invite_bulk_has_zip_signature')) {
    function invite_bulk_has_zip_signature(string $path): bool
    {
        $prefix = invite_bulk_file_prefix($path, 4);
        return in_array($prefix, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true);
    }
}

if (!function_exists('invite_bulk_is_likely_text_file')) {
    function invite_bulk_is_likely_text_file(string $path): bool
    {
        $sample = invite_bulk_file_prefix($path, 4096);
        if ($sample === '') {
            return false;
        }
        return strpos($sample, "\0") === false;
    }
}

if (!function_exists('invite_bulk_validate_upload_type')) {
    function invite_bulk_validate_upload_type(string $originalName, string $tmpPath): array
    {
        $ext = invite_bulk_detect_extension($originalName);
        if ($ext === '') {
            return [
                'ok' => false,
                'error' => "Uploaded file must have an extension (.xlsx, .csv, or .pdf).",
            ];
        }

        if (!in_array($ext, invite_bulk_allowed_extensions(), true)) {
            return [
                'ok' => false,
                'error' => "Unsupported file type. Only .xlsx, .csv, and .pdf are allowed.",
            ];
        }

        if ($tmpPath === '' || !is_file($tmpPath)) {
            return [
                'ok' => false,
                'error' => "Uploaded file is invalid.",
            ];
        }

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = strtolower(trim((string)@finfo_file($finfo, $tmpPath)));
                @finfo_close($finfo);
            }
        }

        if ($ext === 'pdf' && !invite_bulk_has_pdf_signature($tmpPath)) {
            return [
                'ok' => false,
                'error' => "Invalid PDF file content. Please upload a valid .pdf file.",
            ];
        }

        if ($ext === 'xlsx' && !invite_bulk_has_zip_signature($tmpPath)) {
            return [
                'ok' => false,
                'error' => "Invalid XLSX file content. Please upload a valid .xlsx file.",
            ];
        }

        if ($ext === 'csv' && !invite_bulk_is_likely_text_file($tmpPath)) {
            return [
                'ok' => false,
                'error' => "Invalid CSV file content. Please upload a valid text-based .csv file.",
            ];
        }

        return [
            'ok' => true,
            'extension' => $ext,
            'mime' => $mime,
        ];
    }
}
