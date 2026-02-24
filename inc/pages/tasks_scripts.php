<script>
    const taskLeaderMap = <?= json_encode($taskLeaderMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

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

        $("#ratingValue").val(0);
        highlightTaskStars(0);

        const leader = taskLeaderMap[String(id)] || null;
        if (leader && leader.user_id) {
            $("#acceptLeaderName").text(leader.full_name);
            $("#leaderRatingBlock").show();
            $("#leaderRatingValue").val(0);
            highlightLeaderStars(0);
        } else {
            $("#acceptLeaderName").text("N/A");
            $("#leaderRatingBlock").hide();
            $("#leaderRatingValue").val(0);
        }

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

    function openValidationModal(message) {
        $("#validationErrorText").text(message);
        $("#validationErrorModal").css("display", "flex").hide().fadeIn(200);
    }

    function closeValidationModal() {
        $("#validationErrorModal").fadeOut(200);
    }

    // Task rating stars
    $(".task-rating-input i").hover(function() {
        const val = $(this).data('value');
        highlightTaskStars(val);
    }, function() {
        const current = $("#ratingValue").val();
        highlightTaskStars(current);
    });

    $(".task-rating-input i").click(function() {
        const val = $(this).data('value');
        $("#ratingValue").val(val);
        highlightTaskStars(val);
    });

    function highlightTaskStars(val) {
        $(".task-rating-input i").each(function() {
            if ($(this).data('value') <= val) {
                $(this).addClass('active').css('color', '#F59E0B');
            } else {
                $(this).removeClass('active').css('color', '#D1D5DB');
            }
        });
    }

    // Leader rating stars
    $(".leader-rating-input i").hover(function() {
        const val = $(this).data('value');
        highlightLeaderStars(val);
    }, function() {
        const current = $("#leaderRatingValue").val();
        highlightLeaderStars(current);
    });

    $(".leader-rating-input i").click(function() {
        const val = $(this).data('value');
        $("#leaderRatingValue").val(val);
        highlightLeaderStars(val);
    });

    function highlightLeaderStars(val) {
        $(".leader-rating-input i").each(function() {
            if ($(this).data('value') <= val) {
                $(this).addClass('active').css('color', '#F59E0B');
            } else {
                $(this).removeClass('active').css('color', '#D1D5DB');
            }
        });
    }

    $("#acceptModal form").on("submit", function(e) {
        const taskRating = parseInt($("#ratingValue").val(), 10) || 0;
        if (taskRating < 1 || taskRating > 5) {
            e.preventDefault();
            openValidationModal("Please provide a task rating.");
            return;
        }

        const leaderBlockVisible = $("#leaderRatingBlock").is(":visible");
        if (leaderBlockVisible) {
            const leaderRating = parseInt($("#leaderRatingValue").val(), 10) || 0;
            if (leaderRating < 1 || leaderRating > 5) {
                e.preventDefault();
                openValidationModal("Please provide a leader rating.");
                return;
            }
        }
    });
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
