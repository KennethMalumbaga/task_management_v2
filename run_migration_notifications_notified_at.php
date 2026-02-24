<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

function notifications_column_exists(PDO $pdo, string $driver, string $column): bool
{
    if ($driver === "mysql") {
        $sql = "SELECT 1
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'notifications'
                  AND column_name = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$column]);
        return (bool)$stmt->fetchColumn();
    }

    $sql = "SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'notifications'
              AND column_name = ?
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$column]);
    return (bool)$stmt->fetchColumn();
}

function notifications_index_exists(PDO $pdo, string $driver, string $indexName): bool
{
    if ($driver === "mysql") {
        $sql = "SELECT 1
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = 'notifications'
                  AND index_name = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$indexName]);
        return (bool)$stmt->fetchColumn();
    }

    $sql = "SELECT 1
            FROM pg_indexes
            WHERE schemaname = 'public'
              AND tablename = 'notifications'
              AND indexname = ?
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$indexName]);
    return (bool)$stmt->fetchColumn();
}

try {
    $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    if ($driver !== "mysql" && $driver !== "pgsql") {
        throw new RuntimeException("Unsupported driver: " . $driver);
    }

    if (!notifications_column_exists($pdo, $driver, "notified_at")) {
        if ($driver === "mysql") {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN notified_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER date");
        } else {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN notified_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
        }
        echo "Added notifications.notified_at column.\n";
    } else {
        echo "notifications.notified_at already exists.\n";
    }

    if ($driver === "mysql") {
        $pdo->exec("UPDATE notifications
                    SET notified_at = CAST(CONCAT(`date`, ' 00:00:00') AS DATETIME)
                    WHERE notified_at IS NULL AND `date` IS NOT NULL");
    } else {
        $pdo->exec("UPDATE notifications
                    SET notified_at = \"date\"::timestamp
                    WHERE notified_at IS NULL AND \"date\" IS NOT NULL");
    }
    echo "Backfilled notifications.notified_at values.\n";

    $indexName = "idx_notifications_recipient_notified_at";
    if (!notifications_index_exists($pdo, $driver, $indexName)) {
        if ($driver === "mysql") {
            $pdo->exec("CREATE INDEX idx_notifications_recipient_notified_at ON notifications (recipient, notified_at)");
        } else {
            $pdo->exec("CREATE INDEX idx_notifications_recipient_notified_at ON notifications (recipient, notified_at DESC)");
        }
        echo "Created index $indexName.\n";
    } else {
        echo "Index $indexName already exists.\n";
    }

    echo "Migration applied: notifications timestamp support is ready.\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}

