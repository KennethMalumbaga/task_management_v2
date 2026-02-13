<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (isset($_SESSION['id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'employee') {
    require 'DB_connection.php';

    $user_id = (int) $_SESSION['id'];
    $now = date('H:i:s');

    // Auto clock-out on logout if there is an active attendance record.
    $sql = "SELECT id, time_in FROM attendance
            WHERE user_id = ?
              AND time_in IS NOT NULL
              AND (time_out IS NULL OR time_out = '00:00:00')
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $att = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($att) {
        $hours = round((strtotime($now) - strtotime($att['time_in'])) / 3600, 2);
        if ($hours < 0) {
            $hours = 0;
        }

        $update = "UPDATE attendance SET time_out = ?, total_hours = ? WHERE id = ?";
        $pdo->prepare($update)->execute([$now, $hours, $att['id']]);
    }
}

session_unset();
session_destroy();

header("Location: login.php");
exit();
