<?php

if (!function_exists('paymongo_load_env_files')) {
    function paymongo_load_env_files()
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        $root = dirname(__DIR__);
        $envFiles = [
            $root . DIRECTORY_SEPARATOR . '.env.local',
            $root . DIRECTORY_SEPARATOR . '.env',
        ];

        foreach ($envFiles as $envFile) {
            if (!is_readable($envFile)) {
                continue;
            }

            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $line) {
                $line = trim((string)$line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
                $name = trim((string)$name);
                $value = trim((string)$value);

                if ($name === '') {
                    continue;
                }

                if ($value !== '' && $value[0] === '"' && str_ends_with($value, '"')) {
                    $value = substr($value, 1, -1);
                }

                if (getenv($name) === false) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                }
            }
        }

        $loaded = true;
    }
}

if (!function_exists('paymongo_env')) {
    function paymongo_env($name, $default = '')
    {
        paymongo_load_env_files();
        $value = getenv((string)$name);
        if ($value === false) {
            return $default;
        }

        return trim((string)$value);
    }
}

if (!function_exists('paymongo_is_configured')) {
    function paymongo_is_configured()
    {
        $secretKey = paymongo_env('PAYMONGO_SECRET_KEY', '');
        return $secretKey !== '' && str_starts_with($secretKey, 'sk_');
    }
}

if (!function_exists('paymongo_app_url')) {
    function paymongo_app_url()
    {
        $appUrl = rtrim(paymongo_env('APP_URL', 'http://localhost/task_management'), '/');
        return $appUrl !== '' ? $appUrl : 'http://localhost/task_management';
    }
}

if (!function_exists('paymongo_build_app_url')) {
    function paymongo_build_app_url($path, array $query = [])
    {
        $url = paymongo_app_url() . '/' . ltrim((string)$path, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}

if (!function_exists('paymongo_checkout_method_catalog')) {
    function paymongo_checkout_method_catalog()
    {
        return [
            'card' => [
                'label' => 'Credit/Debit Card',
                'types' => ['card'],
            ],
            'gcash' => [
                'label' => 'GCash',
                'types' => ['gcash'],
            ],
        ];
    }
}

if (!function_exists('paymongo_checkout_method_options')) {
    function paymongo_checkout_method_options()
    {
        $catalog = paymongo_checkout_method_catalog();
        $options = [];

        foreach ($catalog as $key => $config) {
            $options[$key] = (string)($config['label'] ?? ucfirst((string)$key));
        }

        return $options;
    }
}

if (!function_exists('paymongo_resolve_checkout_method')) {
    function paymongo_resolve_checkout_method($methodKey)
    {
        $catalog = paymongo_checkout_method_catalog();
        $methodKey = strtolower(trim((string)$methodKey));
        if (!isset($catalog[$methodKey])) {
            return null;
        }

        $config = $catalog[$methodKey];
        $types = [];
        foreach ((array)($config['types'] ?? []) as $type) {
            $type = strtolower(trim((string)$type));
            if ($type !== '') {
                $types[] = $type;
            }
        }

        return [
            'key' => $methodKey,
            'label' => (string)($config['label'] ?? ucfirst($methodKey)),
            'types' => array_values(array_unique($types)),
        ];
    }
}

if (!function_exists('paymongo_workspace_plan_prices')) {
    function paymongo_workspace_plan_prices()
    {
        return [
            'starter' => 399,
            'professional' => 799,
            'enterprise' => 1599,
        ];
    }
}

if (!function_exists('paymongo_post_signup_plan_prices')) {
    function paymongo_post_signup_plan_prices()
    {
        return [
            'starter' => 9,
            'professional' => 17,
            'enterprise' => 29,
        ];
    }
}

if (!function_exists('paymongo_plan_price_php')) {
    function paymongo_plan_price_php($planCode, $context = 'workspace')
    {
        $prices = strtolower(trim((string)$context)) === 'post_signup'
            ? paymongo_post_signup_plan_prices()
            : paymongo_workspace_plan_prices();

        $code = strtolower(trim((string)$planCode));
        if (isset($prices[$code])) {
            return (int)$prices[$code];
        }

        $fallback = reset($prices);
        return $fallback !== false ? (int)$fallback : 0;
    }
}

if (!function_exists('paymongo_plan_price_centavos')) {
    function paymongo_plan_price_centavos($planCode, $context = 'workspace')
    {
        return max(0, paymongo_plan_price_php($planCode, $context) * 100);
    }
}

if (!function_exists('paymongo_create_state_token')) {
    function paymongo_create_state_token()
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            return sha1(uniqid('paymongo-', true));
        }
    }
}

