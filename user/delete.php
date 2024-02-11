<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
$allowedMethods = ['DELETE', 'OPTIONS'];
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
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

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    handleDeleteRequest($conn);
} elseif (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
    sendJsonResponse(405, ["message" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
} else {
    sendJsonResponse(403, ["message" => "You are not allowed to access this content!"]);
}
$conn->close();

function handleDeleteRequest($conn) {
    global $users_table_name;
    global $user;
    global $isAdmin;
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['password'])) {
        sendJsonResponse(400, ["message" => "Password is required"]);
        return;
    }

    if (!validatePassword($data['password'], $user['id'])) {
        sendJsonResponse(400, ["message" => "Invalid password"]);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM $users_table_name WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(200, ["message" => "User deleted successfully"]);
    } else {
        sendJsonResponse(404, ["message" => "User not found"]);
    }
}

function handleOptionsRequest() {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}