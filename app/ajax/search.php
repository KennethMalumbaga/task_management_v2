<?php 
session_start();
require_once "../../inc/performance.php";
performance_monitor_request('messages.search');

if (isset($_SESSION['id'])) {
    
    if (isset($_POST['key'])) {
       $currentUserId = (int)$_SESSION['id'];
       session_write_close();

       include "../../DB_connection.php";
       require_once "../../inc/tenant.php";
       include "../model/user.php";
       include "../model/Message.php";
       include "../model/Group.php";
       include "../model/GroupMessage.php";
       include "../model/ChatVisibility.php";
       include "../helpers/chat_sidebar.php";

       $key = "%{$_POST['key']}%";
     
       $orgId = tenant_get_current_org_id();
       if ($orgId && tenant_table_exists($pdo, 'organization_members')) {
           $sql = "SELECT u.*
                   FROM users u
                   INNER JOIN organization_members om ON om.user_id = u.id
                   WHERE om.organization_id = ?
                     AND (LOWER(u.full_name) LIKE LOWER(?) OR LOWER(u.username) LIKE LOWER(?))";
           $params = [(int)$orgId, $key, $key];
       } else {
           $sql = "SELECT * FROM users
                   WHERE (LOWER(full_name) LIKE LOWER(?) OR LOWER(username) LIKE LOWER(?))";
           $params = [$key, $key];
           $scope = tenant_get_scope($pdo, 'users');
           $sql .= $scope['sql'];
           $params = array_merge($params, $scope['params']);
       }
       $stmt = $pdo->prepare($sql);
       $stmt->execute($params);
       $matchedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

       $groupSql = "SELECT g.*
                    FROM `groups` g
                    INNER JOIN group_members gm ON g.id = gm.group_id
                     WHERE gm.user_id = ?
                       AND LOWER(g.name) LIKE LOWER(?)";
       $groupParams = [$currentUserId, $key];
       $scope = tenant_get_scope($pdo, 'groups', 'g');
       $groupSql .= $scope['sql'];
       $groupParams = array_merge($groupParams, $scope['params']);
       $scope = tenant_get_scope($pdo, 'group_members', 'gm');
       $groupSql .= $scope['sql'] . "
                     ORDER BY g.id DESC";
       $groupParams = array_merge($groupParams, $scope['params']);
       $groupStmt = $pdo->prepare($groupSql);
       $groupStmt->execute($groupParams);
       $matchedGroups = $groupStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

       $sidebarData = chat_sidebar_build_data($pdo, $currentUserId, $matchedUsers, $matchedGroups);
       $usersHtml = chat_sidebar_render_users(
           $sidebarData['users'],
           $currentUserId,
           '<div style="padding: 20px; text-align: center; color: var(--text-gray); font-size: 13px;"><i class="fa fa-user-times"></i> No user found</div>'
       );
       $groupsHtml = chat_sidebar_render_groups(
           $pdo,
           $sidebarData['groups'],
           $currentUserId,
           '<div style="padding: 12px; color:#9CA3AF; font-size:13px;">No groups found</div>'
       );

       echo json_encode([
           'users' => $usersHtml,
           'groups' => $groupsHtml
       ]);
    }
}

