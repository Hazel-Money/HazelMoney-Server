<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
$allowedMethods = ['PUT', 'OPTIONS, GET'];
header("Access-Control-Allow-Methods: GET, PUT, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 3600");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    handleOptionsRequest();
}

require_once '../db_connection.php';
require_once '../authorization.php';
require_once '../functions.php';
$env = parse_ini_file("../.env");

$authResponse = authorizeUser();
$auth = json_decode($authResponse, true);

if (isset($auth['message'])) {
    sendJsonResponse(401, $auth['message']);
    return;
}

$user = $auth['data'];
$isAdmin = $user['id'] == $env['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    handlePutRequest($conn);
} elseif($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetRequest($conn);
}elseif (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
    sendJsonResponse(405, ["message" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
} else {
    sendJsonResponse(403, ["message" => "You are not allowed to access this content!"]);
}
$conn->close();

function handlePutRequest($conn) {
    global $users_table_name;
    global $currencies_table_name;
    global $user;

    $data = json_decode(file_get_contents("php://input"), true);

    $email = $data['email'] ?? null;
    $username = $data['username'] ?? null;

    if (hasEmptyData([$email, $username])) {
        sendJsonResponse(400, ["message" => "All fields are required"]);
    }

    if(!validateEmail($email)) {
        sendJsonResponse(400, ["message" => "Email is invalid"]);
    }

    $stmt = $conn->prepare(
        "SELECT *
        FROM $users_table_name u
        WHERE u.email = ?
        AND u.id <> ?"
    );
    $stmt->bind_param("si", $email, $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        sendJsonResponse(400, ["message" => "Email already in use"]);
    }

    $result = $conn->query(
        "SELECT u.username, u.email
        FROM $users_table_name u
        WHERE u.id = $user[id]"
    );

    $current_user_data = $result->fetch_assoc();
    $current_email = $current_user_data['email'];
    $current_username = $current_user_data['username'];

    if ($current_email === $email && $current_username === $username) {
        sendJsonResponse(204, ["message" => ""]);
    }

    $stmt = $conn->prepare(
        "UPDATE $users_table_name u
        SET u.username = ?,
        u.email = ?
        WHERE u.id = ?"
    );
    $stmt->bind_param("ssi", $username, $email, $user['id']);
    $stmt->execute();
    
    if ($stmt->affected_rows < 1) {
        sendJsonResponse(400, ["message" => "An error occurred when updating user data"]);
    }
    sendJsonResponse(200, ["message" => "Updated user data successfully"]);
}

function handleGetRequest($conn) {
    global $user;
    global $users_table_name;
    $result = $conn->query(
        "SELECT u.username, u.email
        FROM $users_table_name u
        WHERE u.id = $user[id]"
    );
    if ($result->num_rows === 0) {
        sendJsonResponse(500, ["message" => "Something went wrong, sorry!"]);
    }
    $data = $result->fetch_assoc();
    sendJsonResponse(200, $data);
}

function handleOptionsRequest() {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, PUT');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
}