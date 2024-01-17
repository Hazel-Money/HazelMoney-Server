<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 3600");

require_once 'db_connection.php';

require "vendor/autoload.php";
use \Firebase\JWT\JWT;

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
        WHERE email = ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $password_hash = $user['password_hash'];

    if (!$user) {
        sendJsonResponse(400, ["error"=> "Email or password does not match"]);
        return;
    }
    if (!password_verify($password, $password_hash)) {
        sendJsonResponse(400, ['error'=> "Email or password does not match"]);
        return;
    }

    $id = $user["id"];
    $username = $user["username"];

    $secret_key = json_decode(file_get_contents("config/secret_key.json"), true)["secret_key"];
    $issuer_claim = "hazelmoneydb";
    $audience_claim = "localhost:80/api/";
    $issuedate_claim = time(); // issued at
    $notbefore_claim = $issuedate_claim; //not before in seconds
    $expire_claim = $issuedate_claim + 60; // expire time in seconds
    $token = array(
        "iss" => $issuer_claim,
        "aud" => $audience_claim,
        "iat" => $issuedate_claim,
        "nbf" => $notbefore_claim,
        "exp" => $expire_claim,
        "data" => array(
            "id" => $id,
            "email" => $email,
            "username" => $username
        ));
    $jwt = JWT::encode($token, $secret_key, 'HS256');
    sendJsonResponse(200, [
        "message" => "Successful login",
        "jwt" => $jwt,
        "email" => $email,
        "expireAt" => $expire_claim
    ]);
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