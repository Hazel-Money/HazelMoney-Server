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
        $userId = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM user WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result !== false && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            sendJsonResponse(200, $user);
        } else {
            sendJsonResponse(404, ['error' => 'User not found']);
        }
        $stmt->close();
    } else {
        $result = $conn->query("SELECT * FROM user");
        $users = [];

        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        sendJsonResponse(200, $users);
    }
}

function handlePostRequest($conn) {
    $data = json_decode(file_get_contents("php://input"), true);

    $email = $data['email'];
    $name = $data['username'];
    $password = password_hash($data['password'], PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO user (email, username, password_hash) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $name, $password);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(201, ['message' => 'User added successfully']);
    } else {
        sendJsonResponse(400, ['error' => 'Query execution failed: ' . $conn->error]);
    }
    $stmt->close();
}

function handlePutRequest($conn) {
    $data = json_decode(file_get_contents("php://input"), true);

    $user_id = $data['id'];
    $name = $data['username'];
    $email = $data['email'];

    $stmt = $conn->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result !== false && $result->num_rows > 0) {
        if (isset($data['password'])) {
            $password = password_hash($data['password'], PASSWORD_BCRYPT);

            $stmt = $conn->prepare("UPDATE user SET username = ?, email = ?, password_hash = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $email, $password, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE user SET username = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $email, $user_id);
        }

        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ['message' => 'User updated successfully']);
        } else {
            sendJsonResponse(400, ['error' => 'Query execution failed: ' . $conn->error]);
        }
        $stmt->close();
    } else {
        sendJsonResponse(404, ['error' => 'User not found']);
    }
}

function handleDeleteRequest($conn) {
    $data = json_decode(file_get_contents("php://input"), true);

    $user_id = $data["id"];

    $stmt = $conn->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result !== false && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user["is_active"] === 0) {
            sendJsonResponse(409, ["message" => "User already deleted"]);
        } else {
            $stmt = $conn->prepare("UPDATE user SET is_active = '0' WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            sendJsonResponse(200, ["message" => "User deleted successfully"]);
        }
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
