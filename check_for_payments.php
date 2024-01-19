<?php
require_once 'db_connection.php';

global $regular_payments_table_name;
global $frequencies_table_name;
$result = $conn->query("SELECT * FROM $regular_payments_table_name WHERE id = 2");
$regular_payment = $result->fetch_assoc();
$result = $conn->query("SELECT sql_interval FROM $frequencies_table_name WHERE id = $regular_payment[frequency_id]");
$frequency = $result->fetch_assoc();
// $result = $conn->query("SELECT TIMESTAMPDIFF($frequency[sql_interval], $regular_payment[last_payment_date], NOW())");
$result = $conn->query("SELECT TIMESTAMPDIFF(WEEK, '2022-01-01', NOW())");
$time_difference = $result->fetch_row();
echo json_encode($time_difference);