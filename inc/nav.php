<nav class="side-bar">
			<div class="user-p">
				<img src="img/user.png">
				<h4>@<?=$_SESSION['username']?></h4>
			</div>
			
			<?php 

               if($_SESSION['role'] == "employee"){
			 ?>
			 <!-- Employee Navigation Bar -->
			<ul id="navList">
				<li>
					<a href="index.php">
						<i class="fa fa-tachometer" aria-hidden="true"></i>
						<span>Dashboard</span>
					</a>
				</li>
				<li>
					<a href="my_task.php">
						<i class="fa fa-tasks" aria-hidden="true"></i>
						<span>My Task</span>
					</a>
				</li>
				<li>
					<a href="my_subtasks.php">
						<i class="fa fa-list-alt" aria-hidden="true"></i>
						<span>My Subtasks</span>
					</a>
				</li>
				<li>
					<a href="profile.php">
						<i class="fa fa-user" aria-hidden="true"></i>
						<span>Profile</span>
					</a>
				</li>
	                <li>
	                    <a href="dtr.php">
	                        <i class="fa fa-calendar" aria-hidden="true"></i>
	                        <span>DTR</span>
	                    </a>
	                </li>
				<li>
					<a href="notifications.php">
						<i class="fa fa-bell" aria-hidden="true"></i>
						<span>Notifications</span>
					</a>
				</li>
				<li>
					<a href="logout.php" class="js-logout-link">
						<i class="fa fa-sign-out" aria-hidden="true"></i>
						<span>Logout</span>
					</a>
				</li>
			</ul>
		<?php }else { ?>
			<!-- Admin Navigation Bar -->
            <ul id="navList">
				<li>
					<a href="index.php">
						<i class="fa fa-tachometer" aria-hidden="true"></i>
						<span>Dashboard</span>
					</a>
				</li>
				<li>
					<a href="user.php">
						<i class="fa fa-users" aria-hidden="true"></i>
						<span>Manage Users</span>
					</a>
				</li>
				<li>
					<a href="create_task.php">
						<i class="fa fa-plus" aria-hidden="true"></i>
						<span>Create Task</span>
					</a>
				</li>
				<li>
					<a href="tasks.php">
						<i class="fa fa-tasks" aria-hidden="true"></i>
						<span>All Tasks</span>
					</a>
				</li>
				<li>
					<a href="screenshots.php">
						<i class="fa fa-camera" aria-hidden="true"></i>
						<span>Screenshots</span>
					</a>
				</li>
				<li>
					<a href="logout.php" class="js-logout-link">
						<i class="fa fa-sign-out" aria-hidden="true"></i>
						<span>Logout</span>
					</a>
				</li>
			</ul>
		<?php } ?>
		</nav>

<div id="logoutConfirmModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1200; align-items:center; justify-content:center;">
    <div style="background:#fff; width:min(92vw, 360px); border-radius:12px; padding:22px; text-align:center; box-shadow:0 10px 25px rgba(0,0,0,0.15);">
        <div style="width:46px; height:46px; margin:0 auto 12px; border-radius:50%; background:#FEF3C7; color:#B45309; display:flex; align-items:center; justify-content:center; font-size:18px;">
            <i class="fa fa-sign-out"></i>
        </div>
        <h3 style="margin:0 0 8px; font-size:20px; color:#111827;">Logout?</h3>
        <p style="margin:0 0 16px; font-size:14px; color:#6B7280;">Are you sure you want to logout?</p>
        <div style="display:flex; gap:10px; justify-content:center;">
            <button type="button" id="logoutCancelBtn" style="border:none; border-radius:8px; background:#F3F4F6; color:#374151; padding:10px 16px; font-weight:600; cursor:pointer;">Cancel</button>
            <button type="button" id="logoutConfirmBtn" style="border:none; border-radius:8px; background:#EF4444; color:#fff; padding:10px 16px; font-weight:600; cursor:pointer;">Yes, Logout</button>
        </div>
    </div>
</div>

<script>
    (function () {
        var links = document.querySelectorAll('a.js-logout-link');
        if (!links.length) return;

        var modal = document.getElementById('logoutConfirmModal');
        var cancelBtn = document.getElementById('logoutCancelBtn');
        var confirmBtn = document.getElementById('logoutConfirmBtn');
        var pendingHref = 'logout.php';

        function openModal(href) {
            pendingHref = href || 'logout.php';
            if (modal) modal.style.display = 'flex';
        }

        function closeModal() {
            if (modal) modal.style.display = 'none';
        }

        links.forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                openModal(link.getAttribute('href'));
            });
        });

        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                try {
                    localStorage.setItem('taskflow_force_stop_capture', String(Date.now()));
                } catch (e) {}
                window.location.href = pendingHref;
            });
        }
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
        }
    })();
</script>
