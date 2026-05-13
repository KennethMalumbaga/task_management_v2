<?php

session_start();
require_once "../../inc/csrf.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    exit;
}

if (!csrf_verify('chat_ajax_actions', $_POST['csrf_token'] ?? null, false)) {
    http_response_code(403);
    exit;
}

$currentUserId = (int)$_SESSION['id'];
$chatType = trim((string)($_POST['chat_type'] ?? ''));
$groupId = (int)($_POST['group_id'] ?? 0);
$otherUserId = (int)($_POST['user_id'] ?? 0);
session_write_close();

include "../../DB_connection.php";
include "../model/user.php";
include "../model/Group.php";
include "../model/Message.php";
include "../model/GroupMessage.php";
include "../model/ChatAssets.php";

if ($chatType === 'group') {
    if ($groupId <= 0 || !is_user_in_group($pdo, $groupId, $currentUserId)) {
        exit;
    }

    $group = get_group_by_id($pdo, $groupId);
    $members = get_group_members($pdo, $groupId);
    $leaderId = get_group_leader_id($pdo, $groupId);
    $assets = get_group_chat_assets($pdo, $groupId);
    ?>
    <div class="chat-info-section">
        <div class="chat-info-section-label">Group Members (<?= (int)count($members) ?>)</div>
        <?php foreach ($members as $member) { ?>
            <?php
            $isLeader = ((int)($member['user_id'] ?? 0) === (int)$leaderId);
            $isAdmin = ((string)($member['user_role'] ?? '') === 'admin');
            $roleLabel = 'Employee';
            if ($isAdmin) {
                $roleLabel = 'Admin';
            }
            if ($isLeader) {
                $roleLabel = 'Project Leader';
            }

            $memberAvatar = '';
            if (!empty($member['profile_image']) && $member['profile_image'] !== 'default.png' && file_exists('../../uploads/' . $member['profile_image'])) {
                $memberAvatar = '<img src="uploads/' . htmlspecialchars($member['profile_image'], ENT_QUOTES, 'UTF-8') . '" alt="Profile">';
            } else {
                $memberAvatar = '<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:var(--primary-dark); font-weight:700;">'
                    . htmlspecialchars(strtoupper(substr((string)$member['full_name'], 0, 1)), ENT_QUOTES, 'UTF-8')
                    . '</div>';
            }
            ?>
            <div class="group-member-item">
                <div class="group-member-avatar"><?= $memberAvatar ?></div>
                <div class="group-member-info">
                    <div class="group-member-name">
                        <?= htmlspecialchars((string)$member['full_name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ((int)($member['user_id'] ?? 0) === $currentUserId) { ?>
                            <span style="color:#9CA3AF; font-weight:400;">(You)</span>
                        <?php } ?>
                    </div>
                    <div class="group-member-role"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php if ($isLeader) { ?>
                    <i class="fa fa-crown" style="color: #F59E0B; font-size: 12px;" title="Leader"></i>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <div class="chat-info-section">
        <div class="chat-info-section-label">Media and Files</div>
        <?= render_chat_media_files_browser($assets, 'No shared media in this group yet.', 'No shared files in this group yet.') ?>
    </div>
    <?php
    exit;
}

if ($otherUserId <= 0 || $otherUserId === $currentUserId) {
    exit;
}

$otherUser = get_user_by_id($pdo, $otherUserId);
if (empty($otherUser) || !is_array($otherUser)) {
    exit;
}

$presenceMap = get_users_clocked_in_map($pdo, [$otherUserId]);
$otherUser['is_online'] = !empty($presenceMap[$otherUserId]);
$assets = get_direct_chat_assets($pdo, $currentUserId, $otherUserId);
?>
<div class="chat-info-user-card">
    <div class="chat-info-user-avatar">
        <?= chat_user_avatar_html($otherUser, false) ?>
    </div>
    <div class="chat-info-user-copy">
        <div class="chat-info-user-name"><?= htmlspecialchars((string)($otherUser['full_name'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="chat-info-user-presence"><?= chat_user_presence_html(!empty($otherUser['is_online'])) ?></div>
    </div>
</div>

<div class="chat-info-section">
    <div class="chat-info-section-label">Media and Files</div>
    <?= render_chat_media_files_browser($assets, 'No shared media in this chat yet.', 'No shared files in this chat yet.') ?>
</div>
