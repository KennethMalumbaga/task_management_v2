<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    require_once "inc/csrf.php";
    include "app/model/user.php";
    include "app/model/Message.php";
    include "app/model/Group.php";
    include "app/model/GroupMessage.php";
    $chatAjaxCsrfToken = csrf_token('chat_ajax_actions');
    
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
    $userPresenceMap = get_users_clocked_in_map($pdo, array_column($users, 'id'));

    // Fetch groups
    $all_groups = get_groups_for_user($pdo, $_SESSION['id']);
    $groups = [];
    if (!empty($all_groups)) {
        foreach ($all_groups as $group) {
            $lastGroupMsg = get_last_group_message($pdo, $group['id']);
            $group['last_message_data'] = $lastGroupMsg;
            $group['last_msg_sort_time'] = (!empty($lastGroupMsg) && !empty($lastGroupMsg['created_at']))
                ? $lastGroupMsg['created_at']
                : null;
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
    $dashboardCssVersion = @filemtime(__DIR__ . "/css/dashboard.css") ?: time();
    $chatCssVersion = @filemtime(__DIR__ . "/css/chat.css") ?: time();
    $chatAttachmentsCssVersion = @filemtime(__DIR__ . "/css/chat_attachments.css") ?: time();
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
    <link rel="stylesheet" href="css/dashboard.css?v=<?= $dashboardCssVersion ?>">
    <link rel="stylesheet" href="css/chat.css?v=<?= $chatCssVersion ?>">
    <link rel="stylesheet" href="css/chat_attachments.css?v=<?= $chatAttachmentsCssVersion ?>">
    
    <!-- jQuery for AJAX -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body class="messages-page" style="overflow: hidden;">
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main messages-page-main">
        <div class="chat-layout">
            
            <!-- Chat Sidebar (Users) -->
            <div class="chat-sidebar">
                <div class="chat-search">
                    <div class="chat-search-input-wrapper">
                        <i class="fa fa-search chat-search-icon"></i>
                        <input type="text" id="searchText" placeholder="Search users or groups...">
                    </div>
                </div>
                <div class="chat-filter-tabs" role="tablist" aria-label="Filter chats">
                    <button type="button" class="chat-filter-tab active" data-filter="all" role="tab" aria-selected="true">All</button>
                    <button type="button" class="chat-filter-tab" data-filter="users" role="tab" aria-selected="false">Users</button>
                    <button type="button" class="chat-filter-tab" data-filter="groups" role="tab" aria-selected="false">Groups</button>
                </div>
                <div class="chat-list" id="allChatList"></div>
                
                <div class="chat-list" id="chatList">
                    <?php 
                    if ($users != 0) {
                        foreach ($users as $user) {
                            $lastMessage = $user['last_message_data'];
                            $unreadCount = countUnreadChat($user['id'], $_SESSION['id'], $pdo);
                            $user['is_online'] = !empty($userPresenceMap[(int)$user['id']]);
                    ?>
                    <?= render_chat_user_list_item($user, $lastMessage, $unreadCount, (int)$_SESSION['id']) ?>
                    <?php 
                        }
                    } 
                    ?>
                </div>

                <!-- Group Chats -->
                <div class="chat-group-heading">
                    <div class="chat-group-heading-label">Groups</div>
                </div>
                <div class="chat-list" id="groupList">
                    <?php if (!empty($groups)) { foreach ($groups as $group) { ?>
                        <?php
                            $lastGroupMsg = $group['last_message_data'] ?? [];
                            $groupLastTimestamp = !empty($group['last_msg_sort_time']) ? strtotime($group['last_msg_sort_time']) : 0;
                            if ($groupLastTimestamp === false) $groupLastTimestamp = 0;
                            $groupPreview = format_group_list_preview($pdo, $lastGroupMsg, (int)$_SESSION['id']);
                        ?>
                        <div class="chat-item group-item" data-group-id="<?=$group['id']?>" data-group-name="<?=htmlspecialchars($group['name'])?>" data-last-ts="<?=$groupLastTimestamp?>">
                            <div class="avatar-md" style="background:var(--primary-soft-3); color:var(--primary);">
                                <i class="fa fa-users"></i>
                            </div>
                            <div class="chat-item-content">
                                <div class="chat-item-header">
                                    <span class="chat-user-name"><?=htmlspecialchars($group['name'])?></span>
                                </div>
                                <div class="chat-item-sub-row">
                                    <div class="chat-item-last-msg"><?=$groupPreview?></div>
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
                <div id="noChatSelected" class="no-chat-state" style="height: 100%; display: flex; align-items: center; justify-content: center; color: #9CA3AF; flex-direction: column;">
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
                                <span id="chatUserMeta" class="chat-header-meta">Offline</span>
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
                             <input type="text" id="messageInput" placeholder="Send a message...">
                             <div id="mentionSuggestions" class="mention-suggestions" style="display:none;"></div>
                        </div>
                        <button id="sendBtn" class="btn-send"><span class="btn-send-label">Send</span><i class="fa fa-paper-plane-o"></i></button>
                    </div>
                 </div>

            </div>

            <!-- Right Sidebar (Group Info) -->
            <div class="chat-info-overlay" id="chatInfoOverlay"></div>
            <div class="chat-info-sidebar" id="rightSidebar">
                <div class="chat-info-header">
                    <span>Group Info</span>
                    <button class="btn-close-info" id="closeInfoSidebar"><i class="fa fa-times"></i></button>
                </div>
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
            var chatAjaxCsrfToken = <?= json_encode($chatAjaxCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var groupMentionMembers = [];
            var mentionSuggestionsData = [];
            var mentionSelectionIndex = -1;
            var currentListFilter = "all";
            var activeTypingMetaHtml = "";
            var lastTypingInputAt = 0;
            var lastTypingStateKey = "";
            var lastTypingSentAt = 0;
            var typingStatusRequestInFlight = false;

            // Search Filter
             $("#searchText").on("input", function(){
               var searchText = $(this).val();
               if(searchText == "") {
                   // If empty, maybe reload filtered list or just show all if client-side hidden (currently ajax)
                   // For now, assuming ajax refresh is robust
               }
               
               $.post('app/ajax/search.php', { key: searchText }, function(data, status){
                    var res = JSON.parse(data);
                    $("#chatList").html(res.users);
                    $("#groupList").html(res.groups);
                    applyChatFilter(currentListFilter);
                });
             });

            bindFilterTabs();
            applyChatFilter(currentListFilter);

            function buildUserMetaHtml(isOnline){
                var stateClass = isOnline ? "is-online" : "is-offline";
                var label = isOnline ? "Online" : "Offline";
                return '<span class="chat-user-presence ' + stateClass + '"><span class="chat-user-presence-dot ' + stateClass + '"></span>' + label + '</span>';
            }

            function updateUserHeaderFromItem(chatItem){
                if (!chatItem || !chatItem.length) return;

                var userName = chatItem.attr("data-name") || "User";
                var isOnline = String(chatItem.attr("data-online") || "") === "1";
                var avatarHtml = chatItem.find(".avatar-md").html();

                $("#chatUserName").text(userName);
                $("#chatUserMeta").html(buildUserMetaHtml(isOnline));
                $("#headerAvatar").html(avatarHtml);
            }

            function syncActiveChatHeader(){
                if (currentChatType === "user" && currentChatUserId != 0) {
                    var activeUserItem = $('.chat-item[data-id="' + currentChatUserId + '"]').not(".group-item").first();
                    if (activeUserItem.length > 0) {
                        updateUserHeaderFromItem(activeUserItem);
                    }
                    return;
                }

                if (currentChatType === "group" && currentGroupId != 0) {
                    var activeGroupItem = $('.group-item[data-group-id="' + currentGroupId + '"]').first();
                    if (activeGroupItem.length > 0) {
                        var groupName = activeGroupItem.attr("data-group-name") || "Group";
                        $("#chatUserName").text(groupName);
                    }
                    $("#chatUserMeta").text("Group Chat");
                    $("#headerAvatar").html('<i class="fa fa-users"></i>');
                }
            }

            function getActiveTypingContext() {
                if (currentChatType === "group" && currentGroupId != 0) {
                    return {
                        type: "group",
                        id: parseInt(currentGroupId, 10) || 0,
                        key: "group:" + currentGroupId
                    };
                }

                if (currentChatType === "user" && currentChatUserId != 0) {
                    return {
                        type: "user",
                        id: parseInt(currentChatUserId, 10) || 0,
                        key: "user:" + currentChatUserId
                    };
                }

                return null;
            }

            function buildTypingAvatarStackHtml(avatars) {
                if (!Array.isArray(avatars) || avatars.length === 0) {
                    return "";
                }

                var html = '<span class="chat-typing-avatar-stack" aria-hidden="true">';
                for (var i = 0; i < avatars.length && i < 2; i++) {
                    var avatar = avatars[i] || {};
                    var imageUrl = $.trim(avatar.image_url || "");
                    var initials = $.trim(avatar.initials || "?") || "?";
                    var name = $.trim(avatar.name || "User") || "User";

                    html += '<span class="chat-typing-avatar" title="' + escapeHtml(name) + '">';
                    if (imageUrl !== "") {
                        html += '<img src="' + escapeHtml(imageUrl) + '" alt="' + escapeHtml(name) + '">';
                    } else {
                        html += '<span class="chat-typing-avatar-fallback">' + escapeHtml(initials) + '</span>';
                    }
                    html += '</span>';
                }
                html += '</span>';

                return html;
            }

            function buildTypingMetaHtml(label, avatars) {
                var safeLabel = $.trim(label || "") || "Typing";
                var avatarHtml = buildTypingAvatarStackHtml(avatars);
                return '<div class="message-incoming chat-typing-message" id="chatTypingIndicator">' +
                            avatarHtml +
                            '<div class="message-structure">' +
                                '<div class="chat-typing-bubble" role="status" aria-live="polite" aria-label="' + escapeHtml(safeLabel) + '">' +
                                    '<span class="chat-typing-dots" aria-hidden="true"><span></span><span></span><span></span></span>' +
                                '</div>' +
                            '</div>' +
                       '</div>';
            }

            function isChatBoxNearBottom() {
                var chatBox = $("#chatBox");
                if (chatBox.length === 0 || !chatBox[0]) {
                    return true;
                }

                return chatBox[0].scrollHeight - chatBox[0].scrollTop <= chatBox[0].clientHeight + 50;
            }

            function renderTypingIndicatorInChat(shouldScroll) {
                var chatBox = $("#chatBox");
                if (chatBox.length === 0) {
                    return;
                }

                chatBox.find("#chatTypingIndicator").remove();
                if (activeTypingMetaHtml !== "") {
                    chatBox.append(activeTypingMetaHtml);
                }

                if (shouldScroll) {
                    scrollDown();
                }
            }

            function applyTypingMetaHtml(html) {
                var shouldScroll = isChatBoxNearBottom();
                activeTypingMetaHtml = html || "";
                renderTypingIndicatorInChat(shouldScroll);
            }

            function postTypingState(context, isTyping) {
                if (!context || !context.id) return;

                var payload = {
                    is_typing: isTyping ? 1 : 0,
                    csrf_token: chatAjaxCsrfToken
                };

                if (context.type === "group") {
                    payload.group_id = context.id;
                } else {
                    payload.user_id = context.id;
                }

                $.ajax({
                    url: 'app/ajax/setTypingStatus.php',
                    type: 'POST',
                    data: payload
                });
            }

            function syncOwnTypingState(isTyping, force, contextOverride) {
                var context = contextOverride || getActiveTypingContext();
                if (!context || !context.id) {
                    if (!isTyping) {
                        lastTypingStateKey = "";
                        lastTypingSentAt = 0;
                    }
                    return;
                }

                if (!isTyping && lastTypingStateKey === "") {
                    return;
                }

                var nextStateKey = context.key + ":" + (isTyping ? "1" : "0");
                var now = Date.now();

                if (lastTypingStateKey === nextStateKey) {
                    if (!isTyping) {
                        if (!force || (now - lastTypingSentAt) < 400) {
                            return;
                        }
                    } else if ((now - lastTypingSentAt) < 1800) {
                        return;
                    }
                }

                postTypingState(context, isTyping);
                lastTypingStateKey = nextStateKey;
                lastTypingSentAt = now;
            }

            function maintainOwnTypingState() {
                var context = getActiveTypingContext();
                if (!context) {
                    return;
                }

                var hasText = $.trim($("#messageInput").val() || "") !== "";
                var isActivelyTyping = hasText && lastTypingInputAt > 0 && (Date.now() - lastTypingInputAt) < 4000;

                if (!hasText && /:1$/.test(lastTypingStateKey)) {
                    syncOwnTypingState(false, true);
                    return;
                }

                syncOwnTypingState(isActivelyTyping, false);
            }

            function refreshTypingStatus() {
                var context = getActiveTypingContext();
                if (!context || !context.id || typingStatusRequestInFlight) {
                    if (!context) {
                        applyTypingMetaHtml("");
                    }
                    return;
                }

                typingStatusRequestInFlight = true;
                var requestKey = context.key;
                var payload = { csrf_token: chatAjaxCsrfToken };

                if (context.type === "group") {
                    payload.group_id = context.id;
                } else {
                    payload.user_id = context.id;
                }

                $.ajax({
                    url: 'app/ajax/getTypingStatus.php',
                    type: 'POST',
                    dataType: 'json',
                    data: payload
                }).done(function(res){
                    var activeContext = getActiveTypingContext();
                    if (!activeContext || activeContext.key !== requestKey) {
                        return;
                    }

                    if (res && res.ok && res.typing) {
                        applyTypingMetaHtml(buildTypingMetaHtml(res.label, res.avatars || []));
                        return;
                    }

                    applyTypingMetaHtml("");
                }).always(function(){
                    typingStatusRequestInFlight = false;
                });
            }

            function bindChatClicks(){
                $(".chat-item").off("click.chatUser").on("click.chatUser", function(){
                    if ($(this).hasClass("group-item")) return;

                    // Data
                    var userId = $(this).attr("data-id");
                    var previousContext = getActiveTypingContext();
                    var nextTypingKey = "user:" + userId;
                    var hasContextChanged = !previousContext || previousContext.key !== nextTypingKey;

                    if (previousContext && hasContextChanged) {
                        syncOwnTypingState(false, true, previousContext);
                    }

                    if (hasContextChanged) {
                        lastTypingInputAt = 0;
                        lastTypingStateKey = "";
                        lastTypingSentAt = 0;
                        applyTypingMetaHtml("");
                    }
                    
                    // Styles
                    $(".chat-item").removeClass("active");
                    $('.chat-item[data-id="' + userId + '"]').addClass("active");
                    $('.chat-item[data-id="' + userId + '"]').removeClass("unread");

                    // Mobile Toggle Class
                    $(".chat-layout").addClass("mobile-chat-active");

                    // Clear Badge logic
                    var badge = $(this).find(".message-badge");
                    if(badge.length > 0){
                         var count = parseInt(badge.first().text()) || 0;
                         $('.chat-item[data-id="' + userId + '"] .message-badge').remove();

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

                          // Update Mobile Header Badge
                          var mobileHeaderBadge = $(".mobile-unread-badge");
                          if(mobileHeaderBadge.length > 0){
                              var currentTotalHeader = parseInt(mobileHeaderBadge.text()) || 0;
                              var newTotalHeader = currentTotalHeader - count;
                              if(newTotalHeader <= 0){
                                  mobileHeaderBadge.remove();
                              }else{
                                  mobileHeaderBadge.text(newTotalHeader);
                              }
                          }
                    }

                    currentChatUserId = userId;
                    currentGroupId = 0;
                    currentChatType = "user";
                    groupMentionMembers = [];
                    hideMentionSuggestions();

                    // UI Update
                    $("#noChatSelected").hide();
                    $("#chatInterface").css("display", "flex");
                    updateUserHeaderFromItem($(this));
                    
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
                $(".group-item").off("click.chatGroup").on("click.chatGroup", function(){
                    var groupId = $(this).attr("data-group-id");
                    var groupName = $(this).attr("data-group-name");
                    var previousContext = getActiveTypingContext();
                    var nextTypingKey = "group:" + groupId;
                    var hasContextChanged = !previousContext || previousContext.key !== nextTypingKey;

                    if (previousContext && hasContextChanged) {
                        syncOwnTypingState(false, true, previousContext);
                    }

                    if (hasContextChanged) {
                        lastTypingInputAt = 0;
                        lastTypingStateKey = "";
                        lastTypingSentAt = 0;
                        applyTypingMetaHtml("");
                    }

                    // Styles
                    $(".chat-item").removeClass("active");
                    $('.group-item[data-group-id="' + groupId + '"]').addClass("active");

                    $(".chat-layout").addClass("mobile-chat-active");

                    // Clear Badge logic
                    var badge = $(this).find(".message-badge");
                    if(badge.length > 0){
                         var count = parseInt(badge.first().text()) || 0;
                         $('.group-item[data-group-id="' + groupId + '"] .message-badge').remove();

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

                         // Update Mobile Header Badge
                         var mobileHeaderBadge = $(".mobile-unread-badge");
                         if(mobileHeaderBadge.length > 0){
                             var currentTotalHeader = parseInt(mobileHeaderBadge.text()) || 0;
                             var newTotalHeader = currentTotalHeader - count;
                             if(newTotalHeader <= 0){
                                 mobileHeaderBadge.remove();
                             }else{
                                 mobileHeaderBadge.text(newTotalHeader);
                             }
                         }
                    }

                    currentGroupId = groupId;
                    currentChatUserId = 0;
                    currentChatType = "group";
                    hideMentionSuggestions();

                    $("#noChatSelected").hide();
                    $("#chatInterface").css("display", "flex");
                    $("#chatUserName").text(groupName);
                    $("#chatUserMeta").text("Group Chat");
                    $("#headerAvatar").html('<i class="fa fa-users"></i>');
                    
                    // UI Set for Group Chat
                    $("#chatInfoToggle").show();
                    $("#rightSidebar").removeClass("active");
                    $("#chatInfoOverlay").removeClass("active");
                    
                    // Reset attachment
                    resetAttachment();
                    loadMessages();

                    loadGroupDetails(groupId);
                    loadGroupMentionMembers(groupId);
                });
            }

            function buildCombinedChatList(){
                var combined = [];
                $("#chatList .chat-item, #groupList .chat-item").each(function(){
                    var ts = parseInt($(this).attr("data-last-ts"), 10);
                    if (isNaN(ts)) ts = 0;
                    combined.push({
                        ts: ts,
                        html: this.outerHTML
                    });
                });

                combined.sort(function(a, b){
                    return b.ts - a.ts;
                });

                if (combined.length === 0) {
                    $("#allChatList").html('<div style="padding: 12px; color:#9CA3AF; font-size:13px;">No conversations yet.</div>');
                    return;
                }

                var html = "";
                for (var i = 0; i < combined.length; i++) {
                    html += combined[i].html;
                }
                $("#allChatList").html(html);
            }

            function bindFilterTabs(){
                $(".chat-filter-tab").off("click").on("click", function(){
                    var selectedFilter = $(this).attr("data-filter") || "all";
                    applyChatFilter(selectedFilter);
                });
            }

            function applyChatFilter(filterType){
                var allowedFilters = { all: true, users: true, groups: true };
                currentListFilter = allowedFilters[filterType] ? filterType : "all";
                var sidebar = $(".chat-sidebar");

                buildCombinedChatList();

                sidebar.removeClass("filter-all filter-users-only filter-groups-only");

                $(".chat-filter-tab").removeClass("active").attr("aria-selected", "false");
                $('.chat-filter-tab[data-filter="' + currentListFilter + '"]').addClass("active").attr("aria-selected", "true");

                if(currentListFilter === "users"){
                    sidebar.addClass("filter-users-only");
                    $("#allChatList, #groupList, .chat-group-heading").hide();
                    $("#chatList").show();
                } else if(currentListFilter === "groups"){
                    sidebar.addClass("filter-groups-only");
                    $("#allChatList, #chatList").hide();
                    $("#groupList, .chat-group-heading").show();
                } else {
                    sidebar.addClass("filter-all");
                    $("#chatList, #groupList, .chat-group-heading").hide();
                    $("#allChatList").show();
                }

                if(currentChatType === "user" && currentChatUserId != 0){
                    $('.chat-item[data-id="' + currentChatUserId + '"]').addClass("active");
                }
                if(currentChatType === "group" && currentGroupId != 0){
                    $('.group-item[data-group-id="' + currentGroupId + '"]').addClass("active");
                }

                bindChatClicks();
                bindGroupClicks();
                syncActiveChatHeader();
            }

            $("#chatInfoToggle").click(function(){
                $("#rightSidebar").toggleClass("active");
                if($(window).width() <= 900) {
                    $("#chatInfoOverlay").toggleClass("active");
                }
            });

            $("#closeInfoSidebar, #chatInfoOverlay").click(function(){
                $("#rightSidebar").removeClass("active");
                $("#chatInfoOverlay").removeClass("active");
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
                     
                     // Warning if > 50MB
                     if(totalSize > 50 * 1024 * 1024) {
                         html += `<div style="color: red; font-size: 12px; margin-top: 5px;">Total size exceeds 50MB!</div>`;
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

            $("#messageInput").on("input", function(){
                updateMentionSuggestions();
                var hasText = $.trim($(this).val() || "") !== "";
                lastTypingInputAt = hasText ? Date.now() : 0;
                syncOwnTypingState(hasText, true);
            });

            $("#messageInput").on("keydown", function(e){
                if (!isMentionSuggestionsVisible()) {
                    if (e.which === 13) sendMessage();
                    return;
                }

                if (e.which === 40) { // Down
                    e.preventDefault();
                    moveMentionSelection(1);
                    return;
                }
                if (e.which === 38) { // Up
                    e.preventDefault();
                    moveMentionSelection(-1);
                    return;
                }
                if (e.which === 13) { // Enter select mention
                    e.preventDefault();
                    applySelectedMention();
                    return;
                }
                if (e.which === 27) { // Esc
                    e.preventDefault();
                    hideMentionSuggestions();
                }
            });

            $(document).on("mousedown", ".mention-suggestion-item", function(e){
                e.preventDefault();
                var idx = parseInt($(this).attr("data-idx"), 10);
                if (!isNaN(idx) && mentionSuggestionsData[idx] && mentionSuggestionsData[idx].full_name) {
                    applyMentionName(mentionSuggestionsData[idx].full_name);
                }
            });

            $(document).on("click", function(e){
                if (!$(e.target).closest("#messageInput, #mentionSuggestions").length) {
                    hideMentionSuggestions();
                }
            });

            function sendMessage() {
                var message = $("#messageInput").val();
                
                if(message == "" && selectedFiles.length == 0) return;
                
                // Total Size Check Client Side
                var totalSize = 0;
                for(var i=0; i<selectedFiles.length; i++){
                    totalSize += selectedFiles[i].size;
                }
                if(totalSize > 50 * 1024 * 1024) {
                    alert("Total file size exceeds 50MB limit.");
                    return;
                }

                var formData = new FormData();
                formData.append("message", message);
                formData.append("csrf_token", chatAjaxCsrfToken);
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
                        lastTypingInputAt = 0;
                        syncOwnTypingState(false, true);
                        hideMentionSuggestions();
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
                 payload.csrf_token = chatAjaxCsrfToken;

                 $.post(endpoint, payload, function(data, status){
                    var chatBox = $("#chatBox");
                    var isScrolledToBottom = chatBox[0].scrollHeight - chatBox[0].scrollTop <= chatBox[0].clientHeight + 50;
                    
                    $("#chatBox").html(data);
                    renderTypingIndicatorInChat(false);
                    
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

            setInterval(function(){
                maintainOwnTypingState();
                refreshTypingStatus();
            }, 1500);

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

                    // Update Global Badges (Sidebar & Mobile Header)
                    if(res.totalUnread > 0) {
                        // Update Sidebar Badge
                        if($(".dash-nav-badge").length > 0) {
                            $(".dash-nav-badge").text(res.totalUnread);
                        } else {
                            // Find messages link in sidebar and add badge
                            $('.dash-nav-item[href="messages.php"]').append('<span class="dash-nav-badge">' + res.totalUnread + '</span>');
                        }

                        // Update Mobile Header Badge
                        if($(".mobile-unread-badge").length > 0) {
                            $(".mobile-unread-badge").text(res.totalUnread);
                        } else {
                            // Add badge if it doesn't exist
                            $('.mobile-msg-icon').append('<span class="mobile-unread-badge">' + res.totalUnread + '</span>');
                        }
                    } else {
                        $(".dash-nav-badge").remove();
                        $(".mobile-unread-badge").remove();
                    }

                    // Re-apply active class
                    if(activeUserId != 0){
                        $(`.chat-item[data-id="${activeUserId}"]`).addClass("active");
                    }
                    if(activeGroupId != 0){
                        $(`.group-item[data-group-id="${activeGroupId}"]`).addClass("active");
                    }

                    applyChatFilter(currentListFilter);
                });
            }

            function loadGroupMentionMembers(groupId) {
                groupMentionMembers = [];
                mentionSuggestionsData = [];
                mentionSelectionIndex = -1;
                if (!groupId) return;

                $.post('app/ajax/getGroupMembers.php', { group_id: groupId, csrf_token: chatAjaxCsrfToken }, function(data){
                    var res = null;
                    try {
                        res = (typeof data === "string") ? JSON.parse(data) : data;
                    } catch (e) {
                        res = null;
                    }
                    if (res && res.ok && Array.isArray(res.members)) {
                        groupMentionMembers = res.members;
                    }
                });
            }

            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function isMentionSuggestionsVisible() {
                return $("#mentionSuggestions").is(":visible") && mentionSuggestionsData.length > 0;
            }

            function hideMentionSuggestions() {
                mentionSuggestionsData = [];
                mentionSelectionIndex = -1;
                $("#mentionSuggestions").hide().empty();
            }

            function getMentionContext() {
                if (currentChatType !== "group") return null;
                var inputEl = document.getElementById("messageInput");
                if (!inputEl) return null;

                var text = inputEl.value || "";
                var caret = inputEl.selectionStart || 0;
                var left = text.slice(0, caret);
                var match = left.match(/(?:^|\s)@([^\n@]*)$/);
                if (!match) return null;

                var query = match[1] || "";
                var start = caret - query.length - 1; // points to '@'
                return { query: query, start: start, caret: caret };
            }

            function renderMentionSuggestions(items) {
                if (!items.length) {
                    hideMentionSuggestions();
                    return;
                }

                mentionSuggestionsData = items;
                if (mentionSelectionIndex < 0 || mentionSelectionIndex >= mentionSuggestionsData.length) {
                    mentionSelectionIndex = 0;
                }

                var html = "";
                for (var i = 0; i < mentionSuggestionsData.length; i++) {
                    var item = mentionSuggestionsData[i];
                    var activeClass = (i === mentionSelectionIndex) ? " active" : "";
                    var display = item.display_name || item.full_name || "";
                    var subtitle = item.subtitle || "";
                    var avatarHtml = '';
                    if (item.is_everyone) {
                        avatarHtml = '<div class="mention-suggestion-avatar everyone"><i class="fa fa-users"></i></div>';
                    } else if (item.profile_image && item.profile_image !== 'default.png') {
                        avatarHtml = '<div class="mention-suggestion-avatar"><img src="uploads/' + encodeURIComponent(item.profile_image) + '" alt=""></div>';
                    } else {
                        var initial = display ? display.charAt(0).toUpperCase() : '?';
                        avatarHtml = '<div class="mention-suggestion-avatar fallback">' + escapeHtml(initial) + '</div>';
                    }

                    html += '<div class="mention-suggestion-item' + activeClass + '" data-idx="' + i + '">' +
                                avatarHtml +
                                '<div class="mention-suggestion-meta">' +
                                    '<div class="mention-suggestion-name">' + escapeHtml(display) + '</div>' +
                                    (subtitle ? ('<div class="mention-suggestion-sub">' + escapeHtml(subtitle) + '</div>') : '') +
                                '</div>' +
                            '</div>';
                }

                $("#mentionSuggestions").html(html).show();
            }

            function updateMentionSuggestions() {
                var ctx = getMentionContext();
                if (!ctx || !Array.isArray(groupMentionMembers) || groupMentionMembers.length === 0) {
                    hideMentionSuggestions();
                    return;
                }

                var query = (ctx.query || "").trim().toLowerCase();
                var filtered = groupMentionMembers.filter(function(member){
                    var name = (member.full_name || "").toLowerCase();
                    if (!name) return false;
                    return query === "" || name.indexOf(query) !== -1;
                }).slice(0, 7).map(function(member){
                    return {
                        id: member.id,
                        full_name: member.full_name,
                        display_name: member.full_name,
                        mention_value: member.full_name,
                        profile_image: member.profile_image || '',
                        subtitle: (member.user_role || 'member').toString().toUpperCase(),
                        is_everyone: false
                    };
                });

                if ("everyone".indexOf(query) !== -1 || query === "") {
                    filtered.unshift({
                        id: 0,
                        full_name: 'everyone',
                        display_name: 'everyone',
                        mention_value: 'everyone',
                        profile_image: '',
                        subtitle: 'Mention everyone in this chat',
                        is_everyone: true
                    });
                }

                renderMentionSuggestions(filtered);
            }

            function moveMentionSelection(direction) {
                if (!mentionSuggestionsData.length) return;
                mentionSelectionIndex += direction;
                if (mentionSelectionIndex < 0) mentionSelectionIndex = mentionSuggestionsData.length - 1;
                if (mentionSelectionIndex >= mentionSuggestionsData.length) mentionSelectionIndex = 0;
                renderMentionSuggestions(mentionSuggestionsData);
            }

            function applySelectedMention() {
                if (!mentionSuggestionsData.length || mentionSelectionIndex < 0) return;
                var picked = mentionSuggestionsData[mentionSelectionIndex];
                if (!picked || !picked.mention_value) return;
                applyMentionName(picked.mention_value);
            }

            function applyMentionName(name) {
                var ctx = getMentionContext();
                var inputEl = document.getElementById("messageInput");
                if (!ctx || !inputEl) return;

                var value = inputEl.value || "";
                var before = value.slice(0, ctx.start);
                var after = value.slice(ctx.caret);
                var replacement = "@" + name + " ";
                var nextValue = before + replacement + after;
                inputEl.value = nextValue;

                var newCaret = (before + replacement).length;
                inputEl.focus();
                inputEl.setSelectionRange(newCaret, newCaret);
                hideMentionSuggestions();
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



