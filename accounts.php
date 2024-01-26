<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
$allowedMethods= ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 3600");

require_once 'db_connection.php';
require_once 'authorization.php';

$authResponse = authorizeUser();
$auth = json_decode($authResponse, true);

$user = null;
$isAdmin = false;

if (!isset($auth["message"])) {
    $user = $auth['data'];
    $isAdmin = $user['id'] == 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $user != null) {
    handleGetRequest($conn, $user['id']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $user != null) {
    handlePostRequest($conn, $user['id']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' && $user != null) {
    handlePutRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $user != null) {
    handleDeleteRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    handleOptionsRequest($conn);
} elseif (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
    sendJsonResponse(405, ["message" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
} else {
    sendJsonResponse(403, ["message" => "You are not permitted to access this content!"]);
}
$conn->close();

function handleGetRequest($conn, $user_id) {
    global $accounts_table_name;
    if (isset($_GET['id'])) {
        $accountId = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM $accounts_table_name WHERE id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result !== false && $result->num_rows > 0) {
            $account = $result->fetch_assoc();
            if ($account['user_id'] != $user_id) {
                sendJsonResponse(403, ['message'=> 'You are not permitted to access this account!']);
                return;
            }
            sendJsonResponse(200, $account);
        } else {
            sendJsonResponse(404, ["message" => 'Account not found']);
        }
        $stmt->close();
    } elseif (isset($_GET['user_id'])) {
        if ($user_id != $_GET['user_id']) {
            sendJsonResponse(403, ['message'=> 'You are not permitted to access this account!']);
            return;
        }
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
        if ($user_id != 1) {
            sendJsonResponse(403, ["message"=> "You are not allowed to get all accounts!"]);
        }
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
    $currency_id = $data['currency_id'];
    $balance = $data['balance'];

    $stmt = $conn->prepare("INSERT INTO $accounts_table_name (user_id, name, currency_id, balance) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $user_id, $name, $currency_id, $balance);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(201, ["message" => 'Account added successfully']);
    } else {
        sendJsonResponse(400, ["message" => 'Query execution failed: ' . $conn->error]);
    }
    $stmt->close();
}

function handlePutRequest($conn) {
    global $accounts_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $id = $data['id'];
    $name = $data['name'];
    $currency_id = $data['currency_id'];
    $balance = $data['balance'];

    $stmt = $conn->prepare("SELECT * FROM $accounts_table_name WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(404, ["message"=> "Account not found"]);
        return;
    }

    $stmt = $conn->prepare("UPDATE $accounts_table_name SET name = ?, currency_id = ?, balance = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $currency_id, $balance, $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(200, ["message" => 'Account updated successfully']);
    } else {
        sendJsonResponse(400, ["message" => 'Query execution failed: ' . $conn->error]);
    }
    $stmt->close();
}

function handleDeleteRequest($conn) {
    global $accounts_table_name;
    global $categories_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['id'])) {
        $id = $data['id'];
    
        $stmt = $conn->prepare("DELETE FROM $accounts_table_name WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message" => "Account deleted successfully"]);
        } else {
            sendJsonResponse(404, ["message" => "Account not found"]);
        }
    } elseif (isset($data['user_id'])){
        $user_id = $data['user_id'];
        $stmt = $conn->prepare("SELECT * FROM $categories_table_name WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ["message"=> "User not found"]);
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

function handleOptionsRequest($conn) {
    header('Allow: OPTIONS, GET, POST, PUT, DELETE');
    sendJsonResponse(204, []);
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}
