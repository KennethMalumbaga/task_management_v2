<?php 

function get_all_users($pdo, $role = 'all'){
    if ($role === 'all') {
        $sql = "SELECT * FROM users";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } else {
        $sql = "SELECT * FROM users WHERE role = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$role]);
    }
    $users = $stmt->fetchAll();
	return $users ?: [];
}


function insert_user($pdo, $data){
	$sql = "INSERT INTO users (full_name, username, password, role) VALUES(?,?,?, ?)";
	$stmt = $pdo->prepare($sql);
	$stmt->execute($data);
}

function update_user($pdo, $data){
	$sql = "UPDATE users SET full_name=?, username=?, password=?, role=? WHERE id=? AND role=?";
	$stmt = $pdo->prepare($sql);
	$stmt->execute($data);
}

function user_has_tasks($pdo, $user_id){
	// Only check for active tasks (not completed)
	// Users with only completed tasks can be deleted
	$sql = "SELECT 1 FROM tasks WHERE assigned_to=? AND status != 'completed' LIMIT 1";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([$user_id]);
	return (bool)$stmt->fetchColumn();
}

function delete_user($pdo, $data){
	try {
		$sql = "DELETE FROM users WHERE id=? AND role=?";
		$stmt = $pdo->prepare($sql);
		$stmt->execute($data);
		return true;
	} catch(PDOException $e) {
		return false;
	}
}


function get_user_by_id($pdo, $id){
	$sql = "SELECT * FROM users WHERE id = ? ";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([$id]);
    $user = $stmt->fetch();
	return $user ?: 0;
}

function update_profile($pdo, $data){
	$sql = "UPDATE users SET full_name=?, password=?, bio=?, phone=?, address=?, skills=?, profile_image=?, must_change_password=FALSE WHERE id=? ";
	$stmt = $pdo->prepare($sql);
	$stmt->execute($data);
}

function update_profile_info($pdo, $data){
	$sql = "UPDATE users SET full_name=?, bio=?, phone=?, address=?, skills=?, profile_image=? WHERE id=? ";
	$stmt = $pdo->prepare($sql);
	$stmt->execute($data);
}

function count_users($pdo){
	$sql = "SELECT COUNT(*) FROM users WHERE role='employee'";
	$stmt = $pdo->query($sql);
	return $stmt->fetchColumn();
}