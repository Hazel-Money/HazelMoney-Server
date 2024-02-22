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
    
    $currency_id = $result->fetch_assoc()['currency_id'];
    $result = $conn->query(
        "SELECT symbol
        FROM $currencies_table_name
        WHERE id = $currency_id"
    );
    $currency = $result->fetch_assoc()['symbol'];
    
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

    $format = 'Y-m-d';
    $date1 = DateTime::createFromFormat($format, $_GET['start']);
    $date2 = DateTime::createFromFormat($format, $_GET['end']);

    if ($date1 === false || $date2 === false ||
        $date1->format($format) !== $_GET['start'] ||
        $date2->format($format) !== $_GET['end']) {
        sendJsonResponse(400, ["message" => "Invalid date format"]);
    } elseif ($date1 >= $date2) {
        sendJsonResponse(400, ["message" => "First date must be before the second date"]);
    }

    $date1 = $date1->format($format);
    $date2 = $date2->format($format);
    
    $stmt = $conn->prepare(
        "SELECT 
        date_seq.date,
        ROUND(IFNULL(SUM(CASE WHEN t.is_income = 1 THEN t.amount ELSE 0 END), 0), 2) AS income,
        ROUND(IFNULL(SUM(CASE WHEN t.is_income = 0 THEN t.amount ELSE 0 END), 0), 2) AS expense
        FROM (
            SELECT DATE('$date1' + INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY) AS date
            FROM (
                SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
            ) AS a
            CROSS JOIN (
                SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
            ) AS b
            CROSS JOIN (
                SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
            ) AS c
        ) date_seq
        LEFT JOIN transactions t ON date_seq.date = DATE(t.payment_date) AND t.account_id = ?
        WHERE date_seq.date >= '$date1' AND date_seq.date <= '$date2'
        GROUP BY date_seq.date
        ORDER BY date_seq.date;
    ");
    $stmt->bind_param("i", $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    $dates = [];
    while ($date = $result->fetch_assoc()) {
        $dates[] = $date;
    }
    $data = [];
    foreach ($dates as $date) {
        $data[] = [     
            "date" => $date['date'],
            "income" => $date['income'],
            "expense" => $date['expense']
        ];
    }
    sendJsonResponse(200, $data);
    $stmt->close();
}

function handleOptionsRequest() {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, GET');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
}