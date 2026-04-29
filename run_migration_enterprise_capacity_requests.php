<?php
include "maintenance_guard.php";
include "DB_connection.php";

enforce_maintenance_script_access();

if (tenant_ensure_enterprise_capacity_requests_table($pdo)) {
    echo "enterprise_capacity_requests table is ready.\n";
    exit();
}

http_response_code(500);
echo "Failed to create enterprise_capacity_requests table.\n";
