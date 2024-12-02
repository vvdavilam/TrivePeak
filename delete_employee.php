<?php
include 'db_connection.php';

$employeeId = isset($_POST['employee1']) ? intval($_POST['employee1']) : 0;

if ($employeeId > 0) {
    $query = $conn->prepare("DELETE FROM Employee WHERE EmployeeID = ?");
    $query->bind_param("i", $employeeId);

    if ($query->execute()) {
        echo json_encode(['success' => true, 'message' => 'Employee deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting employee: ' . $query->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID.']);
}
?>
