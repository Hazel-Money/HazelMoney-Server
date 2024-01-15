<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    handleOptionsRequest($conn);
} else {
    sendJsonResponse(405, ["error" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
}
$conn->close();

function handleGetRequest($conn) {
    global $currencies_table_name;
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM $currencies_table_name WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result !== false && $result->num_rows > 0) {
            $currency = $result->fetch_assoc();
            sendJsonResponse(200, $currency);
        } else {
            sendJsonResponse(404, ['error' => 'Currency not found']);
        }
        $stmt->close();
    } else {
        $result = $conn->query("SELECT * FROM $currencies_table_name");
        $currency = [];

        while ($row = $result->fetch_assoc()) {
            $currency[] = $row;
        }
        sendJsonResponse(200, $currency);
    }
}

function handleOptionsRequest($conn) {
    header('Allow: OPTIONS, GET');
    sendJsonResponse(204, []);
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}
?>