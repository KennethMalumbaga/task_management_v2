<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    include "app/model/user.php";
    include "app/model/Task.php";
    include "app/model/Subtask.php";
    
    $is_super_admin = is_super_admin($_SESSION['id'], $pdo);

    // Modifying logic to exclude admins by default from the directory view
    $role_filter = isset($_GET['role']) ? $_GET['role'] : 'employee'; 
    if ($role_filter == 'all') {
        // If 'all' is requested, we still might want to hide admins based on user request "admin is not in users directory"
        // So we force 'employee' or we filter the result. 
        // Let's assume 'all' means all non-admins for this directory context.
        $role_filter = 'employee';
    }
    
    // Only super admin can see other admins
    if ($role_filter == 'admin' && !$is_super_admin) {
        $role_filter = 'employee';
    }
    
    // However, if we want to show 'all' as in 'all employees' vs 'specific role employees', but we only have 'employee' role really besides admin.
    // The previous code had "Admin" button. User said "admin is not in users directory".
    // So usually directory is for employees.
    
    $users = get_all_users($pdo, $role_filter);

    // Helper to get rating (Fix: Use task_assignees to include members)
    // function get_user_avg_rating($pdo, $user_id) ... REMOVED, using Model function now
 ?>
<!DOCTYPE html>
<html>
<head>
	<title>Users Directory | TaskFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .filter-active {
            background: #4F46E5 !important;
            color: white !important;
            border-color: #4F46E5 !important;
        }
        
        /* User Card Grid - 4 Columns on Desktop, 2 Columns on Mobile */
        .grid-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            align-items: stretch;
        }

        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 12px !important;
            }
        }

        .user-card {
            background: white;
            border-radius: 10px;
            padding: 16px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .btn-edit-absolute {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 26px;
            height: 26px;
            background: #F3E8FF;
            color: #7C3AED;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 12px;
        }
        
        .btn-edit-absolute:hover {
            background: #7C3AED;
            color: white;
        }

        .user-card-avatar {
            width: 56px;
            height: 56px;
            margin: 0 auto 10px;
            background: #E0E7FF;
            color: #4F46E5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 22px;
            object-fit: cover;
        }

        .user-info-text {
            text-align: center;
            margin-bottom: 10px;
        }

        .user-name {
            font-size: 15px;
            font-weight: 600;
            color: #111827;
            margin: 0 0 3px 0;
        }

        .user-email {
            font-size: 11px;
            color: #6B7280;
            margin-bottom: 6px;
            display: block;
        }

        .stats-row {
            display: flex;
            justify-content: center;
            gap: 10px;
            font-size: 11px;
            margin-bottom: 10px;
            background: #F9FAFB;
            padding: 6px;
            border-radius: 6px;
        }

        .skill-tags {
            font-size: 10px;
            color: #6B7280;
            text-align: center;
            height: 28px;
            overflow: hidden;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .action-row {
            margin-top: auto;
            display: flex;
            gap: 8px;
        }

        .btn-action-card {
            flex: 1;
            padding: 7px 0;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-msg {
            background: #4F46E5;
            color: white;
            border-color: #4F46E5;
        }
        .btn-msg:hover { background: #4338CA; border-color: #4338CA; }

        .btn-profile {
            background: white;
            color: #374151;
            border-color: #D1D5DB;
        }
        .btn-profile:hover { background: #F3F4F6; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            padding: 32px;
            border-radius: 12px;
            width: 400px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .modal-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .modal-header i {
            color: #7C3AED;
            font-size: 20px;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        .role-note {
            background: #FFFBEB;
            border: 1px solid #FEF3C7;
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
            color: #92400E;
            margin-top: 15px;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
        }
        .btn-cancel {
            padding: 8px 16px;
            border: 1px solid #E5E7EB;
            background: white;
            color: #374151;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }
        .btn-update-role {
            padding: 8px 16px;
            background: #7C3AED;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <h2 style="font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0;">Users Directory</h2>
                
                <div style="display: flex; gap: 10px; align-items: center;">
                     <a href="user.php?role=employee" class="btn-outline <?= ($role_filter == 'employee') ? 'filter-active' : '' ?>">Employees</a>
                     <?php if ($is_super_admin) { ?>
                        <a href="user.php?role=admin" class="btn-outline <?= ($role_filter == 'admin') ? 'filter-active' : '' ?>">Admins</a>
                     <?php } ?>
                </div>
            </div>
        </div>

        <?php if (!empty($users)) { ?>
        <div class="grid-container">
            <?php foreach ($users as $user) { 
                // Skip the super admin itself ('admin' username)
                if ($user['username'] == 'admin') continue;
                
                // If not super admin, skip other admins
                if (!$is_super_admin && $user['role'] == 'admin') continue;

                $stats = get_user_rating_stats($pdo, $user['id']);
                $avg_rating = $stats['avg'];
                $collab = get_collaborative_scores_by_user($pdo, $user['id']);
                $dirStats = get_todays_attendance_stats($pdo, $user['id']);
            ?>
            <div class="user-card" onclick="location.href='user_details.php?id=<?=$user['id']?>'" style="cursor: pointer;">
                
                <!-- Edit Role Absolute Button -->
                <?php if ($is_super_admin) { ?>
                <button onclick="event.stopPropagation(); openModal('<?=$user['id']?>', '<?=addslashes($user['full_name'])?>', '<?=$user['role']?>')" class="btn-edit-absolute" title="Edit Role">
                    <i class="fa fa-pencil"></i>
                </button>
                <?php } ?>

                <div class="user-card-avatar">
                     <?php if (!empty($user['profile_image']) && $user['profile_image'] != 'default.png' && file_exists('uploads/' . $user['profile_image'])): ?>
                        <img src="uploads/<?=$user['profile_image']?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                     <?php else: ?>
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                     <?php endif; ?>
                </div>
                
                <div class="user-info-text">
                    <h3 class="user-name"><?= htmlspecialchars($user['full_name']) ?></h3>
                    <span class="user-email"><?= htmlspecialchars($user['username']) ?></span>
                    <span class="badge badge-in_progress" style="font-size: 10px; padding: 2px 8px;"><?= ucfirst($user['role']) ?></span>
                </div>

                <!-- Stats Row -->
                <div class="stats-row">
                    <div style="color: #F59E0B;" title="Task Rating">
                        <i class="fa fa-star"></i> <?= $avg_rating ?>
                    </div>
                    <div style="width: 1px; background: #E5E7EB;"></div>
                    <div style="color: #8B5CF6;" title="Collaborative Score">
                        <i class="fa fa-users"></i> <?= $collab['avg'] ?>
                    </div>
                    <div style="width: 1px; background: #E5E7EB;"></div>
                    <div style="color: #10B981;" title="Total Hours">
                         <i class="fa fa-clock-o"></i> <?= str_replace('Oh ','0h ', $dirStats['overall_duration']) ?>
                    </div>
                </div>

                <div class="skill-tags">
                     <?= !empty($user['skills']) ? htmlspecialchars(mb_strimwidth($user['skills'], 0, 50, "...")) : 'No skills listed' ?>
                </div>

                <!-- Action Buttons: Chat Only -->
                <div class="action-row">
                    <a href="messages.php?id=<?=$user['id']?>" onclick="event.stopPropagation();" class="btn-action-card btn-msg">
                        <i class="fa fa-comment-o" style="margin-right: 5px;"></i> Chat
                    </a>
                </div>

            </div>
            <?php } ?>
        </div>
        <?php } else { ?>
             <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                <h3>No users found</h3>
            </div>
        <?php } ?>

    </div>

    <!-- Edit Role Modal -->
    <div id="roleModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fa fa-shield"></i>
                <h3>Edit User Role</h3>
            </div>
            <p style="font-size: 14px; color: #6B7280; margin-bottom: 20px;">
                Change role for: <strong id="modalUserName" style="color: #111827;"></strong>
            </p>
            
            <form action="app/update-user-role.php" method="POST">
                <input type="hidden" name="user_id" id="modalUserId">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px;">Select New Role</label>
                    <select name="role" id="modalUserRole" style="width: 100%; padding: 10px; border: 1px solid #D1D5DB; border-radius: 6px; outline: none;">
                        <option value="employee">Employee</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="role-note">
                    <strong>Note:</strong> Changing a user's role will update their permissions immediately.
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-update-role">
                        <i class="fa fa-shield"></i> Update Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id, name, role) {
            document.getElementById('modalUserId').value = id;
            document.getElementById('modalUserName').innerText = name;
            document.getElementById('modalUserRole').value = role;
            document.getElementById('roleModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('roleModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('roleModal');
            if (event.target == modal) {
                closeModal();
            }
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

