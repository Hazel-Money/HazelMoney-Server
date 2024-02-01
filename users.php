<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
$allowedMethods = ['GET', 'PUT', 'DELETE', 'OPTIONS'];
header("Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS");
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

function handleGetRequest($conn) {
    global $users_table_name;
    global $user;
    global $currencies_table_name;
    if (isset($_GET['id'])) {
        if ($user['id'] != $_GET['id']) {
            sendJsonResponse(403, ["message" => "You are not permitted to access this content!"]);
            return;
        }
        $userId = $_GET['id'];
        $stmt = $conn->prepare("SELECT id, email, username, default_currency_id FROM $users_table_name WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ["message" => 'User not found']);
        }
        $user = $result->fetch_assoc();

        $result = $conn->query(
            "SELECT code
            FROM $currencies_table_name
            WHERE id = $user[default_currency_id]"
        );
        $user['default_currency_code'] = $result->fetch_assoc()['code'];

        $result = $conn->query(
            "SELECT ROUND(SUM(accounts.balance * currencies.inverse_rate * user_currencies.rate), 2)
            AS rounded_total_balance
            FROM users 
            JOIN accounts ON users.id = accounts.user_id
            JOIN currencies ON accounts.currency_id = currencies.id
            JOIN currencies AS user_currencies ON users.default_currency_id = user_currencies.id
            WHERE users.id = $user[id]
            GROUP BY users.id
        ");
        $user['balance'] = $result->fetch_assoc()['rounded_total_balance'];

        
        sendJsonResponse(200, $user);
        $stmt->close();
    } else {
        global $isAdmin;
        if (!$isAdmin) {
            sendJsonResponse(403, ["message" => "You are not permitted to get all users!"]);
            return;
        }
        $result = $conn->query("SELECT * FROM $users_table_name");
        $users = [];

        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        sendJsonResponse(200, $users);
    }
}

function handlePutRequest($conn) {
    global $users_table_name;
    global $user;
    global $isAdmin;
    $data = json_decode(file_get_contents("php://input"), true);

    $user_id = $data['id'];
    $name = $data['username'];
    $email = $data['email'];
    $default_currency_id = $data['default_currency_id'];

    if ($user_id != $user['id'] && !$isAdmin) {
        sendJsonResponse(403, ["message" => "You are not permitted to access this content!"]);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM $users_table_name WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result !== false && $result->num_rows > 0) {
        if (isset($data['password'])) {
            $password = password_hash($data['password'], PASSWORD_BCRYPT);

            $stmt = $conn->prepare("UPDATE $users_table_name SET username = ?, email = ?, password_hash = ?, default_currency_id = ? WHERE id = ?");
            $stmt->bind_param("sssii", $name, $email, $password, $default_currency_id, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE $users_table_name SET username = ?, email = ?, default_currency_id = ? WHERE id = ?");
            $stmt->bind_param("ssii", $name, $email, $default_currency_id, $user_id);
        }

        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message" => 'User updated successfully']);
        } else {
            sendJsonResponse(400, ["message" => 'Query execution failed: ' . $conn->error]);
        }
        $stmt->close();
    } else {
        sendJsonResponse(404, ["message" => 'User not found']);
    }
}

function handleDeleteRequest($conn) {
    global $users_table_name;
    global $user;
    global $isAdmin;
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data["id"])) {
        if ($user['id'] != $data['id'] && !$isAdmin) {
            sendJsonResponse(403, ["message" => "You are not allowed to delete this user"]);
            return;
        }
        $user_id = $data["id"];
        $stmt = $conn->prepare("DELETE FROM $users_table_name WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message" => "User deleted successfully"]);
        } else {
            sendJsonResponse(404, ["message" => "User not found"]);
        }
    } else {
        if ($isAdmin) {
            sendJsonResponse(403, ["message"=> "You can't delete all users hahahaha 😜"]);
            return;
        }
        $stmt = $conn->prepare("DELETE FROM $users_table_name");
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message" => "Deleted all users"]);
        } else {
            sendJsonResponse(200, ["message" => "No users were found"]);
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