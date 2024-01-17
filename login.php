<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");

require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    handleOptionsRequest($conn);
} else {
    sendJsonResponse(405, ["error" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
}
$conn->close();

function handlePostRequest($conn) {
    global $users_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $email = $data['email'];
    $password = $data["password"];

    $stmt = $conn->prepare(
        "SELECT * FROM $users_table_name
        WHERE email LIKE ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        sendJsonResponse(400, ["error"=> "Email or password does not match"]);
        return;
    }
    if (!password_verify($password, $user['password_hash'])) {
        sendJsonResponse(400, ['error'=> "Email or password does not match"]);
        return;
    }

    sendJsonResponse(200, phpversion());

}

function handleOptionsRequest($conn) {
    header('Allow: OPTIONS, POST');
    sendJsonResponse(204, []);
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}