<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authro");
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
    global $transactions_table_name;
    global $currencies_table_name;
    global $accounts_table_name;
    global $user;
    $stmt = $conn->prepare(
        "SELECT *
        FROM $users_table_name
        WHERE id = ?"
    );
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(404, ["message" => 'User not found']);
        return;
    }

    $default_currency_id = $result->fetch_assoc()['default_currency_id'];
    $result = $conn->query(
        "SELECT symbol
        FROM $currencies_table_name
        WHERE id = $default_currency_id"
    );
    $currency = $result->fetch_assoc()['symbol'];

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

    $result = $conn->query(
        "SELECT 
        date_seq.date,
        ROUND(IFNULL(SUM(CASE WHEN t.is_income = 1 THEN t.amount * account_currencies.inverse_rate * user_currencies.rate ELSE 0 END), 0), 2) AS income,
        ROUND(IFNULL(SUM(CASE WHEN t.is_income = 0 THEN t.amount * account_currencies.inverse_rate * user_currencies.rate ELSE 0 END), 0), 2) AS expense
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
        LEFT JOIN transactions t ON date_seq.date = DATE(t.payment_date)
        JOIN accounts a ON u.id = a.user_id
        LEFT JOIN currencies account_currencies ON a.currency_id = account_currencies.id
        LEFT JOIN currencies user_currencies ON u.default_currency_id = user_currencies.id
        WHERE date_seq.date >= '$date1' AND date_seq.date <= '$date2'
        AND a.user_id = $user[id]
        GROUP BY date_seq.date
        ORDER BY date_seq.date;"
    );
    $data = [];
    while ($date = $result->fetch_assoc()) {
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