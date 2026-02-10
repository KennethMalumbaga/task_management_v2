<?php
session_start();
if (isset($_SESSION['id']) && isset($_POST['group_id'])) {
    include "../../DB_connection.php";
    include "../model/Group.php";

    $user_id = $_SESSION['id'];
    $group_id = (int)$_POST['group_id'];

    if (!is_user_in_group($pdo, $group_id, $user_id)) {
        exit();
    }

    $members = get_group_members($pdo, $group_id);
    $leader_id = get_group_leader_id($pdo, $group_id);

    echo '<div style="margin-bottom: 24px;">';
    echo '<h4 style="margin: 0 0 12px; font-size: 12px; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.5px;">Group Members (' . count($members) . ')</h4>';
    
    foreach ($members as $member) {
        $isLeader = ($member['user_id'] == $leader_id);
        $isAdmin = ($member['user_role'] == 'admin');
        
        $roleLabel = "Employee";
        if($isAdmin) $roleLabel = "Admin";
        if($isLeader) $roleLabel = "Project Leader";

        $avatarHtml = '';
        if (!empty($member['profile_image']) && $member['profile_image'] != 'default.png' && file_exists('../../uploads/' . $member['profile_image'])) {
            $avatarHtml = '<img src="uploads/'.$member['profile_image'].'" alt="Profile">';
        } else {
            $avatarHtml = '<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#6366F1; font-weight:700;">' . strtoupper(substr($member['full_name'], 0, 1)) . '</div>';
        }

        ?>
        <div class="group-member-item">
            <div class="group-member-avatar">
                <?=$avatarHtml?>
            </div>
            <div class="group-member-info">
                <div class="group-member-name">
                    <?=htmlspecialchars($member['full_name'])?> 
                    <?php if($member['user_id'] == $_SESSION['id']) echo '<span style="color:#9CA3AF; font-weight:400;">(You)</span>'; ?>
                </div>
                <div class="group-member-role"><?=htmlspecialchars($roleLabel)?></div>
            </div>
             <?php if($isLeader) { ?>
                <i class="fa fa-crown" style="color: #F59E0B; font-size: 12px;" title="Leader"></i>
            <?php } ?>
        </div>
        <?php
    }
    echo '</div>';

}
