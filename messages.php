<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/Model/User.php";
    include "app/Model/Message.php";
    
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
    <link rel="stylesheet" href="css/chat.css">
    
    <!-- jQuery for AJAX -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
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
                    <div class="chat-search-input-wrapper">
                        <i class="fa fa-search chat-search-icon"></i>
                        <input type="text" id="searchText" placeholder="Search users...">
                    </div>
                </div>
                
                <div class="chat-list" id="chatList">
                    <?php 
                    if ($users != 0) {
                        foreach ($users as $user) {
                            if ($user['id'] == $_SESSION['id']) continue; // Skip self

                            $lastMessage = lastChat($_SESSION['id'], $user['id'], $pdo);
                            $unreadCount = countUnreadChat($user['id'], $_SESSION['id'], $pdo);
                            $unreadClass = ($unreadCount > 0) ? "unread" : "";
                    ?>
                    <div class="chat-item <?=$unreadClass?>" data-id="<?=$user['id']?>" data-name="<?=htmlspecialchars($user['full_name'])?>" data-role="<?=ucfirst($user['role'])?>">
                        <div class="avatar-md">
                             <?php if (!empty($user['profile_image']) && $user['profile_image'] != 'default.png' && file_exists('uploads/' . $user['profile_image'])): ?>
                                <img src="uploads/<?=$user['profile_image']?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                             <?php else: ?>
                                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                             <?php endif; ?>
                        </div>
                        <div class="chat-item-content">
                            <div class="chat-item-header">
                                <span class="chat-user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                                <?php if($unreadCount > 0) { ?>
                                    <span class="message-badge"><?=$unreadCount?></span>
                                <?php } ?>
                            </div>
                            <!-- <div class="chat-user-role"><?= ucfirst($user['role']) ?></div> -->
                            <?php if(!empty($lastMessage)) { ?>
                                <div class="chat-item-last-msg">
                                    <?php 
                                        if($lastMessage['sender_id'] == $_SESSION['id']) echo "You: ";
                                        echo htmlspecialchars($lastMessage['message']);
                                    ?>
                                </div>
                                <div class="chat-time" style="text-align: right; margin-top: -15px; font-size: 10px;">
                                    <?=formatChatTime($lastMessage['created_at'])?>
                                </div>
                            <?php } else { ?>
                                <div class="chat-user-role"><?= ucfirst($user['role']) ?></div>
                            <?php } ?>
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
                    <i class="fa fa-comments-o" style="font-size: 64px; margin-bottom: 16px; opacity: 0.2;"></i>
                    <p style="font-size: 16px; font-weight: 500;">Select a user to start messaging</p>
                </div>

                <!-- Chat Interface (Hidden initially) -->
                 <div id="chatInterface" style="display: none; height: 100%; flex-direction: column;">
                     
                    <div class="chat-header">
                        <div class="avatar-md chat-header-avatar" id="headerAvatar">
                            <!-- JS will populate this -->
                        </div>
                        <div class="chat-header-info">
                            <h3 id="chatUserName">User Name</h3>
                            <span id="chatUserRole">Role</span>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chatBox">
                        <!-- Messages load here -->
                    </div>

                    <div class="chat-input-area">
                        <div class="chat-input-wrapper">
                             <input type="text" id="messageInput" placeholder="Type a message...">
                        </div>
                        <button id="sendBtn" class="btn-send"><i class="fa fa-paper-plane-o"></i></button>
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
               if(searchText == "") {
                   // If empty, maybe reload filtered list or just show all if client-side hidden (currently ajax)
                   // For now, assuming ajax refresh is robust
               }
               
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
                    $(this).removeClass("unread"); // Remove unread styling

                    // Clear Badge logic
                    var badge = $(this).find(".message-badge");
                    if(badge.length > 0){
                         var count = parseInt(badge.text()) || 0;
                         badge.remove(); // Remove badge from user list

                         // Update Sidebar Badge
                         var sidebarBadge = $(".dash-nav-badge");
                         if(sidebarBadge.length > 0){
                             var currentTotal = parseInt(sidebarBadge.text()) || 0;
                             var newTotal = currentTotal - count;
                             if(newTotal <= 0){
                                 sidebarBadge.remove();
                             }else{
                                 sidebarBadge.text(newTotal);
                             }
                         }
                    }

                    // Data
                    var userId = $(this).attr("data-id");
                    var userName = $(this).attr("data-name");
                    var userRole = $(this).attr("data-role");
                    
                    // Avatar clone for header
                    var avatarHtml = $(this).find(".avatar-md").html();

                    currentChatUserId = userId;

                    // UI Update
                    $("#noChatSelected").hide();
                    $("#chatInterface").css("display", "flex");
                    $("#chatUserName").text(userName);
                    $("#chatUserRole").text(userRole);
                    $("#headerAvatar").html(avatarHtml);

                    // Load Messages immediately
                    loadMessages();
                    
                    // Auto scroll down will happen in loadMessages for first load
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
                    loadMessages(true); // true to force scroll
                });
            }

            function loadMessages(forceScroll = false) {
                if(currentChatUserId == 0) return;
                
                 $.post("app/ajax/getMessage.php", { id_2: currentChatUserId }, function(data, status){
                    var chatBox = $("#chatBox");
                    var isScrolledToBottom = chatBox[0].scrollHeight - chatBox[0].scrollTop <= chatBox[0].clientHeight + 50;
                    
                    $("#chatBox").html(data);
                    
                    // Scroll down if we were already at bottom or if forced (like after sending)
                    if(isScrolledToBottom || forceScroll) {
                        scrollDown();
                    }
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
