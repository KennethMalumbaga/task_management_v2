<?php

require_once __DIR__ . '/../../inc/tenant.php';

function notification_is_pgsql($pdo)
{
    try {
        return strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'pgsql';
    } catch (Throwable $e) {
        return false;
    }
}

function notification_unread_sql($pdo)
{
    if (notification_is_pgsql($pdo)) {
        return "(is_read IS NULL OR is_read = FALSE)";
    }

    return "(is_read IS NULL OR is_read = 0 OR is_read = '0' OR is_read = 'f' OR is_read = 'false')";
}

function notification_read_sql_value($pdo)
{
    return notification_is_pgsql($pdo) ? 'TRUE' : '1';
}

function notification_has_notified_at($pdo)
{
    static $cache = [];
    $key = spl_object_hash($pdo);
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = tenant_column_exists($pdo, 'notifications', 'notified_at');
    }
    return (bool)$cache[$key];
}

function notification_append_scope($pdo, $sql, $params, $joinWord = 'AND')
{
    $scope = tenant_get_scope($pdo, 'notifications', '', $joinWord);
    return [$sql . $scope['sql'], array_merge($params, $scope['params'])];
}

function get_all_my_notifications($pdo, $id){
	$sql = "SELECT * FROM notifications WHERE recipient=?";
	[$sql, $params] = notification_append_scope($pdo, $sql, [$id]);
    if (notification_has_notified_at($pdo)) {
        $sql .= " ORDER BY notified_at DESC, id DESC";
    } else {
        $sql .= " ORDER BY date DESC, id DESC";
    }
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);

	if($stmt->rowCount() > 0){
		$notifications = $stmt->fetchAll();
	}else $notifications = 0;

	return $notifications;
}


function count_notification($pdo, $id){
	$sql = "SELECT COUNT(*) FROM notifications WHERE recipient=? AND " . notification_unread_sql($pdo);
	[$sql, $params] = notification_append_scope($pdo, $sql, [$id]);
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);

	return $stmt->fetchColumn();
}

function insert_notification($pdo, $data){
	// Automatically set the current date when inserting a notification.
    // If `notified_at` exists, also store full timestamp for "x minutes/hours ago" UI.
	// $data should be: [$message, $recipient, $type] or [$message, $recipient, $type, $task_id]
	
	// Check if task_id column exists in the table (PostgreSQL version)
	$has_task_id_column = false;
	try {
        $check_sql = "SELECT 1 FROM information_schema.columns WHERE table_name = 'notifications' AND column_name = 'task_id'";
		$check_stmt = $pdo->query($check_sql);
		$has_task_id_column = (bool)$check_stmt->fetchColumn();
	} catch (Exception $e) {
		$has_task_id_column = false;
	}
	
	// Check if task_id is provided
	$task_id = (count($data) >= 4 && isset($data[3])) ? $data[3] : null;

    $columns = ['message', 'recipient', 'type', 'date'];
    $values = ['?', '?', '?', 'CURRENT_DATE'];
    $params = [$data[0], $data[1], $data[2]];

    if ($has_task_id_column) {
        $columns[] = 'task_id';
        if ($task_id !== null) {
            $values[] = '?';
            $params[] = $task_id;
        } else {
            $values[] = 'NULL';
        }
    }

    if (notification_has_notified_at($pdo)) {
        $columns[] = 'notified_at';
        $values[] = 'CURRENT_TIMESTAMP';
    }

    if (tenant_column_exists($pdo, 'notifications', 'organization_id') && tenant_get_current_org_id()) {
        $columns[] = 'organization_id';
        $values[] = '?';
        $params[] = tenant_get_current_org_id();
    }

    $sql = "INSERT INTO notifications (" . implode(', ', $columns) . ") VALUES(" . implode(', ', $values) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function notification_make_read($pdo, $recipient_id, $notification_id){
	$sql = "UPDATE notifications SET is_read=" . notification_read_sql_value($pdo) . " WHERE id=? AND recipient=?";
	[$sql, $params] = notification_append_scope($pdo, $sql, [$notification_id, $recipient_id]);
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
}

function notification_make_all_read($pdo, $recipient_id){
	$sql = "UPDATE notifications SET is_read=" . notification_read_sql_value($pdo) . " WHERE recipient=?";
	[$sql, $params] = notification_append_scope($pdo, $sql, [$recipient_id]);
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
}
