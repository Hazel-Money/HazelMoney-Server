<?php
include_once 'db_connection.php';
include_once 'functions.php';
require "vendor/autoload.php";
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;
$env = parse_ini_file(".env");

function authorizeUser() {
    global $env;
    $authHeader = null;
    if (isset($_SERVER['AUTHORIZATION'])) {
        $authHeader = $_SERVER['AUTHORIZATION'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER["HTTP_AUTHORIZATION"];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER["REDIRECT_HTTP_AUTHORIZATION"];
    } else {
        sendJsonResponse(401, ["message" => "JWT Authorization Required"]);
    }
    $authHeaderArray = explode(" ", $authHeader);
    if (isset($authHeaderArray[1])) {
        $jwt = $authHeaderArray[1];
        $key = $env['secret'];
        $alg = $env['jwt_alg'];
        try {
            $decoded = JWT::decode($jwt, new Key($key, $alg));
            return (array)$decoded->data;
        } catch (Exception $e) {
            sendJsonResponse(401, ["message" => $e->getMessage()]);
        }   
    } else {
        sendJsonResponse(401, ["message" => "JWT Authorization Required"]);
    }
}