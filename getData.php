<?php
include 'db_connection.php';

// Get the parameters from the request
$departmentId = isset($_GET['department']) ? intval($_GET['department']) : 0;
$jobRoleId = isset($_GET['jobRole']) ? intval($_GET['jobRole']) : 0;

// Fetch job roles if only department ID is provided
if ($departmentId > 0 && $jobRoleId === 0) {
    $query = $conn->prepare("
        SELECT DISTINCT JobRole.JobRoleID, JobRole.JobRole 
        FROM JobRole
        JOIN Employee ON JobRole.JobRoleID = Employee.JobRoleID
        WHERE Employee.DepartmentID = ?
    ");
    $query->bind_param("i", $departmentId);

    if ($query->execute()) {
        $result = $query->get_result();
        $jobRoles = [];
        while ($row = $result->fetch_assoc()) {
            $jobRoles[] = $row;
        }
        echo json_encode(['success' => true, 'jobRoles' => $jobRoles]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch job roles: ' . $query->error]);
    }
    exit;
}

// Fetch employees if both department ID and job role ID are provided
if ($departmentId > 0 && $jobRoleId > 0) {
    $query = $conn->prepare("
        SELECT Employee.EmployeeID, Employee.Name 
        FROM Employee
        JOIN JobRole ON Employee.JobRoleID = JobRole.JobRoleID
        WHERE Employee.DepartmentID = ? AND Employee.JobRoleID = ?
    ");
    $query->bind_param("ii", $departmentId, $jobRoleId);

    if ($query->execute()) {
        $result = $query->get_result();
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        echo json_encode(['success' => true, 'employees' => $employees]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch employees: ' . $query->error]);
    }
    exit;
}

// If no valid parameters are provided
echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
?>
