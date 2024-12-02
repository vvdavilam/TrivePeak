<?php
// Check if the session has already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'db_connection.php';

// Default values
$_SESSION['userPhoto'] = 'images/default.png';
$_SESSION['userName'] = 'User';
$_SESSION['userJobRole'] = 'Job Role';
$_SESSION['userEmail'] = 'example@example.com';

// Check if the user is logged in
if (isset($_SESSION['user_id'])) {
    $userID = $_SESSION['user_id'];

    // Get user data from the database
    $query = "SELECT Employee.Name, Employee.Photo, Employee.Email, JobRole.JobRole 
              FROM Employee 
              JOIN JobRole ON Employee.JobRoleID = JobRole.JobRoleID 
              WHERE Employee.EmployeeID = $userID";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $loggedInEmployee = $result->fetch_assoc();

        // Store user data in session variables
        $_SESSION['userName'] = $loggedInEmployee['Name'];
        $_SESSION['userPhoto'] = !empty($loggedInEmployee['Photo']) ? $loggedInEmployee['Photo'] : 'images/default.png';
        $_SESSION['userJobRole'] = $loggedInEmployee['JobRole'];
        $_SESSION['userEmail'] = $loggedInEmployee['Email'];
    }
}
?>
