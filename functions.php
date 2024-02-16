<?php
require_once "db_connection.php";
$env = parse_ini_file(".env");

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


function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    list($username, $domain) = explode('@', $email);

    if (checkdnsrr($domain, 'MX')) {
        return true;
    } else {
        return false;
    }
}

function validatePasswordForRegistration($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }

    if (!preg_match('/\d/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }

    if (empty($errors)) {
        return true;
    } else {
        return $errors;
    }
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function hasEmptyData(array $data) {
    foreach ($data as $element) {
        if (is_null($element) || empty($element) && $element != 0) {
            return true;
        }
    }
    return false;
}