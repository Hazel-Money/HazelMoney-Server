<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
$allowedMethods = ['GET', 'OPTIONS'];
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 3600");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    handleOptionsRequest();
}

require_once '../db_connection.php';
require_once '../authorization.php';
$env = parse_ini_file("../.env");

$user = authorizeUser();
$isAdmin = $user['id'] == $env['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetRequest($conn);
} elseif (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
    sendJsonResponse(405, ["message" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
} else {
    sendJsonResponse(403, ["message" => "You are not allowed to access this content!"]);
}
$conn->close();

function handleGetRequest($conn) {
    if (!isset($_GET["account_id"])) {
        sendJsonResponse(400, ["message"=> "Account id not provided"]);
        return;
    }
    global $users_table_name;
    global $accounts_table_name;
    global $currencies_table_name;
    global $user;
    $accountId = $_GET["account_id"];
    $stmt = $conn->prepare(
        "SELECT *
        FROM $accounts_table_name
        WHERE id = ?"
    );
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(404, ["message" => 'Account not found']);
        return;
    }

    $stmt = $conn->prepare(
        "SELECT *
        FROM $accounts_table_name
        WHERE user_id = ?
        AND id = ?"
    );
    $stmt->bind_param("ii", $user['id'], $accountId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(403, ["message" => "You are not authorized to access this account"]);
    }

    $stmt = $conn->prepare(
        "SELECT symbol
        FROM $currencies_table_name c
        JOIN $accounts_table_name a
        ON c.id = a.currency_id
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    $currency = $result->fetch_assoc()['symbol'];

    sendJsonResponse(200, ['currency' => $currency]);
    $stmt->close();
}

function handleOptionsRequest() {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, GET');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
}