<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/Model/User.php";
    
    // Fetch users for the chat list
    $users = get_all_users($pdo);
?>
<!DOCTYPE html>
<html>
<head>
	<title>Messages | TaskFlow</title>
	<!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main" style="height: 100vh; overflow: hidden; display: flex; flex-direction: column;">
        <h2 style="margin-bottom: 20px;">Messages</h2>
        
        <div class="chat-layout">
            
            <!-- Chat Sidebar (Users) -->
            <div class="chat-sidebar">
                <div class="chat-search">
                    <input type="text" placeholder="Search users..." class="form-input" style="margin-bottom: 0;">
                </div>
                <div class="chat-list">
                    <!-- Static Active User for Demo -->
                    <div class="chat-item active">
                        <div class="user-avatar-sm" style="width: 40px; height: 40px;">JS</div>
                        <div>
                            <div style="font-weight: 600; font-size: 14px;">John Smith</div>
                            <div style="font-size: 12px; color: var(--text-gray);">Hey, how is the project going?</div>
                        </div>
                    </div>

                    <?php 
                    if ($users != 0) {
                        foreach ($users as $user) {
                            if ($user['id'] == $_SESSION['id']) continue; // Skip self
                    ?>
                    <div class="chat-item">
                        <div class="user-avatar-sm" style="width: 40px; height: 40px; background: #E0E7FF; color: var(--primary);">
                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($user['full_name']) ?></div>
                            <div style="font-size: 12px; color: var(--text-gray);"><?= ucfirst($user['role']) ?></div>
                        </div>
                    </div>
                    <?php 
                        }
                    } 
                    ?>
                </div>
            </div>

            <!-- Chat Main Area -->
            <div class="chat-main">
                <div class="chat-header">
                    John Smith <span style="font-weight: 400; color: var(--text-gray); font-size: 12px; margin-left: 10px;">Project Leader</span>
                </div>
                
                <div class="chat-messages">
                    <!-- Demo Messages -->
                     <div style="display: flex; flex-direction: column; gap: 15px;">
                        <div style="align-self: flex-start; max-width: 70%; background: white; padding: 10px 15px; border-radius: 0 12px 12px 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            <div style="font-size: 14px;">Hi everyone, please remember to update your tasks by EOD.</div>
                            <div style="font-size: 10px; color: var(--text-gray); margin-top: 5px;">10:30 AM</div>
                        </div>

                        <div style="align-self: flex-end; max-width: 70%; background: var(--primary); color: white; padding: 10px 15px; border-radius: 12px 12px 0 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            <div style="font-size: 14px;">Sure, I'm working on the documentation now.</div>
                            <div style="font-size: 10px; opacity: 0.8; margin-top: 5px;">10:32 AM</div>
                        </div>
                    </div>
                </div>

                <div class="chat-input-area">
                    <input type="text" placeholder="Type a message..." class="form-input" style="margin-bottom: 0;">
                    <button class="btn-primary" style="padding: 0 20px;"><i class="fa fa-paper-plane"></i></button>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
<?php }else{ 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
?>
