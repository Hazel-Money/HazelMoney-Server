<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 3600");

require_once 'db_connection.php';
require "vendor/autoload.php";
$env = parse_ini_file('.env');
use \Firebase\JWT\JWT;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    handleOptionsRequest($conn);
} else {
    sendJsonResponse(405, ["message" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
}
$conn->close();

function handlePostRequest($conn) {
    global $env;
    global $users_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $email = $data['email'] ?? null;
    $password = $data["password"] ?? null;

    if (hasEmptyData([$email, $password])) {
        sendJsonResponse(400, ["message"=> "All fields are required"]);
        return;
    }

    $stmt = $conn->prepare(
        "SELECT * FROM $users_table_name
        WHERE email = ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        sendJsonResponse(400, ["message"=> "Email or password does not match"]);
        return;
    }
    $password_hash = $user['password_hash'];
    if (!password_verify($password, $password_hash)) {
        sendJsonResponse(400, ["message"=> "Email or password does not match"]);
        return;
    }

    $id = $user["id"];
    $username = $user["username"];

    $issuer_claim = "hazelmoney";
    $audience_claim = "localhost/api/";
    $issuedate_claim = time(); // issued at
    $notbefore_claim = $issuedate_claim; //not before in seconds
    $expire_claim = $issuedate_claim + 3 * 60 * 60; // expire time in seconds
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
    $key = $env['secret'];
    $alg = $env['jwt_alg'];
    $jwt = JWT::encode($token, $key, $alg);
    sendJsonResponse(200, [
        "message" => "Successful login",
        "jwt" => $jwt,
        "id" => $id,
        "email" => $email,
        "username" => $username,
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

function hasEmptyData(array $data) {
    foreach ($data as $element) {
        if (is_null($element) || empty($element) && $element != 0) {
            return true;
        }
    }
    return false;
}