if (!function_exists('paymongo_reference_number')) {
    function paymongo_reference_number($prefix, $entityId)
    {
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$prefix));
        if ($prefix === '') {
            $prefix = 'TF';
        }

        try {
            $random = strtoupper(bin2hex(random_bytes(4)));
        } catch (Throwable $e) {
            $random = strtoupper(substr(sha1(uniqid((string)$entityId, true)), 0, 8));
        }

        $reference = $prefix . '-' . max(1, (int)$entityId) . '-' . $random;
        return substr($reference, 0, 40);
    }
}

if (!function_exists('paymongo_response_error_message')) {
    function paymongo_response_error_message($body)
    {
        if (!is_array($body)) {
            return null;
        }

        if (isset($body['errors']) && is_array($body['errors']) && !empty($body['errors'])) {
            $first = $body['errors'][0];
            if (is_array($first)) {
                foreach (['detail', 'message', 'title', 'code'] as $key) {
                    $value = trim((string)($first[$key] ?? ''));
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        $message = trim((string)($body['message'] ?? ''));
        return $message !== '' ? $message : null;
    }
}

if (!function_exists('paymongo_api_request')) {
    function paymongo_api_request($method, $path, $payload = null)
    {
        if (!paymongo_is_configured()) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => null,
                'data' => null,
                'error' => 'PayMongo is not configured. Add PAYMONGO_SECRET_KEY with your sk_test_ key.',
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => null,
                'data' => null,
                'error' => 'cURL is required to contact PayMongo from this server.',
            ];
        }

        $secretKey = paymongo_env('PAYMONGO_SECRET_KEY', '');
        $baseUrl = rtrim(paymongo_env('PAYMONGO_API_BASE_URL', 'https://api.paymongo.com/v1'), '/');
        $url = $baseUrl . '/' . ltrim((string)$path, '/');
        $method = strtoupper(trim((string)$method));

        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => null,
                'data' => null,
                'error' => 'Unable to initialize the PayMongo request.',
            ];
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($secretKey . ':'),
                'Content-Type: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ];

        if ($payload !== null) {
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if ($jsonPayload === false) {
                curl_close($ch);
                return [
                    'ok' => false,
                    'status' => 0,
                    'body' => null,
                    'data' => null,
                    'error' => 'Unable to encode the PayMongo request payload.',
                ];
            }
            $options[CURLOPT_POSTFIELDS] = $jsonPayload;
        }

        curl_setopt_array($ch, $options);
        $rawBody = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($rawBody === false || $curlErrno !== 0) {
            return [
                'ok' => false,
                'status' => $statusCode,
                'body' => null,
                'data' => null,
                'error' => 'Unable to reach PayMongo right now. ' . ($curlError !== '' ? $curlError : 'Please try again.'),
            ];
        }

        $body = json_decode((string)$rawBody, true);
        if (!is_array($body)) {
            $body = null;
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $errorMessage = paymongo_response_error_message($body);
            return [
                'ok' => false,
                'status' => $statusCode,
                'body' => $body,
                'data' => null,
                'error' => $errorMessage ?: 'PayMongo request failed right now.',
            ];
        }

        return [
            'ok' => true,
            'status' => $statusCode,
            'body' => $body,
            'data' => $body['data'] ?? null,
            'error' => null,
        ];
    }
}

