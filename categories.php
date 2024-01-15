<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    handleOptionsRequest($conn);
} else {
    sendJsonResponse(405, ["error" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
}
$conn->close();

function handleGetRequest($conn) {
    global $categories_table_name;
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM $categories_table_name WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result !== false && $result->num_rows > 0) {
            $category = $result->fetch_assoc();
            sendJsonResponse(200, $category);
        } else {
            sendJsonResponse(404, ['error' => 'Category not found']);
        }
        $stmt->close();
    } elseif (isset($_GET['user_id']) && isset($_GET['is_income'])){
        $userId = $_GET['user_id'];
        $isIncome = $_GET['is_income'];
        $stmt = $conn->prepare("SELECT * FROM $categories_table_name WHERE (user_id = ? OR user_id = 1) AND is_income = $isIncome");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $categories = [];

        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        sendJsonResponse(200, $categories);
    } else if (isset($_GET['user_id'])) {
        $userId = $_GET['user_id'];
        $stmt = $conn->prepare("SELECT * FROM $categories_table_name WHERE user_id = ? OR user_id = 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $categories = [];

        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        sendJsonResponse(200, $categories);
    } else {
        $result = $conn->query("SELECT * FROM $categories_table_name");
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        sendJsonResponse(200, $categories);
    }
}

function handlePostRequest($conn) {
    global $categories_table_name;
    global $users_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $userId = $data['user_id'];
    $name = $data['name'];
    $isIncome = $data['is_income'];
    $icon = "icons/".$data['icon'];
    $color = $data['color'];

    $stmt = $conn->prepare("SELECT * FROM $users_table_name WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(404, ['error' => 'User not found']);
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO $categories_table_name
        VALUES (NULL, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sssss", $userId, $name, $isIncome, $icon, $color);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(201, ['message' => 'User added successfully']);
    } else {
        sendJsonResponse(400, ['error' => 'Query execution failed: ' . $conn->error]);
    }
    $stmt->close();
}

function handleOptionsRequest($coon) {
    header('Allow: OPTIONS, GET, POST');
    sendJsonResponse(204, []);
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}
?>