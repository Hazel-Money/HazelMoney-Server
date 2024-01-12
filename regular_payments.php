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
} else {
    sendJsonResponse(405, ["error" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
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
        sendJsonResponse(404, ['error' => 'Regular payment not found']);
    } elseif (isset($_GET['account_id'])) {
        $accountId = $_GET['account_id'];

        $stmt = $conn->prepare("SELECT * FROM $accounts_table_name WHERE id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ["error"=> "Account not found"]);
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
            sendJsonResponse(404, ["error"=> "User not found"]);
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
    $data = json_decode(file_get_contents("php://input"), true);

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

    $stmt = $conn->prepare("INSERT INTO $regular_payments_table_name VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $accountId, $categoryId, $frequencyId, $amount, $isIncome, $startDate, $lastPaymentDate, $description);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(201, ['message' => 'Regular payment added successfully']);
    } else {
        sendJsonResponse(400, ['error' => 'Query execution failed: ' . $conn->error]);
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
        sendJsonResponse(404, ['error' => 'Regular payment not found']);
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
        sendJsonResponse(200, ['message' => 'Regular payment updated successfully']);
    } else {
        sendJsonResponse(400, ['error' => 'Query execution failed: ' . $conn->error]);
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
            sendJsonResponse(404, ["error" => "Regular payment not found"]);
        }
    } elseif (isset($data['account_id'])) {
        $accountId = $data['account_id'];

        $stmt = $conn->prepare("SELECT * FROM $accounts_table_name WHERE id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ["error" => "Account not found"]);
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

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}
?>
