<?php

require_once __DIR__ . '/google_workspace.php';

if (!function_exists('google_calendar_required_scope')) {
    function google_calendar_required_scope()
    {
        return 'https://www.googleapis.com/auth/calendar.events.owned';
    }
}

if (!function_exists('google_calendar_timezone')) {
    function google_calendar_timezone()
    {
        $timezone = trim((string)date_default_timezone_get());
        return $timezone !== '' ? $timezone : 'Asia/Manila';
    }
}

if (!function_exists('google_calendar_is_enabled')) {
    function google_calendar_is_enabled()
    {
        return google_workspace_is_enabled();
    }
}

if (!function_exists('google_calendar_redirect_uri')) {
    function google_calendar_redirect_uri()
    {
        return APP_URL . '/app/google-calendar-callback.php';
    }
}

if (!function_exists('google_calendar_scopes')) {
    function google_calendar_scopes()
    {
        return [
            'openid',
            'email',
            'profile',
            google_calendar_required_scope(),
        ];
    }
}

if (!function_exists('google_calendar_build_auth_url')) {
    function google_calendar_build_auth_url($state, $forceConsent = false)
    {
        $params = [
            'client_id' => google_workspace_client_id(),
            'redirect_uri' => google_calendar_redirect_uri(),
            'response_type' => 'code',
            'scope' => implode(' ', google_calendar_scopes()),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'state' => (string)$state,
            'prompt' => $forceConsent ? 'consent select_account' : 'select_account',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('google_calendar_api_error_message')) {
    function google_calendar_api_error_message(array $response, $defaultMessage)
    {
        $message = google_workspace_api_error_message($response, $defaultMessage);
        $rawBody = is_array($response['body'] ?? null) ? json_encode($response['body']) : (string)($response['raw'] ?? '');
        $rawLower = strtolower((string)$rawBody);

        if (
            strpos($rawLower, 'insufficient') !== false
            || strpos($rawLower, 'permission') !== false
            || strpos($rawLower, 'scope') !== false
        ) {
            if (stripos($message, 'calendar scope') === false) {
                $message .= ' Make sure the Google OAuth consent screen includes the Calendar scope.';
            }
        }

        return trim($message);
    }
}

if (!function_exists('google_calendar_exchange_code_for_tokens')) {
    function google_calendar_exchange_code_for_tokens($code)
    {
        $payload = http_build_query([
            'code' => trim((string)$code),
            'client_id' => google_workspace_client_id(),
            'client_secret' => google_workspace_client_secret(),
            'redirect_uri' => google_calendar_redirect_uri(),
            'grant_type' => 'authorization_code',
        ], '', '&', PHP_QUERY_RFC3986);

        $response = google_workspace_http_request(
            'https://oauth2.googleapis.com/token',
            'POST',
            ['Content-Type: application/x-www-form-urlencoded'],
            $payload,
            20
        );

        if (!$response['ok']) {
            return [
                'ok' => false,
                'tokens' => null,
                'error' => google_calendar_api_error_message($response, 'Unable to complete Google Calendar authorization.'),
            ];
        }

        return [
            'ok' => true,
            'tokens' => is_array($response['body']) ? $response['body'] : [],
            'error' => '',
        ];
    }
}

if (!function_exists('google_calendar_extract_meet_link')) {
    function google_calendar_extract_meet_link(array $event)
    {
        $hangoutLink = trim((string)($event['hangoutLink'] ?? ''));
        if ($hangoutLink !== '') {
            return $hangoutLink;
        }

        $conference = is_array($event['conferenceData'] ?? null) ? $event['conferenceData'] : [];
        $entryPoints = $conference['entryPoints'] ?? [];
        if (is_array($entryPoints)) {
            foreach ($entryPoints as $entryPoint) {
                if (!is_array($entryPoint)) {
                    continue;
                }

                if (trim((string)($entryPoint['entryPointType'] ?? '')) === 'video') {
                    $uri = trim((string)($entryPoint['uri'] ?? ''));
                    if ($uri !== '') {
                        return $uri;
                    }
                }
            }
        }

        return '';
    }
}

if (!function_exists('google_calendar_extract_conference_id')) {
    function google_calendar_extract_conference_id(array $event)
    {
        $conference = is_array($event['conferenceData'] ?? null) ? $event['conferenceData'] : [];
        return trim((string)($conference['conferenceId'] ?? ''));
    }
}

if (!function_exists('google_calendar_get_event')) {
    function google_calendar_get_event($accessToken, $eventId)
    {
        $eventId = trim((string)$eventId);
        if ($eventId === '') {
            return [
                'ok' => false,
                'event' => null,
                'error' => 'Google Calendar event id is missing.',
            ];
        }

        $response = google_workspace_http_request(
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($eventId) . '?conferenceDataVersion=1',
            'GET',
            ['Authorization: Bearer ' . trim((string)$accessToken)],
            null,
            20
        );

        if (!$response['ok']) {
            return [
                'ok' => false,
                'event' => null,
                'error' => google_calendar_api_error_message($response, 'Unable to read the Google Calendar event.'),
            ];
        }

        return [
            'ok' => true,
            'event' => is_array($response['body']) ? $response['body'] : [],
            'error' => '',
        ];
    }
}

if (!function_exists('google_calendar_normalize_meeting_payload')) {
    function google_calendar_normalize_meeting_payload(array $payload)
    {
        $title = trim((string)($payload['title'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $meetingDate = trim((string)($payload['meeting_date'] ?? ''));
        $startTime = substr(trim((string)($payload['start_time'] ?? '')), 0, 5);
        $endTime = substr(trim((string)($payload['end_time'] ?? '')), 0, 5);
        $timezone = trim((string)($payload['timezone'] ?? ''));

        if ($title === '') {
            $title = 'TaskFlow Meeting';
        }
        if ($timezone === '') {
            $timezone = google_calendar_timezone();
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $meetingDate)) {
            return ['ok' => false, 'data' => null, 'error' => 'Meeting date is invalid.'];
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            return ['ok' => false, 'data' => null, 'error' => 'Meeting start and end time are required.'];
        }

        try {
            $tz = new DateTimeZone($timezone);
            $startAt = new DateTimeImmutable($meetingDate . ' ' . $startTime . ':00', $tz);
            $endAt = new DateTimeImmutable($meetingDate . ' ' . $endTime . ':00', $tz);
        } catch (Throwable $e) {
            return ['ok' => false, 'data' => null, 'error' => 'Meeting date or time could not be parsed.'];
        }

        if ($endAt <= $startAt) {
            return ['ok' => false, 'data' => null, 'error' => 'Meeting end time must be after the start time.'];
        }

        return [
            'ok' => true,
            'data' => [
                'title' => $title,
                'description' => $description,
                'meeting_date' => $meetingDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'timezone' => $timezone,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ],
            'error' => '',
        ];
    }
}

if (!function_exists('google_calendar_build_source_url')) {
    function google_calendar_build_source_url(DateTimeImmutable $startAt, $meetingDate)
    {
        return APP_URL . '/calendar.php?date=' . rawurlencode((string)$meetingDate)
            . '&month=' . rawurlencode($startAt->format('m'))
            . '&year=' . rawurlencode($startAt->format('Y'));
    }
}

if (!function_exists('google_calendar_create_meeting_event')) {
    function google_calendar_create_meeting_event($accessToken, array $payload)
    {
        $normalized = google_calendar_normalize_meeting_payload($payload);
        if (!$normalized['ok']) {
            return ['ok' => false, 'event' => null, 'error' => (string)($normalized['error'] ?? 'Meeting details are invalid.')];
        }

        $data = (array)($normalized['data'] ?? []);
        $sourceUrl = google_calendar_build_source_url($data['start_at'], $data['meeting_date']);

        try {
            $requestId = bin2hex(random_bytes(12));
        } catch (Throwable $e) {
            $requestId = hash('sha256', uniqid('taskflow_meeting_', true) . microtime(true));
        }

        $body = [
            'summary' => (string)$data['title'],
            'description' => (string)$data['description'],
            'start' => [
                'dateTime' => $data['start_at']->format(DATE_RFC3339),
                'timeZone' => (string)$data['timezone'],
            ],
            'end' => [
                'dateTime' => $data['end_at']->format(DATE_RFC3339),
                'timeZone' => (string)$data['timezone'],
            ],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => $requestId,
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet',
                    ],
                ],
            ],
            'source' => [
                'title' => 'TaskFlow Calendar',
                'url' => $sourceUrl,
            ],
            'reminders' => [
                'useDefault' => true,
            ],
        ];

        $response = google_workspace_http_request(
            'https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1',
            'POST',
            [
                'Authorization: Bearer ' . trim((string)$accessToken),
                'Content-Type: application/json',
            ],
            json_encode($body),
            20
        );

        if (!$response['ok']) {
            return [
                'ok' => false,
                'event' => null,
                'error' => google_calendar_api_error_message($response, 'Unable to create the Google Meet event.'),
            ];
        }

        $event = is_array($response['body']) ? $response['body'] : [];
        $eventId = trim((string)($event['id'] ?? ''));
        if ($eventId === '') {
            return ['ok' => false, 'event' => null, 'error' => 'Google Calendar did not return an event id.'];
        }

        $meetLink = google_calendar_extract_meet_link($event);
        if ($meetLink === '') {
            $fetched = google_calendar_get_event($accessToken, $eventId);
            if ($fetched['ok']) {
                $event = (array)($fetched['event'] ?? []);
                $meetLink = google_calendar_extract_meet_link($event);
            }
        }

        if ($meetLink === '') {
            return ['ok' => false, 'event' => null, 'error' => 'Google Calendar created the event but did not return a Google Meet link.'];
        }

        $event['hangoutLink'] = $meetLink;

        return [
            'ok' => true,
            'event' => $event,
            'error' => '',
        ];
    }
}

if (!function_exists('google_calendar_update_meeting_event')) {
    function google_calendar_update_meeting_event($accessToken, $eventId, array $payload)
    {
        $eventId = trim((string)$eventId);
        if ($eventId === '') {
            return ['ok' => false, 'event' => null, 'error' => 'Google Calendar event id is missing.'];
        }

        $normalized = google_calendar_normalize_meeting_payload($payload);
        if (!$normalized['ok']) {
            return ['ok' => false, 'event' => null, 'error' => (string)($normalized['error'] ?? 'Meeting details are invalid.')];
        }

        $data = (array)($normalized['data'] ?? []);
        $existing = google_calendar_get_event($accessToken, $eventId);
        if (!$existing['ok']) {
            return ['ok' => false, 'event' => null, 'error' => (string)($existing['error'] ?? 'Unable to read the Google Calendar event.')];
        }

        $existingEvent = (array)($existing['event'] ?? []);
        $body = [
            'summary' => (string)$data['title'],
            'description' => (string)$data['description'],
            'start' => [
                'dateTime' => $data['start_at']->format(DATE_RFC3339),
                'timeZone' => (string)$data['timezone'],
            ],
            'end' => [
                'dateTime' => $data['end_at']->format(DATE_RFC3339),
                'timeZone' => (string)$data['timezone'],
            ],
            'source' => [
                'title' => 'TaskFlow Calendar',
                'url' => google_calendar_build_source_url($data['start_at'], $data['meeting_date']),
            ],
            'reminders' => [
                'useDefault' => true,
            ],
        ];

        $response = google_workspace_http_request(
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($eventId) . '?conferenceDataVersion=1',
            'PATCH',
            [
                'Authorization: Bearer ' . trim((string)$accessToken),
                'Content-Type: application/json',
            ],
            json_encode($body),
            20
        );

        if (!$response['ok']) {
            return [
                'ok' => false,
                'event' => null,
                'error' => google_calendar_api_error_message($response, 'Unable to update the Google Meet event.'),
            ];
        }

        $event = is_array($response['body']) ? $response['body'] : [];
        $meetLink = google_calendar_extract_meet_link($event);
        if ($meetLink === '') {
            $fetched = google_calendar_get_event($accessToken, $eventId);
            if ($fetched['ok']) {
                $event = (array)($fetched['event'] ?? []);
                $meetLink = google_calendar_extract_meet_link($event);
            }
        }
        if ($meetLink === '') {
            $meetLink = google_calendar_extract_meet_link($existingEvent);
        }
        if ($meetLink !== '') {
            $event['hangoutLink'] = $meetLink;
        }
        if (empty($event['htmlLink']) && !empty($existingEvent['htmlLink'])) {
            $event['htmlLink'] = $existingEvent['htmlLink'];
        }

        return [
            'ok' => true,
            'event' => $event,
            'error' => '',
        ];
    }
}

if (!function_exists('google_calendar_delete_event')) {
    function google_calendar_delete_event($accessToken, $eventId)
    {
        $eventId = trim((string)$eventId);
        if ($eventId === '') {
            return [
                'ok' => true,
                'not_found' => false,
                'error' => '',
            ];
        }

        $response = google_workspace_http_request(
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($eventId),
            'DELETE',
            ['Authorization: Bearer ' . trim((string)$accessToken)],
            null,
            20
        );

        if ($response['ok']) {
            return [
                'ok' => true,
                'not_found' => false,
                'error' => '',
            ];
        }

        if ((int)($response['status'] ?? 0) === 404) {
            return [
                'ok' => true,
                'not_found' => true,
                'error' => '',
            ];
        }

        return [
            'ok' => false,
            'not_found' => false,
            'error' => google_calendar_api_error_message($response, 'Unable to delete the Google Meet event.'),
        ];
    }
}
