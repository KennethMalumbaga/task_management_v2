<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {

if (isset($_POST['full_name']) && isset($_SESSION['role'])) {
	include "../DB_connection.php";

    function validate_input($data) {
	  $data = trim($data);
	  $data = stripslashes($data);
	  $data = htmlspecialchars($data);
	  return $data;
	}

	
	$full_name = validate_input($_POST['full_name']);

	$phone = validate_input($_POST['phone']);
	$bio = validate_input($_POST['bio']);
	$address = validate_input($_POST['address']);
	$skills = validate_input($_POST['skills']); // This might be comma separated or text
	
	// Image handling
	$profile_image = "default.png";
	$upload_path = "../uploads/";

	if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
		$img_name = $_FILES['profile_image']['name'];
		$img_temp_name = $_FILES['profile_image']['tmp_name'];
		$img_size = $_FILES['profile_image']['size'];
        $img_ext = pathinfo($img_name, PATHINFO_EXTENSION);
        $img_ext_lc = strtolower($img_ext);

        $allowed_exs = array("jpg", "jpeg", "png");

        if (in_array($img_ext_lc, $allowed_exs)) {
             $new_img_name = uniqid("IMG-", true) . '.' . $img_ext_lc;
             move_uploaded_file($img_temp_name, $upload_path . $new_img_name);
             $profile_image = $new_img_name;
        } else {
             $em = "You can't upload files of this type";
             header("Location: ../edit_profile.php?error=$em");
             exit();
        }
    } else {
        // If no new image, keep the old one (retrieved from DB later or handled by model if we pass null?)
        // Better: Fetch user first to get old image if not uploading new one
    }

	// Password handling
	$password = validate_input($_POST['password']);
	$new_password = validate_input($_POST['new_password']);
	$confirm_password = validate_input($_POST['confirm_password']);
	
   $id = $_SESSION['id'];

	if (empty($full_name)) {
		$em = "Full name is required";
	    header("Location: ../edit_profile.php?error=$em");
	    exit();
	}

    include "model/user.php";
    $user = get_user_by_id($pdo, $id);

    if ($user) {
        $current_image = $profile_image === "default.png" ? $user['profile_image'] : $profile_image;
        // If the user uploaded a new image, $profile_image is the new name.
        // If NOT, $profile_image is "default.png". 
        // Logic fix: if NO file uploaded, use existing.
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
             // Already handled above, $profile_image has new name
        } else {
             $current_image = $user['profile_image']; // Keep existing
        }
        
        // If user is trying to change password
        if (!empty($new_password) || !empty($confirm_password)) {
            if (empty($password)) {
                $em = "Old password is required to set a new password";
                header("Location: ../edit_profile.php?error=$em");
                exit();
            }
            if ($new_password != $confirm_password) {
                $em = "New password and confirm password do not match";
                header("Location: ../edit_profile.php?error=$em");
                exit();
            }
            if (!password_verify($password, $user['password'])) {
                $em = "Incorrect old password";
                header("Location: ../edit_profile.php?error=$em");
                exit();
            }
            // All good, hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $data = array($full_name, $hashed_password, $bio, $phone, $address, $skills, $current_image, $id);
            update_profile($pdo, $data);
            
            // Clear the forced flag session if it was set
            if (isset($_SESSION['must_change_password'])) {
                unset($_SESSION['must_change_password']);
            }
        } else {
            // Not changing password
            if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password']) {
                 $em = "You must change your password to continue.";
                 header("Location: ../edit_profile.php?warning=" . urlencode($em));
                 exit();
            }
            
            $data = array($full_name, $bio, $phone, $address, $skills, $current_image, $id);
            update_profile_info($pdo, $data);
        }

        $em = "Profile updated successfully";
        header("Location: ../profile.php?success=$em");
        exit();

    } else {
        $em = "User not found";
        header("Location: ../edit_profile.php?error=$em");
        exit();
    }
}else {
   $em = "Unknown error occurred";
   header("Location: ../edit_profile.php?error=$em");
   exit();
}

}else{ 
   $em = "First login";
   header("Location: ../login.php?error=$em");
   exit();
}
