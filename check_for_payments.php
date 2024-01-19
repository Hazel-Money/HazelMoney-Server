<?php
require_once 'db_connection.php';

global $regular_payments_table_name;
global $frequencies_table_name;

$result = $conn->query("SELECT * FROM $regular_payments_table_name WHERE id = 2");
$row = $result->fetch_assoc();
$last_payment_date = $row['last_payment_date'];

$result = $conn->query("SELECT sql_interval FROM $frequencies_table_name WHERE id = $row[frequency_id]");
$row = $result->fetch_assoc();
$frequency = $row['sql_interval'];

// $result = $conn->query("SELECT TIMESTAMPDIFF($frequency[sql_interval], $regular_payment[last_payment_date], NOW())");
$result = $conn->query("SELECT TIMESTAMPDIFF($frequency, '$last_payment_date', NOW())");
$time_difference = $result->fetch_row();
echo json_encode($time_difference);