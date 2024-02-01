<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
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

$authResponse = authorizeUser();
$auth = json_decode($authResponse, true);

if (isset($auth['message'])) {
    sendJsonResponse(401, $auth['message']);
    return;
}

$user = $auth['data'];
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
    global $users_table_name;
    global $transactions_table_name;
    global $currencies_table_name;
    global $accounts_table_name;
    global $user;
    $userId = $user['id'];
    $stmt = $conn->prepare(
        "SELECT *
        FROM $users_table_name
        WHERE id = ?"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(404, ["message" => 'User not found']);
    }

    $result = $conn->query(
        "SELECT -COALESCE(SUM($transactions_table_name.amount), 0) AS total_expense
        FROM $users_table_name
        JOIN $accounts_table_name ON $users_table_name.id = $accounts_table_name.user_id
        LEFT JOIN $transactions_table_name ON $accounts_table_name.id = $transactions_table_name.account_id AND $transactions_table_name.is_income = 0
        WHERE $users_table_name.id = $user[id]
    ");
    $total_expense = $result->fetch_assoc()['total_expense'];

    
    sendJsonResponse(200, ['total_expense' => $total_expense]);
    $stmt->close();
}

function handleOptionsRequest() {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, GET');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}