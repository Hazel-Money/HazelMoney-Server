<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 3600");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    handleOptionsRequest();
}

require_once 'db_connection.php';
require_once 'functions.php';
$env = parse_ini_file('.env');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($conn);
} else {
    sendJsonResponse(405, ["message" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
}
$conn->close();

function handlePostRequest($conn) {
    global $env;
    global $users_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $email = $data['email'] ?? null;
    $name = $data['username'] ?? null;
    $password = $data['password'] ?? null;
    $default_currency_id = 1;
    $profile_picture_path = $_SERVER["DOCUMENT_ROOT"] . $env["pfp_path"];
    $profile_picture_name = $env["default_pfp_name"];

    $hasEmptyData = hasEmptyData([$email, $name, $password]);
    if ($hasEmptyData) {
        sendJsonResponse(400, ['message'=> 'All fields are required']);
        return;
    }

    createDirectoryIfNotExists($profile_picture_path);

    $passwordValid = validatePasswordForRegistration($password);

    if ($passwordValid !== true) {
        sendJsonResponse(400, ["message" => $passwordValid]);
    }

    $password = password_hash($password, PASSWORD_BCRYPT); 

    $stmt = $conn->prepare(
        "SELECT *
        FROM $users_table_name u
        WHERE u.email = ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        sendJsonResponse(400, ["message" => "Email already in use"]);
        return;
    }

    if (!validateEmail($email)) {
        sendJsonResponse(400, ["message" => "Email is invalid"]);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO $users_table_name (email, username, password_hash, default_currency_id, profile_picture_path) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $email, $name, $password, $default_currency_id, $profile_picture_name);
    $stmt->execute();

    if ($stmt->affected_rows < 1) {
        sendJsonResponse(400, ["message" => 'Query execution failed: ' . $conn->error]);
        return;
    }
    sendJsonResponse(201, ["message" => 'User added successfully']);
    $stmt->close();
}

function handleOptionsRequest() {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, POST');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
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