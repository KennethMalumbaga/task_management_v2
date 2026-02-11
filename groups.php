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
        .member-badges { display: flex; flex-wrap: wrap; gap: 6px; }
        .member-picker { border: 1px solid #d1d5db; border-radius: 10px; overflow: hidden; background: #fff; }
        .member-search { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
        .member-search i { color: #9ca3af; }
        .member-search input { border: none; outline: none; width: 100%; font-size: 14px; }
        .member-list { max-height: 260px; overflow-y: auto; display: none; }
        .member-picker.open .member-list { display: block; }
        .member-picker.open .member-search { border-bottom: 1px solid #e5e7eb; box-shadow: inset 0 0 0 2px #6366f1; border-radius: 10px 10px 0 0; }
        .user-option { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border-bottom: 1px solid #f3f4f6; cursor: pointer; transition: background 0.15s ease; }
        .user-option:last-child { border-bottom: none; }
        .user-option:hover { background: #f8fafc; }
        .user-option.selected { background: #eef2ff; }
        .user-option.disabled { opacity: 0.5; cursor: not-allowed; }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: #e5e7eb; color: #374151; display: flex; align-items: center; justify-content: center; font-weight: 600; overflow: hidden; flex-shrink: 0; }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-name { font-size: 14px; font-weight: 600; color: #111827; }
        .user-meta { font-size: 12px; color: #6b7280; }
        .user-action { color: #4f46e5; font-size: 18px; font-weight: 600; padding: 2px 6px; border-radius: 6px; }
        .user-option.selected .user-action { color: #10b981; }
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
                        <input type="hidden" name="leader_id" id="groupLeaderId" value="">
                        <div class="member-picker" id="groupLeaderPicker">
                            <div class="member-search">
                                <i class="fa fa-search"></i>
                                <input type="text" id="groupLeaderSearch" placeholder="Select Leader">
                            </div>
                            <div class="member-list" id="groupLeaderList">
                                <?php if (!empty($users)) { foreach ($users as $user) { 
                                    $roleText = ucfirst($user['role']);
                                    $profileImage = $user['profile_image'] ?? '';
                                    $hasImage = !empty($profileImage) && $profileImage !== 'default.png' && file_exists('uploads/' . $profileImage);
                                ?>
                                    <div class="user-option" data-id="<?=$user['id']?>" data-name="<?=htmlspecialchars($user['full_name'])?>" data-role="<?=htmlspecialchars($roleText)?>">
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php if ($hasImage): ?>
                                                    <img src="uploads/<?=$profileImage?>" alt="Avatar">
                                                <?php else: ?>
                                                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="user-name"><?=htmlspecialchars($user['full_name'])?></div>
                                                <div class="user-meta"><?=$roleText?></div>
                                            </div>
                                        </div>
                                        <div class="user-action">+</div>
                                    </div>
                                <?php } } ?>
                            </div>
                        </div>
                        <div id="groupLeaderSelected" class="member-badges"></div>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display:block; font-size:14px; font-weight:500; color:#374151; margin-bottom:6px;">Team Members</label>
                        <div class="member-picker" id="groupMemberPicker">
                            <div class="member-search">
                                <i class="fa fa-search"></i>
                                <input type="text" id="groupMemberSearch" placeholder="Search and add members...">
                            </div>
                            <div class="member-list" id="groupMemberList">
                                <?php if (!empty($users)) { foreach ($users as $user) { 
                                    $roleText = ucfirst($user['role']);
                                    $profileImage = $user['profile_image'] ?? '';
                                    $hasImage = !empty($profileImage) && $profileImage !== 'default.png' && file_exists('uploads/' . $profileImage);
                                ?>
                                    <div class="user-option" data-id="<?=$user['id']?>" data-name="<?=htmlspecialchars($user['full_name'])?>" data-role="<?=htmlspecialchars($roleText)?>">
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php if ($hasImage): ?>
                                                    <img src="uploads/<?=$profileImage?>" alt="Avatar">
                                                <?php else: ?>
                                                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="user-name"><?=htmlspecialchars($user['full_name'])?></div>
                                                <div class="user-meta"><?=$roleText?></div>
                                            </div>
                                        </div>
                                        <div class="user-action">+</div>
                                    </div>
                                <?php } } ?>
                            </div>
                        </div>
                        <div id="groupMembersList" class="member-badges"></div>
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
                            <div class="member-badges" style="margin-top:8px;">
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
        var currentGroupLeaderId = "";
        var groupLeaderIdInput = document.getElementById('groupLeaderId');
        var groupLeaderList = document.getElementById('groupLeaderList');
        var groupMemberList = document.getElementById('groupMemberList');
        var groupLeaderPicker = document.getElementById('groupLeaderPicker');
        var groupMemberPicker = document.getElementById('groupMemberPicker');
        var groupLeaderSelected = document.getElementById('groupLeaderSelected');

        function updateGroupMemberLeaderState() {
            var memberOptions = groupMemberList.querySelectorAll('.user-option');
            memberOptions.forEach(function(opt){
                var id = opt.getAttribute('data-id');
                if (currentGroupLeaderId && id === currentGroupLeaderId) {
                    opt.classList.add('disabled');
                } else {
                    opt.classList.remove('disabled');
                }
            });
        }

        function selectGroupLeader(optionEl) {
            if (!optionEl) return;
            var newLeaderId = optionEl.getAttribute('data-id');
            var leaderName = optionEl.getAttribute('data-name') || '';
            if (!newLeaderId) return;

            var leaderOptions = groupLeaderList.querySelectorAll('.user-option');
            leaderOptions.forEach(function(opt){
                opt.classList.remove('selected');
            });
            optionEl.classList.add('selected');
            groupLeaderIdInput.value = newLeaderId;
            if (groupLeaderSelected) {
                groupLeaderSelected.innerHTML = '';
                var badge = document.createElement('div');
                badge.className = 'pill';
                badge.id = 'group_leader_badge_' + newLeaderId;
                badge.innerHTML = leaderName + ' <span style="margin-left:6px; cursor:pointer;" onclick="clearGroupLeader()">&times;</span>';
                groupLeaderSelected.appendChild(badge);
            }

            if (selectedGroupMembers[newLeaderId]) {
                removeGroupMember(newLeaderId);
            }

            currentGroupLeaderId = newLeaderId;
            updateGroupMemberLeaderState();
            closePicker(groupLeaderPicker);
        }

        function clearGroupLeader() {
            currentGroupLeaderId = "";
            groupLeaderIdInput.value = "";
            if (groupLeaderSelected) groupLeaderSelected.innerHTML = "";
            var leaderOptions = groupLeaderList.querySelectorAll('.user-option');
            leaderOptions.forEach(function(opt){
                opt.classList.remove('selected');
            });
            updateGroupMemberLeaderState();
        }

        function addGroupMember(optionEl) {
            if (!optionEl || optionEl.classList.contains('disabled')) return;
            var id = optionEl.getAttribute('data-id');
            var name = optionEl.getAttribute('data-name');
            if (!id || id === "0") return;
            if (selectedGroupMembers[id]) return;
            if (currentGroupLeaderId && id === currentGroupLeaderId) return;

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

            optionEl.classList.add('selected');
        }

        function removeGroupMember(id) {
            delete selectedGroupMembers[id];
            var input = document.getElementById('group_input_' + id);
            if (input) input.remove();
            var badge = document.getElementById('group_badge_' + id);
            if (badge) badge.remove();
            var optionEl = groupMemberList.querySelector('.user-option[data-id="' + id + '"]');
            if (optionEl) optionEl.classList.remove('selected');
        }

        function filterOptions(searchInput, listEl) {
            var query = searchInput.value.toLowerCase();
            var items = listEl.querySelectorAll('.user-option');
            items.forEach(function(item){
                var name = (item.getAttribute('data-name') || '').toLowerCase();
                var role = (item.getAttribute('data-role') || '').toLowerCase();
                if (name.indexOf(query) !== -1 || role.indexOf(query) !== -1) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function openPicker(pickerEl) {
            if (pickerEl) pickerEl.classList.add('open');
        }
        function closePicker(pickerEl) {
            if (pickerEl) pickerEl.classList.remove('open');
        }

        document.getElementById('groupLeaderSearch').addEventListener('focus', function(){
            openPicker(groupLeaderPicker);
        });
        document.getElementById('groupLeaderSearch').addEventListener('click', function(){
            openPicker(groupLeaderPicker);
        });
        document.getElementById('groupLeaderSearch').addEventListener('input', function(){
            openPicker(groupLeaderPicker);
            filterOptions(this, groupLeaderList);
        });

        document.getElementById('groupMemberSearch').addEventListener('focus', function(){
            openPicker(groupMemberPicker);
        });
        document.getElementById('groupMemberSearch').addEventListener('click', function(){
            openPicker(groupMemberPicker);
        });
        document.getElementById('groupMemberSearch').addEventListener('input', function(){
            openPicker(groupMemberPicker);
            filterOptions(this, groupMemberList);
        });

        groupLeaderList.querySelectorAll('.user-option').forEach(function(opt){
            opt.addEventListener('click', function(){
                selectGroupLeader(opt);
            });
        });

        groupMemberList.querySelectorAll('.user-option').forEach(function(opt){
            opt.addEventListener('click', function(){
                addGroupMember(opt);
            });
            var action = opt.querySelector('.user-action');
            if (action) {
                action.addEventListener('click', function(e){
                    e.stopPropagation();
                    addGroupMember(opt);
                });
            }
        });

        document.addEventListener('click', function(e){
            if (groupLeaderPicker && !groupLeaderPicker.contains(e.target)) {
                closePicker(groupLeaderPicker);
            }
            if (groupMemberPicker && !groupMemberPicker.contains(e.target)) {
                closePicker(groupMemberPicker);
            }
        });
    </script>
</body>
</html>
<?php } else {
    $em = "First login";
    header("Location: login.php?error=$em");
    exit();
} ?>
