<?php
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] === "admin") {
    require_once "DB_connection.php";
    require_once "app/model/Task.php";
    require_once "app/model/Subtask.php";
    require_once "app/model/user.php";

    $text = "Tasks";
    // Filter Logic
    if (isset($_GET['due_date']) && $_GET['due_date'] === "Due Today") {
        $text = "Due Today";
        $tasks = get_all_tasks_due_today($pdo);
    } elseif (isset($_GET['due_date']) && $_GET['due_date'] === "Overdue") {
        $text = "Overdue";
        $tasks = get_all_tasks_overdue($pdo);
    } elseif (isset($_GET['due_date']) && $_GET['due_date'] === "No Deadline") {
        $text = "No Deadline";
        $tasks = get_all_tasks_NoDeadline($pdo);
    } elseif (isset($_GET['status']) && $_GET['status'] === "Pending") {
        $text = "Pending";
        $tasks = get_all_tasks_pending($pdo);
    } elseif (isset($_GET['status']) && $_GET['status'] === "in_progress") {
        $text = "In Progress";
        $tasks = get_all_tasks_in_progress($pdo);
    } elseif (isset($_GET['status']) && $_GET['status'] === "Completed") {
        $text = "Completed";
        $tasks = get_all_tasks_completed($pdo);
    } else {
        $tasks = get_all_tasks($pdo);
    }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tasks | TaskFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/task_redesign.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- Ensure jQuery -->
    <script>
        // Ensure global modal helpers exist even if later PHP errors truncate output.
        if (typeof window.openTaskModal !== "function") {
            window.openTaskModal = function(taskId) {
                var modal = document.getElementById("modal-task-" + taskId);
                if (modal) {
                    modal.style.display = "flex";
                    document.body.style.overflow = "hidden";
                }
            };
        }
        if (typeof window.closeTaskModal !== "function") {
            window.closeTaskModal = function(taskId) {
                var modal = document.getElementById("modal-task-" + taskId);
                if (modal) {
                    modal.style.display = "none";
                    document.body.style.overflow = "auto";
                }
            };
        }
        // Vanilla auto-open fallback (works even if jQuery fails to load).
        document.addEventListener("DOMContentLoaded", function () {
            var params = new URLSearchParams(window.location.search);
            var openTaskId = params.get("open_task");
            if (openTaskId && typeof window.openTaskModal === "function") {
                window.openTaskModal(openTaskId);
                var el = document.getElementById("task-card-" + openTaskId);
                if (el) {
                    el.scrollIntoView({ behavior: "smooth", block: "center" });
                }
            }
        });
    </script>
    <style>
        .tasks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }
        
        .task-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            border: 1px solid #E5E7EB;
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .task-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transform: translateY(-2px);
        }

        .task-title {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
            margin: 0 0 8px 0;
            line-height: 1.4;
        }

        .leader-box-preview {
            background: #F5F3FF;
            border: 1px solid #E0E7FF;
            border-radius: 8px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            width: fit-content;
            min-width: 200px;
        }

        .task-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #F3F4F6;
            color: #6B7280;
            font-size: 13px;
        }
        
        /* Ensure Modals appear on top */
        .modal-overlay {
            z-index: 2000 !important; /* Force higher z-index */
        }
        /* Modal Overlay for Action Modals */
        .modal-background {
            background: rgba(0,0,0,0.5);
        }

        /* Missing Modal Internal Styles */
        .modal-header-section {
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #E5E7EB;
        }

        .section-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #9CA3AF;
            margin-bottom: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .info-box {
            background: #F9FAFB;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #F3F4F6;
        }
        /* Leader Box Style */
        .leader-box {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #F5F3FF;
            border: 1px solid #E0E7FF;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        /* Fix modal content width that might have been overridden by task_redesign.css .modal-box */
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90% !important;
            max-width: 900px !important;
            max-height: 90vh;
            overflow-y: auto;
            padding: 32px;
            position: relative;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
         .close-modal {
            position: absolute;
            top: 24px;
            right: 24px;
            border: none;
            background: none;
            cursor: pointer;
            color: #6B7280;
            font-size: 20px;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.2s;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .tasks-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px !important;
            }
            
            .task-card {
                padding: 12px !important;
                border-radius: 8px !important;
            }
            
            .task-title {
                font-size: 13px !important;
                margin-bottom: 4px !important;
                line-height: 1.3 !important;
            }
            
            /* Compact Badge */
            .badge-v2 {
                font-size: 9px !important;
                padding: 2px 6px !important;
            }
            
            .preview-content div[style*="font-size: 14px"] {
                font-size: 11px !important;
                margin-bottom: 10px !important;
                line-height: 1.3 !important;
                height: 2.6em; /* Limit height roughly 2 lines */
                overflow: hidden;
            }
            
            .leader-box-preview {
                min-width: unset !important;
                width: 100% !important;
                padding: 6px !important;
                gap: 8px !important;
                margin-bottom: 8px !important;
            }
            
            .leader-box-preview img {
                width: 24px !important;
                height: 24px !important;
            }
            
            .leader-box-preview div:nth-child(2) div:first-child {
                font-size: 8px !important;
            }
            
            .leader-box-preview div:nth-child(2) div:last-child {
                font-size: 11px !important;
            }
            
            .task-footer {
                margin-top: 10px !important;
                padding-top: 10px !important;
                font-size: 10px !important;
            }
            
             .task-footer i {
                font-size: 10px !important;
             }
        }
    </style>
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        
        <div style="margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 24px; font-weight: 700; color: #111827; margin: 0;"><?= $text ?></h2>
            
            <a href="create_task.php" style="background: #4F46E5; color: white; padding: 10px 24px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);">
                <i class="fa fa-plus"></i> Create Task
            </a>
        </div>

        <?php if (isset($_GET['success'])) {?>
            <div style="background: #ECFDF5; color: #065F46; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; border: 1px solid #A7F3D0;">
                <i class="fa fa-check-circle" style="margin-right: 8px;"></i> <?php echo stripcslashes($_GET['success']); ?>
            </div>
        <?php } ?>
        <?php if (isset($_GET['error'])) {?>
            <div style="background: #FEF2F2; color: #991B1B; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; border: 1px solid #FECACA;">
                <i class="fa fa-exclamation-circle" style="margin-right: 8px;"></i> <?php echo stripcslashes($_GET['error']); ?>
            </div>
        <?php } ?>
        
        <div class="tasks-grid">
            <?php if (!empty($tasks)) { 
                foreach ($tasks as $task) { 
                    $badgeClass = "badge-v2 pending";
                    $statusDisplay = str_replace('_',' ',$task['status']);
                    if ($task['status'] == 'in_progress') $badgeClass = "badge-v2 in_progress";
                    if ($task['status'] == 'completed') $badgeClass = "badge-v2 completed";
                    if ($task['status'] == 'completed' && ($task['rating'] == 0 || $task['rating'] == NULL)) {
                         $statusDisplay = "submitted for review"; 
                         $badgeClass = "badge-v2 submitted"; 
                    }

                    // Organize Assignees
                    $assignees = get_task_assignees($pdo, $task['id']);
                    $leader = null;
                    $members = [];
                    if ($assignees != 0) {
                        foreach ($assignees as $a) {
                            if ($a['role'] == 'leader') {
                                $leader = $a;
                            } else {
                                $members[] = $a;
                            }
                        }
                    }
            ?>
            <!-- Task Card -->
            <div class="task-card" id="task-card-<?=$task['id']?>" onclick="openTaskModal(<?=$task['id']?>)">
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                     <h3 class="task-title"><?= htmlspecialchars($task['title']) ?></h3>
                     <!-- Delete Button (Replaces Edit) -->
                     <object>
                        <button onclick="openDeleteModal(event, <?=$task['id']?>, '<?=htmlspecialchars($task['title'], ENT_QUOTES)?>')" style="border: none; background: none; cursor: pointer; color: #EF4444; padding: 0;">
                            <i class="fa fa-trash"></i>
                        </button>
                     </object>
                </div>
                <div style="margin-bottom: 16px;">
                    <span class="<?= $badgeClass ?>"><?= $statusDisplay ?></span>
                </div>
                <div class="preview-content">
                    <div style="font-size: 14px; color: #6B7280; margin-bottom: 20px; line-height: 1.5;">
                        <?= htmlspecialchars(mb_strimwidth($task['description'], 0, 100, "...")) ?>
                    </div>
                    <?php if ($leader) { 
                        $leaderImg = !empty($leader['profile_image']) ? 'uploads/' . $leader['profile_image'] : 'img/user.png';
                    ?>
                    <div class="leader-box-preview">
                        <img src="<?= $leaderImg ?>" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                        <div>
                            <div style="font-size: 10px; font-weight: 700; color: #6366F1; letter-spacing: 0.5px; text-transform: uppercase;">
                                <i class="fa fa-crown"></i> Leader
                            </div>
                            <div style="font-weight: 600; color: #1F2937; font-size: 13px;">
                                <?= htmlspecialchars($leader['full_name']) ?>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    <?php if (!empty($members)) { ?>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-users" style="color: #059669; font-size: 12px;"></i>
                        <div style="font-size: 12px; font-weight: 600; color: #059669;">Team Members</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 6px;">
                        <div style="display: flex; padding-left: 8px;">
                            <?php foreach (array_slice($members, 0, 4) as $m) { 
                                $mImg = !empty($m['profile_image']) ? 'uploads/' . $m['profile_image'] : 'img/user.png';
                            ?>
                            <img src="<?= $mImg ?>" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid white; margin-left: -8px; object-fit: cover; background: #E5E7EB;">
                            <?php } ?>
                        </div>
                        <span style="font-size: 12px; color: #6B7280;"><?= count($members) ?> member<?= count($members)>1?'s':''?></span>
                    </div>
                    <?php } ?>
                </div>
                <!-- Footer -->
                <div class="task-footer">
                    <div>Due: <?= empty($task['due_date']) ? 'No Date' : date("M j", strtotime($task['due_date'])) ?></div>
                    <?php if ($leader) { 
                        $lStats = get_user_rating_stats($pdo, $leader['user_id']);
                        if($lStats['avg'] > 0) {
                    ?>
                    <div style="color: #F59E0B; font-weight: 600;"><i class="fa fa-star"></i> <?= $lStats['avg'] ?>/5</div>
                    <?php } } ?>
                </div>
            </div>
            <?php } 
            } else { ?>
                 <div style="grid-column: 1/-1; padding: 40px; text-align: center; color: #6B7280;">
                    <i class="fa fa-folder-open-o" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h3>No tasks found</h3>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- MODALS GENERATED OUTSIDE GRID for Layout Safety -->
    <?php if (!empty($tasks)) { 
        foreach ($tasks as $task) { 
            // Re-calculate necessary variables for Modal
            $badgeClass = "badge-v2 pending";
            $statusDisplay = str_replace('_',' ',$task['status']);
            if ($task['status'] == 'in_progress') $badgeClass = "badge-v2 in_progress";
            if ($task['status'] == 'completed') $badgeClass = "badge-v2 completed";
            $isAwaitingReview = false;
            if ($task['status'] == 'completed' && ($task['rating'] == 0 || $task['rating'] == NULL)) {
                    $statusDisplay = "submitted for review"; 
                    $badgeClass = "badge-v2 submitted"; 
                    $isAwaitingReview = true;
            }
            $submissionNote = isset($task['submission_note']) ? $task['submission_note'] : null;
            $assignees = get_task_assignees($pdo, $task['id']);
            $leader = null;
            $members = [];
            if ($assignees != 0) {
                foreach ($assignees as $a) {
                    if ($a['role'] == 'leader') $leader = $a;
                    else $members[] = $a;
                }
            }
            $subtasks = [];
            try { $subtasks = get_subtasks_by_task($pdo, $task['id']); } catch (Throwable $e) {}
    ?>
    <div class="modal-overlay" id="modal-task-<?=$task['id']?>" onclick="if(event.target === this) closeTaskModal(<?=$task['id']?>)">
        <div class="modal-content">
            <button class="close-modal" onclick="closeTaskModal(<?=$task['id']?>)"><i class="fa fa-times"></i></button>

            <!-- Header Section -->
                <div class="modal-header-section">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <h2 style="margin: 0; font-size: 20px; color: #111827;"><?= htmlspecialchars($task['title']) ?></h2>
                </div>
                <span class="<?= $badgeClass ?>" style="font-size: 12px;"><?= $statusDisplay ?></span>
                </div>

            <div style="margin-bottom: 24px;">
                <div class="section-label">Description</div>
                <div style="color: #374151; font-size: 14px; line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($task['description'])) ?>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-box">
                    <div class="section-label"><i class="fa fa-calendar"></i> Due Date</div>
                    <div style="font-weight: 500; font-size: 14px;"><?= empty($task['due_date']) ? 'No Date' : date("M d, Y", strtotime($task['due_date'])) ?></div>
                </div>
                <div class="info-box">
                    <div class="section-label"><i class="fa fa-clock-o"></i> Created</div>
                    <div style="font-weight: 500; font-size: 14px;"><?= isset($task['created_at']) ? date("M d, Y", strtotime($task['created_at'])) : 'Unknown' ?></div>
                </div>
            </div>

            <?php if ($task['status'] == 'completed' && isset($task['rating']) && $task['rating'] > 0) { ?>
            <div class="rating-feedback-box">
                <div class="rating-header">
                    <i class="fa fa-star"></i> <?= $task['rating'] ?>/5
                </div>
                <div class="rating-feedback-text">
                    <?= !empty($task['review_comment']) ? htmlspecialchars($task['review_comment']) : 'No feedback provided.' ?>
                </div>
            </div>
            <?php } ?>

            <!-- Admin Review Sections -->
            <?php if (!empty($submissionNote)) { ?>
                <div class="admin-review-section">
                        <div class="admin-review-header">
                        <i class="fa fa-paper-plane admin-review-icon"></i>
                        <span class="admin-review-title">Submitted for Admin Review</span>
                    </div>
                    <div class="admin-review-text">
                        <?= htmlspecialchars($submissionNote) ?>
                        <div style="margin-top: 6px; font-size: 12px; color: #60A5FA;">
                            Submitted: <?= isset($task['reviewed_at']) ? date("F j, Y, g:i A", strtotime($task['reviewed_at'])) : 'Recently' ?>
                        </div>
                        <?php if (!empty($task['submission_file'])) { ?>
                            <div style="margin-top: 10px;">
                                <a href="<?= htmlspecialchars($task['submission_file']) ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; background: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; color: #2563EB; font-weight: 500; font-size: 13px; border: 1px solid #BFDBFE;">
                                    <i class="fa fa-paperclip"></i> View Attached File
                                </a>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
            <?php if ($isAwaitingReview) { ?>
                <div class="awaiting-review-section">
                    <div class="awaiting-review-title">
                        <i class="fa fa-exclamation-circle"></i> Awaiting Admin Review
                    </div>
                    <div class="leader-notes-box">
                        <strong>Leader's Notes:</strong><br>
                        <?= !empty($submissionNote) ? htmlspecialchars($submissionNote) : "No notes provided." ?>
                    </div>
                </div>
            <?php } ?>

            <!-- People -->
            <?php if ($leader) { 
                $leaderImg = !empty($leader['profile_image']) ? 'uploads/' . $leader['profile_image'] : 'img/user.png';
            ?>
            <div class="section-label">Project Leader</div>
            <div class="leader-box">
                    <img src="<?= $leaderImg ?>" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                    <div style="flex: 1;">
                    <div style="font-size: 10px; font-weight: 700; color: #6366F1; letter-spacing: 0.5px; text-transform: uppercase;">
                        <i class="fa fa-crown" style="margin-right: 4px;"></i> Project Leader
                    </div>
                    <div style="font-weight: 600; color: #1F2937; font-size: 14px; margin-top: 4px; border-bottom: 1px solid #E0E7FF; padding-bottom: 4px; margin-bottom: 4px;">
                        <?= htmlspecialchars($leader['full_name']) ?>
                    </div>
                    <div style="font-size: 11px; color: #6B7280; display: flex; gap: 10px;">
                        <?php $lStats = get_user_rating_stats($pdo, $leader['user_id']); ?>
                        <span><i class="fa fa-star" style="color:#F59E0B"></i> <?= $lStats['avg'] ?>/5</span>
                        <?php $lCollab = get_collaborative_scores_by_user($pdo, $leader['user_id']); ?>
                        <span style="color: #8B5CF6;"><i class="fa fa-users"></i> Collab: <?= $lCollab['avg'] ?>/5</span>
                    </div>
                    </div>
            </div>
            <?php } ?>
            
            <?php if (!empty($members)) { ?>
                <div class="section-label">Team Members (<?= count($members) ?>)</div>
                <div style="background: #F0FDFA; border: 1px solid #CCFBF1; border-radius: 8px; padding: 12px; margin-bottom: 24px;">
                    <?php foreach ($members as $member) { 
                            $memImg = !empty($member['profile_image']) ? 'uploads/' . $member['profile_image'] : 'img/user.png';
                    ?>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; last-child: margin-bottom: 0;">
                            <img src="<?= $memImg ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                            <div>
                            <div style="font-weight: 500; font-size: 13px; color: #1F2937;"><?= htmlspecialchars($member['full_name']) ?></div>
                             <div style="font-size: 11px; color: #6B7280; display: flex; gap: 10px;">
                                <?php $mStats = get_user_rating_stats($pdo, $member['user_id']); ?>
                                <span><i class="fa fa-star" style="color:#F59E0B"></i> <?= $mStats['avg'] ?>/5</span>
                                 <?php $mCollab = get_collaborative_scores_by_user($pdo, $member['user_id']); ?>
                                <span style="color: #8B5CF6;"><i class="fa fa-users"></i> Collab: <?= $mCollab['avg'] ?>/5</span>
                            </div>
                            </div>
                    </div>
                    <?php } ?>
                </div>
            <?php } ?>

            <!-- Subtasks Accordion -->
            <div class="subtasks-section">
                    <div class="subtasks-header" onclick="$('#subtaskList-<?=$task['id']?>').slideToggle();" style="cursor: pointer;">
                    <button type="button" class="btn-v2 btn-white" style="width: 100%; justify-content: space-between;">
                        <span><i class="fa fa-chevron-down"></i> View Subtasks (<?= !empty($subtasks) ? count($subtasks) : 0 ?>)</span>
                    </button>
                </div>
                <div id="subtaskList-<?=$task['id']?>" style="display: none; margin-top: 12px;">
                        <?php if (!empty($subtasks)) { foreach($subtasks as $sub) { 
                            $sClass = "pending";
                            if($sub['status']=='completed') $sClass="completed";
                            if($sub['status']=='in_progress') $sClass="in_progress";
                            if($sub['status']=='submitted') $sClass="submitted";
                            if($sub['status']=='revise') $sClass="revision_needed";
                            if($sub['status']=='rejected') $sClass="rejected";
                    ?>
                    <div class="subtask-card" style="padding: 12px; margin-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="font-weight: 600; font-size: 13px; color: #1F2937;"><?= htmlspecialchars($sub['description']) ?></span>
                            <span class="badge-v2 <?=$sClass?>" style="font-size: 10px;"><?= str_replace('_',' ', $sub['status']) ?></span>
                        </div>
                        <div style="font-size: 12px; color: #6B7280; display: flex; justify-content: space-between;">
                            <span>Assigned: <?= htmlspecialchars($sub['member_name']) ?></span>
                            <?php if($sub['score']) { echo "<span style='color:#F59E0B'><i class='fa fa-star'></i> ".$sub['score']."/5</span>"; } ?>
                        </div>
                        <?php if(!empty($sub['submission_file'])) { ?>
                            <div style="font-size: 12px; margin-top: 5px;">
                                <a href="<?=$sub['submission_file']?>" target="_blank" style="color: #4F46E5;">View File</a>
                            </div>
                        <?php } ?>
                    </div>
                    <?php }} else { echo "<div style='color: #9CA3AF; font-size: 13px; padding: 10px;'>No subtasks.</div>"; } ?>
                </div>
            </div>

            <!-- Admin Actions -->
            <?php if ($isAwaitingReview || ($task['status'] == 'completed')) { ?>
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 30px; border-top: 1px solid #E5E7EB; padding-top: 20px;">
                        <button class="btn-v2 btn-yellow" onclick="openRevisionDialog(<?=$task['id']?>, `<?=htmlspecialchars($task['title'])?>`, <?=count($subtasks)?>)">
                        <i class="fa fa-refresh"></i> Request Revision
                    </button>
                    <button class="btn-v2 btn-green" onclick="openAcceptDialog(<?=$task['id']?>, `<?=htmlspecialchars($task['title'])?>`, <?=count($subtasks)?>)">
                        <i class="fa fa-check"></i> Accept & Rate
                    </button>
                </div>
            <?php } ?>

        </div>
    </div>
    <?php } } ?>

    <!-- SHARED ACTION MODALS -->
    
    <!-- Accept & Rate Modal -->
    <div id="acceptModal" class="modal-overlay" style="display: none; z-index: 2200 !important;">
        <div class="modal-box">
            <h3 style="margin-top: 0; font-size: 18px; color: #111827;">Accept & Rate Task</h3>
            
            <div style="background: #F3F4F6; padding: 10px; border-radius: 6px; margin: 15px 0; font-size: 14px; font-weight: 500;">
                <span id="acceptTaskTitle">Task Title</span>
                <div style="font-size: 12px; color: #6B7280; font-weight: 400; margin-top: 4px;">
                     <span id="acceptSubtaskCount">0</span> completed subtasks
                </div>
            </div>

            <form action="app/admin-review-task.php" method="POST">
                <input type="hidden" name="task_id" id="acceptTaskId">
                <input type="hidden" name="action" value="accept">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Performance Rating</label>
                    <div class="rating-input" id="ratingStars">
                        <i class="fa fa-star" data-value="1"></i>
                        <i class="fa fa-star" data-value="2"></i>
                        <i class="fa fa-star" data-value="3"></i>
                        <i class="fa fa-star" data-value="4"></i>
                        <i class="fa fa-star" data-value="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingValue" value="0">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Feedback (Optional)</label>
                    <textarea name="feedback" class="form-input-v2" rows="3" placeholder="Add your feedback about the completed task..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-v2 btn-white" onclick="closeActionModal('acceptModal')">Cancel</button>
                    <button type="submit" class="btn-v2 btn-green">Accept & Rate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Request Revision Modal -->
    <div id="revisionModal" class="modal-overlay" style="display: none; z-index: 2200 !important;">
         <div class="modal-box">
            <h3 style="margin-top: 0; font-size: 18px; color: #111827;">Request Revision</h3>
            
            <div style="background: #F3F4F6; padding: 10px; border-radius: 6px; margin: 15px 0; font-size: 14px; font-weight: 500;">
                <span id="reviseTaskTitle">Task Title</span>
                <div style="font-size: 12px; color: #6B7280; font-weight: 400; margin-top: 4px;">
                     <span id="reviseSubtaskCount">0</span> completed subtasks
                </div>
            </div>

            <form action="app/admin-review-task.php" method="POST">
                <input type="hidden" name="task_id" id="reviseTaskId">
                <input type="hidden" name="action" value="revise">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px;">Revision Notes</label>
                    <textarea name="feedback" class="form-input-v2" rows="3" placeholder="Explain what needs to be revised..." required></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-v2 btn-white" onclick="closeActionModal('revisionModal')">Cancel</button>
                    <button type="submit" class="btn-v2 btn-yellow">Request Revision</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Task Modal -->
    <div id="deleteTaskModal" class="modal-overlay" style="display: none; z-index: 2300 !important;">
        <div class="modal-box">
            <div style="text-align: center;">
                <i class="fa fa-exclamation-triangle" style="font-size: 48px; color: #EF4444; margin-bottom: 15px;"></i>
                <h3 style="margin: 0; font-size: 20px; color: #111827;">Delete Task?</h3>
                <p style="color: #6B7280; font-size: 14px; margin: 10px 0 20px;">
                    Are you sure you want to delete <span id="deleteTaskTitle" style="font-weight: 600; color: #111827;"></span>? 
                    <br>This action cannot be undone.
                </p>
                <form action="app/delete-task.php" method="POST">
                    <input type="hidden" name="id" id="deleteTaskId">
                    <div style="display: flex; justify-content: center; gap: 10px;">
                        <button type="button" class="btn-v2 btn-white" onclick="closeDeleteModal()">Cancel</button>
                        <button type="submit" class="btn-v2 btn-red" style="background: #EF4444;">Delete Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openTaskModal(taskId) {
            var modal = document.getElementById("modal-task-" + taskId);
            if(modal) {
                modal.style.display = "flex";
                document.body.style.overflow = "hidden"; // Prevent scrolling
            }
        }

        function closeTaskModal(taskId) {
            var modal = document.getElementById("modal-task-" + taskId);
            if(modal) {
                modal.style.display = "none";
                document.body.style.overflow = "auto";
            }
        }

        // Action Modal Functions
        function openAcceptDialog(id, title, subCount) {
            $("#acceptTaskId").val(id);
            $("#acceptTaskTitle").text(title);
            $("#acceptSubtaskCount").text(subCount);
            $("#acceptModal").css("display", "flex").hide().fadeIn(200);
        }

        function openRevisionDialog(id, title, subCount) {
            $("#reviseTaskId").val(id);
            $("#reviseTaskTitle").text(title);
            $("#reviseSubtaskCount").text(subCount);
            $("#revisionModal").css("display", "flex").hide().fadeIn(200);
        }

        function closeActionModal(id) {
            $("#" + id).fadeOut(200);
        }

        // Delete Modal Functions
        function openDeleteModal(event, id, title) {
            event.stopPropagation(); // Prevent opening task details modal
            $("#deleteTaskId").val(id);
            $("#deleteTaskTitle").text(title);
            $("#deleteTaskModal").css("display", "flex").hide().fadeIn(200);
        }

        function closeDeleteModal() {
            $("#deleteTaskModal").fadeOut(200);
        }

         // Rating Star Logic
        $(".rating-input i").hover(function() {
            let val = $(this).data('value');
            highlightStars(val);
        }, function() {
            let current = $("#ratingValue").val();
            highlightStars(current);
        });

        $(".rating-input i").click(function() {
            let val = $(this).data('value');
            $("#ratingValue").val(val);
            highlightStars(val);
        });

        function highlightStars(val) {
            $(".rating-input i").each(function() {
                if ($(this).data('value') <= val) {
                    $(this).addClass('active');
                    $(this).css('color', '#F59E0B');
                } else {
                    $(this).removeClass('active');
                    $(this).css('color', '#D1D5DB');
                }
            });
        }
    </script>
    <script>
        $(document).ready(function() {
            const urlParams = new URLSearchParams(window.location.search);
            const openTaskId = urlParams.get('open_task');
            if (openTaskId) {
                // Assuming openTaskModal or similar exists, otherwise use toggleTask logic
                if (typeof openTaskModal === "function") {
                    openTaskModal(openTaskId);
                } else if (typeof toggleTask === "function") {
                    toggleTask(openTaskId);
                }
                
                // Scroll to task
                const element = document.getElementById("task-card-" + openTaskId);
                if (element) {
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    </script>
</body>
</html>
<?php 
} else {
    header("Location: login.php?error=First login");
    exit();
}
?>


