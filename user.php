<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    include "app/model/user.php";
    include "app/model/Subtask.php";

    $role = isset($_GET['role']) ? $_GET['role'] : 'employee';
    if (!in_array($role, ['employee', 'admin'], true)) {
        $role = 'employee';
    }

    $users = get_all_users($pdo, $role);
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
        .tab-btn {
            padding: 10px 18px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: white;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            color: var(--text-dark);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .tab-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .status-pill {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .status-in {
            background: #ECFDF3;
            color: #047857;
            border: 1px solid #A7F3D0;
        }
        .status-out {
            background: #F3F4F6;
            color: #6B7280;
            border: 1px solid #E5E7EB;
        }
        .admin-clockout-btn {
            width: 100%;
            margin-top: 12px;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid #F59E0B;
            background: #FFFBEB;
            color: #92400E;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-sizing: border-box;
        }
        .admin-clockout-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .user-card .name-link {
            color: var(--text-dark);
            text-decoration: none;
        }
        .user-card .name-link:hover {
            text-decoration: underline;
        }
        .user-card {
            display: flex;
            flex-direction: column;
        }
        .user-card-actions {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .user-card-actions .btn-primary {
            width: 100%;
            padding: 8px 10px;
            font-size: 14px;
            font-weight: 600;
            box-sizing: border-box;
        }
        .user-card-actions .btn-primary i {
            font-size: 14px;
        }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; background: white; padding: 16px 18px; border-radius: 12px; border: 1px solid #E5E7EB;">
            <div>
                <h2 style="font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0 0 6px 0;">Users Directory</h2>
                <span style="color: var(--text-gray); font-size: 14px;">Monitor user status and manage attendance</span>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a class="tab-btn <?= $role === 'employee' ? 'active' : '' ?>" href="user.php?role=employee">Employees</a>
                <a class="tab-btn <?= $role === 'admin' ? 'active' : '' ?>" href="user.php?role=admin">Admins</a>
            </div>
        </div>

        <?php if (!empty($users)) { ?>
            <div class="grid-container">
                <?php foreach ($users as $user) { 
                    $is_clocked_in = is_user_clocked_in($pdo, $user['id']);

                    $rating_stats = ['avg' => '0.0', 'count' => 0];
                    $collab_scores = ['avg' => '0.0'];
                    $attendance_stats = ['daily_duration' => '0h 0m'];

                    if ($user['role'] === 'employee') {
                        $rating_stats = get_user_rating_stats($pdo, $user['id']);
                        $collab_scores = get_collaborative_scores_by_user($pdo, $user['id']);
                        $attendance_stats = get_todays_attendance_stats($pdo, $user['id']);
                    }
                ?>
                <div class="user-card" data-user-id="<?=$user['id']?>">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <span class="status-pill <?= $is_clocked_in ? 'status-in' : 'status-out' ?>" data-status>
                            <i class="fa <?= $is_clocked_in ? 'fa-circle' : 'fa-circle-o' ?>"></i>
                            <?= $is_clocked_in ? 'Clocked In' : 'Clocked Out' ?>
                        </span>
                        <a href="edit-user.php?id=<?=$user['id']?>" title="Edit User" style="color: #A78BFA; background: #F3E8FF; padding: 6px 8px; border-radius: 999px; font-size: 12px;">
                            <i class="fa fa-pencil"></i>
                        </a>
                    </div>

                    <div class="user-card-avatar" style="overflow: hidden;">
                        <?php if (!empty($user['profile_image']) && $user['profile_image'] != 'default.png' && file_exists('uploads/' . $user['profile_image'])): ?>
                            <img src="uploads/<?=$user['profile_image']?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <h3 style="margin: 0; font-size: 16px;">
                        <a class="name-link" href="user_details.php?id=<?=$user['id']?>">
                            <?= htmlspecialchars($user['full_name']) ?>
                        </a>
                    </h3>
                    <div style="color: var(--text-gray); font-size: 12px; margin-top: 4px;">
                        <?= htmlspecialchars($user['username']) ?>
                    </div>
                    <div style="margin-top: 6px;">
                        <span class="badge" style="background: #EEF2FF; color: #3730A3;">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </div>

                    <div style="display: flex; justify-content: center; gap: 14px; font-size: 12px; margin: 12px 0 6px 0;">
                        <span style="color: #F59E0B;"><i class="fa fa-star"></i> <?= $rating_stats['avg'] ?></span>
                        <span style="color: #8B5CF6;"><i class="fa fa-users"></i> <?= $collab_scores['avg'] ?></span>
                        <span style="color: #10B981;"><i class="fa fa-clock-o"></i> <?= str_replace('Oh ', '0h ', $attendance_stats['daily_duration']) ?></span>
                    </div>

                    <div style="color: var(--text-gray); font-size: 12px; margin-top: 4px;">
                        <?= htmlspecialchars($user['skills'] ?? 'No skills listed') ?>
                    </div>

                    <div class="user-card-actions">
                        <button type="button" class="admin-clockout-btn" data-user-id="<?=$user['id']?>" data-user-name="<?= htmlspecialchars($user['full_name']) ?>" <?= $is_clocked_in ? '' : 'disabled' ?>>
                            <i class="fa fa-sign-out"></i> Clock Out
                        </button>

                        <a href="messages.php?id=<?=$user['id']?>" class="btn-primary" style="width: 100%; justify-content: center;">
                            <i class="fa fa-comment"></i> Chat
                        </a>
                    </div>
                </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                <i class="fa fa-users" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                <h3>No users found</h3>
            </div>
        <?php } ?>
    </div>

    <!-- Admin Clock Out Confirmation Modal -->
    <div id="adminConfirmModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center;">
        <div style="background:white; padding:30px; border-radius:12px; width:360px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.15);">
            <div style="width:50px; height:50px; background:#FEF3C7; color:#D97706; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; margin:0 auto 15px;">
                <i class="fa fa-power-off"></i>
            </div>
            <h3 style="margin:0 0 10px; color:#111827;">Clock Out User?</h3>
            <p style="color:#6B7280; font-size:14px; margin-bottom:25px; line-height:1.5;">
                Are you sure you want to clock out <strong id="adminConfirmName">this user</strong>?
            </p>
            <div style="display:flex; gap:10px; justify-content:center;">
                <button type="button" onclick="adminCloseConfirm()" style="background:#F3F4F6; color:#374151; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Cancel</button>
                <button type="button" onclick="adminConfirmClockOut()" style="background:#F59E0B; color:white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Yes, Clock Out</button>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var pendingBtn = null;
            var confirmModal = document.getElementById('adminConfirmModal');
            var confirmName = document.getElementById('adminConfirmName');

            function openConfirm(btn) {
                pendingBtn = btn;
                if (confirmName) {
                    confirmName.textContent = btn.getAttribute('data-user-name') || 'this user';
                }
                if (confirmModal) {
                    confirmModal.style.display = 'flex';
                } else {
                    doClockOut(btn);
                }
            }

            function closeConfirm() {
                if (confirmModal) {
                    confirmModal.style.display = 'none';
                }
                pendingBtn = null;
            }

            function doClockOut(btn) {
                var userId = btn.getAttribute('data-user-id');
                if (!userId) return;
                if (btn.disabled) return;

                btn.disabled = true;
                var originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Clocking out...';

                var body = new URLSearchParams();
                body.append('user_id', userId);

                fetch('admin_clock_out.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.status === 'success') {
                        var card = btn.closest('.user-card');
                        if (card) {
                            var status = card.querySelector('[data-status]');
                            if (status) {
                                status.classList.remove('status-in');
                                status.classList.add('status-out');
                                status.innerHTML = '<i class="fa fa-circle-o"></i> Clocked Out';
                            }
                        }
                        btn.innerHTML = '<i class="fa fa-check"></i> Clocked Out';
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        alert(data.message || 'Unable to clock out user.');
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    alert('Failed to clock out user. Please try again.');
                });
            }

            var buttons = document.querySelectorAll('.admin-clockout-btn');
            buttons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (btn.disabled) return;
                    openConfirm(btn);
                });
            });

            window.adminConfirmClockOut = function() {
                if (pendingBtn) {
                    var btn = pendingBtn;
                    closeConfirm();
                    doClockOut(btn);
                }
            };

            window.adminCloseConfirm = function() {
                closeConfirm();
            };
        })();
    </script>
</body>
</html>
<?php 
} else { 
    $em = "First login";
    header("Location: login.php?error=$em");
    exit();
}
?>
