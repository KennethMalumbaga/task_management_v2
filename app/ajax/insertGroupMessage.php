<?php

session_start();

if (isset($_SESSION['id'])) {
    if (isset($_POST['message']) && isset($_POST['group_id'])) {
        include "../../DB_connection.php";
        include "../model/GroupMessage.php";
        include "../model/Group.php";

        $message = $_POST['message'];
        $group_id = (int)$_POST['group_id'];
        $from_id = $_SESSION['id'];

        if (!is_user_in_group($pdo, $group_id, $from_id)) {
            exit();
        }

        $msg_id = insert_group_message($pdo, $group_id, $from_id, $message);

        if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
            $allowed = ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg','zip','json','txt'];
            $upload_dir = "../../uploads";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $total_files = count($_FILES['files']['name']);
            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['files']['name'][$i];
                    $file_tmp = $_FILES['files']['tmp_name'][$i];
                    $file_size = $_FILES['files']['size'][$i];
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if (in_array($ext, $allowed) && $file_size <= 100 * 1024 * 1024) {
                        $new_filename = "group_chat_" . time() . "_" . $i . "_" . $from_id . "." . $ext;
                        if (move_uploaded_file($file_tmp, "$upload_dir/$new_filename")) {
                            insert_group_attachment($pdo, $msg_id, $new_filename);
                        }
                    }
                }
            }
        }
    }
}
