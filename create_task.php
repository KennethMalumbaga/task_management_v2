<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    include "app/Model/User.php";
    include "app/Model/Task.php";

    // Only get employees (exclude admin)
    $users = get_all_users($pdo, 'employee');
 ?>
<!DOCTYPE html>
<html>
<head>
	<title>Create Task | TaskFlow</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="css/dashboard.css">
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

                <!-- Project Leader -->
                 <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Project Leader (Optional)</label>
                    <select name="leader_id" id="leaderSelect" onchange="onLeaderChange()" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; outline: none; background: white;">
                        <option value="0">None</option>
                        <?php if ($users != 0) { foreach ($users as $user) { 
                            $pendingCount = count_my_active_tasks($pdo, $user['id']);
                            $pendingText = $pendingCount > 0 ? " (Pending: $pendingCount)" : "";
                        ?>
                            <option value="<?=$user['id']?>"><?=$user['full_name'] . $pendingText?></option>
                        <?php } } ?>
                    </select>
                </div>

                <!-- Team Members -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Team Members (Optional)</label>
                    
                    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                         <select id="memberSelect" style="flex: 1; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; outline: none; background: white;">
                            <option value="0">Add Team Member</option>
                            <?php if ($users != 0) { foreach ($users as $user) { 
                                $pendingCount = count_my_active_tasks($pdo, $user['id']);
                                $pendingText = $pendingCount > 0 ? " (Pending: $pendingCount)" : "";
                            ?>
                                <option value="<?=$user['id']?>" data-name="<?=htmlspecialchars($user['full_name'])?>"><?=$user['full_name'] . $pendingText?></option>
                            <?php } } ?>
                        </select>
                         <button type="button" id="addMemberBtn" style="background: white; border: 1px solid #d1d5db; border-radius: 6px; padding: 0 15px; cursor: pointer; color: #374151;">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>

                    <div id="membersList" style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <!-- Added members will appear here as badges -->
                    </div>
                    <!-- Hidden inputs -->
                    <div id="memberInputs"></div>
                </div>

                <!-- Due Date -->
                <div style="margin-bottom: 20px;">
                     <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Due Date (Optional)</label>
                     <input type="date" name="due_date" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; outline: none;">
                </div>

                 <!-- File -->
                 <div style="margin-bottom: 30px;">
                     <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px;">Attachment (Optional)</label>
                     <input type="file" name="template_file" style="width: 100%; font-size: 14px;">
                </div>

                <!-- Actions -->
                <div style="display: flex; gap: 10px;">
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
        
        // Function called when project leader is changed
        function onLeaderChange() {
            var leaderSelect = document.getElementById('leaderSelect');
            var memberSelect = document.getElementById('memberSelect');
            var newLeaderId = leaderSelect.value;
            
            // Show the previously hidden leader option in member select
            if (currentLeaderId !== "0") {
                var prevOption = memberSelect.querySelector('option[value="' + currentLeaderId + '"]');
                if (prevOption) {
                    prevOption.style.display = '';
                }
            }
            
            // Hide the new leader from member select
            if (newLeaderId !== "0") {
                var newOption = memberSelect.querySelector('option[value="' + newLeaderId + '"]');
                if (newOption) {
                    newOption.style.display = 'none';
                }
                
                // If the new leader was already added as team member, remove them
                if (selectedMembers[newLeaderId]) {
                    removeMember(newLeaderId, null);
                }
            }
            
            // Reset member select if current selection is the new leader
            if (memberSelect.value === newLeaderId) {
                memberSelect.value = "0";
            }
            
            currentLeaderId = newLeaderId;
        }
        
        document.getElementById('addMemberBtn').addEventListener('click', function() {
            var select = document.getElementById('memberSelect');
            var leaderSelect = document.getElementById('leaderSelect');
            var id = select.value;
            var name = select.options[select.selectedIndex].getAttribute('data-name');
            
            if(id == "0") return;
            if(selectedMembers[id]) return;
            
            // Don't allow adding the project leader as a team member
            if(id === leaderSelect.value) {
                alert('This person is already selected as Project Leader!');
                select.value = "0";
                return;
            }

            addMemberBadge(id, name);
            
            // Add to selected
            selectedMembers[id] = name;
            
            // Create hidden input
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'member_ids[]';
            input.value = id;
            input.id = 'input_' + id;
            document.getElementById('memberInputs').appendChild(input);

            // Reset select
            select.value = "0";
        });

        function addMemberBadge(id, name) {
            var badge = document.createElement('div');
            badge.id = 'badge_' + id;
            badge.style.cssText = "background: #EEF2FF; color: #4F46E5; padding: 4px 10px; border-radius: 20px; font-size: 13px; display: flex; align-items: center; gap: 6px;";
            badge.innerHTML = `
                ${name} 
                <span onclick="removeMember('${id}', this)" style="cursor: pointer; font-size: 16px; opacity: 0.6;">&times;</span>
            `;
            document.getElementById('membersList').appendChild(badge);
        }

        window.removeMember = function(id, span) {
            delete selectedMembers[id];
            var inputEl = document.getElementById('input_' + id);
            if (inputEl) inputEl.remove();
            var badgeEl = document.getElementById('badge_' + id);
            if (badgeEl) badgeEl.remove();
            if (span) span.parentElement.remove();
        }
    </script>

</body>
</html>
<?php }else{ 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
?>