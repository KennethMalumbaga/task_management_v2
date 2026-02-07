<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {

	if (isset($_POST['id'])) {
		include "../DB_connection.php";
        include "model/Task.php";

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
            // Delete task
			delete_task($pdo, [$id]);

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

