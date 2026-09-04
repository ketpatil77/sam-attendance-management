<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $stmt = $pdo->prepare("INSERT INTO student_information 
            (STUDENT_NAME, STUDENT_ID, ERN_NO, BATCH, DEPARTMENT, AYEAR, MOBILE, PARENT_MOB, CITY, ADDRESS, PHOTO)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'], $_POST['sid'], $_POST['ern'], $_POST['batch'], $_POST['dept'],
            $_POST['year'], $_POST['mobile'], $_POST['pmobile'], $_POST['city'], $_POST['address'],
            base64_decode($_POST['photo']) // decode base64 string
        ]);
        echo "Student added.";
    }

    elseif ($action === 'read') {
        $stmt = $pdo->query("SELECT * FROM student_information");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (!empty($row['PHOTO'])) {
                $row['PHOTO'] = 'data:image/jpeg;base64,' . base64_encode($row['PHOTO']);
            } else {
                $row['PHOTO'] = '';
            }
        }
        unset($row);
        echo json_encode($rows);
    }

    elseif ($action === 'update') {
        $params = [
            $_POST['name'], $_POST['sid'], $_POST['ern'], $_POST['batch'], $_POST['dept'],
            $_POST['year'], $_POST['mobile'], $_POST['pmobile'], $_POST['city'], $_POST['address']
        ];

        $sql = "UPDATE student_information SET 
                STUDENT_NAME = ?, STUDENT_ID = ?, ERN_NO = ?, BATCH = ?, DEPARTMENT = ?, AYEAR = ?, 
                MOBILE = ?, PARENT_MOB = ?, CITY = ?, ADDRESS = ?";

        if (!empty($_POST['photo'])) {
            $sql .= ", PHOTO = ?";
            $params[] = base64_decode($_POST['photo']);
        }

        $sql .= " WHERE RID = ?";
        $params[] = $_POST['rid'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo "Student updated.";
    }

    elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM student_information WHERE RID = ?");
        $stmt->execute([$_POST['rid']]);
        echo "Student deleted.";
    }
}











