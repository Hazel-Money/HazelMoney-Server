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
    global $transactions_table_name;
    global $accounts_table_name;
    global $categories_table_name;
    global $users_table_name;
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $t = $transactions_table_name;
        $c = $categories_table_name;
        $stmt = $conn->prepare("SELECT * FROM $transactions_table_name WHERE id = ?");
        $stmt = $conn->prepare(
            "SELECT $t.id, $t.account_id, $t.category_id, $t.amount, $t.is_income, $t.payment_date, $t.description, $c.icon
            FROM $t INNER JOIN $c ON $t.category_id = $c.id
            WHERE  $t.id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result !== false && $result->num_rows > 0) {
            $transaction = $result->fetch_assoc();
            sendJsonResponse(200, $transaction);
            return;
        }
        sendJsonResponse(404, ["message" => 'Transaction not found']);
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

        $t = $transactions_table_name;
        $c = $categories_table_name;

        if (isset($_GET['is_income'])) {
            $stmt = $conn->prepare(
                "SELECT $t.id, $t.account_id, $t.category_id, $t.amount, $t.is_income, $t.payment_date, $t.description, $c.icon
                FROM $t INNER JOIN $c ON $t.category_id = $c.id
                WHERE $t.account_id = ? AND $t.is_income = ?
                GROUP BY $t.id
                ORDER BY $t.payment_date DESC"
            );
            $stmt->bind_param("ii", $accountId, $_GET['is_income']);
        } else {
            $stmt = $conn->prepare(
                "SELECT $t.id, $t.account_id, $t.category_id, $t.amount, $t.is_income, $t.payment_date, $t.description, $c.icon
                FROM $t INNER JOIN $c ON $t.category_id = $c.id
                WHERE $t.account_id = ?
                GROUP BY $t.id
                ORDER BY $t.payment_date DESC"
            );
            $stmt->bind_param("i", $accountId);
        }
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
            sendJsonResponse(404, ["message"=> "User not found"]);
            return;
        }

        $t = $transactions_table_name;
        $a = $accounts_table_name;
        $c = $categories_table_name;
        if (isset($_GET['is_income'])) {
            $stmt = $conn->prepare(
                "SELECT $t.id, $t.account_id, $t.category_id, $t.amount, $t.is_income, $t.payment_date, $t.description, $c.icon
                FROM $a INNER JOIN 
                ($t INNER JOIN $c ON $t.category_id = $c.id) ON $a.id = $t.account_id
                WHERE $a.user_id = ? AND $t.is_income = ?
                GROUP BY $t.id
                ORDER BY $t.payment_date DESC"
            );
            $stmt->bind_param("ii", $userId, $_GET['is_income']);
        }
        else {
            $stmt = $conn->prepare(
                "SELECT $t.id, $t.account_id, $t.category_id, $t.amount, $t.is_income, $t.payment_date, $t.description, $c.icon
                FROM $a INNER JOIN 
                ($t INNER JOIN $c ON $t.category_id = $c.id) ON $a.id = $t.account_id
                WHERE $a.user_id = ? 
                GROUP BY $t.id
                ORDER BY $t.payment_date DESC"
            );
            $stmt->bind_param("i", $userId);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $transactions = [];

        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        sendJsonResponse(200, $transactions);
    } else {
        $result = $conn->query("SELECT * FROM $transactions_table_name");
        $t = $transactions_table_name;
        $c = $categories_table_name;
        if (isset($_GET['is_income'])) {
            $stmt = $conn->prepare(
                "SELECT $t.id, $t.account_id, $t.category_id, $t.amount, $t.is_income, $t.payment_date, $t.description, $c.icon
                FROM $t INNER JOIN $c ON $t.category_id = $c.id
                WHERE $t.is_income = ?
                GROUP BY $t.id
                ORDER BY $t.payment_date DESC"
                );
            $stmt->bind_param("i", $_GET["is_income"]);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query(
                "SELECT $t.id, $t.account_id, $t.category_id, $t.amount, $t.is_income, $t.payment_date, $t.description, $c.icon
                FROM $t INNER JOIN $c ON $t.category_id = $c.id
                GROUP BY $t.id
                ORDER BY $t.payment_date DESC"
            );
        }
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        sendJsonResponse(200, $transactions);
    }
}

function handlePostRequest($conn) {
    global $transactions_table_name;
    global $accounts_table_name;
    $data = json_decode(file_get_contents("php://input"), true);

    $accountId = $data["account_id"] ?? null;
    $categoryId = $data["category_id"] ?? null;
    $amount = isset($data["amount"]) ? intval($data["amount"]) : null;
    $isIncome = isset($data["is_income"]) ? intval($data["is_income"]) : null;
    $paymentDate = $data["payment_date"] ?? null;
    $description = $data["description"] ?? null;

    if (is_null($accountId) || is_null($categoryId) || is_null($amount) || is_null($isIncome) || is_null($paymentDate)) {
        sendJsonResponse(400, ["message" => "All fields are required"]);
        return;
    }

    if ($amount <= 0 || is_nan($amount)) {
        sendJsonResponse(400, ["message" => "Invalid amount"]);
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO $transactions_table_name 
        VALUES (NULL, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $accountId, $categoryId, $amount, $isIncome, $paymentDate, $description);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        sendJsonResponse(400, ["message" => 'Query execution failed: ' . $conn->error]);
        return;
    }

    $operation = ($isIncome == 1) ? '+' : '-';
    $stmt = $conn->prepare(
        "UPDATE $accounts_table_name
        SET balance = balance $operation ?
        WHERE id = ?"
    );
    $stmt->bind_param('si', $amount, $accountId);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        sendJsonResponse(400, ["message"=> $conn->error]);
        return;
    }
    sendJsonResponse(201, ["message" => 'Transaction added successfully']);
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
        sendJsonResponse(404, ["message" => 'Transaction not found']);
        return;
    }

    $stmt = $conn->prepare(
        "UPDATE $transactions_table_name 
        SET account_id = ?, category_id = ?, amount = ?, is_income = ?, payment_date = ?, description = ?
        WHERE id = ?");
    $stmt->bind_param("ssssssi", $accountId, $categoryId, $amount, $isIncome, $paymentDate, $description, $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(200, ["message" => 'Transaction updated successfully']);
    } else {
        sendJsonResponse(400, ["message" => 'Query execution failed: ' . $conn->error]);
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
            sendJsonResponse(404, ["message" => "Transaction not found"]);
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

function handleOptionsRequest($coon) {
    header('Allow: OPTIONS, GET, POST, PUT, DELETE');
    sendJsonResponse(204, []);
}

function sendJsonResponse($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
}
