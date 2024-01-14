<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetRequest($conn);
} else {
    sendJsonResponse(405, ["error" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
}
$conn->close();

function handleGetRequest($conn) {
    global $frequencies_table_name;
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM $frequencies_table_name WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result !== false && $result->num_rows > 0) {
            $frequency = $result->fetch_assoc();
            sendJsonResponse(200, $frequency);
        } else {
            sendJsonResponse(404, ['error' => 'Frequency not found']);
        }
        $stmt->close();
    } else {
        $result = $conn->query("SELECT * FROM $frequencies_table_name");
        $frequency = [];

        while ($row = $result->fetch_assoc()) {
            $frequency[] = $row;
        }
        sendJsonResponse(200, $frequency);
    }
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}
?>