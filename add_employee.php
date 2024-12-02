<?php
include 'db_connection.php';
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $departmentID = $_POST['department'];
    $jobRoleID = $_POST['jobRole'];
    $managerID = $_POST['managerID'] ?? null;

    $addEmployeeQuery = "INSERT INTO Employee (Name, DepartmentID, JobRoleID, ManagerID) 
                         VALUES ('$name', '$departmentID', '$jobRoleID', " . ($managerID ? "'$managerID'" : "NULL") . ")";

    if ($conn->query($addEmployeeQuery) === TRUE) {
        // Fetch the new employee data
        $newEmployeeID = $conn->insert_id;

        // Fetch the updated hierarchy after adding the new employee
        $employees = getEmployees($conn);
        $updatedHierarchy = buildHierarchy($employees, $managerID ?? $newEmployeeID); // Start with the manager or the new employee as the root

        // Return the updated hierarchy
        echo json_encode([
            "success" => true, 
            "message" => "Employee added successfully", 
            "updatedHierarchy" => $updatedHierarchy
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $conn->error]);
    }
    exit();
}

$conn->close();

// Function to get all employees
function getEmployees($conn) {
    $query = "SELECT Employee.EmployeeID, Employee.Name, JobRole.JobRole AS JobRole, Employee.ManagerID, Employee.DepartmentID 
            FROM Employee
            JOIN JobRole ON Employee.JobRoleID = JobRole.JobRoleID";
    $result = $conn->query($query);
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    return $employees;
}

// Recursive function to build hierarchy starting from a given employee ID
function buildHierarchy($employees, $startID) {
    $hierarchy = [];
    foreach ($employees as $employee) {
        if ($employee['ManagerID'] == $startID) {
            $employee['subordinates'] = buildHierarchy($employees, $employee['EmployeeID']);
            $hierarchy[] = $employee;
        }
    }
    return $hierarchy;
}
?>

