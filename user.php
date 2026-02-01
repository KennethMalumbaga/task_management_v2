<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    include "app/Model/User.php";
    include "app/Model/Task.php";
    
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
        .user-card-action-btn {
            width: 100%; 
            display: flex; 
            justify-content: center; 
            padding: 7px; 
            border-radius: 6px; 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-msg {
            background: #4F46E5; 
            color: white;
            width: 97%;
        }
        .btn-view {
            background: #F3F4F6; 
            color: #374151;
            margin-top: 8px;
        }
        .btn-view:hover {
            background: #E5E7EB;
        }

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
        
        /* New Button Style */
        .btn-edit-role {
            background: #F3E8FF !important;
            color: #7C3AED !important;
            margin-top: 8px;
            margin-left: 4px;
            border: none;
            width: 100%;
        }
        .btn-edit-role:hover {
            background: #EDE9FE !important;
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
            ?>
            <div class="user-card" style="display: flex; flex-direction: column; height: 100%;">
                
                <div class="user-card-avatar">
                     <?php if (!empty($user['profile_image']) && $user['profile_image'] != 'default.png' && file_exists('uploads/' . $user['profile_image'])): ?>
                        <img src="uploads/<?=$user['profile_image']?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                     <?php else: ?>
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                     <?php endif; ?>
                </div>
                
                <h3 style="margin: 0 0 5px 0; font-size: 18px; color: var(--text-dark);"><?= htmlspecialchars($user['full_name']) ?></h3>
                
                <?php 
                    $roleClass = "badge-in_progress"; 
                ?>
                <div style="margin-bottom: 5px;">
                     <span class="badge <?= $roleClass ?>"><?= ucfirst($user['role']) ?></span>
                </div>
                
                <div style="color: #F59E0B; font-size: 13px; margin-bottom: 15px;">
                     <i class="fa fa-star"></i> <?= $avg_rating ?> / 5.0
                </div>

                <div style="color: var(--text-gray); font-size: 13px; margin-bottom: 10px;">
                    <i class="fa fa-envelope-o"></i> <?= htmlspecialchars($user['username']) ?>
                </div>


                
                <div style="margin-top: auto; padding-top: 15px;">
                    <div style="font-size: 12px; color: var(--text-gray); margin-bottom: 10px;">
                        <strong>Skills:</strong> 
                        <?= !empty($user['skills']) ? htmlspecialchars(substr($user['skills'], 0, 30)) . (strlen($user['skills']) > 30 ? '...' : '') : 'Not listed' ?>
                    </div>

                    <a href="messages.php?id=<?=$user['id']?>" class="user-card-action-btn btn-msg">
                        <i class="fa fa-comment-o" style="margin-right: 5px;"></i> Message
                    </a>

                    <?php 
                        // Super Admin gets "Edit Role" modal
                        if ($is_super_admin) {
                    ?>
                    <button onclick="openModal('<?=$user['id']?>', '<?=addslashes($user['full_name'])?>', '<?=$user['role']?>')" class="user-card-action-btn btn-view btn-edit-role">
                        <i class="fa fa-shield" style="margin-right: 5px;"></i> Edit Role
                    </button>
                    <?php } ?>

                    <a href="user_details.php?id=<?=$user['id']?>" class="user-card-action-btn btn-view" style="margin-top: 8px;">
                        View Profile
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