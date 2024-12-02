<?php
include 'db_connection.php';
session_start();

if (isset($_GET['departmentID'])) {
    $departmentID = intval($_GET['departmentID']); // Sanitize input

    $query = "SELECT EmployeeID, Name FROM Employee WHERE DepartmentID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $departmentID);
    $stmt->execute();
    $result = $stmt->get_result();

    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }

    echo json_encode($employees);
    exit;
}
?>
