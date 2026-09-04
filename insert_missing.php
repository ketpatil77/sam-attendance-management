<?php
require 'db.php';

$students = [
    ['Sutar Nitin Kanhaiyalal', 'STU172', '2505365111251501'],
    ['Deshmukh Utkarsha Amrutrao', 'STU173', '2505365111251502'],
    ['Desale Rajshri Pratap', 'STU174', '2505365111251503'],
    ['More Sakshi Sanjivkumar', 'STU175', '2505365111251504'],
    ['Patil Khumesh Manilal', 'STU176', '2505365111251505'],
    ['Patil Vivek Suklal', 'STU177', '2505365111251506'],
    ['Chaudhari Niraj Vilas', 'STU178', '2505365111251507'],
    ['Wadile Vyankatesh Jagadish', 'STU179', '2505365111251508'],
    ['Mahajan Rahul Kantilal', 'STU180', '2505365111251509']
];

$stmt = $pdo->prepare('INSERT INTO student_information 
    (STUDENT_NAME, STUDENT_ID, ERN_NO, BATCH, DEPARTMENT, AYEAR, MOBILE, PARENT_MOB, CITY, ADDRESS) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

$inserted = 0;
foreach ($students as $s) {
    try {
        $stmt->execute([$s[0], $s[1], $s[2], '2024-28', 'CT', 'TE', '1234567890', '1234567890', 'Dondaicha', 'Dondaicha']);
        $inserted++;
    } catch (Exception $e) {
        echo $e->getMessage() . "\n";
    }
}
echo "Inserted: $inserted\n";











