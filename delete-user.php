<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    include "app/model/user.php";
    include "app/model/Task.php";
    
    $is_super_admin = is_super_admin($_SESSION['id'], $pdo);

    if (!isset($_GET['id'])) {
    	 header("Location: user.php");
    	 exit();
    }
    $id = $_GET['id'];
    $user = get_user_by_id($pdo, $id);

    if ($user == 0) {
    	 $em = "User not found";
    	 header("Location: user.php?error=$em");
    	 exit();
    }

    // Security check: only super admin can delete admins
    if ($user['role'] == 'admin' && !$is_super_admin) {
        $em = "Access denied. Only Super Admin can delete other Admins.";
        header("Location: user.php?error=$em");
        exit();
    }
    
    // Prevent super admin from deleting themselves (username 'admin')
    if ($user['username'] == 'admin') {
        $em = "Access denied. The Super Admin account cannot be deleted.";
        header("Location: user.php?error=$em");
        exit();
    }

    // Check if user has active (non-completed) tasks assigned
    if (user_has_tasks($pdo, $id)) {
    	$em = "Cannot delete user. This user has active tasks assigned. Please reassign or complete the tasks first.";
    	header("Location: user.php?error=$em");
    	exit();
    }

    // Unassign completed tasks (set assigned_to to NULL) before deletion
    unassign_completed_tasks($pdo, $id);

    $sql = "DELETE FROM users WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$id]);
    
    if ($result) {
    	$sm = "User deleted successfully";
    	header("Location: user.php?success=$sm");
    } else {
    	$em = "Failed to delete user. Please try again.";
    	header("Location: user.php?error=$em");
    }
    exit();

 }else{ 
   $em = "First login";
   header("Location: login.php?error=$em");
   exit();
}
 ?>

