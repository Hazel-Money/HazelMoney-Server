<?php
include_once 'db_connection.php';
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
        return json_encode(["error" => "JWT Authorization Required"]);
    }
    $authHeaderArray = explode(" ", $authHeader);
    if (isset($authHeaderArray[1])) {
        $jwt = $authHeaderArray[1];
        $key = $env['secret'];
        $alg = $env['jwt_alg'];
        try {
            $decoded = JWT::decode($jwt, new Key($key, $alg));
            return json_encode($decoded);
        } catch (Exception $e) {
            return json_encode(["error" => $e->getMessage()]);
        }   
    } else {
        return json_encode(["error" => "JWT Authorization Required"]);
    }
}