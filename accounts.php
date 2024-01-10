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
    $data = json_decode(file_get_contents("php://input"), true);

    $user_id = $data['user_id'];
    $name = $data['name'];
    $currency = $data['currency'];
    $balance = $data['balance'];

    $stmt = $conn->prepare("INSERT INTO account (user_id, name, currency, balance) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $user_id, $name, $currency, $balance);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(201, ['message' => 'Account added successfully']);
    } else {
        sendJsonResponse(400, ['error' => 'Query execution failed: ' . $conn->error]);
    }
    $stmt->close();
}

function handlePutRequest($conn) {
    $data = json_decode(file_get_contents("php://input"), true);

    $id = $data['id'];
    $name = $data['name'];
    $currency = $data['currency'];
    $balance = $data['balance'];

    $stmt = $conn->prepare("SELECT * FROM account WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result !== false && $result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE account SET name = ?, currency = ?, balance = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $currency, $balance, $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ['message' => 'Account updated successfully']);
        } else {
            sendJsonResponse(400, ['error' => 'Query execution failed: ' . $conn->error]);
        }
        $stmt->close();
    } else {
        sendJsonResponse(404, ['error' => 'Account not found']);
    }
}

function handleDeleteRequest($conn) {
    $data = json_decode(file_get_contents("php://input"), true);

    $id = $data["id"];
    if (i)
    $stmt = $conn->prepare("SELECT * FROM account WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result !== false && $result->num_rows > 0) {
        $account = $result->fetch_assoc();
        $stmt = $conn->prepare("DELETE FROM transaction WHERE account_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        sendJsonResponse(200, ["message" => "User deleted successfully"]);
        $stmt->close();
    } else {
        sendJsonResponse(404, ["error" => "User not found"]);
    }
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}
?>
