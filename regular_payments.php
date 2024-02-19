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
require_once 'functions.php';
$env = parse_ini_file(".env");

$user = authorizeUser();
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
    global $currencies_table_name;
    global $user;
    global $isAdmin;

    if (isset($_GET['id'])) {
        $id = $_GET['id'];

        $stmt = $conn->prepare(
            "SELECT r.id,
            r.account_id,
            r.category_id,
            r.frequency_id,
            r.amount,
            r.is_income,
            r.start_date,
            r.last_payment_date,
            r.description,
            c.symbol AS currency
            FROM $regular_payments_table_name r
            JOIN $accounts_table_name a ON r.account_id = a.id
            JOIN $currencies_table_name c ON a.currency_id = c.id
            JOIN $users_table_name u ON a.user_id = u.id
            WHERE u.id = ?
            AND r.id = ?
            GROUP BY r.id"
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

        $stmt = $conn->prepare("SELECT * FROM $accounts_table_name WHERE id = ?");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ["message"=> "Account not found"]);
            return;
        }

        $stmt = $conn->prepare(
            "SELECT *
            FROM $accounts_table_name
            WHERE user_id = ? AND id = ?"
        );
        $stmt->bind_param("ii", $user['id'], $accountId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(403, ["message"=> "You are not permitted to access this account's data"]);
            return;
        }
        
        $stmt = $conn->prepare(
            "SELECT r.id,
            r.account_id,
            r.category_id,
            r.frequency_id,
            r.amount,
            r.is_income,
            r.start_date,
            r.last_payment_date,
            r.description,
            c.symbol AS currency
            FROM $regular_payments_table_name r
            JOIN $accounts_table_name a ON r.account_id = a.id
            JOIN $currencies_table_name c ON a.currency_id = c.id
            JOIN $users_table_name u ON u.id = a.user_id
            WHERE u.id = ?
            AND a.id = ?
            GROUP BY r.id"
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
        sendJsonResponse(200, $regular_payments);
        $stmt->close();
    } elseif (isset($_GET['user_id'])) {
        $userId = $_GET['user_id'];

        if ($userId != $user['id']) {
            sendJsonResponse(403, ['message'=> 'You are not permitted to access this user\'s data']);
            return;
        }

        $stmt = $conn->prepare("SELECT * FROM $users_table_name WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(404, ["message"=> "User not found"]);
            return;
        }

        $stmt = $conn->prepare(
            "SELECT r.id,
            r.account_id,
            r.category_id,
            r.amount,
            r.is_income,
            r.frequency_id,
            r.start_date,
            r.last_payment_date,
            r.description,
            c.symbol AS currency
            FROM $regular_payments_table_name r
            JOIN $accounts_table_name a ON r.account_id = a.id
            JOIN $currencies_table_name c ON a.currency_id = c.id
            WHERE user_id = ? 
            GROUP BY r.id"
        );
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
        $stmt->close();
    } else {
        if (!$isAdmin) {
            sendJsonResponse(403, ['message'=> 'You are not allowed to access all regular payments']);
        }
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
    global $categories_table_name;
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

    if ($amount <= 0 || !is_numeric($amount)) {
        sendJsonResponse(400, ["message" => "Invalid amount"]);
        return;
    }
    if (($isIncome != 0 && $isIncome != 1) || !is_numeric($isIncome)) {
        sendJsonResponse(400, ["message" => "Invalid transaction type"]);
        return;
    }

    $date1 = new DateTime("now");
    $format = 'Y-m-d H:i:s';
    $date2 = DateTime::createFromFormat($format, $startDate);
    if ($date2 === false || $date2->format($format) !== $startDate) {
        sendJsonResponse(400, ["message" => "Invalid date format"]);
        return;
    }
    
    $date_diff = date_diff($date1, $date2, true);
    if ($date1 > $date2 && $date_diff->y >= 10) {
        sendJsonResponse(400, ["message"=> "Invalid date - date is too ancient"]);
        return;
    }

    $stmt = $conn->prepare(
        "SELECT * FROM $categories_table_name
        WHERE id = ?"
    );
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        sendJsonResponse(400, ["message"=> "Invalid category"]);
        return;
    }
    $category = $result->fetch_assoc();
    if (intval($category['is_income']) != $isIncome) {
        sendJsonResponse(400, ["message"=> "Category and transaction type conflict"]);
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
    global $accounts_table_name;
    global $categories_table_name;
    global $frequencies_table_name;
    global $user;
    $data = json_decode(file_get_contents("php://input"), true);

    $id = $data["id"] ?? null;
    $categoryId = $data["category_id"] ?? null;
    $frequencyId = $data["frequency_id"] ?? null;
    $amount = $data["amount"] ?? null;
    $description = $data["description"] ?? null;

    $hasEmptyData = hasEmptyData([$id, $categoryId, $frequencyId, $amount]);

    if ($hasEmptyData) {
        sendJsonResponse(400, ["message"=> "All fields are required"]);
        return;
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
        "SELECT a.user_id FROM
        $accounts_table_name a
        JOIN $regular_payments_table_name r
        ON a.id = r.account_id
        WHERE a.user_id = ?
        AND r.id = ?"
    );

    $stmt->bind_param("ii", $user['id'], $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false || $result->num_rows === 0) {
        sendJsonResponse(403, ["message" => "You are not permitted to access this regular payment"]);
        return;
    }

    $stmt = $conn->prepare(
        "SELECT * FROM $categories_table_name
        WHERE id = ?"
    );
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        sendJsonResponse(400, ["message"=> "Invalid category"]);
        return;
    }

    $stmt = $conn->prepare(
        "SELECT * FROM $frequencies_table_name
        WHERE id = ?"
    );
    $stmt->bind_param("i", $frequencyId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        sendJsonResponse(400, ["message"=> "Invalid frequency"]);
        return;
    }
    
    // $date1 = new DateTime("now");
    // $format = 'Y-m-d H:i:s';
    // $date2 = DateTime::createFromFormat($format, $paymentDate);
    // if ($date2 === false || $date2->format($format) !== $paymentDate) {
    //     sendJsonResponse(400, ["message" => "Invalid date format"]);
    //     return;
    // }
    
    // $date_diff = date_diff($date1, $date2, true);
    // if ($date1 > $date2 && $date_diff->y >= 1) {
    //     sendJsonResponse(400, ["message"=> "Invalid date - date is too ancient"]);
    //     return;
    // }
    // if ($date1 < $date2) {
    //     sendJsonResponse(400, ["message"=> "Invalid date - date is in the future"]);
    //     return;
    // }

    $stmt = $conn->prepare(
        "UPDATE $regular_payments_table_name 
        SET category_id = ?, frequency_id = ? amount = ?, description = ?
        WHERE id = ?");
    $stmt->bind_param("iiisi", $categoryId, $frequencyId, $amount, $description, $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(200, ["message" => 'Transaction updated successfully']);
    } else {
        sendJsonResponse(400, ["message" => 'Query execution failed: ' . $conn->error]);
    }
    $stmt->close();
}

function handleDeleteRequest($conn) {
    global $regular_payments_table_name;
    global $accounts_table_name;
    global $user;
    global $isAdmin;
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['id'])) {
        $id = $data['id'];

        $stmt = $conn->prepare(
            "SELECT a.user_id FROM
            $accounts_table_name a
            JOIN $regular_payments_table_name r
            ON a.id = r.account_id
            WHERE a.user_id = ?
            AND r.id = ?"
        );
        $stmt->bind_param("ii", $user['id'], $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(403, ["message" => "You are not permitted to access this regular payment"]);
            return;
        }
    
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
        $stmt = $conn->prepare(
            "SELECT a.id
            FROM $accounts_table_name a
            WHERE a.user_id = ?"
        );
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            sendJsonResponse(403, ['message'=> 'You are not permitted to access this account\'s regular payments']);
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
        if (!$isAdmin) {
            sendJsonResponse(403, "You are not permitted to delete all regular payments");
            return;
        }
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
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
}