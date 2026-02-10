<?php
session_start();

if (!isset($_SESSION['role']) || !isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "event: error\ndata: {\"message\":\"Unauthorized\"}\n\n";
    exit();
}

include "DB_connection.php";
include "app/Model/User.php";

// Release session lock so other requests (clock in/out) can proceed
session_write_close();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
@set_time_limit(0);
while (ob_get_level() > 0) {
    @ob_end_flush();
}
@ob_implicit_flush(true);

$raw = $_GET['user_ids'] ?? '';
$raw = is_string($raw) ? $raw : '';
$parts = array_filter(array_map('trim', explode(',', $raw)));
$ids = [];
foreach ($parts as $p) {
    if (ctype_digit($p)) {
        $ids[] = (int)$p;
    }
}

if (empty($ids)) {
    echo "event: error\ndata: {\"message\":\"No users\"}\n\n";
    exit();
}

$last_hash = '';
$last_heartbeat = time();

while (true) {
    if (connection_aborted()) {
        break;
    }

    $users = [];
    foreach ($ids as $id) {
        $clocked_in = is_user_clocked_in($pdo, $id);
        $attendance = get_todays_attendance_stats($pdo, $id);
        $users[] = [
            'id' => $id,
            'clocked_in' => (bool)$clocked_in,
            'daily_duration' => str_replace('Oh ', '0h ', $attendance['daily_duration'] ?? '0h 0m')
        ];
    }

    $payload = json_encode(['users' => $users]);
    $hash = md5($payload);

    if ($hash !== $last_hash) {
        echo "data: " . $payload . "\n\n";
        $last_hash = $hash;
        $last_heartbeat = time();
    } elseif ((time() - $last_heartbeat) >= 15) {
        echo ": heartbeat\n\n";
        $last_heartbeat = time();
    }

    @flush();
    usleep(500000);
}
