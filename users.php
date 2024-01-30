<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
$allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
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

function handleGetRequest($conn, $user_id) {
    global $users_table_name;
    if (isset($_GET['id'])) {
        if ($user_id != $_GET['id']) {
            sendJsonResponse(403, ["message" => "You are not permitted to access this content!"]);
            return;
        }
        $userId = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM $users_table_name WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result !== false && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            sendJsonResponse(200, $user);
        } else {
            sendJsonResponse(404, ["message" => 'User not found']);
        }
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

            $stmt = $conn->prepare("UPDATE $users_table_name SET username = ?, email = ?, password_hash = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $email, $password, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE $users_table_name SET username = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $email, $user_id);
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
    header('Allow: OPTIONS, GET, POST, PUT, DELETE');
    sendJsonResponse(204, []);
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}