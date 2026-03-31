<?php

if (!function_exists('task_link_normalize_google_doc_url')) {
    function task_link_normalize_google_doc_url($rawUrl)
    {
        $url = trim((string)$rawUrl);
        if ($url === '') {
            return '';
        }

        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }

        $validatedUrl = filter_var($url, FILTER_VALIDATE_URL);
        if ($validatedUrl === false) {
            return null;
        }

        $parts = parse_url($validatedUrl);
        if ($parts === false) {
            return null;
        }

        $host = strtolower((string)($parts['host'] ?? ''));
        $path = (string)($parts['path'] ?? '');

        if ($host !== 'docs.google.com' && $host !== 'www.docs.google.com') {
            return null;
        }

        if (!preg_match('~^/document(?:/|$)~i', $path)) {
            return null;
        }

        return $validatedUrl;
    }
}
