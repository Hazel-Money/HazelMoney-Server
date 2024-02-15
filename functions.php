<?php
require_once "db_connection.php";
function validatePassword($password, $user_id) {
    global $conn;
    global $users_table_name;
    $stmt = $conn->prepare(
        "SELECT * 
        FROM $users_table_name
        WHERE id = ?"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        return false;
    }
    
    $password_hash = $user['password_hash'];
    if (!password_verify($password, $password_hash)) {
        return false;
    }
    
    return true;
}

function createDirectoryIfNotExists($directory) {
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    } 
}