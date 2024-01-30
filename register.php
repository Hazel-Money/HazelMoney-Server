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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($conn);
} else {
    sendJsonResponse(405, ["message" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
}
$conn->close();

function handlePostRequest($conn) {
    global $users_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $email = $data['email'] ?? null;
    $name = $data['username'] ?? null;
    $password = isset($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : null;

    $hasEmptyData = hasEmptyData([$email, $name, $password]);
    if ($hasEmptyData) {
        sendJsonResponse(400, ['message'=> 'All fields are required']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO $users_table_name (email, username, password_hash) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $name, $password);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(201, ["message" => 'User added successfully']);
    } else {
        sendJsonResponse(400, ["message" => 'Query execution failed: ' . $conn->error]);
    }
    $stmt->close();
}

function handleOptionsRequest() {
    header('Allow: OPTIONS, POST');
    sendJsonResponse(204, []);
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}

function hasEmptyData(array $data) {
    foreach ($data as $element) {
        if (is_null($element) || empty($element) && $element != 0) {
            return true;
        }
    }
    return false;
}