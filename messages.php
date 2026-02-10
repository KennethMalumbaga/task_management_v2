<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    include "app/model/user.php";
    include "app/model/Message.php";
    include "app/model/Group.php";
    include "app/model/GroupMessage.php";
    
    // Fetch users for the chat list
    // Fetch users for the chat list
    $all_users = get_all_users($pdo);
    $users = [];
    foreach ($all_users as $user) {
        if ($user['id'] == $_SESSION['id']) continue;
        
        $lastMessage = lastChat($_SESSION['id'], $user['id'], $pdo);
        $user['last_msg_time'] = !empty($lastMessage) ? $lastMessage['created_at'] : '0000-00-00 00:00:00';
        $user['last_message_data'] = $lastMessage; // Cache it to avoid re-querying
        $users[] = $user;
    }

    // Sort users by last message time desc
    usort($users, function($a, $b) {
        return strtotime($b['last_msg_time']) - strtotime($a['last_msg_time']);
    });

    // Fetch groups
    $all_groups = get_groups_for_user($pdo, $_SESSION['id']);
    $groups = [];
    if (!empty($all_groups)) {
        foreach ($all_groups as $group) {
            $lastGroupMsg = get_last_group_message($pdo, $group['id']);
            if (!empty($lastGroupMsg) && !empty($lastGroupMsg['created_at'])) {
                $group['last_msg_time'] = $lastGroupMsg['created_at'];
            } elseif (!empty($group['created_at'])) {
                $group['last_msg_time'] = $group['created_at'];
            } else {
                $group['last_msg_time'] = null;
            }
            $groups[] = $group;
        }
        // Sort groups by last message time desc
        usort($groups, function($a, $b) {
            return strtotime($b['last_msg_time'] ?? '1970-01-01 00:00:00') - strtotime($a['last_msg_time'] ?? '1970-01-01 00:00:00');
        });
    }
