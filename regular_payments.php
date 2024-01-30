<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
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
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    handlePutRequest($conn);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    handleDeleteRequest($conn);
} else {
    sendJsonResponse(405, ["message" => "$_SERVER[REQUEST_METHOD] requests are not allowed"]);
}
$conn->close();

function handleGetRequest($conn) {
    global $regular_payments_table_name;
    global $accounts_table_name;
    global $categories_table_name;
    global $frequencies_table_name;
    global $users_table_name;
    global $user;
    if (isset($_GET['id'])) {
        $id = $_GET['id'];

        $stmt = $conn->prepare(
            "SELECT $regular_payments_table_name.id,
            $regular_payments_table_name.account_id,
            $regular_payments_table_name.category_id,
            $regular_payments_table_name.frequency_id,
            $regular_payments_table_name.amount,
            $regular_payments_table_name.is_income,
            $regular_payments_table_name.start_date,
            $regular_payments_table_name.last_payment_date,
            $regular_payments_table_name.description
            FROM $regular_payments_table_name
            INNER JOIN (
                $accounts_table_name INNER JOIN $users_table_name
                ON $accounts_table_name.user_id = $users_table_name.id
            )
            ON $regular_payments_table_name.account_id = $accounts_table_name.id
            WHERE $users_table_name.id = ?
            AND $regular_payments_table_name.id = ?"
        );
        $stmt->bind_param("ii", $user['id'], $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ['message'=> 'Requested payment not found or doesn\'t belong to you']);
            return;
        }
        $regular_payment = $result->fetch_assoc();
        $frequency_result = $conn->query("SELECT id, sql_interval FROM $frequencies_table_name WHERE id = $regular_payment[frequency_id]");
        $frequency_row = $frequency_result->fetch_assoc();
        $frequency = $frequency_row['sql_interval'];
        $last_payment_date = $regular_payment['last_payment_date'];

        $date_result = $conn->query("SELECT DATE_ADD('$last_payment_date', INTERVAL 1 $frequency) AS next_payment_date");
        $date_row = $date_result->fetch_assoc();

        $regular_payment['next_payment_date'] = $date_row['next_payment_date'];
        sendJsonResponse(200, $regular_payment);
    } elseif (isset($_GET['account_id'])) {
        $accountId = $_GET['account_id'];

        // $stmt = $conn->prepare("SELECT * FROM $accounts_table_name WHERE id = ?");
        // $stmt->bind_param("i", $accountId);
        // $stmt->execute();
        // $result = $stmt->get_result();
        // if ($result === false || $result->num_rows === 0) {
        //     sendJsonResponse(404, ["message"=> "Account not found"]);
        //     return;
        // }

        // $stmt = $conn->prepare("SELECT * FROM $regular_payments_table_name WHERE account_id = ?");
        // $stmt->bind_param("i", $accountId);
        // $stmt->execute();
        // $result = $stmt->get_result();
        
        $stmt = $conn->prepare(
            "SELECT $regular_payments_table_name.id,
            $regular_payments_table_name.account_id,
            $regular_payments_table_name.category_id,
            $regular_payments_table_name.frequency_id,
            $regular_payments_table_name.amount,
            $regular_payments_table_name.is_income,
            $regular_payments_table_name.start_date,
            $regular_payments_table_name.last_payment_date,
            $regular_payments_table_name.description
            FROM $regular_payments_table_name
            INNER JOIN (
                $accounts_table_name INNER JOIN $users_table_name
                ON $accounts_table_name.user_id = $users_table_name.id
            )
            ON $regular_payments_table_name.account_id = $accounts_table_name.id
            WHERE $users_table_name.id = ?
            AND $accounts_table_name.id = ?"
        );
        $stmt->bind_param("ii", $user['id'], $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        $regular_payments = [];
        
        while ($row = $result->fetch_assoc()) {
            $frequency_result = $conn->query("SELECT id, sql_interval FROM $frequencies_table_name WHERE id = $row[frequency_id]");
            $frequency_row = $frequency_result->fetch_assoc();
            $frequency = $frequency_row['sql_interval'];
            $last_payment_date = $row['last_payment_date'];

            $date_result = $conn->query("SELECT DATE_ADD('$last_payment_date', INTERVAL 1 $frequency) AS next_payment_date");
            $date_row = $date_result->fetch_assoc();
            $row['next_payment_date'] = $date_row['next_payment_date'];

            $regular_payments[] = $row;
        }
        
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ['message' => 'Account not found or not authorized']);
            return;
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
            "SELECT $rp.id, $rp.account_id, $rp.category_id, $rp.amount, $rp.is_income, $rp.frequency_id, $rp.start_date, $rp.last_payment_date, $rp.description
            FROM $rp INNER JOIN $accounts_table_name ON 
            $rp.account_id = $accounts_table_name.id WHERE user_id = ? 
            GROUP BY $rp.id");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $regular_payments = [];

        while ($row = $result->fetch_assoc()) {
            $frequency_result = $conn->query("SELECT id, sql_interval FROM $frequencies_table_name WHERE id = $row[frequency_id]");
            $frequency_row = $frequency_result->fetch_assoc();
            $frequency = $frequency_row['sql_interval'];
            $last_payment_date = $row['last_payment_date'];

            $date_result = $conn->query("SELECT DATE_ADD('$last_payment_date', INTERVAL 1 $frequency) AS next_payment_date");
            $date_row = $date_result->fetch_assoc();
            $row['next_payment_date'] = $date_row['next_payment_date'];

            $regular_payments[] = $row;
        }
        sendJsonResponse(200, $regular_payments);
    } else {
        $result = $conn->query("SELECT * FROM $regular_payments_table_name");
        $regular_payments = [];
        while ($row = $result->fetch_assoc()) {
            $frequency_result = $conn->query("SELECT id, sql_interval FROM $frequencies_table_name WHERE id = $row[frequency_id]");
            $frequency_row = $frequency_result->fetch_assoc();
            $frequency = $frequency_row['sql_interval'];
            $last_payment_date = $row['last_payment_date'];

            $date_result = $conn->query("SELECT DATE_ADD('$last_payment_date', INTERVAL 1 $frequency) AS next_payment_date");
            $date_row = $date_result->fetch_assoc();
            $row['next_payment_date'] = $date_row['next_payment_date'];

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

function handleOptionsRequest() {
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