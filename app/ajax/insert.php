<?php 

session_start();

if (isset($_SESSION['id'])) {

	if (isset($_POST['message']) && isset($_POST['to_id'])) {
	
	include "../../DB_connection.php";
    include "../Model/Message.php";

	$message = $_POST['message'];
	$to_id = $_POST['to_id'];
	$from_id = $_SESSION['id'];

	insertChat($from_id, $to_id, $message, $pdo);

	}
}
