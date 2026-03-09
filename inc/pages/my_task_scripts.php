<script>
    function openTaskModal(taskId) {
        var modal = document.getElementById("modal-task-" + taskId);
        if(modal) {
            modal.style.display = "flex";
            document.body.style.overflow = "hidden";
        }
    }

    function closeTaskModal(taskId) {
        var modal = document.getElementById("modal-task-" + taskId);
        if(modal) {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
        }
    }

    function openTaskSubmissionModal(taskId) {
        $("#modal_task_id").val(taskId);
        // Fix: Force flex display for centering, avoid jquery fadeIn default block
        $("#taskSubmissionModal").css("display", "flex").hide().fadeIn(200);
    }

    function closeTaskSubmissionModal() {
        $("#taskSubmissionModal").fadeOut(200);
    }

    function openResubmitModal(taskId, feedback) {
        $("#resubmit_task_id").val(taskId);
        $("#resubmitFeedback").text(feedback);
        $("#resubmitModal").css("display", "flex").hide().fadeIn(200);
    }

    function closeResubmitModal() {
        $("#resubmitModal").fadeOut(200);
    }

    function openValidationModal(message) {
        $("#validationErrorText").text(message);
        $("#validationErrorModal").css("display", "flex").hide().fadeIn(200);
    }

    function closeValidationModal() {
        $("#validationErrorModal").fadeOut(200);
    }

    // Auto-open task if param exists
    $(document).ready(function() {
        const urlParams = new URLSearchParams(window.location.search);
        const openTaskId = urlParams.get('open_task');

        if (openTaskId) {
            // Remove the param from URL without reload (optional but cleaner)
            // window.history.replaceState(null, null, window.location.pathname); 

            // Open task modal
            openTaskModal(openTaskId);

            // Scroll to task
            const element = document.getElementById("task-card-" + openTaskId);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

    });

    // Star Rating Functions
    var selectedScores = {};

    function highlightStars(subId, index) {
        for (var i = 1; i <= 5; i++) {
            var star = document.querySelector('.star-' + subId + '-' + i);
            if (star) {
                star.parentElement.style.color = (i <= index) ? '#F59E0B' : '#D1D5DB';
            }
        }
    }

    function resetStars(subId) {
        var selected = selectedScores[subId] || 0;
        for (var i = 1; i <= 5; i++) {
            var star = document.querySelector('.star-' + subId + '-' + i);
            if (star) {
                star.parentElement.style.color = (i <= selected) ? '#F59E0B' : '#D1D5DB';
            }
        }
    }

    function setScore(subId, score) {
        selectedScores[subId] = score;
        document.getElementById('score-label-' + subId).innerText = score + "/5";
        resetStars(subId); // Force color update
    }

    // Leader rating stars
    function paintLeaderStars(taskId, score) {
        var stars = document.querySelectorAll('.leader-star-' + taskId);
        for (var i = 0; i < stars.length; i++) {
            var val = parseInt(stars[i].getAttribute('data-value'), 10);
            stars[i].style.color = (val <= score) ? '#F59E0B' : '#D1D5DB';
        }
    }

    function setLeaderScore(taskId, score) {
        var input = document.getElementById('leader-rating-' + taskId);
        var label = document.getElementById('leader-rating-label-' + taskId);
        if (input) input.value = score;
        if (label) label.innerText = score + '/5';
        paintLeaderStars(taskId, score);
    }

    function previewLeaderStars(taskId, score) {
        paintLeaderStars(taskId, score);
    }

    function restoreLeaderStars(taskId) {
        var input = document.getElementById('leader-rating-' + taskId);
        var current = input ? parseInt(input.value, 10) : 0;
        paintLeaderStars(taskId, isNaN(current) ? 0 : current);
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form) return;

        if (form.getAttribute('action') === 'app/rate-leader.php') {
            var hidden = form.querySelector('input[name="rating"]');
            if (!hidden || parseInt(hidden.value, 10) < 1) {
                e.preventDefault();
                openValidationModal('Please select a star rating.');
            }
            return;
        }

        if (form.getAttribute('action') === 'app/review-subtask.php') {
            var submitter = e.submitter || document.activeElement;
            var action = submitter ? submitter.value : '';
            if (action !== 'accept') return;

            var scoreInputs = form.querySelectorAll('input[name="score"]');
            if (scoreInputs.length > 0) {
                var selected = form.querySelector('input[name="score"]:checked');
                if (!selected) {
                    e.preventDefault();
                    openValidationModal('Please provide a performance score before accepting.');
                }
            }
        }
    });
</script>
