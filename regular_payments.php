<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 3600");

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
    sendJsonResponse(405, ["message" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
}
$conn->close();

function handleGetRequest($conn) {
    global $regular_payments_table_name;
    global $accounts_table_name;
    global $categories_table_name;
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM $regular_payments_table_name WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result !== false && $result->num_rows > 0) {
            $regular_payment = $result->fetch_assoc();
            sendJsonResponse(200, $regular_payment);
            return;
        }
        sendJsonResponse(404, ["message" => 'Regular payment not found']);
    } elseif (isset($_GET['account_id'])) {
        $accountId = $_GET['account_id'];

        $stmt = $conn->prepare("SELECT * FROM $accounts_table_name WHERE id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ["message"=> "Account not found"]);
            return;
        }

        $stmt = $conn->prepare("SELECT * FROM $regular_payments_table_name WHERE account_id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $regular_payments = [];

        while ($row = $result->fetch_assoc()) {
            $regular_payments[] = $row;
        }
        sendJsonResponse(200, $regular_payments);
    } elseif (isset($_GET['user_id'])) {
        $userId = $_GET['user_id'];

        $stmt = $conn->prepare("SELECT * FROM $categories_table_name WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ["message"=> "User not found"]);
            return;
        }

        $rp = $regular_payments_table_name;
        $stmt = $conn->prepare(
            "SELECT $rp.id, $rp.account_id, $rp.category_id, $rp.amount, $rp.is_income, $rp.start_date, $rp.last_payment_date, $rp.description
            FROM $rp INNER JOIN $accounts_table_name ON 
            $rp.account_id = $accounts_table_name.id WHERE user_id = ? 
            GROUP BY $rp.id");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $regular_payments = [];

        while ($row = $result->fetch_assoc()) {
            $regular_payments[] = $row;
        }
        sendJsonResponse(200, $regular_payments);
    } else {
        $result = $conn->query("SELECT * FROM $regular_payments_table_name");
        $regular_payments = [];
        while ($row = $result->fetch_assoc()) {
            $regular_payments[] = $row;
        }
        sendJsonResponse(200, $regular_payments);
    }
}

function handlePostRequest($conn) {
    global $regular_payments_table_name;
    global $frequencies_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $accountId = $data["account_id"] ?? null;
    $categoryId = $data["category_id"] ?? null;
    $frequencyId = $data["frequency_id"] ?? null;
    $amount = $data["amount"] ?? null;
    $isIncome = $data["is_income"] ?? null;
    $startDate = $data["start_date"] ?? null;
    $description = $data["description"] ?? null;

    $hasEmptyData = hasEmptyData([$accountId, $categoryId, $frequencyId, $amount, $isIncome, $startDate]);
    if ($hasEmptyData) {
        sendJsonResponse(400, ["message" => "All fields are required"]);
        return;
    }

    $stmt = $conn->prepare("SELECT sql_interval FROM $frequencies_table_name WHERE id = ?");
    $stmt->bind_param("i", $frequencyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $frequency = $result->fetch_assoc()['sql_interval'];
    
    $result = $conn->query("SELECT DATE_ADD('$startDate', INTERVAL -1 $frequency) as last_payment_date");
    $lastPaymentDate = $result->fetch_assoc()['last_payment_date'];

    $stmt = $conn->prepare("INSERT INTO $regular_payments_table_name VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $accountId, $categoryId, $frequencyId, $amount, $isIncome, $startDate, $lastPaymentDate, $description);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(201, ["message" => 'Regular payment added successfully']);
    } else {
        sendJsonResponse(400, ["message" => 'Query execution failed: ' . $conn->error]);
    }
    $stmt->close();
}

function handlePutRequest($conn) {
    global $regular_payments_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $id = $data["id"];
    $accountId = $data["account_id"];
    $categoryId = $data["category_id"];
    $frequencyId = $data["frequency_id"];
    $amount = $data["amount"];
    $isIncome = (int)$data["is_income"];
    $startDate = $data["start_date"];
    $lastPaymentDate = $data["start_date"];
    $description = $data["description"];
    if ($description == "") {
        $description = null;
    }

    $stmt = $conn->prepare("SELECT * FROM $regular_payments_table_name WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(404, ["message" => 'Regular payment not found']);
        return;
    }

    $stmt = $conn->prepare(
        "UPDATE $regular_payments_table_name 
        SET account_id = ?, category_id = ?, frequency_id = ?, amount = ?,
        is_income = ?, start_date = ?, last_payment_date = ?, description = ?
        WHERE id = ?"
    );
    $stmt->bind_param("ssssssssi", $accountId, $categoryId, $frequencyId, $amount, $isIncome, $startDate, $lastPaymentDate, $description, $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(200, ["message" => 'Regular payment updated successfully']);
    } else {
        sendJsonResponse(400, ["message" => 'Query execution failed: ' . $conn->error]);
    }
    $stmt->close();
}

function handleDeleteRequest($conn) {
    global $regular_payments_table_name;
    global $accounts_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['id'])) {
        $id = $data['id'];
    
        $stmt = $conn->prepare("DELETE FROM $regular_payments_table_name WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message" => "Regular payment deleted successfully"]);
        } else {
            sendJsonResponse(404, ["message" => "Regular payment not found"]);
        }
    } elseif (isset($data['account_id'])) {
        $accountId = $data['account_id'];

        $stmt = $conn->prepare("SELECT * FROM $accounts_table_name WHERE id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ["message" => "Account not found"]);
            return;
        }

        $stmt = $conn->prepare("DELETE FROM $regular_payments_table_name WHERE account_id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message"=> "Regular payments deleted successfully"]);
        } else {
            sendJsonResponse(200, ["message"=> "Account has no regular payments"]);
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM $regular_payments_table_name");
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message" => "Deleted all regular payments"]);
        } else {
            sendJsonResponse(200, ["message" => "No transactions were found"]);
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

function hasEmptyData(array $data) {
    foreach ($data as $element) {
        if (is_null($element) || empty($element) && $element != 0) {
            return true;
        }
    }
    return false;
}