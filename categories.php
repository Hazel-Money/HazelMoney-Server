<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
$allowedMethods = ['GET', 'POST', 'OPTIONS'];
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 3600");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    handleOptionsRequest();
}

require_once 'db_connection.php';
require_once 'authorization.php';
require_once 'functions.php';
$env = parse_ini_file('.env');

$user = authorizeUser();
$isAdmin = $user['id'] == $env['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($conn);
} elseif (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
    sendJsonResponse(405, ["message" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
} else {
    sendJsonResponse(403, ["message" => "You are not allowed to access this content!"]);
}
$conn->close();

function handleGetRequest($conn) {
    global $categories_table_name;
    global $users_table_name;
    global $user;
    if (isset($_GET['id'])) {
        $id = $_GET['id'];

        $stmt = $conn->prepare("SELECT * FROM $categories_table_name WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result !== false && $result->num_rows > 0) {
            $category = $result->fetch_assoc();
            sendJsonResponse(200, $category);
        } else {
            sendJsonResponse(404, ["message" => 'Category not found']);
        }
        $stmt->close();
    } elseif (isset($_GET['user_id']) && isset($_GET['is_income'])){
        if ($user['id'] != $_GET['user_id']) {
            sendJsonResponse(403, ['message'=> 'You are not allowed to access this content!!🤬']);
            return;
        }
        $isIncome = intval($_GET['is_income']);
        if ($isIncome != 0 && $isIncome != 1) {
            sendJsonResponse(400, ['message'=> 'Invalid is_income type']);
            return;
        }
        $stmt = $conn->prepare("SELECT * FROM $categories_table_name WHERE (user_id = ? OR user_id = 1) AND is_income = ?");
        $stmt->bind_param("ii", $user['id'], $isIncome);
        $stmt->execute();
        $result = $stmt->get_result();
        $categories = [];

        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        sendJsonResponse(200, $categories);
    } else if (isset($_GET['user_id'])) {
        if ($user['id'] != $_GET['user_id']) {
            sendJsonResponse(403, ['message'=> 'You are not allowed to access this content!!🤬']);
            return;
        }
        $stmt = $conn->prepare("SELECT * FROM $categories_table_name WHERE user_id = ? OR user_id = 1");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $categories = [];

        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        sendJsonResponse(200, $categories);
    } else {
        if ($user['id'] != 1) {
            sendJsonResponse(403, ["message"=> "You are not allowed to access this content"]);
            return;
        }
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
    global $env;
    global $user;
    $data = json_decode(file_get_contents("php://input"), true);

    $userId = isset($data['user_id']) ? intval($data['user_id']) : null;
    $name = $data['name'] ?? null;
    $isIncome = isset($data['is_income']) ? intval($data['is_income']) : null;
    $icon = $data['icon'] ?? null;
    $color = $data['color'] ?? null;

    $hasEmptyData = hasEmptyData([$userId, $name, $isIncome, $icon, $color]);

    if ($hasEmptyData) {
        sendJsonResponse(400, ["message" => "All fields are required"]);
        return;
    }

    if ($userId != $user['id']) {
        sendJsonResponse(403, ["message"=> "You are not permitted to access this content! 🥰"]);
        return;
    }

    $result = $conn->query(
        "SELECT name
        FROM $categories_table_name
        WHERE user_id = $user[id]
        OR user_id = $env[admin_id]"
    );
    $user_categories = [];
    while($category = $result->fetch_assoc()) {
        $user_categories[] = $category['name'];
    }

    if (in_array($name, $user_categories)) {
        sendJsonResponse(400, ["message" => "Category '$name' already exists"]);
    }

    $stmt = $conn->prepare(
        "INSERT INTO $categories_table_name
        VALUES (NULL, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("isiss", $userId, $name, $isIncome, $icon, $color);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(201, ["message" => 'Category added successfully']);
    } else {
        sendJsonResponse(400, ["message" => 'Query execution failed: ' . $conn->error]);
    }
    $stmt->close();
}

function handleOptionsRequest($coon) {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, GET, POST');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
}