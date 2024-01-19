<?php
require_once 'db_connection.php';

global $regular_payments_table_name;
global $frequencies_table_name;
global $transactions_table_name;
global $accounts_table_name;

$regular_payments = $conn->query("SELECT * FROM $regular_payments_table_name");
while ($rp_row = $regular_payments->fetch_assoc()) {
    $id = $rp_row["id"];
    $account_id = $rp_row['account_id'];
    $category_id = $rp_row['category_id'];
    $frequency_id = $rp_row['frequency_id'];
    $amount = $rp_row['amount'];
    $is_income = (int)$rp_row['is_income'];
    $last_payment_date = $rp_row['last_payment_date'];
    $description = $rp_row['description'];
    
    $result = $conn->query("SELECT id, sql_interval FROM $frequencies_table_name WHERE id = $frequency_id");
    $row = $result->fetch_assoc();
    $frequency = $row['sql_interval'];
    $time_division_factor = ($row['id'] == 3) ? 2 : 1;
    //codigo fixe
    $result = $conn->query("SELECT TIMESTAMPDIFF($frequency, '$last_payment_date', NOW()) AS timediff");
    $row = $result->fetch_row();
    $time_difference = $row[0];

    if ($time_difference <= 0) {
        continue;
    }

    $conn->query(
        "UPDATE $regular_payments_table_name 
        SET last_payment_date = DATE_ADD('$last_payment_date', INTERVAL $time_difference $frequency)
        WHERE id = $id"
    );

    for ($i = 1; $i <= $time_difference; $i++) {
        $interval = $i * $time_division_factor;
        $conn->query(
            "INSERT INTO $transactions_table_name
            VALUES (
            NULL,
            $account_id,
            $category_id,
            $amount,
            $is_income,
            DATE_ADD('$last_payment_date', INTERVAL $interval $frequency),
            '$description'
            )"
        );
    }
}