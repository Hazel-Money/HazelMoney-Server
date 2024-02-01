<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
$allowedMethods= ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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
    handleGetRequest($conn, $user['id']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($conn, $user['id']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    handlePutRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    handleDeleteRequest($conn);
} elseif (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
    sendJsonResponse(405, ["message" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
} else {
    sendJsonResponse(403, ["message" => "You are not allowed to access this content!"]);
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

        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ["message" => 'Account not found']);
            return;
        }
        $account = $result->fetch_assoc();
        if ($account['user_id'] != $user_id) {
            sendJsonResponse(403, ['message'=> 'You are not permitted to access this account!']);
            return;
        }
        sendJsonResponse(200, $account);
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
        global $isAdmin;
        if (!$isAdmin) {
            sendJsonResponse(403, ["message"=> "You are not allowed to get all accounts!"]);
            return;
        }
        $result = $conn->query("SELECT * FROM $accounts_table_name");
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
        sendJsonResponse(200, $accounts);
    }
}

function handlePostRequest($conn, $request_user_id) {
    global $accounts_table_name;
    global $currencies_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $user_id = $data['user_id'] ?? null;
    $name = $data['name'] ?? null;
    $currency_code = $data['currency_code'] ?? null;
    $balance = $data['balance'] ?? null;

    $hasEmptyData = hasEmptyData([$user_id, $name, $currency_code, $balance]);
    if ($hasEmptyData) {
        sendJsonResponse(400, ["message" => "All fields are required"]);
        return;
    }

    if ($user_id != $request_user_id) {
        sendJsonResponse(403, ["message"=> "You are not allowed to create this account!"]);
    }

    $stmt = $conn->prepare(
        "SELECT id
        FROM $currencies_table_name
        WHERE code = ?"
    );
    $stmt->bind_param("s", $currency_code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(400, ["message"=> "Invalid currency code!"]);
        return;
    }
    $currency_id = $result->fetch_assoc()["id"];
    $stmt = $conn->prepare(
        "INSERT INTO $accounts_table_name 
        (user_id, name, currency_id, balance)
        VALUES ( ?, ?, ?, ?)"
    );
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

function handleOptionsRequest() {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}

function hasEmptyData(array $data) {
    foreach ($data as $element) {
        if (is_null($element) || empty($element) && $element != 0) {
            return true;
        }
    }
    return false;
}