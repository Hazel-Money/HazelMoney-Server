<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
$allowedMethods = ['GET', 'OPTIONS'];
header("Access-Control-Allow-Methods: GET, OPTIONS, POST");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Max-Age: 3600");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    handleOptionsRequest();
}

require_once '../db_connection.php';
require_once '../authorization.php';
require_once '../functions.php';
$env = parse_ini_file("../.env");

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
    global $user;
    global $env;
    global $users_table_name;
    

    $result = $conn->query(
        "SELECT profile_picture_path
        FROM $users_table_name u
        WHERE u.id = $user[id]"
    );
    $target_directory = $_SERVER["DOCUMENT_ROOT"] . $env["pfp_path"];
    $file_name = $result->fetch_assoc()["profile_picture_path"];
    $target_file_path = $target_directory . $file_name;
    if (!file_exists($target_file_path)) {
        createDirectoryIfNotExists($target_directory);
        $target_file_path = $target_directory . $env["default_pfp_name"];
        $conn->query(
            "UPDATE $users_table_name u
            SET u.profile_picture_path = $env[default_pfp_name]
            WHERE u.id = $user[id]"
        );
        $image_data = file_get_contents($env["default_pfp_url"]);
        if ($image_data === false) {
            sendJsonResponse(500, ["message" => "Failed to fetch default profile picture image"]);
            return;
        }
        $bytes_written = file_put_contents($target_file_path, $image_data);
        if ($bytes_written === 0) {
            sendJsonResponse(500, ["message" => "Failed to write default profile picture image"]);
            return;
        }
    }
    header('Content-Type: image/jpeg');
    http_response_code(200);
    readfile($target_file_path);
}

function handlePostRequest($conn) {
    if (!isset($_FILES["image"]) || $_FILES["image"]["error"] != 0) {
        $errorMessage = "";

        switch ($_FILES["image"]["error"]) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = "The uploaded file was only partially uploaded.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = "No file was uploaded.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage = "Missing temporary folder.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage = "Failed to write file to disk.";
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMessage = "A PHP extension stopped the file upload.";
                break;
            default:
                $errorMessage = "No file uploaded or an error occurred during upload.";
                break;
        }
        sendJsonResponse(400, ["message" => $errorMessage]);
        return;
    }
    global $user;
    global $env;
    global $users_table_name;
    $supported_mime_types = ['image/jpeg', 'image/png', 'image/gif'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES["image"]["tmp_name"]);

    if (!in_array($mime, $supported_mime_types)) {
        sendJsonResponse(400, ["message" => "Unsupported file type. Only JPG, JPEG, PNG, and GIF files are allowed."]);
        return;
    }

    $target_directory = $_SERVER["DOCUMENT_ROOT"] . $env["pfp_path"];
    $extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
    $file_name = $user['id'] . '_' . time() . ".$extension";
    $target_file_path = $target_directory . $file_name;

    createDirectoryIfNotExists($target_directory);

    if (file_exists($target_file_path)) {
        sendJsonResponse(500, ["message" => "File already exists. Whoever made this function is stupid"]);
        return;
    }
    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file_path)) {
        sendJsonResponse(500, ["message" => "There was an error uploading your file."]);
        return;
    }

    $stmt = $conn->prepare(
        "UPDATE $users_table_name u
        SET profile_picture_path = ?
        WHERE u.id = ?"
    );
    $stmt->bind_param("si", $file_name, $user['id']);
    $result = $stmt->execute();
    if ($result === false) {
        sendJsonResponse(200, ["message" => "Something went wrong idk"]);
        return;
    }
    header('Content-Type: image/jpeg');
    http_response_code(200);
    readfile($target_file_path);
}

function handleOptionsRequest() {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Methods: OPTIONS, GET, POST');
    header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
    exit;
}