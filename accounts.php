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
    global $accounts_table_name;
    if (isset($_GET['id'])) {
        $accountId = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM $accounts_table_name WHERE id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result !== false && $result->num_rows > 0) {
            $account = $result->fetch_assoc();
            sendJsonResponse(200, $account);
        } else {
            sendJsonResponse(404, ['error' => 'Account not found']);
        }
        $stmt->close();
    } elseif (isset($_GET['user_id'])) {
        $userId = $_GET['user_id'];
        $stmt = $conn->prepare("SELECT * FROM $accounts_table_name WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $accounts = [];

        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
        sendJsonResponse(200, $accounts);
    } else {
        $result = $conn->query("SELECT * FROM $accounts_table_name");
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
        sendJsonResponse(200, $accounts);
    }
}

function handlePostRequest($conn) {
    global $accounts_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $user_id = $data['user_id'];
    $name = $data['name'];
    $currency = $data['currency'];
    $balance = $data['balance'];

    $stmt = $conn->prepare("INSERT INTO $accounts_table_name (user_id, name, currency, balance) VALUES (?, ?, ?, ?)");
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
    global $accounts_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $id = $data['id'];
    $name = $data['name'];
    $currency = $data['currency'];
    $balance = $data['balance'];

    $stmt = $conn->prepare("SELECT * FROM $accounts_table_name WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(404, ["error"=> "Account not found"]);
        return;
    }

    $stmt = $conn->prepare("UPDATE $accounts_table_name SET name = ?, currency = ?, balance = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $currency, $balance, $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(200, ['message' => 'Account updated successfully']);
    } else {
        sendJsonResponse(400, ['error' => 'Query execution failed: ' . $conn->error]);
    }
    $stmt->close();
}

function handleDeleteRequest($conn) {
    global $accounts_table_name;
    global $users_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['id'])) {
        $id = $data['id'];
    
        $stmt = $conn->prepare("DELETE FROM $accounts_table_name WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message" => "Account deleted successfully"]);
        } else {
            sendJsonResponse(404, ["error" => "Account not found"]);
        }
    } elseif (isset($data['user_id'])){
        $user_id = $data['user_id'];
        $stmt = $conn->prepare("SELECT * FROM $users_table_name WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ["error"=> "User not found"]);
            return;
        }
        $stmt = $conn->prepare("DELETE FROM $accounts_table_name WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message" => "Accounts deleted successfully"]);
        } else {
            sendJsonResponse(200, ["message" => "User has no accounts"]);
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM $accounts_table_name");
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message" => "Deleted all accounts"]);
        } else {
            sendJsonResponse(200, ["message" => "No accounts were found"]);
        }
    }
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}
?>
