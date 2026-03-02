<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    require_once "inc/tenant.php";
    include "app/model/user.php";

    // Get filter parameters
    $filter_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $filter_date = isset($_GET['date']) ? $_GET['date'] : null;

        // Build query: fetch attendance data with tenant scope
        $sql = "SELECT s.*, u.full_name, u.username, a.att_date, a.time_in, a.time_out 
            FROM screenshots s 
            INNER JOIN users u ON s.user_id = u.id 
            LEFT JOIN attendance a ON s.attendance_id = a.id 
            WHERE 1=1";
    $params = [];

    $scope = tenant_get_scope($pdo, 'screenshots', 's');
    $sql .= $scope['sql'];
    $params = array_merge($params, $scope['params']);

    if ($filter_user_id) {
        $sql .= " AND s.user_id = ?";
        $params[] = $filter_user_id;
    }

    if ($filter_date) {
        $sql .= " AND DATE(s.taken_at) = ?";
        $params[] = $filter_date;
    }

    $sql .= " ORDER BY s.taken_at DESC";

    $stmt = $pdo->prepare($sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $screenshot_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $screenshots = [];
    $grouped_screenshots = [];

    foreach ($screenshot_rows as $screenshot) {
        $imagePath = $screenshot['image_path'];
        $fileExists = file_exists($imagePath);
        $imageUrl = null;

        if ($fileExists) {
            $mtime = @filemtime($imagePath);
            $imageUrl = $imagePath . '?t=' . ($mtime ? $mtime : time());
        }

        $screenshot['file_exists'] = $fileExists;
        $screenshot['image_url'] = $imageUrl;
        $screenshot['taken_at_formatted'] = date('M d, Y h:i A', strtotime($screenshot['taken_at']));

        $screenshots[] = $screenshot;

        $group_key = (string)$screenshot['user_id'];
        if (!isset($grouped_screenshots[$group_key])) {
            $grouped_screenshots[$group_key] = [
                'user_id' => (int)$screenshot['user_id'],
                'full_name' => $screenshot['full_name'],
                'username' => $screenshot['username'],
                'screenshots' => []
            ];
        }

        $grouped_screenshots[$group_key]['screenshots'][] = $screenshot;
    }

    $grouped_screenshots = array_values($grouped_screenshots);

    // Get all users for filter dropdown
    $users = get_all_users($pdo);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Captures | TaskFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .capture-folder-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }

        .capture-folder-card {
            background: white;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
        }

        .capture-folder-card:hover,
        .capture-folder-card.is-active {
            border-color: #D1D5DB;
            box-shadow: 0 8px 22px rgba(17, 24, 39, 0.08);
            transform: translateY(-1px);
        }

        .capture-folder-card:focus {
            outline: 2px solid #C4B5FD;
            outline-offset: 2px;
        }

        .capture-folder-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: #EEF2FF;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .capture-folder-main {
            min-width: 0;
        }

        .capture-folder-title {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .capture-folder-meta {
            margin: 4px 0 0;
            font-size: 12px;
            color: var(--text-gray);
        }

        .capture-folder-sub {
            margin: 4px 0 0;
            font-size: 12px;
            color: #9CA3AF;
        }

        .capture-folder-view {
            display: none;
            background: white;
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            padding: 16px;
        }

        .capture-folder-view-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #F3F4F6;
        }

        .capture-folder-view-title {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .capture-folder-view-meta {
            margin: 4px 0 0;
            font-size: 13px;
            color: var(--text-gray);
        }

        .capture-folder-panel {
            display: none;
        }

        .capture-folder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
        }

        .capture-card {
            border: 1px solid #F1F5F9;
            border-radius: 12px;
            box-shadow: none;
            padding: 14px;
            text-align: left;
            background: white;
        }

        .capture-thumbnail {
            width: 100%;
            height: 170px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #eee;
            cursor: pointer;
            display: block;
        }

        .capture-placeholder {
            width: 100%;
            height: 170px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .capture-card-meta {
            margin-top: 12px;
        }

        .capture-card-time {
            font-size: 12px;
            color: var(--text-gray);
        }

        .capture-modal-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.55);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            z-index: 2;
        }

        .capture-modal-nav:hover {
            background: rgba(15, 23, 42, 0.75);
        }

        .capture-modal-prev {
            left: 8px;
        }

        .capture-modal-next {
            right: 8px;
        }

        .capture-modal-counter {
            color: #D1D5DB;
            text-align: center;
            margin-top: 8px;
            font-size: 13px;
        }

        .empty-captures {
            padding: 40px;
            text-align: center;
            color: var(--text-gray);
            background: white;
            border-radius: 16px;
            border: 1px solid #E5E7EB;
        }

        @media (max-width: 768px) {
            .capture-folder-list {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .capture-folder-card {
                padding: 12px;
            }

            .capture-folder-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .capture-card {
                padding: 10px;
            }

            .capture-thumbnail,
            .capture-placeholder {
                height: 130px;
            }
        }

        @media (max-width: 520px) {
            .capture-folder-list,
            .capture-folder-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        
        <!-- Page Header -->
        <div style="margin-bottom: 20px;">
            <span style="color: var(--text-gray); font-size: 14px;">Monitor employee activity</span>
        </div>
        
        <!-- Filter Section -->
        <div style="background: white; padding: 16px; border-radius: 12px; border: 1px solid #E5E7EB; margin-bottom: 24px;">
            <form method="GET" action="screenshots.php" style="display: flex; flex-wrap: wrap; gap: 10px; margin: 0; align-items: center;">
                <select name="user_id" class="form-input" style="flex: 1; min-width: 180px; margin: 0; padding: 10px; border-radius: 8px; border: 1px solid #E5E7EB;">
                    <option value="">All Employees</option>
                    <?php foreach ($users as $user) { 
                        if ($user['role'] == 'employee') { ?>
                            <option value="<?=$user['id']?>" <?=($filter_user_id == $user['id']) ? 'selected' : ''?>>
                                <?=$user['full_name']?>
                            </option>
                    <?php } } ?>
                </select>
                <input type="date" name="date" value="<?=$filter_date?>" class="form-input" style="flex: 1; min-width: 150px; margin: 0; padding: 10px; border-radius: 8px; border: 1px solid #E5E7EB;">
                <button type="submit" class="btn-primary" style="padding: 10px 20px; white-space: nowrap;">
                    <i class="fa fa-filter"></i> Filter
                </button>
                <a href="screenshots.php" class="btn-outline" style="padding: 10px 20px; white-space: nowrap; text-decoration: none;">
                    <i class="fa fa-refresh"></i> Reset
                </a>
            </form>
        </div>

        <!-- Screenshots Grid -->
        <div id="screenshotsContainer" 
                data-user-id="<?=htmlspecialchars($filter_user_id ?? '')?>" 
                data-date="<?=htmlspecialchars($filter_date ?? '')?>">
            <?php if (!empty($grouped_screenshots)) { ?>
                <div class="capture-folder-list" id="folderList">
                    <?php foreach ($grouped_screenshots as $group) { ?>
                        <?php
                            $capture_count = count($group['screenshots']);
                            $capture_label = $capture_count === 1 ? 'capture' : 'captures';
                            $latest_capture = $capture_count > 0 ? $group['screenshots'][0]['taken_at_formatted'] : 'No captures yet';
                            $username_prefix = !empty($group['username']) ? '@' . htmlspecialchars($group['username']) . ' | ' : '';
                        ?>
                        <div class="capture-folder-card capture-folder-trigger"
                            role="button"
                            tabindex="0"
                            data-user-id="<?=$group['user_id']?>"
                            data-user-name="<?=htmlspecialchars($group['full_name'], ENT_QUOTES, 'UTF-8')?>"
                            data-user-username="<?=htmlspecialchars($group['username'], ENT_QUOTES, 'UTF-8')?>"
                            data-capture-count="<?=$capture_count?>">
                            <div class="capture-folder-icon">
                                <i class="fa fa-folder"></i>
                            </div>
                            <div class="capture-folder-main">
                                <h3 class="capture-folder-title"><?=htmlspecialchars($group['full_name'])?></h3>
                                <p class="capture-folder-meta">
                                    <?=$username_prefix?><?=$capture_count?> <?=$capture_label?>
                                </p>
                                <p class="capture-folder-sub">Latest: <?=$latest_capture?></p>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <div class="capture-folder-view" id="folderView">
                    <div class="capture-folder-view-header">
                        <button type="button" class="btn-outline" id="backToFolders" style="padding: 8px 14px;">
                            <i class="fa fa-arrow-left"></i> Back to folders
                        </button>
                        <div>
                            <h3 class="capture-folder-view-title" id="activeFolderTitle"></h3>
                            <p class="capture-folder-view-meta" id="activeFolderMeta"></p>
                        </div>
                    </div>

                    <?php foreach ($grouped_screenshots as $group) { ?>
                        <div class="capture-folder-panel" data-user-id="<?=$group['user_id']?>">
                            <div class="capture-folder-grid">
                                <?php foreach ($group['screenshots'] as $screenshot) { ?>
                                    <div class="capture-card"
                                        data-screenshot-id="<?=$screenshot['id']?>"
                                        data-image-path="<?=!empty($screenshot['file_exists']) ? htmlspecialchars($screenshot['image_path'], ENT_QUOTES, 'UTF-8') : ''?>"
                                        data-employee-name="<?=htmlspecialchars($screenshot['full_name'], ENT_QUOTES, 'UTF-8')?>"
                                        data-taken-at="<?=htmlspecialchars($screenshot['taken_at'], ENT_QUOTES, 'UTF-8')?>">
                                        <?php if (!empty($screenshot['file_exists']) && !empty($screenshot['image_url'])) { ?>
                                            <img src="<?=htmlspecialchars($screenshot['image_url'], ENT_QUOTES, 'UTF-8')?>"
                                                alt="Screenshot"
                                                class="capture-thumbnail"
                                                onclick='showFullImage(<?=json_encode($screenshot['image_path'])?>, <?=json_encode($screenshot['full_name'])?>, <?=json_encode($screenshot['taken_at'])?>)'>
                                        <?php } else { ?>
                                            <div class="capture-placeholder">
                                                <i class="fa fa-image" style="font-size: 32px; color: #ccc;"></i>
                                            </div>
                                        <?php } ?>
                                        
                                        <div class="capture-card-meta">
                                            <div class="capture-card-time">
                                                <?=htmlspecialchars($screenshot['taken_at_formatted'])?>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="empty-captures" id="emptyState">
                    <i class="fa fa-camera" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h3>No screenshots found</h3>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Modal for Full Image -->
    <div id="imageModal" style="display: none; position: fixed; z-index: 5000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9);">
        <div style="position: relative; margin: auto; padding: 20px; width: 90%; max-width: 1200px; top: 50%; transform: translateY(-50%);">
            <span onclick="closeModal()" style="position: absolute; top: 10px; right: 25px; color: #f1f1f1; font-size: 35px; font-weight: bold; cursor: pointer;">&times;</span>
            <button type="button" class="capture-modal-nav capture-modal-prev" onclick="showPrevImage()" aria-label="Previous screenshot">
                <i class="fa fa-chevron-left"></i>
            </button>
            <button type="button" class="capture-modal-nav capture-modal-next" onclick="showNextImage()" aria-label="Next screenshot">
                <i class="fa fa-chevron-right"></i>
            </button>
            <img id="modalImage" class="modal-image" style="display: block; margin: auto; max-height: 90vh;">
            <div id="modalInfo" style="color: white; text-align: center; margin-top: 15px; font-size: 16px;"></div>
            <div id="modalCounter" class="capture-modal-counter"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <?php include 'inc/modals.php'; ?>

    <script type="text/javascript">
        var active = document.querySelector("#navList li:nth-child(5)");
        if (active) {
            active.classList.add("active");
        }

        var slideshowItems = [];
        var slideshowIndex = -1;
        var slideshowAutoTimer = null;
        var SLIDESHOW_INTERVAL_MS = 3000;

        function stopSlideshowAutoPlay() {
            if (slideshowAutoTimer) {
                clearInterval(slideshowAutoTimer);
                slideshowAutoTimer = null;
            }
        }

        function startSlideshowAutoPlay() {
            stopSlideshowAutoPlay();
            if (slideshowItems.length <= 1) {
                return;
            }

            slideshowAutoTimer = setInterval(function() {
                showNextImage(true);
            }, SLIDESHOW_INTERVAL_MS);
        }

        function collectVisibleSlideshowItems() {
            var items = [];
            var activePanel = document.querySelector('#folderView .capture-folder-panel[style*="display: block"]');
            var cards = activePanel
                ? activePanel.querySelectorAll('.capture-card[data-image-path]')
                : document.querySelectorAll('#screenshotsContainer .capture-card[data-image-path]');

            cards.forEach(function(card) {
                var imagePath = card.getAttribute('data-image-path') || '';
                var employeeName = card.getAttribute('data-employee-name') || '';
                var takenAt = card.getAttribute('data-taken-at') || '';
                if (imagePath) {
                    items.push({
                        imagePath: imagePath,
                        employeeName: employeeName,
                        takenAt: takenAt
                    });
                }
            });

            return items;
        }

        function renderSlideshowImage() {
            if (!slideshowItems.length || slideshowIndex < 0) {
                return;
            }

            var item = slideshowItems[slideshowIndex];
            var modalImg = document.getElementById("modalImage");
            var modalInfo = document.getElementById("modalInfo");
            var modalCounter = document.getElementById("modalCounter");

            modalImg.src = item.imagePath;
            modalInfo.innerHTML = "<strong>" + item.employeeName + "</strong><br>Taken at: " + item.takenAt;
            if (modalCounter) {
                modalCounter.textContent = (slideshowIndex + 1) + " / " + slideshowItems.length;
            }
        }

        function showFullImage(imagePath, employeeName, takenAt) {
            var modal = document.getElementById("imageModal");
            var visibleItems = collectVisibleSlideshowItems();

            slideshowItems = visibleItems.length ? visibleItems : [{
                imagePath: imagePath,
                employeeName: employeeName,
                takenAt: takenAt
            }];

            slideshowIndex = slideshowItems.findIndex(function(item) {
                return item.imagePath === imagePath && item.takenAt === takenAt;
            });

            if (slideshowIndex === -1) {
                slideshowIndex = slideshowItems.findIndex(function(item) {
                    return item.imagePath === imagePath;
                });
            }

            if (slideshowIndex === -1) {
                slideshowIndex = 0;
            }

            modal.style.display = "block";
            document.body.style.overflow = "hidden";
            renderSlideshowImage();
            startSlideshowAutoPlay();
        }

        function showPrevImage(fromAuto) {
            if (!slideshowItems.length) return;
            slideshowIndex = (slideshowIndex - 1 + slideshowItems.length) % slideshowItems.length;
            renderSlideshowImage();
            if (!fromAuto) {
                startSlideshowAutoPlay();
            }
        }

        function showNextImage(fromAuto) {
            if (!slideshowItems.length) return;
            slideshowIndex = (slideshowIndex + 1) % slideshowItems.length;
            renderSlideshowImage();
            if (!fromAuto) {
                startSlideshowAutoPlay();
            }
        }

        function closeModal() {
            var modal = document.getElementById("imageModal");
            modal.style.display = "none";
            document.body.style.overflow = "";
            stopSlideshowAutoPlay();
            slideshowItems = [];
            slideshowIndex = -1;
        }

        // Close modal when clicking outside the image
        window.onclick = function(event) {
            var modal = document.getElementById("imageModal");
            if (event.target == modal) {
                closeModal();
            }
        }

        // Close modal with Escape key and navigate with arrow keys
        document.addEventListener('keydown', function(event) {
            var modal = document.getElementById("imageModal");
            var isModalOpen = modal && modal.style.display === "block";
            if (event.key === 'Escape' && isModalOpen) {
                closeModal();
            } else if (event.key === 'ArrowLeft' && isModalOpen) {
                showPrevImage();
            } else if (event.key === 'ArrowRight' && isModalOpen) {
                showNextImage();
            }
        });

        // Auto-refresh screenshots
        (function() {
            var container = document.getElementById('screenshotsContainer');
            if (!container) return;

            var refreshInterval = null;
            var isRefreshing = false;
            var activeUserId = null;

            function bindFolderEvents() {
                var backButton = document.getElementById('backToFolders');
                if (backButton) {
                    backButton.onclick = function() {
                        closeFolderView();
                    };
                }

                var folderTriggers = container.querySelectorAll('.capture-folder-trigger');
                folderTriggers.forEach(function(trigger) {
                    trigger.onclick = function() {
                        openFolder(trigger.getAttribute('data-user-id'));
                    };

                    trigger.onkeydown = function(event) {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            openFolder(trigger.getAttribute('data-user-id'));
                        }
                    };
                });
            }

            function openFolder(userId) {
                var folderList = document.getElementById('folderList');
                var folderView = document.getElementById('folderView');
                if (!folderList || !folderView) return;

                var selectedTrigger = null;
                var folderTriggers = folderList.querySelectorAll('.capture-folder-trigger');
                folderTriggers.forEach(function(trigger) {
                    var isSelected = String(trigger.getAttribute('data-user-id')) === String(userId);
                    trigger.classList.toggle('is-active', isSelected);
                    if (isSelected) {
                        selectedTrigger = trigger;
                    }
                });

                var matchingPanel = null;
                var panels = folderView.querySelectorAll('.capture-folder-panel');
                panels.forEach(function(panel) {
                    var isMatch = String(panel.getAttribute('data-user-id')) === String(userId);
                    panel.style.display = isMatch ? 'block' : 'none';
                    if (isMatch) {
                        matchingPanel = panel;
                    }
                });

                if (!matchingPanel) {
                    closeFolderView();
                    return;
                }

                if (selectedTrigger) {
                    var titleEl = document.getElementById('activeFolderTitle');
                    var metaEl = document.getElementById('activeFolderMeta');
                    var captureCount = selectedTrigger.getAttribute('data-capture-count') || '0';
                    var captureLabel = captureCount === '1' ? 'capture' : 'captures';
                    var username = selectedTrigger.getAttribute('data-user-username') || '';
                    var metaPrefix = username ? '@' + username + ' | ' : '';

                    if (titleEl) {
                        titleEl.textContent = selectedTrigger.getAttribute('data-user-name') || 'User Captures';
                    }
                    if (metaEl) {
                        metaEl.textContent = metaPrefix + captureCount + ' ' + captureLabel;
                    }
                }

                folderList.style.display = 'none';
                folderView.style.display = 'block';
                activeUserId = String(userId);
                container.setAttribute('data-active-user-id', activeUserId);
            }

            function closeFolderView() {
                var folderList = document.getElementById('folderList');
                var folderView = document.getElementById('folderView');

                if (folderList) {
                    folderList.style.display = 'grid';
                }

                if (folderView) {
                    folderView.style.display = 'none';
                    var panels = folderView.querySelectorAll('.capture-folder-panel');
                    panels.forEach(function(panel) {
                        panel.style.display = 'none';
                    });
                }

                var folderTriggers = container.querySelectorAll('.capture-folder-trigger');
                folderTriggers.forEach(function(trigger) {
                    trigger.classList.remove('is-active');
                });

                activeUserId = null;
                container.removeAttribute('data-active-user-id');
            }

            function createScreenshotCard(screenshot) {
                var card = document.createElement('div');
                card.className = 'capture-card';
                card.setAttribute('data-screenshot-id', screenshot.id);
                card.setAttribute('data-employee-name', screenshot.full_name || '');
                card.setAttribute('data-taken-at', screenshot.taken_at || '');
                card.setAttribute('data-image-path', (screenshot.file_exists && screenshot.image_path) ? screenshot.image_path : '');

                if (screenshot.file_exists && screenshot.image_url) {
                    var image = document.createElement('img');
                    image.src = screenshot.image_url;
                    image.alt = 'Screenshot';
                    image.className = 'capture-thumbnail';
                    image.addEventListener('click', function() {
                        showFullImage(screenshot.image_path, screenshot.full_name, screenshot.taken_at);
                    });
                    card.appendChild(image);
                } else {
                    var placeholder = document.createElement('div');
                    placeholder.className = 'capture-placeholder';
                    placeholder.innerHTML = '<i class="fa fa-image" style="font-size: 32px; color: #ccc;"></i>';
                    card.appendChild(placeholder);
                }

                var meta = document.createElement('div');
                meta.className = 'capture-card-meta';

                var time = document.createElement('div');
                time.className = 'capture-card-time';
                time.textContent = screenshot.taken_at_formatted || screenshot.taken_at;

                meta.appendChild(time);
                card.appendChild(meta);

                return card;
            }

            function createFolderTrigger(group) {
                var captureCount = group.screenshots.length;
                var captureLabel = captureCount === 1 ? 'capture' : 'captures';
                var latestCapture = captureCount > 0 ? (group.screenshots[0].taken_at_formatted || group.screenshots[0].taken_at) : 'No captures yet';
                var usernamePrefix = group.username ? '@' + group.username + ' | ' : '';

                var card = document.createElement('div');
                card.className = 'capture-folder-card capture-folder-trigger';
                card.setAttribute('role', 'button');
                card.setAttribute('tabindex', '0');
                card.setAttribute('data-user-id', group.user_id);
                card.setAttribute('data-user-name', group.full_name || 'Unknown User');
                card.setAttribute('data-user-username', group.username || '');
                card.setAttribute('data-capture-count', String(captureCount));

                var icon = document.createElement('div');
                icon.className = 'capture-folder-icon';
                icon.innerHTML = '<i class="fa fa-folder"></i>';

                var content = document.createElement('div');
                content.className = 'capture-folder-main';

                var title = document.createElement('h3');
                title.className = 'capture-folder-title';
                title.textContent = group.full_name || 'Unknown User';

                var meta = document.createElement('p');
                meta.className = 'capture-folder-meta';
                meta.textContent = usernamePrefix + captureCount + ' ' + captureLabel;

                var sub = document.createElement('p');
                sub.className = 'capture-folder-sub';
                sub.textContent = 'Latest: ' + latestCapture;

                content.appendChild(title);
                content.appendChild(meta);
                content.appendChild(sub);
                card.appendChild(icon);
                card.appendChild(content);

                return card;
            }

            function createFolderPanel(group) {
                var panel = document.createElement('div');
                panel.className = 'capture-folder-panel';
                panel.setAttribute('data-user-id', group.user_id);

                var grid = document.createElement('div');
                grid.className = 'capture-folder-grid';

                group.screenshots.forEach(function(screenshot) {
                    grid.appendChild(createScreenshotCard(screenshot));
                });

                panel.appendChild(grid);
                return panel;
            }

            function renderFolderBrowser(grouped) {
                container.innerHTML = '';

                if (!grouped || grouped.length === 0) {
                    var emptyState = document.createElement('div');
                    emptyState.className = 'empty-captures';
                    emptyState.id = 'emptyState';
                    emptyState.innerHTML = '<i class="fa fa-camera" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i><h3>No screenshots found</h3>';
                    container.appendChild(emptyState);
                    activeUserId = null;
                    return;
                }

                var folderList = document.createElement('div');
                folderList.className = 'capture-folder-list';
                folderList.id = 'folderList';

                grouped.forEach(function(group) {
                    folderList.appendChild(createFolderTrigger(group));
                });

                var folderView = document.createElement('div');
                folderView.className = 'capture-folder-view';
                folderView.id = 'folderView';

                var viewHeader = document.createElement('div');
                viewHeader.className = 'capture-folder-view-header';

                var backButton = document.createElement('button');
                backButton.type = 'button';
                backButton.className = 'btn-outline';
                backButton.id = 'backToFolders';
                backButton.style.padding = '8px 14px';
                backButton.innerHTML = '<i class="fa fa-arrow-left"></i> Back to folders';

                var viewTitleWrap = document.createElement('div');
                var viewTitle = document.createElement('h3');
                viewTitle.className = 'capture-folder-view-title';
                viewTitle.id = 'activeFolderTitle';
                var viewMeta = document.createElement('p');
                viewMeta.className = 'capture-folder-view-meta';
                viewMeta.id = 'activeFolderMeta';
                viewTitleWrap.appendChild(viewTitle);
                viewTitleWrap.appendChild(viewMeta);

                viewHeader.appendChild(backButton);
                viewHeader.appendChild(viewTitleWrap);
                folderView.appendChild(viewHeader);

                grouped.forEach(function(group) {
                    folderView.appendChild(createFolderPanel(group));
                });

                container.appendChild(folderList);
                container.appendChild(folderView);

                bindFolderEvents();

                if (activeUserId) {
                    openFolder(activeUserId);
                }
            }

            function fetchScreenshots() {
                if (isRefreshing) return;
                isRefreshing = true;

                var userId = container.getAttribute('data-user-id') || '';
                var date = container.getAttribute('data-date') || '';
                
                var url = 'get_screenshots_api.php';
                var params = [];
                if (userId) params.push('user_id=' + encodeURIComponent(userId));
                if (date) params.push('date=' + encodeURIComponent(date));
                if (params.length > 0) url += '?' + params.join('&');

                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        isRefreshing = false;
                        if (xhr.status === 200) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if (response.status === 'success') {
                                    updateScreenshots(response.screenshots);
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                            }
                        }
                    }
                };
                xhr.send();
            }

            function updateScreenshots(screenshots) {
                var grouped = groupScreenshotsByUser(screenshots);
                renderFolderBrowser(grouped);
            }

            function groupScreenshotsByUser(screenshots) {
                var grouped = {};
                var order = [];

                screenshots.forEach(function(screenshot) {
                    var key = String(screenshot.user_id || '');
                    if (!grouped[key]) {
                        grouped[key] = {
                            user_id: screenshot.user_id,
                            full_name: screenshot.full_name || 'Unknown User',
                            username: screenshot.username || '',
                            screenshots: []
                        };
                        order.push(key);
                    }
                    grouped[key].screenshots.push(screenshot);
                });

                return order.map(function(key) {
                    return grouped[key];
                });
            }

            bindFolderEvents();
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