?>
<!DOCTYPE html>
<html>
<head>
	<title>Messages | TaskFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/chat.css">
    <link rel="stylesheet" href="css/chat_attachments.css">
    
    <!-- jQuery for AJAX -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body style="overflow: hidden;">
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main" style="height: 100vh; display: flex; flex-direction: column;">
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
                            $lastMessage = $user['last_message_data'];
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
                            </div>
                            
                            <div class="chat-item-sub-row">
                                <?php if(!empty($lastMessage)) { ?>
                                    <div class="chat-item-last-msg">
                                        <?php 
                                            if($lastMessage['sender_id'] == $_SESSION['id']) echo "You: ";
                                            if(!empty($lastMessage['attachment']) && empty($lastMessage['message'])) echo "<i class='fa fa-paperclip'></i> Attachment"; 
                                            else echo htmlspecialchars($lastMessage['message']);
                                        ?>
                                    </div>
                                <?php } else { ?>
                                    <div class="chat-user-role"><?= ucfirst($user['role']) ?></div>
                                <?php } ?>
                                
                                <?php if($unreadCount > 0) { ?>
                                    <span class="message-badge"><?=$unreadCount?></span>
                                <?php } ?>
                            </div>
                            
                            <?php if(!empty($lastMessage)) { ?>
                                <span class="chat-time"><?=formatChatTime($lastMessage['created_at'])?></span>
                            <?php } ?>
                        </div>
                    </div>
                    <?php 
                        }
                    } 
                    ?>
                </div>

                <!-- Group Chats -->
                <div style="margin-top: 12px; padding: 0 8px;">
                    <div style="font-size: 11px; font-weight: 700; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.5px; margin: 6px 0;">Groups</div>
                </div>
                <div class="chat-list" id="groupList">
                    <?php if (!empty($groups)) { foreach ($groups as $group) { ?>
                        <div class="chat-item group-item" data-group-id="<?=$group['id']?>" data-group-name="<?=htmlspecialchars($group['name'])?>">
                            <div class="avatar-md" style="background:#EEF2FF; color:#4F46E5;">
                                <i class="fa fa-users"></i>
                            </div>
                            <div class="chat-item-content">
                                <div class="chat-item-header">
                                    <span class="chat-user-name"><?=htmlspecialchars($group['name'])?></span>
                                </div>
                                <div class="chat-item-sub-row">
                                    <div class="chat-user-role">Group Chat</div>
                                    <?php 
                                        $grpUnread = get_group_unread_count($pdo, $group['id'], $_SESSION['id']);
                                        if($grpUnread > 0){
                                    ?>
                                        <span class="message-badge"><?=$grpUnread?></span>
                                    <?php } ?>
                                </div>
                                <?php if(!empty($group['last_msg_time'])) { ?>
                                     <div class="chat-time"><?=formatChatTime($group['last_msg_time'])?></div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } } else { ?>
                        <div style="padding: 12px; color:#9CA3AF; font-size:13px;">No groups yet.</div>
                    <?php } ?>
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
                        <div class="chat-header-user-area" style="display:flex; align-items:center;">
                            <!-- Back Button (Mobile Only) -->
                            <div class="btn-back-chat" id="backToChatList">
                                <i class="fa fa-arrow-left"></i>
                            </div>

                            <div class="avatar-md chat-header-avatar" id="headerAvatar">
                                <!-- JS will populate this -->
                            </div>
                            <div class="chat-header-info">
                                <h3 id="chatUserName">User Name</h3>
                                <span id="chatUserRole">Role</span>
                            </div>
                        </div>
                        <div class="chat-info-toggle" id="chatInfoToggle" title="Toggle Info" style="display:none;">
                            <i class="fa fa-info-circle"></i>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chatBox">
                        <!-- Messages load here via AJAX -->
                    </div>

                    <!-- Attachment Preview -->
                    <div id="attachmentPreview" class="attachment-preview">
                        <div class="file-info">
                            <i class="fa fa-file"></i> <span id="fileName">file.txt</span>
                        </div>
                        <i class="fa fa-times remove-attachment" id="removeAttachment"></i>
                    </div>

                    <div class="chat-input-area">
                        <div class="chat-input-wrapper">
                             <button type="button" class="btn-attach" id="attachBtn"><i class="fa fa-paperclip"></i></button>
                             <input type="file" id="fileInput" style="display: none;" multiple>
                             <input type="text" id="messageInput" placeholder="Type a message...">
                        </div>
                        <button id="sendBtn" class="btn-send"><i class="fa fa-paper-plane-o"></i></button>
                    </div>
                 </div>

            </div>

            <!-- Right Sidebar (Group Info) -->
            <div class="chat-info-sidebar" id="rightSidebar">
                <div class="chat-info-header">Group Info</div>
                <div class="chat-info-content" id="rightSidebarContent">
                    <!-- Loaded via AJAX -->
                </div>
            </div>

        </div>
    </div>

    <script>
        $(document).ready(function(){
            
            var currentChatUserId = 0;
            var currentGroupId = 0;
            var currentChatType = "user"; // user | group
            var loadInterval;
            var selectedFiles = []; // Array to store multiple files

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
            bindGroupClicks();

            function bindChatClicks(){
                $(".chat-item").click(function(){
                    if ($(this).hasClass("group-item")) return;
                    // Styles
                    $(".chat-item").removeClass("active");
                    $(this).addClass("active");
                    $(this).removeClass("unread"); // Remove unread styling

                    // Mobile Toggle Class
                    $(".chat-layout").addClass("mobile-chat-active");

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
                    currentGroupId = 0;
                    currentChatType = "user";

                    // UI Update
                    $("#noChatSelected").hide();
                    $("#chatInterface").css("display", "flex");
                    $("#chatUserName").text(userName);
                    $("#chatUserRole").text(userRole);
                    $("#headerAvatar").html(avatarHtml);
                    
                    // UI Reset for User Chat
                    $("#chatInfoToggle").hide();
                    $("#rightSidebar").removeClass("active");
                    
                    // Reset attachment
                    resetAttachment();

                    // Load Messages immediately
                    loadMessages();
                    
                    // Auto scroll down will happen in loadMessages for first load
                });
            }

            function bindGroupClicks(){
                $(".group-item").click(function(){
                    // Styles
                    $(".chat-item").removeClass("active");
                    $(this).addClass("active");

                    $(".chat-layout").addClass("mobile-chat-active");

                    // Clear Badge logic
                    var badge = $(this).find(".message-badge");
                    if(badge.length > 0){
                         var count = parseInt(badge.text()) || 0;
                         badge.remove(); // Remove badge from list

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

                    var groupId = $(this).attr("data-group-id");
                    var groupName = $(this).attr("data-group-name");

                    currentGroupId = groupId;
                    currentChatUserId = 0;
                    currentChatType = "group";

                    $("#noChatSelected").hide();
                    $("#chatInterface").css("display", "flex");
                    $("#chatUserName").text(groupName);
                    $("#chatUserRole").text("Group");
                    $("#headerAvatar").html('<i class="fa fa-users"></i>');
                    
                    // UI Set for Group Chat
                    $("#chatInfoToggle").show();
                    if($(window).width() > 900) {
                        $("#rightSidebar").addClass("active");
                    }
                    loadGroupDetails(groupId);
                    $("#chatInfoToggle").show(); // Show toggle for groups
                    
                    // Open sidebar by default for groups if desktop
                    if($(window).width() > 900) {
                        $("#rightSidebar").addClass("active");
                    }
                    loadGroupDetails(groupId);

                    resetAttachment();
                    loadMessages();
                });
            }

            $("#chatInfoToggle").click(function(){
                $("#rightSidebar").toggleClass("active");
            });

            function loadGroupDetails(groupId){
                $.post('app/ajax/getGroupDetails.php', { group_id: groupId }, function(data){
                    $("#rightSidebarContent").html(data);
                });
            }

            // Back Button Logic
            $("#backToChatList").click(function() {
                $(".chat-layout").removeClass("mobile-chat-active");
            });

            // Attachment Logic
            $("#attachBtn").click(function(){
                $("#fileInput").click();
            });
            
            $("#fileInput").change(function(){
                if(this.files && this.files.length > 0) {
                    for(var i=0; i<this.files.length; i++){
                        selectedFiles.push(this.files[i]);
                    }
                    updateAttachmentPreview();
                }
                $(this).val(""); // Clear input to allow re-selection of same file
            });
            
            // Remove specific attachment
            $(document).on("click", ".remove-file-item", function(){
                var index = $(this).attr("data-index");
                selectedFiles.splice(index, 1);
                updateAttachmentPreview();
            });
            
            $("#removeAttachment").click(function(){ // Clear all
                resetAttachment();
            });
            
            function updateAttachmentPreview() {
                if(selectedFiles.length > 0) {
                     var html = "";
                     var totalSize = 0;
                     for(var i=0; i<selectedFiles.length; i++){
                         html += `<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px;">
                                    <span><i class="fa fa-file"></i> ${selectedFiles[i].name}</span>
                                    <i class="fa fa-times remove-file-item" data-index="${i}" style="cursor: pointer; color: red; margin-left: 10px;"></i>
                                  </div>`;
                         totalSize += selectedFiles[i].size;
                     }
                     
                     // Warning if > 100MB
                     if(totalSize > 100 * 1024 * 1024) {
                         html += `<div style="color: red; font-size: 12px; margin-top: 5px;">Total size exceeds 100MB!</div>`;
                     }

                     $("#fileName").html(html); // We are replacing the simple span with list
                     $("#attachmentPreview").css("display", "flex");
                     $("#attachmentPreview").css("flex-direction", "column"); // Allow stacking
                     $("#attachmentPreview").css("align-items", "stretch");
                     $("#removeAttachment").show();
                     $("#removeAttachment").attr("title", "Clear All");
                } else {
                    $("#attachmentPreview").hide();
                    $("#fileName").text("");
                }
            }

            function resetAttachment() {
                selectedFiles = [];
                $("#fileInput").val("");
                $("#attachmentPreview").hide();
            }

            // Paste Event Listener
            window.addEventListener('paste', function(e) {
                var items = (e.clipboardData || e.originalEvent.clipboardData).items;
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf("image") !== -1) {
                        var blob = items[i].getAsFile();
                        
                        // Create a dummy name for the pasted image
                        var date = new Date();
                        var fileName = "screenshot_" + date.getTime() + ".png";
                        
                        // We need to treat blob as file with name
                        // A File object IS a Blob, so we can construct a File from it to keep name
                        var file = new File([blob], fileName, {type: blob.type});
                        
                        selectedFiles.push(file);
                        updateAttachmentPreview();
                        
                        e.preventDefault(); 
                    }
                }
            });

            $("#sendBtn").click(function(){
                sendMessage();
            });

            $("#messageInput").keypress(function(e){
                if(e.which == 13) sendMessage();
            });

            function sendMessage() {
                var message = $("#messageInput").val();
                
                if(message == "" && selectedFiles.length == 0) return;
                
                // Total Size Check Client Side
                var totalSize = 0;
                for(var i=0; i<selectedFiles.length; i++){
                    totalSize += selectedFiles[i].size;
                }
                if(totalSize > 100 * 1024 * 1024) {
                    alert("Total file size exceeds 100MB limit.");
                    return;
                }

                var formData = new FormData();
                formData.append("message", message);
                if (currentChatType === "group") {
                    formData.append("group_id", currentGroupId);
                } else {
                    formData.append("to_id", currentChatUserId);
                }
                
                if(selectedFiles.length > 0) {
                    for(var i=0; i<selectedFiles.length; i++){
                        formData.append("files[]", selectedFiles[i]);
                    }
                }

                $.ajax({
                    url: currentChatType === "group" ? 'app/ajax/insertGroupMessage.php' : 'app/ajax/insert.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(data) {
                        $("#messageInput").val("");
                        resetAttachment();
                        loadMessages(true); // true to force scroll
                        refreshChatLists(); // Update list order immediately
                    }
                });
            }

            function loadMessages(forceScroll = false) {
                if(currentChatType === "user" && currentChatUserId == 0) return;
                if(currentChatType === "group" && currentGroupId == 0) return;

                 var endpoint = currentChatType === "group" ? "app/ajax/getGroupMessage.php" : "app/ajax/getMessage.php";
                 var payload = currentChatType === "group" ? { group_id: currentGroupId } : { id_2: currentChatUserId };

                 $.post(endpoint, payload, function(data, status){
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
            setInterval(function(){
                loadMessages();
                refreshChatLists();
            }, 3000); // Check every 3 seconds

            function refreshChatLists(){
                // Only refresh if search is empty to avoid interrupting typing
                if($("#searchText").val() != "") return;

                $.get('app/ajax/getChatLists.php', function(data){
                    var res = JSON.parse(data);
                    
                    // Preserve active state
                    var activeUserId = currentChatType === 'user' ? currentChatUserId : 0;
                    var activeGroupId = currentChatType === 'group' ? currentGroupId : 0;

                    $("#chatList").html(res.users);
                    $("#groupList").html(res.groups);

                    // Re-apply active class
                    if(activeUserId != 0){
                        $(`.chat-item[data-id="${activeUserId}"]`).addClass("active");
                    }
                    if(activeGroupId != 0){
                        $(`.group-item[data-group-id="${activeGroupId}"]`).addClass("active");
                    }

                    bindChatClicks();
                    bindGroupClicks();
                });
            }

            // Auto-open chat if ID is provided in URL
            const urlParams = new URLSearchParams(window.location.search);
            const openUserId = urlParams.get('id');
            if (openUserId) {
                setTimeout(function() {
                    const targetItem = $(`.chat-item[data-id="${openUserId}"]`);
                    if (targetItem.length > 0) {
                        targetItem.click();
                    }
                }, 500); // Small delay to ensure list is rendered
            }

            const openGroupId = urlParams.get('group_id');
            if (openGroupId) {
                setTimeout(function() {
                    const targetItem = $(`.group-item[data-group-id="${openGroupId}"]`);
                    if (targetItem.length > 0) {
                        targetItem.click();
                    }
                }, 500);
            }

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


