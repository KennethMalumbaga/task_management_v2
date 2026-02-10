<?php
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    include "app/model/user.php";
    include "app/model/Group.php";

    $users = get_all_users($pdo, 'employee');
    $groups = get_all_groups($pdo);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Groups | TaskFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .page-wrap { padding: 20px; }
        .card { background: white; border-radius: 12px; border: 1px solid #e5e7eb; padding: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .pill { background: #EEF2FF; color: #4F46E5; padding: 4px 10px; border-radius: 999px; font-size: 12px; }
        .member-list { display: flex; flex-wrap: wrap; gap: 6px; }
    </style>
</head>
<body>
    <?php include "inc/new_sidebar.php"; ?>

    <div class="dash-main page-wrap">
        <h2 style="margin: 0 0 20px; font-weight: 700; color: #111827;">Groups / Teams</h2>

        <?php if (isset($_GET['error'])) { ?>
            <div style="background: #FEF2F2; color: #991B1B; padding: 10px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;">
                <?php echo stripcslashes($_GET['error']); ?>
            </div>
        <?php } ?>
        <?php if (isset($_GET['success'])) { ?>
            <div style="background: #ECFDF5; color: #065F46; padding: 10px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;">
                <?php echo stripcslashes($_GET['success']); ?>
            </div>
        <?php } ?>

        <div class="grid">
            <div class="card">
                <h3 style="margin-top: 0;">Create Group</h3>
                <form action="app/add-group.php" method="POST">
                    <div style="margin-bottom: 15px;">
                        <label style="display:block; font-size:14px; font-weight:500; color:#374151; margin-bottom:6px;">Group Name</label>
                        <input type="text" name="group_name" required style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display:block; font-size:14px; font-weight:500; color:#374151; margin-bottom:6px;">Team Leader</label>
                        <select name="leader_id" id="groupLeaderSelect" required style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px; background:white;">
                            <option value="">Select Leader</option>
                            <?php if (!empty($users)) { foreach ($users as $user) { ?>
                                <option value="<?=$user['id']?>"><?=htmlspecialchars($user['full_name'])?></option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display:block; font-size:14px; font-weight:500; color:#374151; margin-bottom:6px;">Team Members</label>
                        <div style="display:flex; gap:10px; margin-bottom:10px;">
                            <select id="groupMemberSelect" style="flex:1; padding:10px; border:1px solid #d1d5db; border-radius:6px; background:white;">
                                <option value="0">Add Team Member</option>
                                <?php if (!empty($users)) { foreach ($users as $user) { ?>
                                    <option value="<?=$user['id']?>" data-name="<?=htmlspecialchars($user['full_name'])?>"><?=htmlspecialchars($user['full_name'])?></option>
                                <?php } } ?>
                            </select>
                            <button type="button" id="addGroupMemberBtn" style="background:white; border:1px solid #d1d5db; border-radius:6px; padding:0 15px; cursor:pointer;">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                        <div id="groupMembersList" class="member-list"></div>
                        <div id="groupMemberInputs"></div>
                    </div>
                    <button type="submit" style="padding:10px 16px; border:none; border-radius:8px; background:#6366F1; color:white; font-weight:500;">Create Group</button>
                </form>
            </div>

            <div class="card">
                <h3 style="margin-top:0;">Existing Groups</h3>
                <?php if (!empty($groups)) { foreach ($groups as $group) { 
                    $members = get_group_members($pdo, $group['id']);
                    $leader = '';
                    $memberNames = [];
                    foreach ($members as $m) {
                        if ($m['role'] === 'leader') {
                            $leader = $m['full_name'];
                        } else {
                            $memberNames[] = $m['full_name'];
                        }
                    }
                ?>
                    <div style="border:1px solid #e5e7eb; border-radius:10px; padding:12px; margin-bottom:10px; position: relative;">
                        <div style="font-weight:600; color:#111827; padding-right: 30px;"><?=htmlspecialchars($group['name'])?></div>
                        <form action="app/delete-group.php" method="POST" style="position: absolute; top: 10px; right: 10px;" onsubmit="return confirm('Are you sure you want to delete this group?');">
                            <input type="hidden" name="id" value="<?=$group['id']?>">
                            <button type="submit" style="background: none; border: none; color: #EF4444; cursor: pointer; padding: 4px;">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                        <div style="font-size:13px; color:#6B7280; margin-top:4px;">Leader: <?=htmlspecialchars($leader ?: 'Not set')?></div>
                        <?php if (!empty($memberNames)) { ?>
                            <div class="member-list" style="margin-top:8px;">
                                <?php foreach ($memberNames as $mn) { ?>
                                    <span class="pill"><?=htmlspecialchars($mn)?></span>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div style="font-size:12px; color:#9CA3AF; margin-top:6px;">No members</div>
                        <?php } ?>
                    </div>
                <?php } } else { ?>
                    <div style="color:#9CA3AF;">No groups yet.</div>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        var selectedGroupMembers = {};
        document.getElementById('addGroupMemberBtn').addEventListener('click', function() {
            var select = document.getElementById('groupMemberSelect');
            var id = select.value;
            var name = select.options[select.selectedIndex].getAttribute('data-name');
            var leaderId = document.getElementById('groupLeaderSelect').value;
            if (id == "0") return;
            if (selectedGroupMembers[id]) return;
            if (leaderId && id === leaderId) {
                alert('Leader already selected.');
                select.value = "0";
                return;
            }
            var badge = document.createElement('span');
            badge.className = 'pill';
            badge.id = 'group_badge_' + id;
            badge.innerHTML = name + ' <span style="margin-left:6px; cursor:pointer;" onclick="removeGroupMember(' + id + ')">&times;</span>';
            document.getElementById('groupMembersList').appendChild(badge);

            selectedGroupMembers[id] = name;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'member_ids[]';
            input.value = id;
            input.id = 'group_input_' + id;
            document.getElementById('groupMemberInputs').appendChild(input);
            select.value = "0";
        });

        function removeGroupMember(id) {
            delete selectedGroupMembers[id];
            var input = document.getElementById('group_input_' + id);
            if (input) input.remove();
            var badge = document.getElementById('group_badge_' + id);
            if (badge) badge.remove();
        }
    </script>
</body>
</html>
<?php } else {
    $em = "First login";
    header("Location: login.php?error=$em");
    exit();
} ?>
