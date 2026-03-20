<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

function admin_clock_out_remark_column_exists(PDO $pdo, string $driver): bool
{
    if ($driver === "mysql") {
        $sql = "SELECT 1
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'attendance'
                  AND column_name = 'admin_clock_out_remark'
                LIMIT 1";
        $stmt = $pdo->query($sql);
        return (bool)$stmt->fetchColumn();
    }

    $sql = "SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'attendance'
              AND column_name = 'admin_clock_out_remark'
            LIMIT 1";
    $stmt = $pdo->query($sql);
    return (bool)$stmt->fetchColumn();
}

try {
    $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    if ($driver !== "mysql" && $driver !== "pgsql") {
        throw new RuntimeException("Unsupported driver: " . $driver);
    }

    if (!admin_clock_out_remark_column_exists($pdo, $driver)) {
        if ($driver === "mysql") {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN admin_clock_out_remark VARCHAR(255) NULL AFTER time_out");
        } else {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN admin_clock_out_remark VARCHAR(255) NULL");
        }
        echo "Added attendance.admin_clock_out_remark column.\n";
    } else {
        echo "attendance.admin_clock_out_remark already exists.\n";
    }

    echo "Migration applied: admin clock-out remarks are ready.\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
