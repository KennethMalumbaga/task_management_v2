<?php

function create_group($pdo, $name, $leader_id, $member_ids = []){
    $stmt = $pdo->prepare("INSERT INTO groups (name, created_by) VALUES (?, ?)");
    $stmt->execute([$name, $leader_id]);
    $group_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'leader')");
    $stmt->execute([$group_id, $leader_id]);

    $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')");
    foreach ($member_ids as $id) {
        if ((int)$id === (int)$leader_id) {
            continue;
        }
        $stmt->execute([$group_id, $id]);
    }

    return $group_id;
}

function get_all_groups($pdo){
    $stmt = $pdo->query("SELECT * FROM groups ORDER BY id DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_group_by_id($pdo, $group_id){
    $stmt = $pdo->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->execute([$group_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_groups_for_user($pdo, $user_id){
    $sql = "SELECT g.*
            FROM groups g
            JOIN group_members gm ON gm.group_id = g.id
            WHERE gm.user_id = ?
            ORDER BY g.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_group_members($pdo, $group_id){
    $sql = "SELECT gm.user_id, gm.role, u.full_name, u.profile_image, u.role AS user_role
            FROM group_members gm
            JOIN users u ON u.id = gm.user_id
            WHERE gm.group_id = ?
            ORDER BY gm.role DESC, u.full_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$group_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_group_leader_id($pdo, $group_id){
    $stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ? AND role = 'leader' LIMIT 1");
    $stmt->execute([$group_id]);
    return $stmt->fetchColumn();
}

function is_user_in_group($pdo, $group_id, $user_id){
    $stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$group_id, $user_id]);
    return (bool)$stmt->fetchColumn();
}
