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
    global $transactions_table_name;
    global $accounts_table_name;
    global $users_table_name;
    if (isset($_GET['id'])) {
        $accountId = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM $transactions_table_name WHERE id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result !== false && $result->num_rows > 0) {
            $transaction = $result->fetch_assoc();
            sendJsonResponse(200, $transaction);
            return;
        }
        sendJsonResponse(404, ['error' => 'Transaction not found']);
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

        $stmt = $conn->prepare("SELECT * FROM $transactions_table_name WHERE account_id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $transactions = [];

        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        sendJsonResponse(200, $transactions);
    } elseif (isset($_GET['user_id'])) {
        $userId = $_GET['user_id'];

        $stmt = $conn->prepare("SELECT * FROM $users_table_name WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ["error"=> "User not found"]);
            return;
        }

        $t = $transactions_table_name;
        $stmt = $conn->prepare(
            "SELECT $t.id, $t.account_id, $t.category_id, $t.amount, $t.is_income, $t.payment_date, $t.description 
            FROM $t INNER JOIN $accounts_table_name ON 
            $t.account_id = $accounts_table_name.id WHERE user_id = ? 
            GROUP BY $t.id");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $transactions = [];

        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        sendJsonResponse(200, $transactions);
    } else {
        $result = $conn->query("SELECT * FROM $transactions_table_name");
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        sendJsonResponse(200, $transactions);
    }
}

function handlePostRequest($conn) {
    global $transactions_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $accountId = $data["account_id"];
    $categoryId = $data["category_id"];
    $amount = $data["amount"];
    $isIncome = (int)$data["is_income"];
    $paymentDate = $data["payment_date"];
    $description = $data["description"];
    if ($description == "") {
        $description = null;
    }

    $stmt = $conn->prepare(
        "INSERT INTO $transactions_table_name 
        (account_id, category_id, amount, is_income, payment_date, description)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $accountId, $categoryId, $amount, $isIncome, $paymentDate, $description);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(201, ['message' => 'Transaction added successfully']);
    } else {
        sendJsonResponse(400, ['error' => 'Query execution failed: ' . $conn->error]);
    }
    $stmt->close();
}

function handlePutRequest($conn) {
    global $transactions_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $id = $data["id"];
    $accountId = $data["account_id"];
    $categoryId = $data["category_id"];
    $amount = $data["amount"];
    $isIncome = (int)$data["is_income"];
    $paymentDate = $data["payment_date"];
    $description = $data["description"];
    if ($description == "") {
        $description = null;
    }

    $stmt = $conn->prepare("SELECT * FROM $transactions_table_name WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(404, ['error' => 'Transaction not found']);
        return;
    }

    $stmt = $conn->prepare(
        "UPDATE $transactions_table_name 
        SET account_id = ?, category_id = ?, amount = ?, is_income = ?, payment_date = ?, description = ?
        WHERE id = ?");
    $stmt->bind_param("ssssssi", $accountId, $categoryId, $amount, $isIncome, $paymentDate, $description, $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(200, ['message' => 'Transaction updated successfully']);
    } else {
        sendJsonResponse(400, ['error' => 'Query execution failed: ' . $conn->error]);
    }
    $stmt->close();
}

function handleDeleteRequest($conn) {
    global $transactions_table_name;
    global $accounts_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['id'])) {
        $id = $data['id'];
    
        $stmt = $conn->prepare("DELETE FROM $transactions_table_name WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message" => "Transaction deleted successfully"]);
        } else {
            sendJsonResponse(404, ["error" => "Transaction not found"]);
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

        $stmt = $conn->prepare("DELETE FROM $transactions_table_name WHERE account_id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message"=> "Transactions deleted successfully"]);
        } else {
            sendJsonResponse(200, ["message"=> "Account has no transactions"]);
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM $transactions_table_name");
        $stmt->execute();
    
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(200, ["message" => "Deleted all transactions"]);
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
