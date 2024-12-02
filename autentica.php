<?php
include 'db_connection.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session to keep track of user login
session_start();

if (isset($_GET['logout'])) {
    session_unset(); // Unset all session variables
    session_destroy(); // Destroy the session
    header("Location: login.php"); // Redirect to login page
    exit();
}

// Initialize error message
$error_msg = "";

// Check if form data is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get email and password from POST request
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password']; // Plain-text password for verification

    // SQL query to check if user exists
    $sql = "SELECT * FROM employee WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Fetch user data
        $user = $result->fetch_assoc();
        

        // Verify hashed password
        if (password_verify($password, $user['Password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['EmployeeID'];
            $_SESSION['email'] = $user['Email'];

            // Redirect to the main dashboard or desired page
            header("Location: dashboard_company.php");
            exit();
        } else {
            // Set error message for invalid password
            $error_msg = "Invalid email or password.";
        }
    } else {
        // Set error message for user not found
        $error_msg = "Invalid email or password.";
    }

    // Redirect back to login page with error message
    $_SESSION['error_msg'] = $error_msg;
    header("Location: login.php");
    exit();
}


// Close database connection
$conn->close();
?>
