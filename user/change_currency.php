<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
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


if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    handlePutRequest($conn);
} else {
    sendJsonResponse(405, ["message" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
}
$conn->close();

function handlePutRequest($conn) {
    $data = json_decode(file_get_contents("php://input"), true);
    $newCurrencyCode = $data['code'] ?? null;
    if (hasEmptyData([$newCurrencyCode])) {
        sendJsonResponse(400, ['message'=> 'New currency code required!']);
        return;
    }

    global $users_table_name;
    global $accounts_table_name;
    global $currencies_table_name;
    global $user;
    $stmt = $conn->prepare(
        "SELECT default_currency_id
        FROM $users_table_name
        WHERE id = ?"
    );
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(404, ["message" => "User not found"]);
        return;
    }
    $previousCurrencyId = $result->fetch_assoc()['default_currency_id'];

    $stmt = $conn->prepare(
        "SELECT id
        FROM $currencies_table_name
        WHERE code = ?"
    );
    $stmt->bind_param("s", $newCurrencyCode);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(404, ["message" => "Provided currency not found"]);
        return;
    }
    $newCurrencyId = $result->fetch_assoc()['id'];

    if ($newCurrencyId === $previousCurrencyId) {
        sendJsonResponse(400, ["message" => "Provided currency is already the default currency"]);
    }

    $result = $conn->query(
        "SELECT 
            (SELECT inverse_rate
            FROM $currencies_table_name
            WHERE id = $previousCurrencyId)
            *
            (SELECT rate 
            FROM $currencies_table_name
            WHERE id = $newCurrencyId)
            AS result;"
    );
    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(400, ["message" => "An error occurred while processing your request"]);
        return;
    }
    $newRate = $result->fetch_assoc()['result'];

    $result = $conn->query(
        "UPDATE $users_table_name
        SET default_currency_id = $newCurrencyId
        WHERE id = $user[id]"
    );
    if ($result === false) {
        sendJsonResponse(400, ["message" => "An error occurred while processing your request"]);
        return;
    }
}

function handleOptionsRequest() {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, PUT');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
}