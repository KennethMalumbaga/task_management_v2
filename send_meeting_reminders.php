<?php
date_default_timezone_set('Asia/Manila');

if (PHP_SAPI !== 'cli') {
    session_start();
    if (!isset($_SESSION['id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Admin access required.";
        exit();
    }

    header('Content-Type: text/plain; charset=UTF-8');
}

require_once __DIR__ . '/DB_connection.php';
require_once __DIR__ . '/app/model/CalendarMeetingReminder.php';
require_once __DIR__ . '/app/send_email.php';

$timezone = new DateTimeZone('Asia/Manila');
$now = new DateTimeImmutable('now', $timezone);

$queued = calendar_meeting_reminder_sync_upcoming_queue($pdo, $now, 14);
$dueRows = calendar_meeting_reminder_get_due_rows($pdo, $now, 15);

$sentCount = 0;
$failedCount = 0;
$skippedCount = 0;

foreach ($dueRows as $row) {
    $reminderId = (int)($row['reminder_id'] ?? 0);
    $email = strtolower(trim((string)($row['username'] ?? '')));
    $fullName = trim((string)($row['full_name'] ?? '')) ?: 'Workspace member';

    if ($reminderId <= 0) {
        continue;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        calendar_meeting_reminder_mark_sent($pdo, $reminderId, 'Skipped: recipient email is invalid.', $now);
        $skippedCount++;
        continue;
    }

    $meetingStart = calendar_meeting_reminder_meeting_start_at($row);
    if (!$meetingStart) {
        calendar_meeting_reminder_mark_sent($pdo, $reminderId, 'Skipped: meeting time is invalid.', $now);
        $skippedCount++;
        continue;
    }

    if ($meetingStart <= $now) {
        calendar_meeting_reminder_mark_sent($pdo, $reminderId, 'Skipped: meeting already started.', $now);
        $skippedCount++;
        continue;
    }

    $sent = send_meeting_reminder_email($email, $fullName, $row);
    if ($sent) {
        calendar_meeting_reminder_mark_sent($pdo, $reminderId, null, $now);
        $sentCount++;
        continue;
    }

    calendar_meeting_reminder_mark_error($pdo, $reminderId, 'Email send failed. The scheduler will retry while the reminder window is still open.');
    $failedCount++;
}

echo "TaskFlow meeting reminder run\n";
echo "Now: " . $now->format('Y-m-d H:i:s T') . "\n";
echo "Queued reminders added: {$queued}\n";
echo "Due reminders checked: " . count($dueRows) . "\n";
echo "Sent: {$sentCount}\n";
echo "Failed: {$failedCount}\n";
echo "Skipped: {$skippedCount}\n";
