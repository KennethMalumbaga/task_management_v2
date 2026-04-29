<?php

if (!function_exists('taskflow_is_mobile_device')) {
    function taskflow_is_mobile_device()
    {
        $secChUaMobile = isset($_SERVER['HTTP_SEC_CH_UA_MOBILE'])
            ? trim((string)$_SERVER['HTTP_SEC_CH_UA_MOBILE'])
            : '';

        if ($secChUaMobile === '?1' || $secChUaMobile === '1') {
            return true;
        }

        $userAgent = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($userAgent === '') {
            return false;
        }

        return (bool)preg_match(
            '/android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini|mobile|tablet|silk|kindle/',
            $userAgent
        );
    }
}
