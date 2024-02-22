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
require_once '../functions.php';
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
    global $users_table_name;
    global $user;
    global $currencies_table_name;
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
    $user = $result->fetch_assoc();

    $result = $conn->query(
        "SELECT IFNULL(ROUND(SUM(accounts.balance * currencies.inverse_rate * user_currencies.rate), 0), 0)
        AS rounded_total_balance
        FROM users 
        JOIN accounts ON users.id = accounts.user_id
        JOIN currencies ON accounts.currency_id = currencies.id
        JOIN currencies AS user_currencies ON users.default_currency_id = user_currencies.id
        WHERE users.id = $user[id]
        GROUP BY users.id
    ");

    $balance = $result->fetch_assoc()['rounded_total_balance'] ?? 0;
    
    sendJsonResponse(200, ['balance' => $balance]);
    $stmt->close();
}

function handleOptionsRequest() {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, GET');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
}