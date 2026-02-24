<?php

if (!function_exists('password_policy_pattern')) {
    function password_policy_pattern()
    {
        return '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';
    }
}

if (!function_exists('password_meets_policy')) {
    function password_meets_policy($password)
    {
        return is_string($password) && preg_match(password_policy_pattern(), $password) === 1;
    }
}

if (!function_exists('password_policy_error')) {
    function password_policy_error()
    {
        return 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.';
    }
}
