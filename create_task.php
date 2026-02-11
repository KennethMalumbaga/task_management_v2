<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    include "app/model/user.php";
    include "app/model/Task.php";
    include "app/model/Group.php";

    // Only get employees (exclude admin)
    $users = get_all_users($pdo, 'employee');
    $groups = get_all_groups($pdo);
 ?>
<!DOCTYPE html>
<html>
<head>
	<title>Create Task | TaskFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="css/dashboard.css">
    <style>
        @media (max-width: 768px) {
            .dash-main {
                padding: 70px 15px 30px !important; /* Extra top for mobile header */
                display: block !important;
                min-height: auto !important;
                background: white !important; /* Cleaner on mobile */
            }
            .dash-main > div {
                max-width: 100% !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            .dash-main > div > div:first-child,
            .dash-main form {
                padding: 15px !important;
            }
            .dash-main form .form-actions {
                flex-direction: column !important;
            }
            .dash-main form .form-actions button,
            .dash-main form .form-actions a {
                width: 100% !important;
                flex: none !important;
            }
        }
        .member-picker {
            border: 1px solid #d1d5db;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }
        .member-search {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .member-search i {
            color: #9ca3af;
        }
        .member-search input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 14px;
        }
        .member-list {
            max-height: 260px;
            overflow-y: auto;
            display: none;
        }
        .member-picker.open .member-list {
            display: block;
        }
        .member-picker.open .member-search {
            border-bottom: 1px solid #e5e7eb;
            box-shadow: inset 0 0 0 2px #6366f1;
            border-radius: 10px 10px 0 0;
        }
        .user-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .user-option:last-child {
            border-bottom: none;
        }
        .user-option:hover {
            background: #f8fafc;
        }
        .user-option.selected {
            background: #eef2ff;
        }
        .user-option.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #374151;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            overflow: hidden;
            flex-shrink: 0;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }
        .user-meta {
            font-size: 12px;
            color: #6b7280;
        }
        .user-action {
            color: #4f46e5;
            font-size: 18px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 6px;
        }
        .user-option.selected .user-action {
            color: #10b981;
        }
        .member-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .member-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #eef2ff;
            color: #4f46e5;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 13px;
        }
        .member-badge button {
            background: transparent;
            border: none;
            cursor: pointer;
            color: #6b7280;
            font-size: 16px;
            line-height: 1;
        }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main" style="background: #f3f4f6; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
        
        <div style="background: white; width: 100%; max-width: 600px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); overflow: hidden;">
            
            <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                <h2 style="margin: 0; font-size: 20px; font-weight: 600; color: #111827;">Create Task</h2>
            </div>
            
            <form action="app/add-task.php" method="POST" enctype="multipart/form-data" style="padding: 24px;">
                
                <?php if (isset($_GET['error'])) {?>
                    <div style="background: #FEF2F2; color: #991B1B; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                        <?php echo stripcslashes($_GET['error']); ?>
                    </div>
                <?php } ?>
                
                <?php if (isset($_GET['success'])) {?>
                    <div style="background: #ECFDF5; color: #065F46; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
                        <?php echo stripcslashes($_GET['success']); ?>
                    </div>
                <?php } ?>

                <!-- Title -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Task Title <span style="color: red;">*</span></label>
                    <input type="text" name="title" required placeholder="Enter task title" 
                           style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; outline: none; transition: border-color 0.2s;">
                </div>

                <!-- Description -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Description <span style="color: red;">*</span></label>
                    <textarea name="description" required rows="4" placeholder="Enter task description"
                              style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; outline: none; resize: vertical; transition: border-color 0.2s;"></textarea>
                </div>

                <!-- Assignment Mode -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Assignment Mode</label>
                    <div style="display: flex; gap: 12px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 14px;">
                            <input type="radio" name="assignment_mode" value="manual" checked onchange="toggleAssignmentMode()"> Manual (Leader + Members)
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 14px;">
                            <input type="radio" name="assignment_mode" value="group" onchange="toggleAssignmentMode()"> Select Group/Team
                        </label>
                    </div>
                </div>

                <!-- Group Selection -->
                <div id="groupSection" style="margin-bottom: 20px; display: none;">
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Group/Team</label>
                    <select name="group_id" id="groupSelect" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; outline: none; background: white;">
                        <option value="0">Select Group</option>
                        <?php if (!empty($groups)) { foreach ($groups as $group) { 
                            $members = get_group_members($pdo, $group['id']);
                            $leaderName = '';
                            $memberNames = [];
                            foreach ($members as $m) {
                                if ($m['role'] === 'leader') {
                                    $leaderName = $m['full_name'];
                                } else {
                                    $memberNames[] = $m['full_name'];
                                }
                            }
                            $memberText = !empty($memberNames) ? implode(', ', $memberNames) : 'No members';
                        ?>
                            <option value="<?=$group['id']?>" data-leader="<?=htmlspecialchars($leaderName)?>" data-members="<?=htmlspecialchars($memberText)?>">
                                <?=htmlspecialchars($group['name'])?>
                            </option>
                        <?php } } ?>
                    </select>
                    <div id="groupInfo" style="margin-top: 8px; font-size: 13px; color: #6B7280;"></div>
                </div>

                <!-- Project Leader -->
                <div id="manualLeaderSection" style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Project Leader (Optional)</label>
                    <input type="hidden" name="leader_id" id="leaderIdInput" value="0">
                    <div class="member-picker">
                        <div class="member-search">
                            <i class="fa fa-search"></i>
                            <input type="text" id="leaderSearch" placeholder="Search and select leader...">
                        </div>
                        <div class="member-list" id="leaderList">
                            <?php if ($users != 0) { foreach ($users as $user) { 
                                $pendingCount = count_my_active_tasks($pdo, $user['id']);
                                $pendingText = $pendingCount > 0 ? " • Pending: $pendingCount" : "";
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
                                            <div class="user-meta"><?=$roleText . $pendingText?></div>
                                        </div>
                                    </div>
                                    <div class="user-action">+</div>
                                </div>
                            <?php } } ?>
                        </div>
                    </div>
                    <div id="leaderSelected" class="member-badges"></div>
                </div>

                <!-- Team Members -->
                <div id="manualMembersSection" style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Team Members (Optional)</label>
                    <div class="member-picker">
                        <div class="member-search">
                            <i class="fa fa-search"></i>
                            <input type="text" id="memberSearch" placeholder="Search and add members...">
                        </div>
                        <div class="member-list" id="memberList">
                            <?php if ($users != 0) { foreach ($users as $user) { 
                                $pendingCount = count_my_active_tasks($pdo, $user['id']);
                                $pendingText = $pendingCount > 0 ? " • Pending: $pendingCount" : "";
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
                                            <div class="user-meta"><?=$roleText . $pendingText?></div>
                                        </div>
                                    </div>
                                    <div class="user-action">+</div>
                                </div>
                            <?php } } ?>
                        </div>
                    </div>

                    <div id="membersList" class="member-badges"></div>
                    <div id="memberInputs"></div>
                </div>

                <!-- Due Date -->
                <div style="margin-bottom: 20px;">
                     <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Due Date (Optional)</label>
                     <input type="date" name="due_date" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; outline: none;">
                </div>

                 <!-- File -->
                 <div style="margin-bottom: 30px;">
                     <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Attachment (Optional) <span style="font-size: 12px; color: #6b7280; font-weight: normal;">(up to 100MB)</span></label>
                     <input type="file" name="template_file" style="width: 100%; font-size: 14px;">
                </div>

                <!-- Actions -->
                <div class="form-actions" style="display: flex; gap: 10px;">
                    <a href="tasks.php" style="flex: 1; text-align: center; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; color: #374151; text-decoration: none; font-weight: 500; background: white;">Cancel</a>
                    <button type="submit" style="flex: 1; padding: 12px; border: none; border-radius: 8px; background: #6366F1; color: white; font-weight: 500; cursor: pointer; font-size: 14px;">Create Task</button>
                </div>

            </form>
        </div>

    </div>

    <!-- Script for Members -->
    <script>
        var selectedMembers = {};
        var currentLeaderId = "0";
        var leaderIdInput = document.getElementById('leaderIdInput');
        var leaderList = document.getElementById('leaderList');
        var memberList = document.getElementById('memberList');
        var leaderPicker = leaderList.closest('.member-picker');
        var memberPicker = memberList.closest('.member-picker');
        var leaderSelected = document.getElementById('leaderSelected');

        function toggleAssignmentMode() {
            var mode = document.querySelector('input[name="assignment_mode"]:checked').value;
            var groupSection = document.getElementById('groupSection');
            var leaderSection = document.getElementById('manualLeaderSection');
            var membersSection = document.getElementById('manualMembersSection');
            if (mode === 'group') {
                groupSection.style.display = 'block';
                leaderSection.style.display = 'none';
                membersSection.style.display = 'none';
                clearLeader();
                clearMembers();
            } else {
                groupSection.style.display = 'none';
                leaderSection.style.display = 'block';
                membersSection.style.display = 'block';
                document.getElementById('groupSelect').value = "0";
                document.getElementById('groupInfo').textContent = "";
            }
        }

        document.getElementById('groupSelect').addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];
            if (!opt || this.value === "0") {
                document.getElementById('groupInfo').textContent = "";
                return;
            }
            var leader = opt.getAttribute('data-leader') || 'Not set';
            var members = opt.getAttribute('data-members') || 'No members';
            document.getElementById('groupInfo').textContent = "Leader: " + leader + " | Members: " + members;
        });

        function clearMembers() {
            selectedMembers = {};
            document.getElementById('membersList').innerHTML = "";
            document.getElementById('memberInputs').innerHTML = "";
            var memberOptions = memberList.querySelectorAll('.user-option');
            memberOptions.forEach(function(opt){
                opt.classList.remove('selected');
            });
        }

        window.clearLeader = function() {
            currentLeaderId = "0";
            leaderIdInput.value = "0";
            if (leaderSelected) leaderSelected.innerHTML = "";
            var leaderOptions = leaderList.querySelectorAll('.user-option');
            leaderOptions.forEach(function(opt){
                opt.classList.remove('selected');
            });
            updateMemberLeaderState();
        }
        
        function updateMemberLeaderState() {
            var memberOptions = memberList.querySelectorAll('.user-option');
            memberOptions.forEach(function(opt){
                var id = opt.getAttribute('data-id');
                if (currentLeaderId !== "0" && id === currentLeaderId) {
                    opt.classList.add('disabled');
                } else {
                    opt.classList.remove('disabled');
                }
            });
        }

        function selectLeader(optionEl) {
            if (!optionEl) return;
            var newLeaderId = optionEl.getAttribute('data-id');
            var leaderName = optionEl.getAttribute('data-name') || '';
            if (!newLeaderId) return;

            var leaderOptions = leaderList.querySelectorAll('.user-option');
            leaderOptions.forEach(function(opt){
                opt.classList.remove('selected');
            });

            optionEl.classList.add('selected');
            leaderIdInput.value = newLeaderId;
            if (leaderSelected) {
                leaderSelected.innerHTML = '';
                var badge = document.createElement('div');
                badge.className = 'member-badge';
                badge.id = 'leader_badge_' + newLeaderId;
                badge.innerHTML = leaderName + ' <button type="button" onclick="clearLeader()">&times;</button>';
                leaderSelected.appendChild(badge);
            }

            if (selectedMembers[newLeaderId]) {
                removeMember(newLeaderId);
            }

            currentLeaderId = newLeaderId;
            updateMemberLeaderState();
        }

        function addMember(optionEl) {
            if (!optionEl || optionEl.classList.contains('disabled')) return;
            var id = optionEl.getAttribute('data-id');
            var name = optionEl.getAttribute('data-name');
            if (!id || id === "0") return;
            if (selectedMembers[id]) return;
            if (id === currentLeaderId) return;

            addMemberBadge(id, name);
            selectedMembers[id] = name;

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'member_ids[]';
            input.value = id;
            input.id = 'input_' + id;
            document.getElementById('memberInputs').appendChild(input);

            optionEl.classList.add('selected');
        }

        function addMemberBadge(id, name) {
            var badge = document.createElement('div');
            badge.id = 'badge_' + id;
            badge.className = 'member-badge';
            badge.innerHTML = `${name} <button type="button" onclick="removeMember('${id}')">&times;</button>`;
            document.getElementById('membersList').appendChild(badge);
        }

        window.removeMember = function(id) {
            delete selectedMembers[id];
            var inputEl = document.getElementById('input_' + id);
            if (inputEl) inputEl.remove();
            var badgeEl = document.getElementById('badge_' + id);
            if (badgeEl) badgeEl.remove();
            var optionEl = memberList.querySelector('.user-option[data-id="' + id + '"]');
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

        document.getElementById('leaderSearch').addEventListener('focus', function(){
            openPicker(leaderPicker);
        });
        document.getElementById('leaderSearch').addEventListener('click', function(){
            openPicker(leaderPicker);
        });
        document.getElementById('leaderSearch').addEventListener('input', function(){
            openPicker(leaderPicker);
            filterOptions(this, leaderList);
        });

        document.getElementById('memberSearch').addEventListener('focus', function(){
            openPicker(memberPicker);
        });
        document.getElementById('memberSearch').addEventListener('click', function(){
            openPicker(memberPicker);
        });
        document.getElementById('memberSearch').addEventListener('input', function(){
            openPicker(memberPicker);
            filterOptions(this, memberList);
        });

        leaderList.querySelectorAll('.user-option').forEach(function(opt){
            opt.addEventListener('click', function(){
                selectLeader(opt);
                closePicker(leaderPicker);
            });
        });
        memberList.querySelectorAll('.user-option').forEach(function(opt){
            opt.addEventListener('click', function(e){
                if (opt.classList.contains('disabled')) return;
                if (e.target && e.target.closest('button')) return;
                addMember(opt);
            });
            var action = opt.querySelector('.user-action');
            if (action) {
                action.addEventListener('click', function(e){
                    e.stopPropagation();
                    addMember(opt);
                });
            }
        });

        document.addEventListener('click', function(e){
            if (leaderPicker && !leaderPicker.contains(e.target)) {
                closePicker(leaderPicker);
            }
            if (memberPicker && !memberPicker.contains(e.target)) {
                closePicker(memberPicker);
            }
        });

        // Initialize mode on load
        toggleAssignmentMode();
    </script>

</body>
</html>
<?php }else{ 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
?>

