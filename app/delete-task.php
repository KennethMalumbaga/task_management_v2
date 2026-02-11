<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {

	if (isset($_POST['id'])) {
		include "../DB_connection.php";
        include "model/Task.php";
        include "model/Group.php";

        function validate_input($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data);
            return $data;
        }

        $id = validate_input($_POST['id']);

		if(empty($id)){
			$em = "Unknown error occurred";
			header("Location: ../tasks.php?error=$em");
			exit();
		}else {
            // Get task title before deleting the task so we can remove linked task chat(s)
            $task = get_task_by_id($pdo, $id);
            if ($task) {
                delete_task_chat_groups_by_task_id($pdo, (int)$id);
                // Backward compatibility for older task chats that were linked only by title
                if (isset($task['title'])) {
                    delete_task_chat_groups_by_title($pdo, $task['title']);
                }
            }

            // Delete task
			delete_task($pdo, [$id]);
            // Extra cleanup for any stale/orphan task_chat rows from older data
            delete_orphan_task_chat_groups($pdo);
            if ($task && isset($task['title'])) {
                // Cleanup legacy duplicate group chats that were stored as type='group'
                // for this title and no longer map to any existing task title.
                delete_legacy_duplicate_group_chats_by_title($pdo, $task['title']);
            }

			$em = "Task deleted successfully";
			header("Location: ../tasks.php?success=$em");
			exit();
		}
	}else {
		header("Location: ../tasks.php");
		exit();
	}

}else{ 
	$em = "First login";
	header("Location: ../login.php?error=$em");
	exit();
}

