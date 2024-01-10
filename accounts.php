<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    handlePutRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    handleDeleteRequest($conn);
}
$conn->close();

function handleGetRequest($conn) {
    if (isset($_GET['id'])) {
        $accountId = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM account WHERE id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result !== false && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            sendJsonResponse(200, $user);
        } else {
            sendJsonResponse(404, ['error' => 'Account not found']);
        }
        $stmt->close();
    } elseif (isset($_GET['user_id'])) {
        $userId = $_GET['user_id'];
        $stmt = $conn->prepare("SELECT * FROM account WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $accounts = [];

        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
        sendJsonResponse(200, $accounts);
    } else {
        $result = $conn->query("SELECT * FROM account");
        $accounts = [];

        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
        sendJsonResponse(200, $accounts);
    }
}

function handlePostRequest($conn) {

}

function handlePutRequest($conn) {
    
}

function handleDeleteRequest($conn) {
    
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}
?>
