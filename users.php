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
} elseif ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    handleOptionsRequest($conn);
} else {
    sendJsonResponse(405, ["error" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
}
$conn->close();

function handleGetRequest($conn) {
    global $users_table_name;
    if (isset($_GET['id'])) {
        $userId = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM $users_table_name WHERE id = ?");
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
        $result = $conn->query("SELECT * FROM $users_table_name");
        $users = [];

        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        sendJsonResponse(200, $users);
    }
}

function handlePostRequest($conn) {
    global $users_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $email = $data['email'];
    $name = $data['username'];
    $password = password_hash($data['password'], PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO $users_table_name (email, username, password_hash) VALUES (?, ?, ?)");
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
    global $users_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $user_id = $data['id'];
    $name = $data['username'];
    $email = $data['email'];

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
    global $users_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data["id"])) {
        $user_id = $data["id"];
    
        $stmt = $conn->prepare("DELETE FROM $users_table_name WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message" => "User deleted successfully"]);
        } else {
            sendJsonResponse(404, ["error" => "User not found"]);
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM $users_table_name");
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message" => "Deleted all users"]);
        } else {
            sendJsonResponse(200, ["message" => "No users were found"]);
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
?>
