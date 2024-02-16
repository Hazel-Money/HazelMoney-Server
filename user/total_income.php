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
        return;
    }

    $result = $conn->query(
        "SELECT users.id AS user_id,
        users.username,
        ROUND(SUM(transactions.amount * account_currencies.inverse_rate * user_currencies.rate * CASE WHEN transactions.is_income = 1 THEN 1 ELSE -1 END), 2) AS total_income
        FROM users
        JOIN accounts ON users.id = accounts.user_id
        JOIN transactions ON accounts.id = transactions.account_id
        JOIN currencies AS account_currencies ON accounts.currency_id = account_currencies.id
        JOIN currencies AS user_currencies ON users.default_currency_id = user_currencies.id
        WHERE users.id = $user[id]
        AND transactions.is_income = 1
        GROUP BY users.id;
    ");
    $total_income = $result->fetch_assoc()['total_income'];
    
    sendJsonResponse(200, ['total_income' => $total_income]);
    $stmt->close();
}

function handleOptionsRequest() {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, GET');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
}