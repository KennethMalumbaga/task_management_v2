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
    
    <!-- jQuery for AJAX -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    
    <style>
        .chat-item {
            cursor: pointer;
            transition: background 0.2s;
        }
        .chat-item:hover {
            background: #F3F4F6;
        }
        .chat-item.active {
            background: #EEF2FF;
            border-left: 3px solid var(--primary);
        }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main" style="height: 100vh; overflow: hidden; display: flex; flex-direction: column;">
        <h2 style="margin-bottom: 20px; font-weight: 700; color: #111827;">Messages</h2>
        
        <div class="chat-layout">
            
            <!-- Chat Sidebar (Users) -->
            <div class="chat-sidebar">
                <div class="chat-search">
                    <input type="text" id="searchText" placeholder="Search users..." class="form-input" style="background: #F9FAFB; border-color: #E5E7EB; margin-bottom: 0;">
                </div>
                <div class="chat-list" id="chatList">
                    <?php 
                    if ($users != 0) {
                        foreach ($users as $user) {
                            if ($user['id'] == $_SESSION['id']) continue; // Skip self
                    ?>
                    <div class="chat-item" data-id="<?=$user['id']?>" data-name="<?=htmlspecialchars($user['full_name'])?>" data-role="<?=ucfirst($user['role'])?>">
                        <div class="user-avatar-sm" style="width: 40px; height: 40px; background: #E0E7FF; color: var(--primary);">
                             <?php if (!empty($user['profile_image']) && $user['profile_image'] != 'default.png' && file_exists('uploads/' . $user['profile_image'])): ?>
                                <img src="uploads/<?=$user['profile_image']?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                             <?php else: ?>
                                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                             <?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 14px; color: #111827;"><?= htmlspecialchars($user['full_name']) ?></div>
                            <div style="font-size: 12px; color: #6B7280;"><?= ucfirst($user['role']) ?></div>
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
                
                <!-- If no user selected -->
                <div id="noChatSelected" style="height: 100%; display: flex; align-items: center; justify-content: center; color: #9CA3AF; flex-direction: column;">
                    <i class="fa fa-comments" style="font-size: 64px; margin-bottom: 16px; opacity: 0.2;"></i>
                    <p style="font-size: 16px; font-weight: 500;">Select a user to start messaging</p>
                </div>

                <!-- Chat Header & Box (Hidden initially) -->
                 <div id="chatInterface" style="display: none; height: 100%; flex-direction: column;">
                     <div class="chat-header">
                        <span id="chatUserName" style="font-weight: 600; font-size: 16px; color: #111827;"></span> 
                        <span id="chatUserRole" style="font-weight: 400; color: #6B7280; font-size: 12px; margin-left: 10px;"></span>
                    </div>
                    
                    <div class="chat-messages" id="chatBox">
                        <!-- Messages load here -->
                    </div>

                    <div class="chat-input-area">
                        <input type="text" id="messageInput" placeholder="Type a message..." class="form-input" style="margin-bottom: 0;">
                        <button id="sendBtn" class="btn-primary" style="padding: 0 20px;"><i class="fa fa-paper-plane"></i></button>
                    </div>
                 </div>

            </div>

        </div>
    </div>

    <script>
        $(document).ready(function(){
            
            var currentChatUserId = 0;
            var loadInterval;

            // Search Filter
             $("#searchText").on("input", function(){
               var searchText = $(this).val();
               if(searchText == "") return;
               
               $.post('app/ajax/search.php', { key: searchText }, function(data, status){
                   $("#chatList").html(data);
                   bindChatClicks(); // Rebind clicks on new elements
               });
            });

            bindChatClicks();

            function bindChatClicks(){
                $(".chat-item").click(function(){
                    // Styles
                    $(".chat-item").removeClass("active");
                    $(this).addClass("active");

                    // Data
                    var userId = $(this).attr("data-id");
                    var userName = $(this).attr("data-name");
                    var userRole = $(this).attr("data-role");
                    currentChatUserId = userId;

                    // UI Update
                    $("#noChatSelected").hide();
                    $("#chatInterface").css("display", "flex");
                    $("#chatUserName").text(userName);
                    $("#chatUserRole").text(userRole);

                    // Load Messages immediately
                    loadMessages();

                    // Scroll to bottom
                    scrollDown();
                    
                    // Clear search to restore list (optional UX choice)
                    // if($("#searchText").val() != "") {
                    //    $("#searchText").val("");
                    //    // Reload full list logic needed here if desired
                    // }
                });
            }

            $("#sendBtn").click(function(){
                sendMessage();
            });

            $("#messageInput").keypress(function(e){
                if(e.which == 13) sendMessage();
            });

            function sendMessage() {
                var message = $("#messageInput").val();
                if(message == "") return;

                $.post("app/ajax/insert.php", {
                    message: message,
                    to_id: currentChatUserId
                }, function(data, status){
                    $("#messageInput").val("");
                    loadMessages();
                    scrollDown();
                });
            }

            function loadMessages() {
                if(currentChatUserId == 0) return;
                
                 $.post("app/ajax/getMessage.php", { id_2: currentChatUserId }, function(data, status){
                    $("#chatBox").html(data);
                    // Only scroll down if we are already near bottom or it's first load? 
                    // For simplicity, we can rely on user scrolling or maintain position.
                    // scrollDown(); 
                });
            }

            function scrollDown(){
                 var chatBox = document.getElementById("chatBox");
                 chatBox.scrollTop = chatBox.scrollHeight;
            }

            // Real-time polling
            setInterval(loadMessages, 3000); // Check every 3 seconds

        });
    </script>
</body>
</html>
<?php }else{ 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
?>
