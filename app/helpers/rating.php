<?php

if (!function_exists('tm_apply_peer_rating_smoothing')) {
    function tm_apply_peer_rating_smoothing($peer_raw, $n, $prior_mean = 3.5, $prior_weight = 3)
    {
        $n = (int)$n;
        if ($n <= 0 || $peer_raw === null) {
            return null;
        }

        $peer_raw = (float)$peer_raw;
        $prior_mean = (float)$prior_mean;
        $prior_weight = (float)$prior_weight;

        return (($n / ($n + $prior_weight)) * $peer_raw)
            + (($prior_weight / ($n + $prior_weight)) * $prior_mean);
    }
}
