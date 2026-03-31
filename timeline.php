<?php
session_start();
if (!isset($_SESSION['role'], $_SESSION['id'])) {
    header("Location: login.php?error=First login");
    exit();
}

include "DB_connection.php";
require_once "inc/csrf.php";

$timelineCsrfToken = csrf_token('timeline_action');
$timelineUserRole = (string)($_SESSION['role'] ?? '');
$timelineUserId = (int)($_SESSION['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timeline | TaskFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/timeline-page.css">
</head>
<body class="timeline-page">
    <?php include "inc/new_sidebar.php"; ?>

    <div class="dash-main">
        <div
            class="tlp-shell"
            id="tlpApp"
            data-role="<?= htmlspecialchars($timelineUserRole, ENT_QUOTES) ?>"
            data-user-id="<?= (int)$timelineUserId ?>"
            data-csrf="<?= htmlspecialchars($timelineCsrfToken, ENT_QUOTES) ?>"
        >
            <div class="tlp-loading" id="tlpLoading">Loading timeline data...</div>
            <div class="tlp-alert" id="tlpAlert"></div>

            <section class="tlp-screen tlp-admin-overview active" id="tlpAdminOverview">
                <div class="tlp-stats-grid" id="tlpStatsGrid"></div>

                <div class="tlp-filter-row">
                    <div class="tlp-filter-tabs">
                        <button type="button" class="tlp-filter-btn active" data-filter="all">All</button>
                        <button type="button" class="tlp-filter-btn" data-filter="ongoing">Ongoing</button>
                        <button type="button" class="tlp-filter-btn" data-filter="planning">Planning</button>
                        <button type="button" class="tlp-filter-btn" data-filter="completed">Completed</button>
                    </div>
                    <div class="tlp-search-wrap">
                        <i class="fa fa-search"></i>
                        <input
                            type="text"
                            class="tlp-search-input"
                            id="tlpSearchInput"
                            placeholder="Search projects, groups, leaders..."
                        >
                    </div>
                </div>

                <div class="tlp-tile-grid" id="tlpTileGrid"></div>
                <div class="tlp-empty" id="tlpAdminEmpty">
                    <i class="fa fa-folder-open-o"></i>
                    <strong>No timelines found</strong>
                    <span>Try a different search or filter.</span>
                </div>
            </section>

            <section class="tlp-screen tlp-admin-detail" id="tlpAdminDetail">
                <div class="tlp-detail-head">
                    <button type="button" class="tlp-back-btn" id="tlpBackBtn">
                        <i class="fa fa-arrow-left"></i> All Timelines
                    </button>
                    <div class="tlp-divider"></div>
                    <div class="tlp-detail-meta">
                        <div class="tlp-avatar" id="tlpDetailAvatar">TL</div>
                        <div>
                            <div class="tlp-meta-name" id="tlpDetailName"></div>
                            <div class="tlp-meta-sub" id="tlpDetailSub"></div>
                        </div>
                    </div>
                    <div class="tlp-right-meta">
                        <span class="tlp-status" id="tlpDetailStatus"></span>
                        <div class="tlp-member-stack" id="tlpDetailMembers"></div>
                        <span class="tlp-progress-chip" id="tlpDetailProgress"></span>
                    </div>
                </div>

                <div id="tlpAdminGanttMount"></div>
            </section>

            <section class="tlp-screen" id="tlpEmployeeView">
                <div class="tlp-project-switch" id="tlpProjectSwitch"></div>
                <div id="tlpEmployeeMount"></div>
            </section>
        </div>
    </div>

    <div class="tlp-modal-wrap" id="tlpTaskModalWrap">
        <div class="tlp-modal">
            <h3 id="tlpTaskModalTitle"><i class="fa fa-tasks"></i> Add Timeline Task</h3>
            <div class="tlp-form-group">
                <label class="tlp-form-label">Task Title *</label>
                <input type="text" class="tlp-input" id="tlpTaskNameInput" placeholder="e.g. Frontend Development">
            </div>
            <div class="tlp-form-group">
                <label class="tlp-form-label">Assignee</label>
                <select class="tlp-select" id="tlpTaskAssigneeSelect"></select>
            </div>
            <div class="tlp-modal-actions">
                <button type="button" class="tlp-btn cancel" id="tlpTaskCancelBtn">Cancel</button>
                <button type="button" class="tlp-btn primary" id="tlpTaskSaveBtn">Add Task</button>
            </div>
        </div>
    </div>

    <div class="tlp-modal-wrap" id="tlpPhaseModalWrap">
        <div class="tlp-modal">
            <h3 id="tlpPhaseModalTitle"><i class="fa fa-calendar"></i> Add Phase</h3>
            <div class="tlp-form-group">
                <label class="tlp-form-label">Phase Name *</label>
                <input type="text" class="tlp-input" id="tlpPhaseNameInput" placeholder="e.g. Setup and Planning">
            </div>
            <div class="tlp-form-group">
                <label class="tlp-form-label">Description</label>
                <textarea class="tlp-textarea" id="tlpPhaseDescInput" placeholder="Describe this phase"></textarea>
            </div>
            <div class="tlp-form-group">
                <label class="tlp-form-label">Phase Type</label>
                <select class="tlp-select" id="tlpPhaseTypeInput">
                    <option value="standard">Standard Work</option>
                    <option value="document">Document in Google Docs</option>
                    <option value="sheet">Spreadsheet in Google Sheets</option>
                    <option value="slides">Presentation in Google Slides</option>
                </select>
            </div>
            <div class="tlp-form-row">
                <div class="tlp-form-group">
                    <label class="tlp-form-label">Start Day</label>
                    <input type="number" min="1" max="365" class="tlp-input" id="tlpPhaseStartInput" value="1">
                </div>
                <div class="tlp-form-group">
                    <label class="tlp-form-label">Duration (Days)</label>
                    <input type="number" min="1" max="180" class="tlp-input" id="tlpPhaseDurationInput" value="3">
                </div>
            </div>
            <div class="tlp-form-group tlp-phase-guide">
                <div class="tlp-phase-guide-head">
                    <label class="tlp-form-label">Existing Phase Days</label>
                    <div class="tlp-phase-guide-meta">
                        <span class="tlp-phase-guide-count" id="tlpPhaseGuideCount">0 phases</span>
                        <span class="tlp-phase-guide-limit" id="tlpPhaseGuideLimit">Day 1-365</span>
                    </div>
                </div>
                <div class="tlp-phase-guide-list" id="tlpPhaseGuideList"></div>
                <div class="tlp-phase-guide-tip" id="tlpPhaseGuideTip"></div>
            </div>
            <div class="tlp-form-group">
                <label class="tlp-form-label">Icon</label>
                <div class="tlp-icon-grid" id="tlpIconGrid"></div>
            </div>
            <div class="tlp-form-group">
                <label class="tlp-form-label">Color</label>
                <div class="tlp-color-grid" id="tlpColorGrid"></div>
            </div>
            <div class="tlp-modal-actions">
                <button type="button" class="tlp-btn cancel" id="tlpPhaseCancelBtn">Cancel</button>
                <button type="button" class="tlp-btn primary" id="tlpPhaseSaveBtn">Save Phase</button>
            </div>
        </div>
    </div>

    <div class="tlp-modal-wrap" id="tlpConfirmModalWrap">
        <div class="tlp-modal tlp-confirm-modal">
            <h3 id="tlpConfirmTitle"><i class="fa fa-exclamation-triangle"></i> Confirm Delete</h3>
            <p class="tlp-confirm-message" id="tlpConfirmMessage">Are you sure you want to continue?</p>
            <div class="tlp-modal-actions">
                <button type="button" class="tlp-btn cancel" id="tlpConfirmCancelBtn">Cancel</button>
                <button type="button" class="tlp-btn danger" id="tlpConfirmBtn">Delete</button>
            </div>
        </div>
    </div>

    <div class="tlp-tooltip" id="tlpTooltip">
        <div class="tlp-tooltip-title" id="tlpTooltipTitle"></div>
        <div class="tlp-tooltip-desc" id="tlpTooltipDesc"></div>
        <div class="tlp-tooltip-days" id="tlpTooltipDays"></div>
    </div>

    <script src="app/timeline/timeline-page.js?v=<?= urlencode((string)(@filemtime(__DIR__ . '/app/timeline/timeline-page.js') ?: '1')) ?>"></script>
</body>
</html>
