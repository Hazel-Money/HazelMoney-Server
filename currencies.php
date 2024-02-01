<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 3600");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    handleOptionsRequest();
}

require_once 'db_connection.php';
require_once 'authorization.php';
$env = parse_ini_file(".env");

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
} else {
    sendJsonResponse(405, ["message" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
}
$conn->close();

function handleGetRequest($conn) {
    global $currencies_table_name;
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT id, code, name FROM $currencies_table_name WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result !== false && $result->num_rows > 0) {
            $currency = $result->fetch_assoc();
            sendJsonResponse(200, $currency);
        } else {
            sendJsonResponse(404, ["message" => 'Currency not found']);
        }
        $stmt->close();
    } elseif (isset($_GET['user_id'])) {
        global $user;
        global $users_table_name;
        if ($_GET['user_id'] != $user['id']) {
            sendJsonResponse(403, ['message'=> 'You can\'t access another user\'s currency data']);
            return;
        }
        $result = $conn->query(
            "SELECT default_currency_id
            FROM $users_table_name
            WHERE id = $user[id]"
        );
        $default_currency = $result->fetch_assoc();
        $result = $conn->query(
            "SELECT code
            FROM $currencies_table_name
            WHERE id = $default_currency[default_currency_id]"
        );
        $default_currency_code = $result->fetch_assoc()['code'];
        sendJsonResponse(200, $default_currency_code);
    } else {
        $result = $conn->query("SELECT id, code, name FROM $currencies_table_name");
        $currency = [];

        while ($row = $result->fetch_assoc()) {
            $currency[] = $row;
        }
        sendJsonResponse(200, $currency);
    }
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
?>