if (!function_exists('paymongo_filter_metadata')) {
    function paymongo_filter_metadata(array $metadata)
    {
        $filtered = [];
        foreach ($metadata as $key => $value) {
            $key = trim((string)$key);
            if ($key === '') {
                continue;
            }
            if ($value === null) {
                continue;
            }
            if (is_scalar($value)) {
                $filtered[$key] = trim((string)$value);
            }
        }

        return $filtered;
    }
}

if (!function_exists('paymongo_create_checkout_session')) {
    function paymongo_create_checkout_session(array $params)
    {
        $paymentMethodTypes = [];
        foreach ((array)($params['payment_method_types'] ?? []) as $type) {
            $type = strtolower(trim((string)$type));
            if ($type !== '') {
                $paymentMethodTypes[] = $type;
            }
        }
        $paymentMethodTypes = array_values(array_unique($paymentMethodTypes));

        $amountCentavos = max(0, (int)($params['amount_centavos'] ?? 0));
        $quantity = max(1, (int)($params['quantity'] ?? 1));
        $itemName = trim((string)($params['item_name'] ?? 'TaskFlow Subscription'));
        $itemDescription = trim((string)($params['item_description'] ?? $itemName));
        $description = trim((string)($params['description'] ?? $itemDescription));
        $referenceNumber = trim((string)($params['reference_number'] ?? ''));
        $successUrl = trim((string)($params['success_url'] ?? ''));
        $cancelUrl = trim((string)($params['cancel_url'] ?? ''));

        if ($amountCentavos <= 0) {
            return [
                'ok' => false,
                'checkout' => null,
                'checkout_session_id' => null,
                'checkout_url' => null,
                'error' => 'The checkout amount must be greater than zero.',
            ];
        }

        if ($itemName === '' || $description === '' || $successUrl === '' || $cancelUrl === '' || empty($paymentMethodTypes)) {
            return [
                'ok' => false,
                'checkout' => null,
                'checkout_session_id' => null,
                'checkout_url' => null,
                'error' => 'Missing required PayMongo checkout details.',
            ];
        }

        $attributes = [
            'cancel_url' => $cancelUrl,
            'description' => $description,
            'line_items' => [
                [
                    'amount' => $amountCentavos,
                    'currency' => 'PHP',
                    'description' => $itemDescription,
                    'name' => $itemName,
                    'quantity' => $quantity,
                ],
            ],
            'payment_method_types' => $paymentMethodTypes,
            'reference_number' => $referenceNumber,
            'send_email_receipt' => false,
            'show_description' => true,
            'show_line_items' => true,
            'success_url' => $successUrl,
        ];

        $billingName = trim((string)($params['billing_name'] ?? ''));
        $billingEmail = trim((string)($params['billing_email'] ?? ''));
        if ($billingName !== '' || $billingEmail !== '') {
            $billing = [];
            if ($billingName !== '') {
                $billing['name'] = $billingName;
            }
            if ($billingEmail !== '') {
                $billing['email'] = $billingEmail;
            }
            if (!empty($billing)) {
                $attributes['billing'] = $billing;
            }
        }

        $metadata = paymongo_filter_metadata((array)($params['metadata'] ?? []));
        if (!empty($metadata)) {
            $attributes['metadata'] = $metadata;
        }

        $response = paymongo_api_request('POST', 'checkout_sessions', [
            'data' => [
                'attributes' => $attributes,
            ],
        ]);

        if (empty($response['ok'])) {
            return [
                'ok' => false,
                'checkout' => null,
                'checkout_session_id' => null,
                'checkout_url' => null,
                'error' => (string)($response['error'] ?? 'Unable to create PayMongo checkout.'),
            ];
        }

        $checkout = is_array($response['data']) ? $response['data'] : null;
        $checkoutSessionId = trim((string)($checkout['id'] ?? ''));
        $checkoutUrl = trim((string)($checkout['attributes']['checkout_url'] ?? ''));

        if ($checkoutSessionId === '' || $checkoutUrl === '') {
            return [
                'ok' => false,
                'checkout' => $checkout,
                'checkout_session_id' => null,
                'checkout_url' => null,
                'error' => 'PayMongo checkout was created without a redirect URL.',
            ];
        }

        return [
            'ok' => true,
            'checkout' => $checkout,
            'checkout_session_id' => $checkoutSessionId,
            'checkout_url' => $checkoutUrl,
            'error' => null,
        ];
    }
}

