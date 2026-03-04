<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    include "app/model/user.php";
    include "app/model/Task.php";
    include "app/model/Group.php";
    require_once "inc/csrf.php";

    // Only get employees (exclude admin)
    $users = get_all_users($pdo, 'employee');
    $groups = get_all_groups($pdo);
    $show_duplicate_modal = isset($_GET['duplicate_title']) && $_GET['duplicate_title'] == '1';
    $prefill_due_date = '';
    if (isset($_GET['due_date'])) {
        $dueDateRaw = trim((string)$_GET['due_date']);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDateRaw) === 1) {
            $dueDateObj = DateTime::createFromFormat('Y-m-d', $dueDateRaw);
            $dateErrors = DateTime::getLastErrors();
            $hasParseErrors = is_array($dateErrors) && (
                !empty($dateErrors['warning_count']) || !empty($dateErrors['error_count'])
            );
            if ($dueDateObj instanceof DateTime && !$hasParseErrors && $dueDateObj->format('Y-m-d') === $dueDateRaw) {
                $prefill_due_date = $dueDateRaw;
            }
        }
    }
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
    <link rel="stylesheet" href="css/create-task-page.css">
</head>
<body class="create-task-page">
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main create-task-main">
        
        <div class="create-task-shell">
            <form action="app/add-task.php" method="POST" enctype="multipart/form-data" class="create-task-form">
                <?= csrf_field('create_task_form') ?>
                <?php if (isset($_GET['error'])) {?>
                    <div class="create-task-alert error">
                        <?php echo stripcslashes($_GET['error']); ?>
                    </div>
                <?php } ?>
                
                <?php if (isset($_GET['success'])) {?>
                    <div class="create-task-alert success">
                        <?php echo stripcslashes($_GET['success']); ?>
                    </div>
                <?php } ?>

                <!-- Title -->
                <div class="field">
                    <label class="field-label">Task Title <span class="required-dot"></span></label>
                    <input type="text" name="title" required placeholder="e.g. Design onboarding screens">
                </div>

                <!-- Description -->
                <div class="field">
                    <label class="field-label">Description <span class="required-dot"></span></label>
                    <textarea name="description" required rows="5" placeholder="Describe the task scope, goals, and any relevant context..."
                              class="task-textarea"></textarea>
                </div>

                <div class="divider"></div>

                <!-- Assignment Mode -->
                <div class="field">
                    <label class="field-label">Assignment Mode</label>
                    <div class="toggle-group">
                        <label class="toggle-option">
                            <input type="radio" name="assignment_mode" value="manual" checked onchange="toggleAssignmentMode()">
                            <span class="toggle-label"><span class="radio-circle"></span>Manual (Leader + Members)</span>
                        </label>
                        <label class="toggle-option">
                            <input type="radio" name="assignment_mode" value="group" onchange="toggleAssignmentMode()">
                            <span class="toggle-label"><span class="radio-circle"></span>Select Group / Team</span>
                        </label>
                    </div>
                </div>

                <!-- Group Selection -->
                <div id="groupSection" class="field" style="display: none;">
                    <label class="field-label">Group / Team</label>
                    <input type="hidden" name="group_id" id="groupSelect" value="0">
                    <div class="member-picker" id="groupPicker">
                        <div class="search-input-wrap member-search">
                            <span class="search-icon"><i class="fa fa-search"></i></span>
                            <input type="text" id="groupSearch" placeholder="Search and select group...">
                        </div>
                        <div class="member-list" id="groupList">
                            <?php if (!empty($groups)) { foreach ($groups as $group) {
                                $members = get_group_members($pdo, $group['id']);
                                $leaderName = 'Not set';
                                $memberNames = [];
                                foreach ($members as $m) {
                                    if ($m['role'] === 'leader') {
                                        $leaderName = $m['full_name'];
                                    }
                                    $memberNames[] = $m['full_name'];
                                }
                                $memberCount = count($members);
                                $searchMeta = trim($group['name'] . ' ' . $leaderName . ' ' . implode(' ', $memberNames));
                            ?>
                                <div class="user-option group-option"
                                     data-id="<?=$group['id']?>"
                                     data-name="<?=htmlspecialchars($group['name'])?>"
                                     data-leader="<?=htmlspecialchars($leaderName)?>"
                                     data-member-count="<?=$memberCount?>"
                                     data-meta="<?=htmlspecialchars($searchMeta)?>">
                                    <div class="user-info">
                                        <div class="group-avatar-stack">
                                            <?php
                                            $previewMembers = array_slice($members, 0, 3);
                                            foreach ($previewMembers as $gm) {
                                                $groupProfileImage = $gm['profile_image'] ?? '';
                                                $groupHasImage = !empty($groupProfileImage) && $groupProfileImage !== 'default.png' && file_exists('uploads/' . $groupProfileImage);
                                            ?>
                                                <div class="group-avatar" title="<?=htmlspecialchars($gm['full_name'])?>">
                                                    <?php if ($groupHasImage): ?>
                                                        <img src="uploads/<?=$groupProfileImage?>" alt="<?=htmlspecialchars($gm['full_name'])?>">
                                                    <?php else: ?>
                                                        <?= strtoupper(substr($gm['full_name'], 0, 1)) ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php } ?>
                                            <?php if ($memberCount > 3): ?>
                                                <div class="group-avatar group-avatar-more">+<?=$memberCount - 3?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="user-name"><?=htmlspecialchars($group['name'])?></div>
                                            <div class="user-meta">Leader: <?=htmlspecialchars($leaderName)?> | <?=$memberCount?> member<?= $memberCount === 1 ? '' : 's' ?></div>
                                        </div>
                                    </div>
                                    <div class="user-action">+</div>
                                </div>
                            <?php } } else { ?>
                                <div class="member-empty">No groups available.</div>
                            <?php } ?>
                        </div>
                    </div>
                    <div id="groupSelected" class="member-badges"></div>
                    <div id="groupInfo" class="field-help"></div>
                </div>

                <!-- Project Leader -->
                <div id="manualLeaderSection" class="field">
                    <label class="field-label">Project Leader</label>
                    <input type="hidden" name="leader_id" id="leaderIdInput" value="0">
                    <div class="member-picker">
                        <div class="search-input-wrap member-search">
                            <span class="search-icon"><i class="fa fa-search"></i></span>
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
                <div id="manualMembersSection" class="field">
                    <label class="field-label">Team Members</label>
                    <div class="member-picker">
                        <div class="search-input-wrap member-search">
                            <span class="search-icon"><i class="fa fa-search"></i></span>
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

                <div class="divider"></div>

                <!-- Due Date -->
                <div class="field">
                     <label class="field-label">Due Date</label>
                     <div class="date-wrap">
                        <input type="date" name="due_date" value="<?= htmlspecialchars($prefill_due_date, ENT_QUOTES) ?>">
                        <span class="cal-icon"><i class="fa fa-calendar-o"></i></span>
                     </div>
                </div>

                 <!-- File -->
                 <div class="field">
                     <label class="field-label">Attachment <span class="optional-tag">Optional · up to 50MB</span></label>
                     <label class="file-upload" id="dropZone">
                        <input type="file" name="template_file" id="fileInput" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx,.zip">
                        <div class="upload-idle" id="uploadIdle">
                            <div class="upload-icon"><i class="fa fa-paperclip"></i></div>
                            <div class="upload-text">
                                <strong>Click to upload</strong> or drag and drop<br>
                                PDF, PNG, DOCX, XLSX, ZIP
                            </div>
                        </div>
                        <div class="upload-success" id="uploadSuccess" style="display:none;">
                            <div class="file-preview">
                                <div class="file-icon-wrap" id="fileIconWrap"><i class="fa fa-file-o"></i></div>
                                <div class="file-info">
                                    <div class="file-name" id="fileName">document.pdf</div>
                                    <div class="file-meta" id="fileMeta">2.4 MB - PDF</div>
                                    <div class="file-progress">
                                        <div class="file-progress-bar" id="progressBar"></div>
                                    </div>
                                    <div class="file-status" id="fileStatus">Uploading...</div>
                                </div>
                                <button class="file-remove" id="fileRemove" type="button"><i class="fa fa-times"></i></button>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Actions -->
                <div class="actions">
                    <a href="tasks.php" class="btn btn-cancel">Cancel</a>
                    <button type="submit" class="btn btn-create">Create Task →</button>
                </div>

            </form>
        </div>

    </div>

    <?php if ($show_duplicate_modal) { ?>
    <div id="duplicateTitleModal" class="custom-modal-overlay">
        <div class="custom-modal" role="dialog" aria-modal="true" aria-labelledby="duplicate-title-heading">
            <div id="duplicate-title-heading" class="custom-modal-header">Duplicate Task Title</div>
            <div class="custom-modal-body">This title is already created. Please use a different task title.</div>
            <div class="custom-modal-actions">
                <button type="button" onclick="closeDuplicateModal()">OK</button>
            </div>
        </div>
    </div>
    <?php } ?>

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
        var groupInput = document.getElementById('groupSelect');
        var groupList = document.getElementById('groupList');
        var groupPicker = document.getElementById('groupPicker');
        var groupSearch = document.getElementById('groupSearch');
        var groupSelected = document.getElementById('groupSelected');
        var groupInfo = document.getElementById('groupInfo');

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
                clearGroup();
            }
        }

        window.clearGroup = function() {
            if (groupInput) groupInput.value = "0";
            if (groupSearch) groupSearch.value = "";
            if (groupInfo) groupInfo.textContent = "";
            if (groupSelected) groupSelected.innerHTML = "";
            if (!groupList) return;
            groupList.querySelectorAll('.group-option').forEach(function(opt){
                opt.classList.remove('selected');
                opt.style.display = '';
            });
        };

        function selectGroup(optionEl) {
            if (!optionEl || !groupInput) return;
            var groupId = optionEl.getAttribute('data-id');
            var groupName = optionEl.getAttribute('data-name') || '';
            var groupLeader = optionEl.getAttribute('data-leader') || 'Not set';
            var groupMemberCount = optionEl.getAttribute('data-member-count') || '0';
            if (!groupId || groupId === "0") {
                clearGroup();
                return;
            }

            if (groupList) {
                groupList.querySelectorAll('.group-option').forEach(function(opt){
                    opt.classList.remove('selected');
                });
            }
            optionEl.classList.add('selected');
            groupInput.value = groupId;
            if (groupSearch) groupSearch.value = groupName;
            if (groupInfo) {
                groupInfo.textContent = "Leader: " + groupLeader + " | Members: " + groupMemberCount;
            }

            if (groupSelected) {
                groupSelected.innerHTML = '';
                var badge = document.createElement('div');
                badge.className = 'member-badge';
                badge.id = 'group_badge_' + groupId;
                badge.appendChild(document.createTextNode(groupName + ' '));
                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.innerHTML = '&times;';
                removeBtn.addEventListener('click', function() {
                    clearGroup();
                });
                badge.appendChild(removeBtn);
                groupSelected.appendChild(badge);
            }
            closePicker(groupPicker);
        }

        function filterGroupOptions() {
            if (!groupSearch || !groupList) return;
            var query = groupSearch.value.toLowerCase();
            var items = groupList.querySelectorAll('.group-option');
            items.forEach(function(item){
                var name = (item.getAttribute('data-name') || '').toLowerCase();
                var leader = (item.getAttribute('data-leader') || '').toLowerCase();
                var meta = (item.getAttribute('data-meta') || '').toLowerCase();
                if (name.indexOf(query) !== -1 || leader.indexOf(query) !== -1 || meta.indexOf(query) !== -1) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

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

        if (groupSearch) {
            groupSearch.addEventListener('focus', function(){
                openPicker(groupPicker);
            });
            groupSearch.addEventListener('click', function(){
                openPicker(groupPicker);
            });
            groupSearch.addEventListener('input', function(){
                openPicker(groupPicker);
                filterGroupOptions();
            });
        }

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
        if (groupList) {
            groupList.querySelectorAll('.group-option').forEach(function(opt){
                opt.addEventListener('click', function(e){
                    if (e.target && e.target.closest('button')) return;
                    selectGroup(opt);
                });
                var action = opt.querySelector('.user-action');
                if (action) {
                    action.addEventListener('click', function(e){
                        e.stopPropagation();
                        selectGroup(opt);
                    });
                }
            });
        }

        document.addEventListener('click', function(e){
            if (leaderPicker && !leaderPicker.contains(e.target)) {
                closePicker(leaderPicker);
            }
            if (memberPicker && !memberPicker.contains(e.target)) {
                closePicker(memberPicker);
            }
            if (groupPicker && !groupPicker.contains(e.target)) {
                closePicker(groupPicker);
            }
        });

        var dropZone = document.getElementById('dropZone');
        var fileInput = document.getElementById('fileInput');
        var uploadIdle = document.getElementById('uploadIdle');
        var uploadSuccess = document.getElementById('uploadSuccess');
        var fileNameEl = document.getElementById('fileName');
        var fileMetaEl = document.getElementById('fileMeta');
        var progressBar = document.getElementById('progressBar');
        var fileStatus = document.getElementById('fileStatus');
        var fileIconWrap = document.getElementById('fileIconWrap');
        var fileRemove = document.getElementById('fileRemove');
        var uploadTimer = null;

        var EXT_ICON_CLASSES = {
            pdf: 'fa-file-pdf-o',
            png: 'fa-file-image-o',
            jpg: 'fa-file-image-o',
            jpeg: 'fa-file-image-o',
            docx: 'fa-file-word-o',
            doc: 'fa-file-word-o',
            xlsx: 'fa-file-excel-o',
            xls: 'fa-file-excel-o',
            csv: 'fa-file-excel-o',
            zip: 'fa-file-archive-o',
            default: 'fa-file-o'
        };

        function formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function showUploadIdle() {
            if (uploadTimer) {
                clearInterval(uploadTimer);
                uploadTimer = null;
            }
            if (uploadIdle) uploadIdle.style.display = 'flex';
            if (uploadSuccess) uploadSuccess.style.display = 'none';
            if (progressBar) progressBar.style.width = '0%';
            if (dropZone) dropZone.classList.remove('has-file');
        }

        function simulateUpload(file) {
            if (!file) return;
            var parts = file.name.split('.');
            var ext = parts.length > 1 ? parts.pop().toLowerCase() : '';
            var extLabel = ext ? ext.toUpperCase() : 'FILE';
            var iconClass = EXT_ICON_CLASSES[ext] || EXT_ICON_CLASSES.default;

            if (fileNameEl) fileNameEl.textContent = file.name;
            if (fileMetaEl) fileMetaEl.textContent = formatSize(file.size) + ' - ' + extLabel;
            if (fileIconWrap) fileIconWrap.innerHTML = '<i class="fa ' + iconClass + '"></i>';
            if (progressBar) progressBar.style.width = '0%';
            if (fileStatus) fileStatus.textContent = 'Uploading...';

            if (uploadIdle) uploadIdle.style.display = 'none';
            if (uploadSuccess) uploadSuccess.style.display = 'block';
            if (dropZone) dropZone.classList.add('has-file');

            var pct = 0;
            if (uploadTimer) clearInterval(uploadTimer);
            uploadTimer = setInterval(function() {
                pct += Math.random() * 12 + 4;
                if (pct >= 100) {
                    pct = 100;
                    clearInterval(uploadTimer);
                    uploadTimer = null;
                    if (fileStatus) fileStatus.textContent = 'Upload complete';
                }
                if (progressBar) progressBar.style.width = pct + '%';
            }, 80);
        }

        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                if (e.target.files && e.target.files[0]) {
                    simulateUpload(e.target.files[0]);
                } else {
                    showUploadIdle();
                }
            });
        }

        if (dropZone) {
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                dropZone.classList.add('drag-over');
            });

            dropZone.addEventListener('dragleave', function() {
                dropZone.classList.remove('drag-over');
            });

            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                dropZone.classList.remove('drag-over');
                var file = (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) ? e.dataTransfer.files[0] : null;
                if (!file) return;

                if (fileInput) {
                    try {
                        var dt = new DataTransfer();
                        dt.items.add(file);
                        fileInput.files = dt.files;
                    } catch (err) {
                        // Keep visual state even if assignment is blocked by browser.
                    }
                }
                simulateUpload(file);
            });
        }

        if (fileRemove) {
            fileRemove.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (fileInput) fileInput.value = '';
                showUploadIdle();
            });
        }

        // Initialize mode on load
        toggleAssignmentMode();

        function closeDuplicateModal() {
            var modal = document.getElementById('duplicateTitleModal');
            if (modal) modal.style.display = 'none';
        }

        <?php if ($show_duplicate_modal) { ?>
        document.getElementById('duplicateTitleModal').style.display = 'flex';
        document.getElementById('duplicateTitleModal').addEventListener('click', function(e) {
            if (e.target === this) closeDuplicateModal();
        });
        <?php } ?>
    </script>

</body>
</html>
<?php }else{ 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
?>


