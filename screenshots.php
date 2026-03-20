<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    require_once "inc/tenant.php";
    include "app/model/user.php";

    function capture_profile_image_url($profileImage) {
        $profileImage = trim((string)$profileImage);
        if ($profileImage === '' || strtolower($profileImage) === 'default.png') {
            return '';
        }

        $safeName = basename($profileImage);
        if ($safeName === '') {
            return '';
        }

        $absolutePath = __DIR__ . '/uploads/' . $safeName;
        if (!is_file($absolutePath)) {
            return '';
        }

        $mtime = @filemtime($absolutePath);
        return 'uploads/' . rawurlencode($safeName) . '?t=' . ($mtime ? $mtime : time());
    }

    // Get filter parameters
    $filter_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $open_user_id = isset($_GET['open_user_id']) ? intval($_GET['open_user_id']) : null;
    $filter_date = isset($_GET['date']) ? $_GET['date'] : null;

    $reset_url = 'screenshots.php';
    if ($open_user_id) {
        $reset_url .= '?open_user_id=' . urlencode((string)$open_user_id);
    }

        // Build query: fetch attendance data with tenant scope
        $sql = "SELECT s.*, u.full_name, u.username, u.profile_image, a.att_date, a.time_in, a.time_out 
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
        $screenshot['profile_image_url'] = capture_profile_image_url($screenshot['profile_image'] ?? '');
        $screenshot['taken_at_formatted'] = date('M d, Y h:i A', strtotime($screenshot['taken_at']));

        $screenshots[] = $screenshot;

        $group_key = (string)$screenshot['user_id'];
        if (!isset($grouped_screenshots[$group_key])) {
            $grouped_screenshots[$group_key] = [
                'user_id' => (int)$screenshot['user_id'],
                'full_name' => $screenshot['full_name'],
                'username' => $screenshot['username'],
                'profile_image_url' => $screenshot['profile_image_url'] ?? '',
                'screenshots' => []
            ];
        }

        $grouped_screenshots[$group_key]['screenshots'][] = $screenshot;
    }

    $grouped_screenshots = array_values($grouped_screenshots);

    // Get all users for filter dropdown
    $users = get_all_users($pdo);
    $captures_css_ver = @filemtime(__DIR__ . '/css/captures-v2.css');
    if (!$captures_css_ver) {
        $captures_css_ver = time();
    }

    $total_captures = count($screenshots);
    $today_key = date('Y-m-d');
    $today_captures = 0;
    $today_captured_users_map = [];
    foreach ($screenshots as $shot) {
        $takenAt = (string)($shot['taken_at'] ?? '');
        if ($takenAt !== '' && strpos($takenAt, $today_key) === 0) {
            $today_captures++;
            $uid = (int)($shot['user_id'] ?? 0);
            if ($uid > 0) {
                $today_captured_users_map[(string)$uid] = true;
            }
        }
    }
    $today_captured_users = count($today_captured_users_map);
    $total_active_users = (int)count_users($pdo);
    $last_capture_label = '--';
    if (!empty($screenshots)) {
        $last_capture_ts = strtotime((string)$screenshots[0]['taken_at']);
        if ($last_capture_ts !== false) {
            $last_capture_label = date('h:i A', $last_capture_ts);
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Captures | TaskFlow</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
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
            outline: 2px solid var(--primary-muted-2);
            outline-offset: 2px;
        }

        .capture-folder-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: var(--primary-soft-3);
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

        .capture-modal-controls {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 8px;
        }

        .capture-modal-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.55);
            color: #fff;
            padding: 7px 14px;
            font-size: 13px;
            cursor: pointer;
        }

        .capture-modal-toggle:hover {
            background: rgba(15, 23, 42, 0.75);
        }

        .capture-modal-toggle:disabled {
            opacity: 0.45;
            cursor: not-allowed;
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
    <link rel="stylesheet" href="css/captures-v2.css?v=<?= urlencode((string)$captures_css_ver) ?>">
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main captures-v2-page">
        <div class="captures-v2-shell">
            <div class="pg-head">
                <h1>Employee Captures</h1>
                <p>Monitor and review screenshot activity from your team.</p>
            </div>

            <div class="stats">
                <div class="sc">
                    <div class="si pu"><i class="fa fa-camera"></i></div>
                    <div>
                        <div class="sv" id="statTotalValue"><?= (int)$total_captures ?></div>
                        <div class="sl">Total Captures</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si gr"><i class="fa fa-line-chart"></i></div>
                    <div>
                        <div class="sv" id="statTodayValue"><?= (int)$today_captures ?></div>
                        <div class="sl">Today's Captures</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si bl"><i class="fa fa-clock-o"></i></div>
                    <div>
                        <div class="sv mono" id="statLastValue"><?= htmlspecialchars($last_capture_label) ?></div>
                        <div class="sl">Last Capture</div>
                    </div>
                </div>
                <div class="sc">
                    <div class="si pu"><i class="fa fa-users"></i></div>
                    <div>
                        <div class="sv mono"
                             id="statUsersCapturedRatio"
                             data-active-users="<?= (int)$total_active_users ?>">
                            <?= (int)$today_captured_users ?>/<?= (int)$total_active_users ?>
                        </div>
                        <div class="sl">Today Captured / Active Users</div>
                    </div>
                </div>
            </div>

            <div class="fbar">
                <form method="GET" action="screenshots.php" class="captures-filter-form">
                    <input type="hidden"
                           name="open_user_id"
                           id="openUserFilterInput"
                           value="<?= htmlspecialchars((string)($open_user_id ?? '')) ?>">
                    <div class="sel-w" id="employeeFilterWrap">
                        <select id="employeeFilterSelect" name="user_id" class="form-input">
                            <option value="">All Employees</option>
                            <?php foreach ($users as $user) {
                                if ($user['role'] == 'employee') { ?>
                                    <option value="<?= $user['id'] ?>" <?= ($filter_user_id == $user['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string)$user['full_name']) ?>
                                    </option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div class="date-w">
                        <input type="date" name="date" value="<?= htmlspecialchars((string)$filter_date) ?>" class="form-input">
                    </div>
                    <div class="vdiv"></div>
                    <button type="submit" class="btn btn-p">
                        <i class="fa fa-filter"></i> Filter
                    </button>
                    <a href="<?= htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-g" id="resetFiltersBtn">
                        <i class="fa fa-refresh"></i> Reset
                    </a>
                    <div class="vtog">
                        <button type="button" class="vb active" id="folderGridBtn" title="Grid view">
                            <i class="fa fa-th-large"></i>
                        </button>
                        <button type="button" class="vb" id="folderListBtn" title="List view">
                            <i class="fa fa-list"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div id="screenshotsContainer"
                data-user-id="<?= htmlspecialchars($filter_user_id ?? '') ?>"
                data-open-user-id="<?= htmlspecialchars($open_user_id ?? '') ?>"
                data-date="<?= htmlspecialchars($filter_date ?? '') ?>">
                <?php if (!empty($grouped_screenshots)) { ?>
                    <div class="capture-folder-list" id="folderList">
                        <?php foreach ($grouped_screenshots as $group) { ?>
                            <?php
                                $capture_count = count($group['screenshots']);
                                $capture_label = $capture_count === 1 ? 'capture' : 'captures';
                                $latest_capture = $capture_count > 0
                                    ? date('M d', strtotime((string)$group['screenshots'][0]['taken_at'])) . ' &middot; ' . date('h:i A', strtotime((string)$group['screenshots'][0]['taken_at']))
                                    : 'No captures yet';
                                $username_label = !empty($group['username']) ? $group['username'] : 'No email';
                            ?>
                            <div class="capture-folder-card capture-folder-trigger"
                                role="button"
                                tabindex="0"
                                data-user-id="<?= $group['user_id'] ?>"
                                data-user-name="<?= htmlspecialchars((string)$group['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                data-user-username="<?= htmlspecialchars((string)$group['username'], ENT_QUOTES, 'UTF-8') ?>"
                                data-user-avatar-url="<?= htmlspecialchars((string)($group['profile_image_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-capture-count="<?= $capture_count ?>">
                                <div class="capture-folder-icon"><i class="fa fa-folder"></i></div>
                                <div class="capture-folder-main">
                                    <h3 class="capture-folder-title"><?= htmlspecialchars((string)$group['full_name']) ?></h3>
                                    <p class="capture-folder-meta"><?= htmlspecialchars($username_label) ?></p>
                                    <span class="capture-folder-badge"><i class="fa fa-camera"></i> <?= $capture_count ?> <?= $capture_label ?></span>
                                    <p class="capture-folder-sub"><?= $latest_capture ?></p>
                                </div>
                                <span class="capture-folder-arrow"><i class="fa fa-angle-right"></i></span>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="capture-folder-view" id="folderView">
                        <div class="capture-folder-view-header">
                            <button type="button" class="back-btn" id="backToFolders">
                                <i class="fa fa-angle-left"></i> Back
                            </button>
                            <div class="capture-folder-avatar" id="activeFolderAvatar">TF</div>
                            <div class="capture-folder-view-main">
                                <h3 class="capture-folder-view-title" id="activeFolderTitle"></h3>
                                <p class="capture-folder-view-meta" id="activeFolderMeta"></p>
                            </div>
                            <div class="capture-folder-count" id="activeFolderCount">
                                <i class="fa fa-camera"></i>
                                <span>0 captures</span>
                            </div>
                        </div>

                        <?php foreach ($grouped_screenshots as $group) { ?>
                            <div class="capture-folder-panel" data-user-id="<?= $group['user_id'] ?>">
                                <div class="capture-folder-grid">
                                    <?php foreach ($group['screenshots'] as $screenshot) { ?>
                                        <?php
                                            $shot_ts = strtotime((string)$screenshot['taken_at']);
                                            $shot_time = $shot_ts ? date('h:i A', $shot_ts) : 'N/A';
                                            $shot_date = $shot_ts ? date('M d, Y', $shot_ts) : 'N/A';
                                            $has_file = !empty($screenshot['file_exists']) && !empty($screenshot['image_url']);
                                        ?>
                                        <div class="capture-card <?= $has_file ? 'clickable' : '' ?>"
                                            data-screenshot-id="<?= $screenshot['id'] ?>"
                                            data-image-path="<?= $has_file ? htmlspecialchars((string)$screenshot['image_path'], ENT_QUOTES, 'UTF-8') : '' ?>"
                                            data-employee-name="<?= htmlspecialchars((string)$screenshot['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-taken-at="<?= htmlspecialchars((string)$screenshot['taken_at'], ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $has_file ? "onclick='showFullImage(" . json_encode($screenshot['image_path']) . ", " . json_encode($screenshot['full_name']) . ", " . json_encode($screenshot['taken_at']) . ")'" : '' ?>>
                                            <div class="capture-thumb-wrap">
                                                <?php if ($has_file) { ?>
                                                    <img src="<?= htmlspecialchars((string)$screenshot['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                                        alt="Screenshot"
                                                        class="capture-thumbnail">
                                                <?php } else { ?>
                                                    <div class="capture-placeholder">
                                                        <i class="fa fa-image"></i>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                            <div class="capture-card-meta">
                                                <div class="capture-card-meta-left">
                                                    <div class="capture-card-time"><?= htmlspecialchars($shot_time) ?></div>
                                                    <div class="capture-card-date"><?= htmlspecialchars($shot_date) ?></div>
                                                </div>
                                                <?php if ($has_file) { ?>
                                                    <button
                                                        type="button"
                                                        class="capture-card-download"
                                                        title="Download screenshot"
                                                        onclick='event.stopPropagation(); downloadCapture(<?= json_encode($screenshot["image_path"]) ?>);'
                                                    >
                                                        <i class="fa fa-download"></i>
                                                    </button>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="empty-captures" id="emptyState">
                        <i class="fa fa-camera"></i>
                        <h3>No screenshots found</h3>
                        <p>No screenshots match this filter yet. Try a different date or reset filters.</p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Modal for Full Image -->
    <div id="imageModal" class="capture-lightbox" style="display: none;">
        <div class="capture-lightbox-wrap">
            <div class="capture-lightbox-img-wrap">
                <button type="button" class="capture-lightbox-close" onclick="closeModal()" aria-label="Close">&times;</button>
                <button type="button" class="capture-modal-nav capture-modal-prev" onclick="showPrevImage()" aria-label="Previous screenshot">
                    <i class="fa fa-chevron-left"></i>
                </button>
                <button type="button" class="capture-modal-nav capture-modal-next" onclick="showNextImage()" aria-label="Next screenshot">
                    <i class="fa fa-chevron-right"></i>
                </button>
                <img id="modalImage" class="modal-image" alt="Screenshot preview">
                <div id="modalInfo"></div>
            </div>
            <div class="capture-modal-controls">
                <div id="modalCounter" class="capture-modal-counter"></div>
                <button type="button" id="slideshowToggleBtn" class="capture-modal-toggle" onclick="toggleSlideshowPlay()" aria-label="Pause slideshow">
                    <i class="fa fa-pause"></i> Pause
                </button>
            </div>
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
    var SLIDESHOW_INTERVAL_MS = 1000;
    var slideshowPaused = false;
    var folderLayoutMode = "grid";
    var shotLayoutMode = "grid";

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function getInitials(name) {
        var parts = String(name || "").trim().split(/\s+/);
        var initials = "";
        for (var i = 0; i < parts.length; i++) {
            if (parts[i]) {
                initials += parts[i].charAt(0).toUpperCase();
            }
            if (initials.length >= 2) break;
        }
        return initials || "TF";
    }

    function renderFolderAvatar(avatarEl, displayName, avatarUrl) {
        if (!avatarEl) return;

        var initials = getInitials(displayName);
        var safeAvatarUrl = String(avatarUrl || "").trim();
        avatarEl.classList.remove("has-image");
        avatarEl.innerHTML = "";
        avatarEl.textContent = initials;

        if (!safeAvatarUrl) return;

        var image = document.createElement("img");
        image.src = safeAvatarUrl;
        image.alt = (displayName ? displayName + " profile picture" : "Profile picture");
        image.loading = "lazy";
        image.decoding = "async";
        image.onerror = function() {
            avatarEl.classList.remove("has-image");
            avatarEl.innerHTML = "";
            avatarEl.textContent = initials;
        };

        avatarEl.innerHTML = "";
        avatarEl.appendChild(image);
        avatarEl.classList.add("has-image");
    }

    function parseDate(raw) {
        var source = String(raw || "").trim();
        if (!source) return null;
        var parsed = new Date(source.replace(" ", "T"));
        return isNaN(parsed.getTime()) ? null : parsed;
    }

    function formatTakenParts(raw) {
        var dateObj = parseDate(raw);
        if (!dateObj) {
            return { time: "N/A", date: "N/A", shortDate: "N/A" };
        }
        return {
            time: dateObj.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit", hour12: true }),
            date: dateObj.toLocaleDateString("en-US", { month: "short", day: "2-digit", year: "numeric" }),
            shortDate: dateObj.toLocaleDateString("en-US", { month: "short", day: "2-digit" })
        };
    }

    function updateStatsSummary(screenshots) {
        var totalEl = document.getElementById("statTotalValue");
        var todayEl = document.getElementById("statTodayValue");
        var lastEl = document.getElementById("statLastValue");
        var usersRatioEl = document.getElementById("statUsersCapturedRatio");
        if (!totalEl || !todayEl || !lastEl) return;

        var list = Array.isArray(screenshots) ? screenshots : [];
        totalEl.textContent = String(list.length);

        var now = new Date();
        var todayKey = now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0") + "-" + String(now.getDate()).padStart(2, "0");
        var todayCount = 0;
        var todayUserMap = {};
        list.forEach(function(item) {
            var d = parseDate(item && item.taken_at);
            if (!d) return;
            var key = d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
            if (key === todayKey) {
                todayCount++;
                var uid = item && item.user_id ? String(item.user_id) : "";
                if (uid !== "") {
                    todayUserMap[uid] = true;
                }
            }
        });
        todayEl.textContent = String(todayCount);

        if (list.length > 0) {
            var latest = formatTakenParts(list[0].taken_at);
            lastEl.textContent = latest.time;
        } else {
            lastEl.textContent = "--";
        }

        if (usersRatioEl) {
            var activeUsers = parseInt(usersRatioEl.getAttribute("data-active-users") || "0", 10);
            var capturedUsersToday = Object.keys(todayUserMap).length;
            usersRatioEl.textContent = String(capturedUsersToday) + "/" + String(activeUsers);
        }
    }

    function stopSlideshowAutoPlay() {
        if (slideshowAutoTimer) {
            clearInterval(slideshowAutoTimer);
            slideshowAutoTimer = null;
        }
    }

    function updateSlideshowToggleButton() {
        var toggleBtn = document.getElementById("slideshowToggleBtn");
        if (!toggleBtn) return;

        var canToggle = slideshowItems.length > 1;
        var isPlaying = canToggle && !slideshowPaused;

        toggleBtn.disabled = !canToggle;
        toggleBtn.setAttribute("aria-label", isPlaying ? "Pause slideshow" : "Play slideshow");
        toggleBtn.innerHTML = isPlaying
            ? '<i class="fa fa-pause"></i> Pause'
            : '<i class="fa fa-play"></i> Play';
    }

    function startSlideshowAutoPlay() {
        stopSlideshowAutoPlay();
        if (slideshowItems.length <= 1 || slideshowPaused) {
            updateSlideshowToggleButton();
            return;
        }
        slideshowAutoTimer = setInterval(function() {
            showNextImage(true);
        }, SLIDESHOW_INTERVAL_MS);
        updateSlideshowToggleButton();
    }

    function setSlideshowPaused(paused) {
        slideshowPaused = !!paused;
        if (slideshowPaused) {
            stopSlideshowAutoPlay();
            updateSlideshowToggleButton();
            return;
        }
        startSlideshowAutoPlay();
    }

    function toggleSlideshowPlay() {
        if (slideshowItems.length <= 1) return;
        setSlideshowPaused(!slideshowPaused);
    }

    function collectVisibleSlideshowItems() {
        var items = [];
        var activePanel = document.querySelector("#folderView .capture-folder-panel[style*='display: block']");
        var cards = activePanel
            ? activePanel.querySelectorAll(".capture-card[data-image-path]")
            : document.querySelectorAll("#screenshotsContainer .capture-card[data-image-path]");

        cards.forEach(function(card) {
            var imagePath = card.getAttribute("data-image-path") || "";
            if (!imagePath) return;
            items.push({
                imagePath: imagePath,
                employeeName: card.getAttribute("data-employee-name") || "",
                takenAt: card.getAttribute("data-taken-at") || ""
            });
        });
        return items;
    }

    function renderSlideshowImage() {
        if (!slideshowItems.length || slideshowIndex < 0) {
            updateSlideshowToggleButton();
            return;
        }
        var item = slideshowItems[slideshowIndex];
        var modalImg = document.getElementById("modalImage");
        var modalInfo = document.getElementById("modalInfo");
        var modalCounter = document.getElementById("modalCounter");
        var when = formatTakenParts(item.takenAt);

        modalImg.src = item.imagePath;
        modalInfo.innerHTML = "<strong>" + escapeHtml(item.employeeName) + "</strong><br>" + escapeHtml(when.date + " | " + when.time);
        if (modalCounter) {
            modalCounter.textContent = (slideshowIndex + 1) + " / " + slideshowItems.length;
        }
        updateSlideshowToggleButton();
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

        slideshowPaused = false;
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
        slideshowPaused = false;
        updateSlideshowToggleButton();
    }

    function downloadCapture(imagePath) {
        var path = String(imagePath || "").trim();
        if (!path) return;
        var link = document.createElement("a");
        link.href = path;
        link.setAttribute("download", "");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    window.addEventListener("click", function(event) {
        var modal = document.getElementById("imageModal");
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener("keydown", function(event) {
        var modal = document.getElementById("imageModal");
        var isModalOpen = modal && modal.style.display === "block";
        if (event.key === "Escape" && isModalOpen) {
            closeModal();
        } else if (event.key === "ArrowLeft" && isModalOpen) {
            showPrevImage();
        } else if (event.key === "ArrowRight" && isModalOpen) {
            showNextImage();
        }
    });

    (function() {
        var container = document.getElementById("screenshotsContainer");
        if (!container) return;

        var isRefreshing = false;
        var activeUserId = String(container.getAttribute("data-open-user-id") || "").trim() || null;
        var topbarTitleEl = document.querySelector(".dash-content-topbar-title");
        var topbarBaseText = topbarTitleEl ? (topbarTitleEl.textContent || "Captures").trim() : "Captures";
        var filterFormEl = document.querySelector(".captures-filter-form");
        var employeeSelectEl = document.getElementById("employeeFilterSelect");

        function syncResetLink() {
            var resetBtn = document.getElementById("resetFiltersBtn");
            if (!resetBtn) return;
            var target = "screenshots.php";
            if (activeUserId) {
                target += "?open_user_id=" + encodeURIComponent(activeUserId);
            }
            resetBtn.setAttribute("href", target);
        }

        function setOpenUserFilterValue(userId) {
            var hidden = document.getElementById("openUserFilterInput");
            if (hidden) {
                hidden.value = String(userId || "").trim();
            }
            syncResetLink();
        }

        // Global filter mode: optional employee + date, no forced folder context.
        function applyGlobalFilterContext() {
            if (employeeSelectEl) {
                employeeSelectEl.disabled = false;
            }
            setOpenUserFilterValue("");
        }

        // Folder filter mode: date-only in the opened folder context.
        function applyFolderFilterContext(userId) {
            if (employeeSelectEl) {
                employeeSelectEl.disabled = true;
            }
            setOpenUserFilterValue(userId || activeUserId || "");
        }

        function bindFilterActions() {
            if (!filterFormEl) return;

            filterFormEl.addEventListener("submit", function() {
                if (activeUserId) {
                    applyFolderFilterContext(activeUserId);
                } else {
                    applyGlobalFilterContext();
                }
            });
        }

        function setTopbarTitle(userName) {
            if (!topbarTitleEl) return;
            var name = String(userName || "").trim();
            topbarTitleEl.textContent = "";
            if (name === "") {
                topbarTitleEl.classList.remove("captures-topbar-with-crumb");
                topbarTitleEl.textContent = topbarBaseText;
                return;
            }

            topbarTitleEl.classList.add("captures-topbar-with-crumb");

            var main = document.createElement("span");
            main.className = "captures-topbar-main";
            main.textContent = topbarBaseText;

            var sep = document.createElement("span");
            sep.className = "captures-topbar-sep";
            sep.textContent = "\u203A";

            var crumb = document.createElement("span");
            crumb.className = "captures-topbar-crumb";
            crumb.textContent = name;

            topbarTitleEl.appendChild(main);
            topbarTitleEl.appendChild(sep);
            topbarTitleEl.appendChild(crumb);
        }

        function syncPrimaryToggles() {
            var folderGridBtn = document.getElementById("folderGridBtn");
            var folderListBtn = document.getElementById("folderListBtn");
            var useShotMode = !!activeUserId;
            var mode = useShotMode ? shotLayoutMode : folderLayoutMode;

            if (folderGridBtn) folderGridBtn.classList.toggle("active", mode === "grid");
            if (folderListBtn) folderListBtn.classList.toggle("active", mode === "list");
        }

        function setFolderLayoutMode(mode) {
            folderLayoutMode = mode === "list" ? "list" : "grid";
            var folderList = document.getElementById("folderList");
            var gridBtn = document.getElementById("folderGridBtn");
            var listBtn = document.getElementById("folderListBtn");

            if (gridBtn) gridBtn.classList.toggle("active", folderLayoutMode === "grid");
            if (listBtn) listBtn.classList.toggle("active", folderLayoutMode === "list");

            if (folderList) {
                folderList.classList.toggle("is-list", folderLayoutMode === "list");
                if (folderList.style.display !== "none") {
                    folderList.style.display = folderLayoutMode === "list" ? "flex" : "grid";
                }
            }

            syncPrimaryToggles();
        }

        function setShotLayoutMode(mode) {
            shotLayoutMode = mode === "list" ? "list" : "grid";
            var gridBtn = document.getElementById("shotGridBtn");
            var listBtn = document.getElementById("shotListBtn");

            if (gridBtn) gridBtn.classList.toggle("active", shotLayoutMode === "grid");
            if (listBtn) listBtn.classList.toggle("active", shotLayoutMode === "list");

            var shotGrids = document.querySelectorAll("#folderView .capture-folder-grid");
            shotGrids.forEach(function(grid) {
                grid.classList.toggle("is-list", shotLayoutMode === "list");
            });

            syncPrimaryToggles();
        }

        function bindViewToggles() {
            var folderGridBtn = document.getElementById("folderGridBtn");
            var folderListBtn = document.getElementById("folderListBtn");
            var shotGridBtn = document.getElementById("shotGridBtn");
            var shotListBtn = document.getElementById("shotListBtn");

            if (folderGridBtn) folderGridBtn.onclick = function() {
                if (activeUserId) {
                    setShotLayoutMode("grid");
                } else {
                    setFolderLayoutMode("grid");
                }
            };
            if (folderListBtn) folderListBtn.onclick = function() {
                if (activeUserId) {
                    setShotLayoutMode("list");
                } else {
                    setFolderLayoutMode("list");
                }
            };
            if (shotGridBtn) shotGridBtn.onclick = function() { setShotLayoutMode("grid"); };
            if (shotListBtn) shotListBtn.onclick = function() { setShotLayoutMode("list"); };
        }

        function setEmployeeFilterVisibility(isVisible) {
            var employeeFilterWrap = document.getElementById("employeeFilterWrap");
            if (!employeeFilterWrap) return;
            employeeFilterWrap.style.display = isVisible ? "" : "none";

            var filterForm = employeeFilterWrap.closest(".captures-filter-form");
            if (filterForm) {
                filterForm.classList.toggle("is-folder-mode", !isVisible);

                var dateWrap = filterForm.querySelector(".date-w");
                if (dateWrap) {
                    dateWrap.classList.toggle("is-compact", !isVisible);
                }

                var divider = filterForm.querySelector(".vdiv");
                if (divider) {
                    divider.style.display = isVisible ? "" : "none";
                }
            }

            if (isVisible) {
                applyGlobalFilterContext();
            } else {
                applyFolderFilterContext(activeUserId);
            }
        }
        function bindFolderEvents() {
            var backButton = document.getElementById("backToFolders");
            if (backButton) {
                backButton.onclick = function() {
                    handleBackToFolders();
                };
            }

            var folderTriggers = container.querySelectorAll(".capture-folder-trigger");
            folderTriggers.forEach(function(trigger) {
                trigger.onclick = function() {
                    openFolder(trigger.getAttribute("data-user-id"));
                };
                trigger.onkeydown = function(event) {
                    if (event.key === "Enter" || event.key === " ") {
                        event.preventDefault();
                        openFolder(trigger.getAttribute("data-user-id"));
                    }
                };
            });
        }

        function handleBackToFolders() {
            var dateInput = filterFormEl ? filterFormEl.querySelector("input[name='date']") : null;
            var hasDateValue = !!(dateInput && String(dateInput.value || "").trim() !== "");
            var current = new URL(window.location.href);
            var hasDateParam = String(current.searchParams.get("date") || "").trim() !== "";

            if (hasDateValue || hasDateParam) {
                current.searchParams.delete("date");
                current.searchParams.delete("open_user_id");

                var query = current.searchParams.toString();
                window.location.href = current.pathname + (query ? "?" + query : "");
                return;
            }

            closeFolderView();
        }

        function openFolder(userId) {
            var folderList = document.getElementById("folderList");
            var folderView = document.getElementById("folderView");
            if (!folderList || !folderView) return;

            var selectedTrigger = null;
            var folderTriggers = folderList.querySelectorAll(".capture-folder-trigger");
            folderTriggers.forEach(function(trigger) {
                var isSelected = String(trigger.getAttribute("data-user-id")) === String(userId);
                trigger.classList.toggle("is-active", isSelected);
                if (isSelected) selectedTrigger = trigger;
            });

            var matchingPanel = null;
            var panels = folderView.querySelectorAll(".capture-folder-panel");
            panels.forEach(function(panel) {
                var isMatch = String(panel.getAttribute("data-user-id")) === String(userId);
                panel.style.display = isMatch ? "block" : "none";
                if (isMatch) matchingPanel = panel;
            });

            if (!matchingPanel) {
                closeFolderView();
                return;
            }

            if (selectedTrigger) {
                var titleEl = document.getElementById("activeFolderTitle");
                var metaEl = document.getElementById("activeFolderMeta");
                var avatarEl = document.getElementById("activeFolderAvatar");
                var countEl = document.getElementById("activeFolderCount");
                var captureCount = selectedTrigger.getAttribute("data-capture-count") || "0";
                var captureLabel = captureCount === "1" ? "capture" : "captures";
                var username = selectedTrigger.getAttribute("data-user-username") || "";
                var displayName = selectedTrigger.getAttribute("data-user-name") || "User Captures";
                var avatarUrl = selectedTrigger.getAttribute("data-user-avatar-url") || "";

                if (titleEl) titleEl.textContent = displayName;
                if (metaEl) metaEl.textContent = username || "No email";
                renderFolderAvatar(avatarEl, displayName, avatarUrl);
                if (countEl) countEl.innerHTML = "<i class='fa fa-camera'></i><span>" + captureCount + " " + captureLabel + "</span>";
                setTopbarTitle(displayName);
            }

            folderList.style.display = "none";
            folderView.style.display = "block";
            activeUserId = String(userId);
            container.setAttribute("data-active-user-id", activeUserId);
            setOpenUserFilterValue(activeUserId);
            setShotLayoutMode(shotLayoutMode);
            setEmployeeFilterVisibility(false);
        }

        function closeFolderView() {
            var folderList = document.getElementById("folderList");
            var folderView = document.getElementById("folderView");

            if (folderList) {
                folderList.style.display = folderLayoutMode === "list" ? "flex" : "grid";
            }

            if (folderView) {
                folderView.style.display = "none";
                var panels = folderView.querySelectorAll(".capture-folder-panel");
                panels.forEach(function(panel) {
                    panel.style.display = "none";
                });
            }

            var folderTriggers = container.querySelectorAll(".capture-folder-trigger");
            folderTriggers.forEach(function(trigger) {
                trigger.classList.remove("is-active");
            });

            activeUserId = null;
            container.removeAttribute("data-active-user-id");
            setOpenUserFilterValue("");
            setEmployeeFilterVisibility(true);
            setTopbarTitle("");
            syncPrimaryToggles();
        }

        function createScreenshotCard(screenshot) {
            var hasFile = !!(screenshot.file_exists && screenshot.image_url && screenshot.image_path);
            var parts = formatTakenParts(screenshot.taken_at);

            var card = document.createElement("div");
            card.className = "capture-card" + (hasFile ? " clickable" : "");
            card.setAttribute("data-screenshot-id", screenshot.id);
            card.setAttribute("data-employee-name", screenshot.full_name || "");
            card.setAttribute("data-taken-at", screenshot.taken_at || "");
            card.setAttribute("data-image-path", hasFile ? screenshot.image_path : "");

            if (hasFile) {
                card.addEventListener("click", function() {
                    showFullImage(screenshot.image_path, screenshot.full_name, screenshot.taken_at);
                });
            }

            var thumbWrap = document.createElement("div");
            thumbWrap.className = "capture-thumb-wrap";

            if (hasFile) {
                var image = document.createElement("img");
                image.src = screenshot.image_url;
                image.alt = "Screenshot";
                image.className = "capture-thumbnail";
                thumbWrap.appendChild(image);
            } else {
                var placeholder = document.createElement("div");
                placeholder.className = "capture-placeholder";
                placeholder.innerHTML = "<i class='fa fa-image'></i>";
                thumbWrap.appendChild(placeholder);
            }

            var meta = document.createElement("div");
            meta.className = "capture-card-meta";

            var metaLeft = document.createElement("div");
            metaLeft.className = "capture-card-meta-left";

            var time = document.createElement("div");
            time.className = "capture-card-time";
            time.textContent = parts.time;

            var date = document.createElement("div");
            date.className = "capture-card-date";
            date.textContent = parts.date;

            metaLeft.appendChild(time);
            metaLeft.appendChild(date);
            meta.appendChild(metaLeft);

            if (hasFile) {
                var downloadBtn = document.createElement("button");
                downloadBtn.type = "button";
                downloadBtn.className = "capture-card-download";
                downloadBtn.title = "Download screenshot";
                downloadBtn.innerHTML = "<i class='fa fa-download'></i>";
                downloadBtn.addEventListener("click", function(event) {
                    event.stopPropagation();
                    downloadCapture(screenshot.image_path);
                });
                meta.appendChild(downloadBtn);
            }

            card.appendChild(thumbWrap);
            card.appendChild(meta);

            return card;
        }
        function createFolderTrigger(group) {
            var captureCount = group.screenshots.length;
            var captureLabel = captureCount === 1 ? "capture" : "captures";
            var latestRaw = captureCount > 0 ? group.screenshots[0].taken_at : "";
            var latestParts = formatTakenParts(latestRaw);

            var card = document.createElement("div");
            card.className = "capture-folder-card capture-folder-trigger";
            card.setAttribute("role", "button");
            card.setAttribute("tabindex", "0");
            card.setAttribute("data-user-id", group.user_id);
            card.setAttribute("data-user-name", group.full_name || "Unknown User");
            card.setAttribute("data-user-username", group.username || "");
            card.setAttribute("data-user-avatar-url", group.profile_image_url || "");
            card.setAttribute("data-capture-count", String(captureCount));

            var icon = document.createElement("div");
            icon.className = "capture-folder-icon";
            icon.innerHTML = "<i class='fa fa-folder'></i>";

            var content = document.createElement("div");
            content.className = "capture-folder-main";

            var title = document.createElement("h3");
            title.className = "capture-folder-title";
            title.textContent = group.full_name || "Unknown User";

            var meta = document.createElement("p");
            meta.className = "capture-folder-meta";
            meta.textContent = group.username || "No email";

            var badge = document.createElement("span");
            badge.className = "capture-folder-badge";
            badge.innerHTML = "<i class='fa fa-camera'></i> " + captureCount + " " + captureLabel;

            var sub = document.createElement("p");
            sub.className = "capture-folder-sub";
            sub.textContent = latestRaw ? (latestParts.shortDate + " \u00B7 " + latestParts.time) : "No captures yet";

            var arrow = document.createElement("span");
            arrow.className = "capture-folder-arrow";
            arrow.innerHTML = "<i class='fa fa-angle-right'></i>";

            content.appendChild(title);
            content.appendChild(meta);
            content.appendChild(badge);
            content.appendChild(sub);
            card.appendChild(icon);
            card.appendChild(content);
            card.appendChild(arrow);

            return card;
        }

        function createFolderPanel(group) {
            var panel = document.createElement("div");
            panel.className = "capture-folder-panel";
            panel.setAttribute("data-user-id", group.user_id);

            var grid = document.createElement("div");
            grid.className = "capture-folder-grid" + (shotLayoutMode === "list" ? " is-list" : "");
            group.screenshots.forEach(function(screenshot) {
                grid.appendChild(createScreenshotCard(screenshot));
            });

            panel.appendChild(grid);
            return panel;
        }

        function renderFolderBrowser(grouped) {
            container.innerHTML = "";

            if (!grouped || grouped.length === 0) {
                var emptyState = document.createElement("div");
                emptyState.className = "empty-captures";
                emptyState.id = "emptyState";
                emptyState.innerHTML = "<i class='fa fa-camera'></i><h3>No screenshots found</h3><p>No screenshots match this filter yet. Try a different date or reset filters.</p>";
                container.appendChild(emptyState);
                activeUserId = null;
                setEmployeeFilterVisibility(true);
                setTopbarTitle("");
                syncPrimaryToggles();
                return;
            }

            var folderList = document.createElement("div");
            folderList.className = "capture-folder-list" + (folderLayoutMode === "list" ? " is-list" : "");
            folderList.id = "folderList";
            folderList.style.display = folderLayoutMode === "list" ? "flex" : "grid";

            grouped.forEach(function(group) {
                folderList.appendChild(createFolderTrigger(group));
            });

            var folderView = document.createElement("div");
            folderView.className = "capture-folder-view";
            folderView.id = "folderView";

            var viewHeader = document.createElement("div");
            viewHeader.className = "capture-folder-view-header";

            var backButton = document.createElement("button");
            backButton.type = "button";
            backButton.className = "back-btn";
            backButton.id = "backToFolders";
            backButton.innerHTML = "<i class='fa fa-angle-left'></i> Back";

            var avatar = document.createElement("div");
            avatar.className = "capture-folder-avatar";
            avatar.id = "activeFolderAvatar";
            avatar.textContent = "TF";

            var titleWrap = document.createElement("div");
            titleWrap.className = "capture-folder-view-main";

            var viewTitle = document.createElement("h3");
            viewTitle.className = "capture-folder-view-title";
            viewTitle.id = "activeFolderTitle";

            var viewMeta = document.createElement("p");
            viewMeta.className = "capture-folder-view-meta";
            viewMeta.id = "activeFolderMeta";

            titleWrap.appendChild(viewTitle);
            titleWrap.appendChild(viewMeta);

            var countPill = document.createElement("div");
            countPill.className = "capture-folder-count";
            countPill.id = "activeFolderCount";
            countPill.innerHTML = "<i class='fa fa-camera'></i><span>0 captures</span>";

            viewHeader.appendChild(backButton);
            viewHeader.appendChild(avatar);
            viewHeader.appendChild(titleWrap);
            viewHeader.appendChild(countPill);
            folderView.appendChild(viewHeader);

            grouped.forEach(function(group) {
                folderView.appendChild(createFolderPanel(group));
            });

            container.appendChild(folderList);
            container.appendChild(folderView);

            bindFolderEvents();
            bindViewToggles();
            setFolderLayoutMode(folderLayoutMode);
            setShotLayoutMode(shotLayoutMode);

            if (activeUserId) {
                openFolder(activeUserId);
            } else {
                setEmployeeFilterVisibility(true);
                setTopbarTitle("");
                syncPrimaryToggles();
            }
        }

        function fetchScreenshots() {
            if (isRefreshing) return;
            isRefreshing = true;

            var userId = container.getAttribute("data-user-id") || "";
            var date = container.getAttribute("data-date") || "";
            var url = "get_screenshots_api.php";
            var params = [];
            if (userId) params.push("user_id=" + encodeURIComponent(userId));
            if (date) params.push("date=" + encodeURIComponent(date));
            if (params.length > 0) url += "?" + params.join("&");

            var xhr = new XMLHttpRequest();
            xhr.open("GET", url, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    isRefreshing = false;
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.status === "success") {
                                updateScreenshots(response.screenshots);
                            }
                        } catch (e) {
                            console.error("Error parsing response:", e);
                        }
                    }
                }
            };
            xhr.send();
        }

        function updateScreenshots(screenshots) {
            updateStatsSummary(screenshots);
            var grouped = groupScreenshotsByUser(screenshots);
            renderFolderBrowser(grouped);
        }

        function groupScreenshotsByUser(screenshots) {
            var grouped = {};
            var order = [];

            screenshots.forEach(function(screenshot) {
                var key = String(screenshot.user_id || "");
                if (!grouped[key]) {
                    grouped[key] = {
                        user_id: screenshot.user_id,
                        full_name: screenshot.full_name || "Unknown User",
                        username: screenshot.username || "",
                        profile_image_url: screenshot.profile_image_url || "",
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
        bindViewToggles();
        bindFilterActions();
        setFolderLayoutMode(folderLayoutMode);
        setShotLayoutMode(shotLayoutMode);

        if (activeUserId) {
            openFolder(activeUserId);
        } else {
            setEmployeeFilterVisibility(true);
        }

        window.taskflowCaptureFetchScreenshots = fetchScreenshots;
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
