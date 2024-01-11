<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

$host = "localhost";
$username = "root";
$password = "";
$database = "hazelmoneydb";

// Nomes das tabelas da bd
$users_table_name = 'users';
$accounts_table_name = 'accounts';
$regular_payments_table_name = 'regular_payments';
$transactions_table_name = 'transactions';
$categories_table_name = 'categories';
$currencies_table_name = 'currencies';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}