if (!function_exists('paymongo_retrieve_checkout_session')) {
    function paymongo_retrieve_checkout_session($checkoutSessionId)
    {
        $checkoutSessionId = trim((string)$checkoutSessionId);
        if ($checkoutSessionId === '') {
            return [
                'ok' => false,
                'checkout' => null,
                'error' => 'Checkout session ID is missing.',
            ];
        }

        $response = paymongo_api_request('GET', 'checkout_sessions/' . rawurlencode($checkoutSessionId));
        if (empty($response['ok'])) {
            return [
                'ok' => false,
                'checkout' => null,
                'error' => (string)($response['error'] ?? 'Unable to verify the PayMongo checkout session.'),
            ];
        }

        return [
            'ok' => true,
            'checkout' => is_array($response['data']) ? $response['data'] : null,
            'error' => null,
        ];
    }
}

if (!function_exists('paymongo_checkout_is_paid')) {
    function paymongo_checkout_is_paid($checkout)
    {
        if (!is_array($checkout)) {
            return false;
        }

        $attributes = is_array($checkout['attributes'] ?? null) ? $checkout['attributes'] : [];
        foreach ((array)($attributes['payments'] ?? []) as $payment) {
            $paymentAttributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];
            $paymentStatus = strtolower(trim((string)($paymentAttributes['status'] ?? '')));
            if ($paymentStatus === 'paid') {
                return true;
            }
        }

        $paymentIntentStatus = strtolower(trim((string)($attributes['payment_intent']['attributes']['status'] ?? '')));
        return in_array($paymentIntentStatus, ['succeeded', 'paid'], true);
    }
}

if (!function_exists('paymongo_payment_method_label')) {
    function paymongo_payment_method_label($sourceType, $sourceBrand = '')
    {
        $sourceType = strtolower(trim((string)$sourceType));
        $sourceBrand = trim((string)$sourceBrand);

        if ($sourceType === 'card') {
            if ($sourceBrand !== '') {
                return ucfirst(strtolower($sourceBrand)) . ' Card';
            }
            return 'Card';
        }

        $labels = [
            'gcash' => 'GCash',
        ];

        return $labels[$sourceType] ?? ($sourceType !== '' ? ucfirst(str_replace('_', ' ', $sourceType)) : 'PayMongo');
    }
}

if (!function_exists('paymongo_checkout_payment_summary')) {
    function paymongo_checkout_payment_summary($checkout)
    {
        $summary = [
            'checkout_session_id' => '',
            'reference_number' => '',
            'payment_id' => '',
            'payment_status' => '',
            'method_label' => 'PayMongo',
        ];

        if (!is_array($checkout)) {
            return $summary;
        }

        $attributes = is_array($checkout['attributes'] ?? null) ? $checkout['attributes'] : [];
        $summary['checkout_session_id'] = trim((string)($checkout['id'] ?? ''));
        $summary['reference_number'] = trim((string)($attributes['reference_number'] ?? ''));

        $payments = (array)($attributes['payments'] ?? []);
        if (!empty($payments) && is_array($payments[0])) {
            $payment = $payments[0];
            $paymentAttributes = is_array($payment['attributes'] ?? null) ? $payment['attributes'] : [];
            $source = is_array($paymentAttributes['source'] ?? null) ? $paymentAttributes['source'] : [];

            $summary['payment_id'] = trim((string)($payment['id'] ?? ''));
            $summary['payment_status'] = strtolower(trim((string)($paymentAttributes['status'] ?? '')));
            $summary['method_label'] = paymongo_payment_method_label(
                $source['type'] ?? '',
                $source['brand'] ?? ''
            );
        }

        return $summary;
    }
}

