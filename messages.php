<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    include "DB_connection.php";
    require_once "inc/csrf.php";
    include "app/model/user.php";
    include "app/model/Message.php";
    include "app/model/Group.php";
    include "app/model/GroupMessage.php";
    include "app/model/ChatVisibility.php";
    require_once "app/helpers/chat_sidebar.php";
    require_once "app/model/GoogleWorkspace.php";
    require_once "app/helpers/google_gmail.php";
    $chatAjaxCsrfToken = csrf_token('chat_ajax_actions');
    $composeEmailCsrfToken = csrf_token('compose_email_action');
    
    $chatSidebarData = chat_sidebar_build_data($pdo, (int)$_SESSION['id']);
    $all_users = $chatSidebarData['all_users'];
    $users = $chatSidebarData['users'];
    $groups = $chatSidebarData['groups'];
    $dashboardCssVersion = @filemtime(__DIR__ . "/css/dashboard.css") ?: time();
    $chatCssVersion = @filemtime(__DIR__ . "/css/chat.css") ?: time();
    $chatAttachmentsCssVersion = @filemtime(__DIR__ . "/css/chat_attachments.css") ?: time();
    $isAdminUser = ((string)($_SESSION['role'] ?? '') === 'admin');
    $formalEmailUsers = [];
    if ($isAdminUser) {
        foreach ($all_users as $emailUser) {
            $emailUserId = (int)($emailUser['id'] ?? 0);
            if ($emailUserId <= 0 || $emailUserId === (int)$_SESSION['id']) {
                continue;
            }

            if ((string)($emailUser['role'] ?? '') === 'admin') {
                continue;
            }

            $emailAddress = strtolower(trim((string)($emailUser['username'] ?? '')));
            if ($emailAddress === '' || !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $displayName = trim((string)($emailUser['full_name'] ?? ''));
            if ($displayName === '') {
                $displayName = $emailAddress;
            }

            $formalEmailUsers[] = [
                'id' => $emailUserId,
                'full_name' => $displayName,
                'email' => $emailAddress,
                'initials' => user_display_initials($displayName),
            ];
        }

        usort($formalEmailUsers, function ($left, $right) {
            $leftKey = strtolower((string)($left['full_name'] ?? '') . ' ' . (string)($left['email'] ?? ''));
            $rightKey = strtolower((string)($right['full_name'] ?? '') . ' ' . (string)($right['email'] ?? ''));
            return strcmp($leftKey, $rightKey);
        });
    }

    $gmailTokenRecord = $isAdminUser ? google_workspace_get_token_record($pdo, (int)$_SESSION['id']) : null;
    $gmailReady = $isAdminUser
        && google_gmail_is_enabled()
        && $gmailTokenRecord
        && google_workspace_scope_contains((string)($gmailTokenRecord['scope'] ?? ''), google_gmail_required_scope());
    $gmailConfigReady = google_gmail_is_enabled();
    $gmailConnectUrl = 'app/google-gmail-init.php';
    $formalEmailSenderAddress = strtolower(trim((string)($gmailTokenRecord['google_email'] ?? ($_SESSION['username'] ?? ''))));
    if ($formalEmailSenderAddress === '') {
        $formalEmailSenderAddress = 'Link an email';
    }
    $formalEmailFlash = [
        'type' => '',
        'message' => '',
        'open' => isset($_GET['open_formal_email']) && $_GET['open_formal_email'] === '1',
    ];

    $gmailStatus = trim((string)($_GET['gmail_status'] ?? ''));
    $gmailError = trim((string)($_GET['gmail_error'] ?? ''));
    if ($gmailError !== '') {
        $formalEmailFlash['type'] = 'error';
        $formalEmailFlash['message'] = $gmailError;
    } elseif ($gmailStatus !== '') {
        $formalEmailFlash['type'] = 'success';
        $formalEmailFlash['message'] = $gmailStatus;
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
                <?php if ($isAdminUser) { ?>
                <div class="chat-sidebar-toolbar">
                    <button type="button" class="email-compose-trigger" id="openFormalEmailComposer">
                        <i class="fa fa-envelope-o"></i>
                        <span>Formal Email</span>
                    </button>
                    <div class="email-compose-toolbar-copy">
                        Use Gmail API for official messages to members.
                    </div>
                </div>
                <?php } ?>
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
                    <?= chat_sidebar_render_users($users, (int)$_SESSION['id']) ?>
                </div>

                <!-- Group Chats -->
                <div class="chat-group-heading">
                    <div class="chat-group-heading-label">Groups</div>
                </div>
                <div class="chat-list" id="groupList">
                    <?= chat_sidebar_render_groups($pdo, $groups, (int)$_SESSION['id']) ?>
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
                    <span id="chatInfoTitle">Chat Info</span>
                    <button class="btn-close-info" id="closeInfoSidebar"><i class="fa fa-times"></i></button>
                </div>
                <div class="chat-info-content" id="rightSidebarContent">
                    <!-- Loaded via AJAX -->
                </div>
            </div>

            <div class="chat-delete-modal-backdrop" id="chatDeleteModal" style="display:none;">
                <div class="chat-delete-modal-card" role="dialog" aria-modal="true" aria-labelledby="chatDeleteModalTitle">
                    <h4 id="chatDeleteModalTitle">Delete chat?</h4>
                    <p id="chatDeleteModalText">This will remove the chat from your list.</p>
                    <div class="chat-delete-modal-actions">
                        <button type="button" class="chat-delete-btn-secondary" id="chatDeleteCancel">Cancel</button>
                        <button type="button" class="chat-delete-btn-danger" id="chatDeleteConfirm">Delete</button>
                    </div>
                </div>
            </div>

            <div class="chat-delete-modal-backdrop" id="messageDeleteModal" style="display:none;">
                <div class="chat-delete-modal-card" role="dialog" aria-modal="true" aria-labelledby="messageDeleteModalTitle">
                    <h4 id="messageDeleteModalTitle">Delete message?</h4>
                    <p id="messageDeleteModalText">This message will be removed from the chat.</p>
                    <div class="chat-delete-modal-actions">
                        <button type="button" class="chat-delete-btn-secondary" id="messageDeleteCancel">Cancel</button>
                        <button type="button" class="chat-delete-btn-danger" id="messageDeleteConfirm">Delete</button>
                    </div>
                </div>
            </div>

            <?php if ($isAdminUser) { ?>
            <div class="email-compose-backdrop" id="formalEmailBackdrop" style="display:none;">
                <div class="email-compose-window" role="dialog" aria-modal="true" aria-labelledby="formalEmailTitle">
                    <div class="email-compose-body">
                        <h4 id="formalEmailTitle" class="email-compose-accessible-title">Formal Email</h4>

                        <div class="email-compose-row email-compose-from-row">
                            <label>From</label>
                            <div class="email-compose-from-shell">
                                <a
                                    href="<?= $gmailConfigReady ? htmlspecialchars($gmailConnectUrl, ENT_QUOTES, 'UTF-8') : '#' ?>"
                                    class="email-compose-from-pill<?= $gmailReady ? ' is-connected' : '' ?><?= !$gmailConfigReady ? ' is-disabled' : '' ?>"
                                    id="connectFormalGmailBtn"
                                    <?= $gmailConfigReady ? '' : 'aria-disabled="true"' ?>
                                >
                                    <?= htmlspecialchars($gmailReady ? $formalEmailSenderAddress : ($gmailConfigReady ? 'Link an email' : 'Gmail unavailable'), ENT_QUOTES, 'UTF-8') ?>
                                </a>
                                <span class="email-compose-from-meta" id="formalEmailFromMeta">
                                    <?= htmlspecialchars(
                                        $gmailReady
                                            ? 'Ready to send official messages from your admin account.'
                                            : ($gmailConfigReady
                                                ? 'Authorize Gmail once to start sending formal emails.'
                                                : 'Add Google credentials to enable Gmail API.'
                                            ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </div>
                            <button type="button" class="email-compose-close" id="closeFormalEmailComposer" aria-label="Close email composer">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>

                        <div class="email-compose-row email-compose-recipient-row">
                            <label for="formalEmailRecipientInput">To</label>
                            <div class="email-compose-recipient-shell">
                                <div class="email-recipient-field" id="formalEmailRecipientField">
                                    <div class="email-recipient-chip-list" id="formalEmailRecipientChipList"></div>
                                    <input type="text" id="formalEmailRecipientInput" placeholder="Select members by name or email">
                                </div>
                                <div class="email-compose-recipient-extras">
                                    <button type="button" class="email-compose-mini-link" disabled>Cc</button>
                                    <button type="button" class="email-compose-mini-link" disabled>Bcc</button>
                                </div>
                            </div>
                        </div>

                        <div class="email-recipient-suggestions" id="formalEmailRecipientSuggestions" style="display:none;"></div>

                        <div class="email-compose-row email-compose-subject-row">
                            <label for="formalEmailSubjectInput">Subject</label>
                            <input type="text" id="formalEmailSubjectInput" placeholder="Add a formal subject">
                        </div>

                        <div class="email-compose-editor-shell">
                            <textarea id="formalEmailBodyInput" placeholder="Write a formal message to your members..."></textarea>
                            <div class="email-compose-attachment-list" id="formalEmailAttachmentList"></div>
                            <button type="button" class="email-compose-signature" disabled>Add signature</button>
                        </div>

                        <div class="email-compose-status" id="formalEmailStatus"></div>
                    </div>

                    <div class="email-compose-footer">
                        <div class="email-compose-toolbar-left">
                            <button type="button" class="email-compose-round-btn" id="addFormalEmailAttachment" aria-label="Add attachment">
                                <i class="fa fa-plus"></i>
                            </button>
                            <input type="file" id="formalEmailAttachmentInput" multiple hidden>
                            <button type="button" class="email-compose-toolbar-pill" disabled>
                                <span>Email</span>
                                <i class="fa fa-angle-down"></i>
                            </button>
                            <button type="button" class="email-compose-icon-btn" id="formalEmailToolbarAttach" aria-label="Attach files">
                                <i class="fa fa-paperclip"></i>
                            </button>
                            <button type="button" class="email-compose-icon-btn" id="formalEmailToolbarRecipients" aria-label="Focus recipients">
                                <i class="fa fa-at"></i>
                            </button>
                            <button type="button" class="email-compose-icon-btn" id="formalEmailToolbarGmail" aria-label="Gmail settings">
                                <i class="fa fa-cog"></i>
                            </button>
                        </div>
                        <div class="email-compose-toolbar-right">
                            <div class="email-compose-summary" id="formalEmailSummary">No recipients selected</div>
                            <button type="button" class="email-compose-discard" id="discardFormalEmail">Discard</button>
                            <button type="button" class="email-compose-send" id="sendFormalEmail"<?= $gmailReady ? '' : ' disabled' ?>>
                                <span class="email-compose-send-label">Send</span>
                                <i class="fa fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>

        </div>
    </div>

    <script>
        $(document).ready(function(){
            
            var currentChatUserId = 0;
            var currentGroupId = 0;
            var currentChatType = "user"; // user | group
            var loadInterval;
            var messageRequestInFlight = false;
            var chatListRequestInFlight = false;
            var selectedFiles = []; // Array to store multiple files
            var chatAjaxCsrfToken = <?= json_encode($chatAjaxCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var formalEmailCsrfToken = <?= json_encode($composeEmailCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var formalEmailIsAdmin = <?= $isAdminUser ? 'true' : 'false' ?>;
            var formalEmailUsers = <?= json_encode($formalEmailUsers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var formalEmailGmailReady = <?= $gmailReady ? 'true' : 'false' ?>;
            var formalEmailGmailConfigReady = <?= $gmailConfigReady ? 'true' : 'false' ?>;
            var formalEmailConnectUrl = <?= json_encode($gmailConnectUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var formalEmailSenderLabel = <?= json_encode($formalEmailSenderAddress, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var formalEmailFlash = <?= json_encode($formalEmailFlash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var groupMentionMembers = [];
            var mentionSuggestionsData = [];
            var mentionSelectionIndex = -1;
            var currentListFilter = "all";
            var activeTypingMetaHtml = "";
            var lastTypingInputAt = 0;
            var lastTypingStateKey = "";
            var lastTypingSentAt = 0;
            var typingStatusRequestInFlight = false;
            var chatInfoRequestSerial = 0;
            var pendingChatDeleteTarget = null;
            var pendingMessageDeleteTarget = null;
            var chatDeleteLongPressTimer = null;
            var messageDeleteLongPressTimer = null;
            var suppressChatClickUntil = 0;
            var formalEmailSelectedRecipientIds = [];
            var formalEmailSuggestionsData = [];
            var formalEmailSelectionIndex = -1;
            var formalEmailSending = false;
            var formalEmailAttachments = [];
            var formalEmailAttachmentMaxCount = 10;
            var formalEmailAttachmentMaxBytes = 18 * 1024 * 1024;

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

            function updateChatInfoTitle() {
                var title = currentChatType === "group" ? "Group Info" : "Chat Info";
                $("#chatInfoTitle").text(title);
            }

            function clearChatItemDeleteActions() {
                $(".chat-item").removeClass("show-delete-action");
            }

            function clearMessageDeleteActions() {
                $(".message-outgoing").removeClass("show-delete-action");
            }

            function getDeleteTargetFromButton(button) {
                if (!button || button.length === 0) return null;

                var deleteType = $.trim(button.attr("data-delete-type") || "");
                var deleteId = parseInt(button.attr("data-delete-id") || "0", 10) || 0;
                var deleteName = $.trim(button.attr("data-delete-name") || "") || "this chat";

                if (!deleteType || deleteId <= 0) {
                    return null;
                }

                return {
                    type: deleteType,
                    id: deleteId,
                    name: deleteName
                };
            }

            function getMessageDeleteTargetFromButton(button) {
                if (!button || button.length === 0) return null;

                var messageType = $.trim(button.attr("data-delete-message-type") || "");
                var messageId = parseInt(button.attr("data-delete-message-id") || "0", 10) || 0;
                var messagePreview = $.trim(button.attr("data-delete-message-preview") || "");

                if (!messageType || messageId <= 0) {
                    return null;
                }

                return {
                    type: messageType,
                    id: messageId,
                    preview: messagePreview
                };
            }

            function closeChatDeleteModal() {
                pendingChatDeleteTarget = null;
                $("#chatDeleteModal").hide();
                $("#chatDeleteConfirm").prop("disabled", false);
            }

            function closeMessageDeleteModal() {
                pendingMessageDeleteTarget = null;
                $("#messageDeleteModal").hide();
                $("#messageDeleteConfirm").prop("disabled", false);
            }

            function findFormalEmailUser(userId) {
                var normalizedId = parseInt(userId, 10) || 0;
                for (var i = 0; i < formalEmailUsers.length; i++) {
                    if ((parseInt(formalEmailUsers[i].id, 10) || 0) === normalizedId) {
                        return formalEmailUsers[i];
                    }
                }
                return null;
            }

            function clearFormalEmailStatus() {
                $("#formalEmailStatus").removeClass("is-error is-success").hide().text("");
            }

            function setFormalEmailStatus(message, type) {
                var statusClass = type === "success" ? "is-success" : "is-error";
                $("#formalEmailStatus")
                    .removeClass("is-error is-success")
                    .addClass(statusClass)
                    .text(message || "")
                    .show();
            }

            function formatFormalEmailBytes(bytes) {
                var size = parseInt(bytes, 10) || 0;
                if (size < 1024) {
                    return size + " B";
                }
                if (size < (1024 * 1024)) {
                    return (size / 1024).toFixed(size < (10 * 1024) ? 1 : 0) + " KB";
                }
                return (size / (1024 * 1024)).toFixed(size < (10 * 1024 * 1024) ? 1 : 0) + " MB";
            }

            function getFormalEmailAttachmentKey(file) {
                if (!file) {
                    return "";
                }

                return [
                    String(file.name || ""),
                    String(file.size || 0),
                    String(file.lastModified || 0),
                    String(file.type || "")
                ].join("::");
            }

            function getFormalEmailAttachmentCount() {
                return formalEmailAttachments.length;
            }

            function getFormalEmailAttachmentTotalBytes() {
                var total = 0;
                for (var i = 0; i < formalEmailAttachments.length; i++) {
                    total += parseInt(formalEmailAttachments[i].size, 10) || 0;
                }
                return total;
            }

            function updateFormalEmailSummary() {
                var count = formalEmailSelectedRecipientIds.length;
                var attachmentCount = getFormalEmailAttachmentCount();
                var summaryParts = [];

                if (count === 1) {
                    summaryParts.push("1 member selected");
                } else if (count > 1) {
                    summaryParts.push(count + " members selected");
                } else {
                    summaryParts.push("No recipients selected");
                }

                if (attachmentCount === 1) {
                    summaryParts.push("1 file attached");
                } else if (attachmentCount > 1) {
                    summaryParts.push(attachmentCount + " files attached");
                }

                $("#formalEmailSummary").text(summaryParts.join(" | "));
            }

            function hideFormalEmailSuggestions() {
                formalEmailSuggestionsData = [];
                formalEmailSelectionIndex = -1;
                $("#formalEmailRecipientSuggestions").hide().empty();
            }

            function renderFormalEmailRecipients() {
                var html = "";

                for (var i = 0; i < formalEmailSelectedRecipientIds.length; i++) {
                    var user = findFormalEmailUser(formalEmailSelectedRecipientIds[i]);
                    if (!user) {
                        continue;
                    }

                    var label = $.trim(user.full_name || user.email || "Member");
                    var email = $.trim(user.email || "");
                    var chipMeta = email !== "" && label.toLowerCase() !== email.toLowerCase()
                        ? label + " - " + email
                        : label;

                    html += '<span class="email-recipient-chip" data-user-id="' + user.id + '">' +
                                '<span class="email-recipient-chip-avatar">' + escapeHtml($.trim(user.initials || "?") || "?") + '</span>' +
                                '<span class="email-recipient-chip-text">' + escapeHtml(chipMeta) + '</span>' +
                                '<button type="button" class="email-recipient-chip-remove formal-email-recipient-remove" data-user-id="' + user.id + '" aria-label="Remove recipient">' +
                                    '<i class="fa fa-times"></i>' +
                                '</button>' +
                            '</span>';
                }

                $("#formalEmailRecipientChipList").html(html);
                updateFormalEmailSummary();
            }

            function renderFormalEmailAttachments() {
                var list = $("#formalEmailAttachmentList");
                if (list.length === 0) {
                    return;
                }

                if (!formalEmailAttachments.length) {
                    list.html("").removeClass("has-items");
                    updateFormalEmailSummary();
                    return;
                }

                var html = "";
                for (var i = 0; i < formalEmailAttachments.length; i++) {
                    var item = formalEmailAttachments[i];
                    var extension = "";
                    if (String(item.name || "").indexOf(".") !== -1) {
                        extension = String(item.name || "").split(".").pop().toUpperCase();
                    }
                    if (extension === "") {
                        extension = "FILE";
                    }

                    html += '<div class="email-compose-attachment-chip" data-file-key="' + escapeHtml(item.key) + '">' +
                                '<span class="email-compose-attachment-chip-grip"><i class="fa fa-ellipsis-v"></i></span>' +
                                '<span class="email-compose-attachment-chip-name">' + escapeHtml(item.name || "Attachment") + '</span>' +
                                '<span class="email-compose-attachment-chip-meta">' + escapeHtml(extension) + ' ' + escapeHtml(formatFormalEmailBytes(item.size || 0)) + '</span>' +
                                '<button type="button" class="email-compose-attachment-remove" data-file-key="' + escapeHtml(item.key) + '" aria-label="Remove attachment">' +
                                    '<i class="fa fa-times"></i>' +
                                '</button>' +
                            '</div>';
                }

                list.html(html).addClass("has-items");
                updateFormalEmailSummary();
            }

            function addFormalEmailAttachments(fileList) {
                if (!fileList || !fileList.length) {
                    return;
                }

                var currentBytes = getFormalEmailAttachmentTotalBytes();
                var addedAny = false;
                var hadError = false;

                for (var i = 0; i < fileList.length; i++) {
                    var file = fileList[i];
                    if (!file) {
                        continue;
                    }

                    var key = getFormalEmailAttachmentKey(file);
                    if (key === "") {
                        continue;
                    }

                    var isDuplicate = false;
                    for (var j = 0; j < formalEmailAttachments.length; j++) {
                        if (formalEmailAttachments[j].key === key) {
                            isDuplicate = true;
                            break;
                        }
                    }

                    if (isDuplicate) {
                        continue;
                    }

                    if (formalEmailAttachments.length >= formalEmailAttachmentMaxCount) {
                        setFormalEmailStatus("You can attach up to " + formalEmailAttachmentMaxCount + " files per email.", "error");
                        hadError = true;
                        break;
                    }

                    var nextBytes = currentBytes + (parseInt(file.size, 10) || 0);
                    if (nextBytes > formalEmailAttachmentMaxBytes) {
                        setFormalEmailStatus("Attachments must stay under " + formatFormalEmailBytes(formalEmailAttachmentMaxBytes) + " total.", "error");
                        hadError = true;
                        break;
                    }

                    formalEmailAttachments.push({
                        key: key,
                        file: file,
                        name: String(file.name || "Attachment"),
                        size: parseInt(file.size, 10) || 0
                    });
                    currentBytes = nextBytes;
                    addedAny = true;
                }

                $("#formalEmailAttachmentInput").val("");
                renderFormalEmailAttachments();
                if (addedAny && !hadError) {
                    clearFormalEmailStatus();
                }
            }

            function removeFormalEmailAttachment(fileKey) {
                if (formalEmailSending) {
                    return;
                }

                var normalizedKey = String(fileKey || "");
                formalEmailAttachments = formalEmailAttachments.filter(function(item){
                    return String(item.key || "") !== normalizedKey;
                });

                renderFormalEmailAttachments();
                clearFormalEmailStatus();
                $("#formalEmailAttachmentInput").val("");
            }

            function buildFormalEmailSuggestions() {
                var query = $.trim($("#formalEmailRecipientInput").val() || "").toLowerCase();
                var selectedMap = {};

                for (var i = 0; i < formalEmailSelectedRecipientIds.length; i++) {
                    selectedMap[parseInt(formalEmailSelectedRecipientIds[i], 10) || 0] = true;
                }

                return formalEmailUsers.filter(function(user){
                    var userId = parseInt(user.id, 10) || 0;
                    if (!userId || selectedMap[userId]) {
                        return false;
                    }

                    if (query === "") {
                        return true;
                    }

                    var fullName = String(user.full_name || "").toLowerCase();
                    var email = String(user.email || "").toLowerCase();
                    return fullName.indexOf(query) !== -1 || email.indexOf(query) !== -1;
                }).slice(0, 8);
            }

            function renderFormalEmailSuggestions(items) {
                if (!items.length) {
                    formalEmailSuggestionsData = [];
                    formalEmailSelectionIndex = -1;
                    $("#formalEmailRecipientSuggestions")
                        .html(
                            '<div class="email-recipient-empty">' +
                                escapeHtml(
                                    formalEmailUsers.length === 0
                                        ? "No workspace members with valid email addresses."
                                        : "No matching members found."
                                ) +
                            '</div>'
                        )
                        .show();
                    return;
                }

                formalEmailSuggestionsData = items;
                if (formalEmailSelectionIndex < 0 || formalEmailSelectionIndex >= formalEmailSuggestionsData.length) {
                    formalEmailSelectionIndex = 0;
                }

                var html = "";
                for (var i = 0; i < formalEmailSuggestionsData.length; i++) {
                    var suggestion = formalEmailSuggestionsData[i];
                    var activeClass = i === formalEmailSelectionIndex ? " active" : "";

                    html += '<button type="button" class="email-recipient-suggestion formal-email-suggestion' + activeClass + '" data-user-id="' + suggestion.id + '" data-idx="' + i + '">' +
                                '<span class="email-recipient-suggestion-avatar">' + escapeHtml($.trim(suggestion.initials || "?") || "?") + '</span>' +
                                '<span class="email-recipient-suggestion-copy">' +
                                    '<span class="email-recipient-suggestion-name">' + escapeHtml(suggestion.full_name || suggestion.email || "Member") + '</span>' +
                                    '<span class="email-recipient-suggestion-email">' + escapeHtml(suggestion.email || "") + '</span>' +
                                '</span>' +
                            '</button>';
                }

                $("#formalEmailRecipientSuggestions").html(html).show();
            }

            function refreshFormalEmailSuggestions() {
                renderFormalEmailSuggestions(buildFormalEmailSuggestions());
            }

            function addFormalEmailRecipient(userId) {
                var normalizedId = parseInt(userId, 10) || 0;
                if (!normalizedId) {
                    return;
                }

                if (formalEmailSelectedRecipientIds.indexOf(normalizedId) !== -1) {
                    $("#formalEmailRecipientInput").val("");
                    refreshFormalEmailSuggestions();
                    return;
                }

                var user = findFormalEmailUser(normalizedId);
                if (!user) {
                    return;
                }

                formalEmailSelectedRecipientIds.push(normalizedId);
                $("#formalEmailRecipientInput").val("").focus();
                renderFormalEmailRecipients();
                refreshFormalEmailSuggestions();
                clearFormalEmailStatus();
            }

            function removeFormalEmailRecipient(userId) {
                var normalizedId = parseInt(userId, 10) || 0;
                formalEmailSelectedRecipientIds = formalEmailSelectedRecipientIds.filter(function(existingId){
                    return (parseInt(existingId, 10) || 0) !== normalizedId;
                });

                renderFormalEmailRecipients();
                refreshFormalEmailSuggestions();
            }

            function moveFormalEmailSelection(direction) {
                if (!formalEmailSuggestionsData.length) {
                    return;
                }

                formalEmailSelectionIndex += direction;
                if (formalEmailSelectionIndex < 0) {
                    formalEmailSelectionIndex = formalEmailSuggestionsData.length - 1;
                }
                if (formalEmailSelectionIndex >= formalEmailSuggestionsData.length) {
                    formalEmailSelectionIndex = 0;
                }

                renderFormalEmailSuggestions(formalEmailSuggestionsData);
            }

            function applySelectedFormalEmailRecipient() {
                if (!formalEmailSuggestionsData.length || formalEmailSelectionIndex < 0) {
                    return;
                }

                var picked = formalEmailSuggestionsData[formalEmailSelectionIndex];
                if (!picked || !picked.id) {
                    return;
                }

                addFormalEmailRecipient(picked.id);
            }

            function setFormalEmailComposerEnabled(isEnabled) {
                formalEmailGmailReady = !!isEnabled;
                $("#formalEmailRecipientInput").prop("disabled", !formalEmailGmailReady);
                $("#formalEmailSubjectInput").prop("disabled", !formalEmailGmailReady);
                $("#formalEmailBodyInput").prop("disabled", !formalEmailGmailReady);
                $("#formalEmailAttachmentInput").prop("disabled", !formalEmailGmailReady);
                $("#addFormalEmailAttachment").prop("disabled", !formalEmailGmailReady);
                $("#formalEmailToolbarAttach").prop("disabled", !formalEmailGmailReady);
                $("#formalEmailToolbarRecipients").prop("disabled", !formalEmailGmailReady);
                $("#formalEmailRecipientField").toggleClass("is-disabled", !formalEmailGmailReady);
                $("#addFormalEmailAttachment").toggleClass("is-disabled", !formalEmailGmailReady);
                $("#sendFormalEmail").prop("disabled", !formalEmailGmailReady);
                if (!formalEmailGmailReady) {
                    hideFormalEmailSuggestions();
                }
                syncFormalEmailAuthCard();
            }

            function syncFormalEmailAuthCard() {
                var pill = $("#connectFormalGmailBtn");
                var meta = $("#formalEmailFromMeta");
                var toolbarGmail = $("#formalEmailToolbarGmail");

                if (pill.length === 0) {
                    return;
                }

                pill.removeClass("is-connected is-disabled");
                var message = "";
                var label = "";

                if (!formalEmailGmailConfigReady) {
                    label = "Gmail unavailable";
                    message = "Add Google credentials to enable Gmail API.";
                    pill.addClass("is-disabled").attr("href", "#").attr("aria-disabled", "true");
                    toolbarGmail.prop("disabled", true).addClass("is-disabled").removeClass("is-active");
                } else if (formalEmailGmailReady) {
                    label = formalEmailSenderLabel || "Connected Gmail";
                    message = "Ready to send official messages from your admin account.";
                    pill.addClass("is-connected").attr("href", formalEmailConnectUrl || "app/google-gmail-init.php").removeAttr("aria-disabled");
                    toolbarGmail.prop("disabled", false).removeClass("is-disabled").addClass("is-active");
                } else {
                    label = "Link an email";
                    message = "Authorize Gmail once to start sending formal emails.";
                    pill.attr("href", formalEmailConnectUrl || "app/google-gmail-init.php").removeAttr("aria-disabled");
                    toolbarGmail.prop("disabled", false).removeClass("is-disabled is-active");
                }

                pill.text(label);
                meta.text(message);
            }

            function resetFormalEmailComposer() {
                formalEmailSelectedRecipientIds = [];
                formalEmailSuggestionsData = [];
                formalEmailSelectionIndex = -1;
                formalEmailSending = false;
                formalEmailAttachments = [];
                $("#formalEmailRecipientInput").val("");
                $("#formalEmailSubjectInput").val("");
                $("#formalEmailBodyInput").val("");
                $("#formalEmailAttachmentInput").val("");
                renderFormalEmailRecipients();
                renderFormalEmailAttachments();
                hideFormalEmailSuggestions();
                clearFormalEmailStatus();
                syncFormalEmailAuthCard();
                setFormalEmailComposerEnabled(formalEmailGmailReady);
            }

            function openFormalEmailComposer() {
                if (!formalEmailIsAdmin) {
                    return;
                }

                $("#formalEmailBackdrop").css("display", "flex");
                $("#formalEmailRecipientField").removeClass("is-focused");
                renderFormalEmailRecipients();
                if (formalEmailGmailReady) {
                    refreshFormalEmailSuggestions();
                } else {
                    hideFormalEmailSuggestions();
                }

                if (formalEmailFlash && formalEmailFlash.message) {
                    setFormalEmailStatus(formalEmailFlash.message, formalEmailFlash.type === "success" ? "success" : "error");
                    formalEmailFlash.message = "";
                } else {
                    clearFormalEmailStatus();
                }

                window.setTimeout(function(){
                    if (formalEmailGmailReady) {
                        $("#formalEmailRecipientInput").trigger("focus");
                    } else {
                        $("#connectFormalGmailBtn").trigger("focus");
                    }
                }, 20);
            }

            function closeFormalEmailComposer(shouldReset) {
                if (formalEmailSending) {
                    return;
                }

                $("#formalEmailRecipientField").removeClass("is-focused");
                hideFormalEmailSuggestions();
                $("#formalEmailBackdrop").hide();

                if (shouldReset !== false) {
                    resetFormalEmailComposer();
                }
            }

            function sendFormalEmailMessage() {
                if (!formalEmailIsAdmin || formalEmailSending) {
                    return;
                }

                if (!formalEmailGmailReady) {
                    setFormalEmailStatus("Connect Gmail first before sending formal emails.", "error");
                    return;
                }

                var subject = $("#formalEmailSubjectInput").val() || "";
                var body = $("#formalEmailBodyInput").val() || "";

                if (formalEmailSelectedRecipientIds.length === 0) {
                    setFormalEmailStatus("Select at least one member.", "error");
                    $("#formalEmailRecipientInput").trigger("focus");
                    return;
                }

                if ($.trim(subject) === "" && $.trim(body) === "" && !formalEmailAttachments.length) {
                    setFormalEmailStatus("Add a subject, message, or attachment before sending.", "error");
                    $("#formalEmailBodyInput").trigger("focus");
                    return;
                }

                formalEmailSending = true;
                $("#sendFormalEmail").prop("disabled", true);
                $("#addFormalEmailAttachment").prop("disabled", true);
                $("#formalEmailToolbarAttach").prop("disabled", true);
                $("#formalEmailToolbarRecipients").prop("disabled", true);
                $("#formalEmailToolbarGmail").prop("disabled", true);
                setFormalEmailStatus("Sending formal email...", "success");

                var formData = new FormData();
                formData.append("csrf_token", formalEmailCsrfToken);
                formData.append("subject", subject);
                formData.append("body", body);

                for (var i = 0; i < formalEmailSelectedRecipientIds.length; i++) {
                    formData.append("recipient_ids[]", formalEmailSelectedRecipientIds[i]);
                }

                for (var j = 0; j < formalEmailAttachments.length; j++) {
                    if (formalEmailAttachments[j] && formalEmailAttachments[j].file) {
                        formData.append("attachments[]", formalEmailAttachments[j].file, formalEmailAttachments[j].name || formalEmailAttachments[j].file.name || "attachment");
                    }
                }

                $.ajax({
                    url: 'app/ajax/sendGmailMessage.php',
                    type: 'POST',
                    dataType: 'json',
                    data: formData,
                    processData: false,
                    contentType: false
                }).done(function(res){
                    if (!res || !res.ok) {
                        if (res && res.needs_gmail_auth) {
                            if (res.connect_url) {
                                formalEmailConnectUrl = res.connect_url;
                            }
                            setFormalEmailComposerEnabled(false);
                            setFormalEmailStatus(res.message || "Reconnect Gmail to continue.", "error");
                            return;
                        }

                        setFormalEmailStatus((res && res.message) ? res.message : "Unable to send the formal email right now.", "error");
                        return;
                    }

                    setFormalEmailStatus(res.message || "Formal email sent successfully.", "success");
                    if (typeof showToast === "function") {
                        var toastMessage = "Formal email sent successfully.";
                        var sentCount = parseInt((res && res.sent_count) || 0, 10) || 0;
                        var attachmentCount = parseInt((res && res.attachment_count) || 0, 10) || 0;

                        if (sentCount > 0) {
                            toastMessage = "Formal email sent to " + sentCount + " member" + (sentCount === 1 ? "" : "s") + ".";
                            if (attachmentCount > 0) {
                                toastMessage += " " + attachmentCount + " attachment" + (attachmentCount === 1 ? "" : "s") + " included.";
                            }
                        }

                        showToast(toastMessage, "success");
                    }
                    window.setTimeout(function(){
                        closeFormalEmailComposer(true);
                    }, 450);
                }).fail(function(xhr){
                    var response = xhr && xhr.responseJSON ? xhr.responseJSON : null;
                    if (response && response.needs_gmail_auth) {
                        if (response.connect_url) {
                            formalEmailConnectUrl = response.connect_url;
                        }
                        setFormalEmailComposerEnabled(false);
                    }
                    setFormalEmailStatus(response && response.message ? response.message : "Unable to send the formal email right now.", "error");
                }).always(function(){
                    formalEmailSending = false;
                    $("#sendFormalEmail").prop("disabled", !formalEmailGmailReady);
                    $("#addFormalEmailAttachment").prop("disabled", !formalEmailGmailReady);
                    $("#formalEmailToolbarAttach").prop("disabled", !formalEmailGmailReady);
                    $("#formalEmailToolbarRecipients").prop("disabled", !formalEmailGmailReady);
                    $("#formalEmailToolbarGmail").prop("disabled", !formalEmailGmailConfigReady);
                });
            }

            function openChatDeleteModal(target) {
                if (!target || !target.type || !target.id) return;

                pendingChatDeleteTarget = target;
                var safeName = $.trim(target.name || "") || "this chat";
                var messageText = target.type === "group"
                    ? ('Delete "' + safeName + '" from your chat list? It will appear again if new messages are sent there.')
                    : ('Delete your chat with "' + safeName + '" from your list? It will appear again if either of you sends a new message.');
                $("#chatDeleteModalText").text(messageText);
                $("#chatDeleteModal").css("display", "flex");
            }

            function openMessageDeleteModal(target) {
                if (!target || !target.type || !target.id) return;

                pendingMessageDeleteTarget = target;
                var preview = $.trim(target.preview || "");
                if (preview.length > 120) {
                    preview = preview.slice(0, 117) + "...";
                }
                var messageText = preview !== ""
                    ? ('Delete "' + preview + '" from the chat?')
                    : 'Delete this message from the chat?';
                $("#messageDeleteModalText").text(messageText);
                $("#messageDeleteModal").css("display", "flex");
            }

            function clearCurrentChatSelection() {
                var previousContext = getActiveTypingContext();
                if (previousContext) {
                    syncOwnTypingState(false, true, previousContext);
                }

                currentChatUserId = 0;
                currentGroupId = 0;
                currentChatType = "user";
                groupMentionMembers = [];
                lastTypingInputAt = 0;
                lastTypingStateKey = "";
                lastTypingSentAt = 0;
                applyTypingMetaHtml("");
                hideMentionSuggestions();
                resetAttachment();
                clearChatItemDeleteActions();
                clearMessageDeleteActions();
                closeMessageDeleteModal();

                $("#chatBox").empty();
                $("#chatInterface").hide();
                $("#noChatSelected").css("display", "flex");
                $("#rightSidebar").removeClass("active");
                $("#chatInfoOverlay").removeClass("active");
                $("#rightSidebarContent").empty();
                $("#chatInfoToggle").hide();
                $("#chatInfoTitle").text("Chat Info");
                $("#chatUserName").text("User Name");
                $("#chatUserMeta").text("Offline");
                $("#headerAvatar").html("");
            }

            function reloadChatListsAfterDeletion() {
                if ($("#searchText").val() !== "") {
                    $("#searchText").trigger("input");
                    return;
                }

                refreshChatLists();
            }

            function hideChatThread(target) {
                if (!target || !target.type || !target.id) return;

                var payload = {
                    chat_type: target.type,
                    csrf_token: chatAjaxCsrfToken
                };

                if (target.type === "group") {
                    payload.group_id = target.id;
                } else {
                    payload.user_id = target.id;
                }

                $("#chatDeleteConfirm").prop("disabled", true);

                $.ajax({
                    url: 'app/ajax/hideChatThread.php',
                    type: 'POST',
                    dataType: 'json',
                    data: payload
                }).done(function(res){
                    if (!res || !res.ok) {
                        return;
                    }

                    var isActiveUser = target.type === "user" && currentChatType === "user" && parseInt(currentChatUserId, 10) === parseInt(target.id, 10);
                    var isActiveGroup = target.type === "group" && currentChatType === "group" && parseInt(currentGroupId, 10) === parseInt(target.id, 10);

                    if (isActiveUser || isActiveGroup) {
                        clearCurrentChatSelection();
                    } else {
                        clearChatItemDeleteActions();
                    }

                    closeChatDeleteModal();
                    reloadChatListsAfterDeletion();
                }).always(function(){
                    $("#chatDeleteConfirm").prop("disabled", false);
                });
            }

            function deleteChatMessage(target) {
                if (!target || !target.type || !target.id) return;

                $("#messageDeleteConfirm").prop("disabled", true);

                $.ajax({
                    url: 'app/ajax/deleteMessage.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        message_type: target.type,
                        message_id: target.id,
                        csrf_token: chatAjaxCsrfToken
                    }
                }).done(function(res){
                    if (!res || !res.ok) {
                        return;
                    }

                    closeMessageDeleteModal();
                    clearMessageDeleteActions();
                    loadMessages(true);
                    reloadChatListsAfterDeletion();
                    if ($("#rightSidebar").hasClass("active")) {
                        loadCurrentChatInfoSidebar();
                    }
                }).always(function(){
                    $("#messageDeleteConfirm").prop("disabled", false);
                });
            }

            function clearChatDeleteLongPressTimer() {
                if (chatDeleteLongPressTimer) {
                    window.clearTimeout(chatDeleteLongPressTimer);
                    chatDeleteLongPressTimer = null;
                }
            }

            function clearMessageDeleteLongPressTimer() {
                if (messageDeleteLongPressTimer) {
                    window.clearTimeout(messageDeleteLongPressTimer);
                    messageDeleteLongPressTimer = null;
                }
            }

            function getChatInfoPayload() {
                if (currentChatType === "group" && currentGroupId != 0) {
                    return {
                        chat_type: "group",
                        group_id: currentGroupId,
                        csrf_token: chatAjaxCsrfToken
                    };
                }

                if (currentChatType === "user" && currentChatUserId != 0) {
                    return {
                        chat_type: "user",
                        user_id: currentChatUserId,
                        csrf_token: chatAjaxCsrfToken
                    };
                }

                return null;
            }

            function loadCurrentChatInfoSidebar() {
                var payload = getChatInfoPayload();
                if (!payload) {
                    $("#rightSidebarContent").empty();
                    return;
                }

                updateChatInfoTitle();
                var requestSerial = ++chatInfoRequestSerial;
                var contextKey = currentChatType + ":" + (currentChatType === "group" ? currentGroupId : currentChatUserId);

                $.post('app/ajax/getChatInfo.php', payload, function(data){
                    var activeKey = currentChatType + ":" + (currentChatType === "group" ? currentGroupId : currentChatUserId);
                    if (requestSerial !== chatInfoRequestSerial || activeKey !== contextKey) {
                        return;
                    }

                    $("#rightSidebarContent").html(data);
                });
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
                if (document.hidden) return;
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
                $(".chat-item").off("click.chatUser").on("click.chatUser", function(e){
                    if ($(this).hasClass("group-item")) return;
                    if ($(e.target).closest(".chat-item-delete-btn").length) return;
                    if ($(this).hasClass("show-delete-action")) {
                        clearChatItemDeleteActions();
                        return;
                    }
                    if (Date.now() < suppressChatClickUntil) return;

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
                    clearMessageDeleteActions();
                    closeMessageDeleteModal();

                    // UI Update
                    $("#noChatSelected").hide();
                    $("#chatInterface").css("display", "flex");
                    updateUserHeaderFromItem($(this));
                    
                    // UI Reset for User Chat
                    $("#chatInfoToggle").show();
                    $("#rightSidebar").removeClass("active");
                    $("#chatInfoOverlay").removeClass("active");
                    updateChatInfoTitle();
                    loadCurrentChatInfoSidebar();
                    
                    // Reset attachment
                    resetAttachment();

                    // Load Messages immediately
                    loadMessages();
                    
                    // Auto scroll down will happen in loadMessages for first load
                });
            }

            function bindGroupClicks(){
                $(".group-item").off("click.chatGroup").on("click.chatGroup", function(e){
                    if ($(e.target).closest(".chat-item-delete-btn").length) return;
                    if ($(this).hasClass("show-delete-action")) {
                        clearChatItemDeleteActions();
                        return;
                    }
                    if (Date.now() < suppressChatClickUntil) return;

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
                    clearMessageDeleteActions();
                    closeMessageDeleteModal();

                    $("#noChatSelected").hide();
                    $("#chatInterface").css("display", "flex");
                    $("#chatUserName").text(groupName);
                    $("#chatUserMeta").text("Group Chat");
                    $("#headerAvatar").html('<i class="fa fa-users"></i>');
                    
                    // UI Set for Group Chat
                    $("#chatInfoToggle").show();
                    $("#rightSidebar").removeClass("active");
                    $("#chatInfoOverlay").removeClass("active");
                    updateChatInfoTitle();
                    
                    // Reset attachment
                    resetAttachment();
                    loadMessages();

                    loadCurrentChatInfoSidebar();
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

            if (formalEmailIsAdmin) {
                $("#openFormalEmailComposer").on("click", function(){
                    openFormalEmailComposer();
                });

                $("#closeFormalEmailComposer, #discardFormalEmail").on("click", function(){
                    closeFormalEmailComposer(true);
                });

                $("#formalEmailBackdrop").on("click", function(e){
                    if (e.target === this) {
                        closeFormalEmailComposer(true);
                    }
                });

                $("#formalEmailRecipientField").on("click", function(){
                    if (formalEmailGmailReady) {
                        $("#formalEmailRecipientInput").trigger("focus");
                    }
                });

                $("#formalEmailRecipientInput").on("focus", function(){
                    $("#formalEmailRecipientField").addClass("is-focused");
                    refreshFormalEmailSuggestions();
                });

                $("#formalEmailRecipientInput").on("blur", function(){
                    window.setTimeout(function(){
                        $("#formalEmailRecipientField").removeClass("is-focused");
                        hideFormalEmailSuggestions();
                    }, 120);
                });

                $("#formalEmailRecipientInput").on("input", function(){
                    clearFormalEmailStatus();
                    refreshFormalEmailSuggestions();
                });

                $("#formalEmailSubjectInput, #formalEmailBodyInput").on("input", function(){
                    clearFormalEmailStatus();
                });

                $("#addFormalEmailAttachment").on("click", function(){
                    if (!formalEmailGmailReady || formalEmailSending) {
                        return;
                    }
                    $("#formalEmailAttachmentInput").trigger("click");
                });

                $("#formalEmailToolbarAttach").on("click", function(){
                    if (!formalEmailGmailReady || formalEmailSending) {
                        return;
                    }
                    $("#formalEmailAttachmentInput").trigger("click");
                });

                $("#formalEmailToolbarRecipients").on("click", function(){
                    if (!formalEmailGmailReady) {
                        return;
                    }
                    $("#formalEmailRecipientInput").trigger("focus");
                });

                $("#formalEmailToolbarGmail").on("click", function(){
                    if (!formalEmailGmailConfigReady || formalEmailSending) {
                        return;
                    }
                    window.location.href = formalEmailConnectUrl || "app/google-gmail-init.php";
                });

                $("#connectFormalGmailBtn").on("click", function(e){
                    if ($(this).attr("aria-disabled") === "true") {
                        e.preventDefault();
                    }
                });

                $("#formalEmailAttachmentInput").on("change", function(){
                    addFormalEmailAttachments(this.files);
                });

                $("#formalEmailRecipientInput").on("keydown", function(e){
                    if (e.which === 8 && $.trim($(this).val() || "") === "" && formalEmailSelectedRecipientIds.length > 0) {
                        removeFormalEmailRecipient(formalEmailSelectedRecipientIds[formalEmailSelectedRecipientIds.length - 1]);
                        e.preventDefault();
                        return;
                    }

                    if ($("#formalEmailRecipientSuggestions").is(":visible")) {
                        if (e.which === 40) {
                            e.preventDefault();
                            moveFormalEmailSelection(1);
                            return;
                        }

                        if (e.which === 38) {
                            e.preventDefault();
                            moveFormalEmailSelection(-1);
                            return;
                        }

                        if (e.which === 13) {
                            e.preventDefault();
                            applySelectedFormalEmailRecipient();
                            return;
                        }

                        if (e.which === 27) {
                            e.preventDefault();
                            hideFormalEmailSuggestions();
                        }
                    }
                });

                $("#sendFormalEmail").on("click", function(){
                    sendFormalEmailMessage();
                });

                $("#formalEmailBodyInput").on("keydown", function(e){
                    if ((e.ctrlKey || e.metaKey) && e.which === 13) {
                        e.preventDefault();
                        sendFormalEmailMessage();
                    }
                });

                $(document).on("mousedown", ".formal-email-suggestion", function(e){
                    e.preventDefault();
                    addFormalEmailRecipient($(this).attr("data-user-id"));
                });

                $(document).on("click", ".formal-email-recipient-remove", function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    removeFormalEmailRecipient($(this).attr("data-user-id"));
                    $("#formalEmailRecipientInput").trigger("focus");
                });

                $(document).on("click", ".email-compose-attachment-remove", function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    removeFormalEmailAttachment($(this).attr("data-file-key"));
                });
            }

            $("#chatInfoToggle").click(function(){
                loadCurrentChatInfoSidebar();
                $("#rightSidebar").toggleClass("active");
                if($(window).width() <= 900) {
                    $("#chatInfoOverlay").toggleClass("active");
                }
            });

            $("#closeInfoSidebar, #chatInfoOverlay").click(function(){
                $("#rightSidebar").removeClass("active");
                $("#chatInfoOverlay").removeClass("active");
            });

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
                if (formalEmailIsAdmin && !$(e.target).closest("#formalEmailRecipientField, #formalEmailRecipientSuggestions").length) {
                    hideFormalEmailSuggestions();
                }
                if (!$(e.target).closest(".chat-item.show-delete-action, .chat-delete-modal-card").length) {
                    clearChatItemDeleteActions();
                }
                if (!$(e.target).closest(".message-outgoing.show-delete-action, .chat-delete-modal-card").length) {
                    clearMessageDeleteActions();
                }
            });

            $(document).on("click", ".chat-item-delete-btn", function(e){
                e.preventDefault();
                e.stopPropagation();
                clearChatItemDeleteActions();
                var target = getDeleteTargetFromButton($(this));
                if (target) {
                    openChatDeleteModal(target);
                }
            });

            $(document).on("click", ".message-delete-btn", function(e){
                e.preventDefault();
                e.stopPropagation();
                clearMessageDeleteActions();
                var target = getMessageDeleteTargetFromButton($(this));
                if (target) {
                    openMessageDeleteModal(target);
                }
            });

            $(document).on("touchstart", ".chat-item", function(e){
                if ($(e.target).closest(".chat-item-delete-btn").length) {
                    return;
                }

                clearChatDeleteLongPressTimer();
                var item = $(this);

                chatDeleteLongPressTimer = window.setTimeout(function(){
                    clearChatItemDeleteActions();
                    item.addClass("show-delete-action");
                    suppressChatClickUntil = Date.now() + 700;
                    chatDeleteLongPressTimer = null;
                }, 550);
            });

            $(document).on("touchend touchcancel touchmove", ".chat-item", function(){
                clearChatDeleteLongPressTimer();
            });

            $(document).on("touchstart", ".message-outgoing[data-delete-message-id]", function(e){
                if ($(e.target).closest(".message-delete-btn").length) {
                    return;
                }

                clearMessageDeleteLongPressTimer();
                var item = $(this);

                messageDeleteLongPressTimer = window.setTimeout(function(){
                    clearMessageDeleteActions();
                    item.addClass("show-delete-action");
                    messageDeleteLongPressTimer = null;
                }, 550);
            });

            $(document).on("touchend touchcancel touchmove", ".message-outgoing[data-delete-message-id]", function(){
                clearMessageDeleteLongPressTimer();
            });

            $("#chatDeleteCancel").on("click", function(){
                closeChatDeleteModal();
            });

            $("#chatDeleteModal").on("click", function(e){
                if (e.target === this) {
                    closeChatDeleteModal();
                }
            });

            $("#chatDeleteConfirm").on("click", function(){
                if (!pendingChatDeleteTarget) return;
                hideChatThread(pendingChatDeleteTarget);
            });

            $("#messageDeleteCancel").on("click", function(){
                closeMessageDeleteModal();
            });

            $("#messageDeleteModal").on("click", function(e){
                if (e.target === this) {
                    closeMessageDeleteModal();
                }
            });

            $("#messageDeleteConfirm").on("click", function(){
                if (!pendingMessageDeleteTarget) return;
                deleteChatMessage(pendingMessageDeleteTarget);
            });

            $(document).on("click", ".chat-assets-tab", function(){
                var target = $(this).attr("data-target") || "media";
                var shell = $(this).closest(".chat-assets-shell");
                if (shell.length === 0) return;

                shell.find(".chat-assets-tab").removeClass("active").attr("aria-selected", "false");
                $(this).addClass("active").attr("aria-selected", "true");
                shell.find(".chat-assets-panel").removeClass("active");
                shell.find('.chat-assets-panel[data-panel="' + target + '"]').addClass("active");
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
                        var shouldRefreshChatInfo = $("#rightSidebar").hasClass("active") || selectedFiles.length > 0;
                        $("#messageInput").val("");
                        lastTypingInputAt = 0;
                        syncOwnTypingState(false, true);
                        hideMentionSuggestions();
                        resetAttachment();
                        loadMessages(true); // true to force scroll
                        refreshChatLists(); // Update list order immediately
                        if (shouldRefreshChatInfo) {
                            loadCurrentChatInfoSidebar();
                        }
                    }
                });
            }

            function loadMessages(forceScroll = false) {
                if(currentChatType === "user" && currentChatUserId == 0) return;
                if(currentChatType === "group" && currentGroupId == 0) return;
                if(document.hidden && !forceScroll) return;
                if(messageRequestInFlight) return;

                 var endpoint = currentChatType === "group" ? "app/ajax/getGroupMessage.php" : "app/ajax/getMessage.php";
                 var payload = currentChatType === "group" ? { group_id: currentGroupId } : { id_2: currentChatUserId };
                 payload.csrf_token = chatAjaxCsrfToken;
                 var requestChatType = currentChatType;
                 var requestChatId = requestChatType === "group" ? currentGroupId : currentChatUserId;
                 messageRequestInFlight = true;

                 $.post(endpoint, payload, function(data, status){
                    var activeChatId = requestChatType === "group" ? currentGroupId : currentChatUserId;
                    if (requestChatType !== currentChatType || String(activeChatId) !== String(requestChatId)) {
                        return;
                    }

                    var chatBox = $("#chatBox");
                    var isScrolledToBottom = chatBox[0].scrollHeight - chatBox[0].scrollTop <= chatBox[0].clientHeight + 50;
                    
                    $("#chatBox").html(data);
                    renderTypingIndicatorInChat(false);
                    
                    // Scroll down if we were already at bottom or if forced (like after sending)
                    if(isScrolledToBottom || forceScroll) {
                        scrollDown();
                    }
                }).always(function(){
                    messageRequestInFlight = false;
                });
            }

            function scrollDown(){
                 var chatBox = document.getElementById("chatBox");
                 chatBox.scrollTop = chatBox.scrollHeight;
            }

            // Real-time polling
            setInterval(function(){
                if (!document.hidden) {
                    loadMessages();
                    refreshChatLists();
                }
            }, 8000);

            setInterval(function(){
                maintainOwnTypingState();
                refreshTypingStatus();
            }, 1500);

            function refreshChatLists(){
                // Only refresh if search is empty to avoid interrupting typing
                if($("#searchText").val() != "") return;
                if(document.hidden || chatListRequestInFlight) return;

                chatListRequestInFlight = true;
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
                }).always(function(){
                    chatListRequestInFlight = false;
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

            if (formalEmailIsAdmin) {
                resetFormalEmailComposer();
                if (formalEmailFlash && (formalEmailFlash.open || (formalEmailFlash.message || "") !== "")) {
                    openFormalEmailComposer();
                    if (window.history && window.history.replaceState) {
                        var cleanUrl = new URL(window.location.href);
                        cleanUrl.searchParams.delete("open_formal_email");
                        cleanUrl.searchParams.delete("gmail_status");
                        cleanUrl.searchParams.delete("gmail_error");
                        window.history.replaceState({}, document.title, cleanUrl.toString());
                    }
                }
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



