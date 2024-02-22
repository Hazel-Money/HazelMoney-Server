<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization");
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
    global $user;
    global $accounts_table_name;
    global $currencies_table_name;
    global $transactions_table_name;
    $accountId = $_GET['account_id'];
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
    }
    
    $stmt = $conn->prepare(
        "SELECT *
        FROM $accounts_table_name
        WHERE id = ?
        AND user_id = ?"
    );
    $stmt->bind_param("ii", $accountId, $user["id"]);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(404, ["message" => 'You are not allowed to access this account!']);
    }
    
    $stmt = $conn->prepare(
        "SELECT IFNULL(ROUND(SUM(t.amount), 2), 0) AS total_expense
        FROM $transactions_table_name t
        WHERE t.account_id = ?
        AND t.is_income = 0;
    ");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_expense = 0;
    if ($result !== false && $result->num_rows !== 0) {
        $total_expense = $result->fetch_assoc()['total_expense'];
    }
    sendJsonResponse(200, ['total_expense' => $total_expense]);
    $stmt->close();
}

function handleOptionsRequest() {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, GET');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
}