if (!function_exists('paymongo_activate_workspace_subscription')) {
    function paymongo_activate_workspace_subscription($pdo, $orgId, $checkoutSessionId)
    {
        $orgId = (int)$orgId;
        $checkoutSessionId = trim((string)$checkoutSessionId);

        if ($orgId <= 0 || $checkoutSessionId === '') {
            return [
                'ok' => false,
                'reason' => 'Workspace checkout details are incomplete.',
                'current_period_end' => null,
                'already_processed' => false,
            ];
        }

        $subscription = tenant_ensure_subscription($pdo, $orgId);
        if (!$subscription) {
            return [
                'ok' => false,
                'reason' => 'Unable to initialize workspace subscription.',
                'current_period_end' => null,
                'already_processed' => false,
            ];
        }

        $currentProvider = '';
        $currentProviderReference = '';
        $providerColumnExists = tenant_column_exists($pdo, 'subscriptions', 'provider');
        $providerReferenceExists = tenant_column_exists($pdo, 'subscriptions', 'provider_subscription_id');

        if ($providerColumnExists || $providerReferenceExists) {
            $columns = [];
            if ($providerColumnExists) {
                $columns[] = 'provider';
            }
            if ($providerReferenceExists) {
                $columns[] = 'provider_subscription_id';
            }

            $stmt = $pdo->prepare(
                "SELECT " . implode(', ', $columns) . "
                 FROM subscriptions
                 WHERE organization_id = ?
                 LIMIT 1"
            );
            $stmt->execute([$orgId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $currentProvider = strtolower(trim((string)($row['provider'] ?? '')));
            $currentProviderReference = trim((string)($row['provider_subscription_id'] ?? ''));
        }

        $currentStatus = strtolower(trim((string)($subscription['status'] ?? '')));
        if (
            $currentProviderReference !== ''
            && hash_equals($currentProviderReference, $checkoutSessionId)
            && $currentStatus === 'active'
        ) {
            return [
                'ok' => true,
                'reason' => null,
                'current_period_end' => $subscription['current_period_end'] ?? null,
                'already_processed' => true,
            ];
        }

        $nowTs = time();
        $currentPeriodTs = !empty($subscription['current_period_end'])
            ? strtotime((string)$subscription['current_period_end'])
            : false;
        $canExtendExistingPeriod = $currentStatus === 'active' && $currentPeriodTs !== false && $currentPeriodTs > $nowTs;
        $baseTs = $canExtendExistingPeriod ? $currentPeriodTs : $nowTs;
        $newPeriodEnd = date('Y-m-d H:i:s', strtotime('+1 month', $baseTs));

        $setParts = [
            "status = 'active'",
            "current_period_end = ?",
        ];
        $params = [$newPeriodEnd];

        if ($providerColumnExists) {
            $setParts[] = "provider = ?";
            $params[] = 'paymongo';
        }
        if ($providerReferenceExists) {
            $setParts[] = "provider_subscription_id = ?";
            $params[] = $checkoutSessionId;
        }

        $params[] = $orgId;

        try {
            $stmt = $pdo->prepare("UPDATE subscriptions SET " . implode(', ', $setParts) . " WHERE organization_id = ?");
            $stmt->execute($params);

            return [
                'ok' => true,
                'reason' => null,
                'current_period_end' => $newPeriodEnd,
                'already_processed' => false,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'reason' => 'Unable to activate the workspace subscription right now.',
                'current_period_end' => null,
                'already_processed' => false,
            ];
        }
    }
}
