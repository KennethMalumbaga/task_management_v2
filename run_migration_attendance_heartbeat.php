<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

function attendance_heartbeat_column_exists(PDO $pdo, string $driver): bool
{
    if ($driver === "mysql") {
        $sql = "SELECT 1
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'attendance'
                  AND column_name = 'last_heartbeat_at'
                LIMIT 1";
        $stmt = $pdo->query($sql);
        return (bool)$stmt->fetchColumn();
    }

    $sql = "SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'attendance'
              AND column_name = 'last_heartbeat_at'
            LIMIT 1";
    $stmt = $pdo->query($sql);
    return (bool)$stmt->fetchColumn();
}

try {
    $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    if ($driver !== "mysql" && $driver !== "pgsql") {
        throw new RuntimeException("Unsupported driver: " . $driver);
    }

    if (!attendance_heartbeat_column_exists($pdo, $driver)) {
        if ($driver === "mysql") {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN last_heartbeat_at DATETIME NULL AFTER time_out");
        } else {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN last_heartbeat_at TIMESTAMP NULL");
        }
        echo "Added attendance.last_heartbeat_at column.\n";
    } else {
        echo "attendance.last_heartbeat_at already exists.\n";
    }

    if ($driver === "mysql") {
        $pdo->exec("UPDATE attendance
                    SET last_heartbeat_at = CURRENT_TIMESTAMP
                    WHERE (time_out IS NULL OR time_out = '00:00:00')
                      AND time_in IS NOT NULL
                      AND last_heartbeat_at IS NULL");
    } else {
        $pdo->exec("UPDATE attendance
                    SET last_heartbeat_at = CURRENT_TIMESTAMP
                    WHERE (time_out IS NULL OR time_out = '00:00:00')
                      AND time_in IS NOT NULL
                      AND last_heartbeat_at IS NULL");
    }
    echo "Backfilled active attendance heartbeat values.\n";

    echo "Migration applied: attendance heartbeat support is ready.\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}

