<?php
require 'db.php';

if (isset($_FILES['csv']['tmp_name'])) {
    $handle = fopen($_FILES['csv']['tmp_name'], 'r');
    fgetcsv($handle); // skip header

    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        list($name, $sid, $ern, $batch, $dept, $ayear, $mobile, $pmobile, $city, $address) = $data;

        $stmt = $pdo->prepare("INSERT INTO student_information 
            (STUDENT_NAME, STUDENT_ID, ERN_NO, BATCH, DEPARTMENT, AYEAR, MOBILE, PARENT_MOB, CITY, ADDRESS) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $sid, $ern, $batch, $dept, $ayear, $mobile, $pmobile, $city, $address]);
    }

    fclose($handle);
    echo "CSV uploaded successfully.";
}
?>











