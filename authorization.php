<?php
include_once 'db_connection.php';
require "vendor/autoload.php";
use \Firebase\JWT\JWT;
use Firebase\JWT\Key;
$env = parse_ini_file(".env");

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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
        return false;
    }
    $authHeaderArray = explode(" ", $authHeader);
    if (isset($authHeaderArray[1])) {
        $jwt = $authHeaderArray[1];
        try {
            $alg = 'HS256';
            $decoded = JWT::decode($jwt, new Key($env['secret'], 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            return $e->getMessage();
        }   
    }
}