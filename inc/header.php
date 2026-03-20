<!-- Site-wide stylesheet (added for pages that include this header) -->
<link rel="stylesheet" href="css/style.css">
<?php include_once __DIR__ . "/workspace_theme_style.php"; ?>
<header class="header">
	<h2 class="u-name">Task <b>Manager</b>
		<label for="checkbox">
			<i id="navbtn" class="fa fa-bars" aria-hidden="true"></i>
		</label>
	</h2>
	<div class="header-actions">
		<button class="back-btn" id="backButton" onclick="goBack()" style="display: none;">
			<i class="fa fa-arrow-left"></i> <span>Back</span>
		</button>
		<span class="notification" id="notificationBtn">
			<i class="fa fa-bell" aria-hidden="true"></i>
			<span id="notificationNum"></span>
		</span>
	</div>
</header>
<div class="notification-bar" id="notificationBar">
	<ul id="notifications">
	
	</ul>
</div>
<script type="text/javascript">
	var openNotification = false;

	const notification = ()=> {
		let notificationBar = document.querySelector("#notificationBar");
		if (openNotification) {
			notificationBar.classList.remove('open-notification');
			openNotification = false;
		}else {
			notificationBar.classList.add('open-notification');
			openNotification = true;
		}
	}
	let notificationBtn = document.querySelector("#notificationBtn");
	notificationBtn.addEventListener("click", notification);
</script>

<script src="https://code.jquery.com/jquery-2.2.4.min.js" integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=" crossorigin="anonymous"></script>
<script type="text/javascript">
	$(document).ready(function(){

       $("#notificationNum").load("app/notification-count.php");
       $("#notifications").load("app/notification.php");

   });
   
   // Back button functionality
   function goBack() {
       if (window.history.length > 1) {
           window.history.back();
       } else {
           // If no history, redirect to dashboard
           window.location.href = 'index.php';
       }
   }
   
   // Show back button on pages that are not the dashboard/index
   (function() {
       var currentPage = window.location.pathname.split('/').pop();
       var isDashboard = currentPage === 'index.php' || currentPage === '' || currentPage === 'login.php';
       
       // List of pages where back button should be shown
       var showBackOnPages = [
           'edit-task-employee.php',
           'create_task.php',
           'notifications.php',
           'tasks.php',
           'my_task.php',
           'my_tasks_overdue.php',
           'my_tasks_nodeadline.php',
           'my_tasks_pending.php',
           'my_tasks_in_progress.php',
           'my_tasks_completed.php',
           'screenshots.php',
           'user.php',
           'profile.php',
           'edit_profile.php',
           'edit-user.php'
       ];
       
       if (!isDashboard && showBackOnPages.indexOf(currentPage) !== -1) {
           var backBtn = document.getElementById('backButton');
           if (backBtn) {
               backBtn.style.display = 'flex';
           }
       }
   })();
</script>

<?php include_once __DIR__ . "/toast.php"; ?>
