<?php
session_start();

if (!isset($_SESSION['role']) || !isset($_SESSION['id'])) {
    http_response_code(403);
    echo "event: error\ndata: {\"message\":\"Unauthorized\"}\n\n";
    exit();
}

include "DB_connection.php";
include "app/model/user.php";

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

$user_id = (int)$_SESSION['id'];
$last_hash = '';
$last_heartbeat = time();

while (true) {
    if (connection_aborted()) {
        break;
    }

    $clocked_in = is_user_clocked_in($pdo, $user_id);
    $attendance = get_todays_attendance_stats($pdo, $user_id);

    $payload = [
        'status' => 'success',
        'has_active_attendance' => (bool)$clocked_in,
        'attendance_id' => $clocked_in ? ($attendance['attendance_id'] ?? null) : null,
        'attendance_record_id' => $attendance['latest_attendance_id'] ?? null,
        'time_in' => $attendance['time_in'] ?? '--:--',
        'time_out' => $attendance['time_out'] ?? '--:--',
        'daily_duration' => $attendance['daily_duration'] ?? null,
        'overall_duration' => $attendance['overall_duration'] ?? null,
        'is_paused' => !empty($attendance['is_paused']),
        'pause_reason' => $attendance['pause_reason'] ?? null,
        'pause_started_at' => $attendance['pause_started_at'] ?? null,
        'admin_clock_out_remark' => $attendance['admin_clock_out_remark'] ?? null,
        'clocked_out_by_admin' => !empty($attendance['clocked_out_by_admin']),
    ];

    $json = json_encode($payload);
    $hash = md5($json);

    if ($hash !== $last_hash) {
        echo "data: " . $json . "\n\n";
        $last_hash = $hash;
        $last_heartbeat = time();
    } elseif ((time() - $last_heartbeat) >= 15) {
        echo ": heartbeat\n\n";
        $last_heartbeat = time();
    }

    @flush();
    usleep(500000);
}
