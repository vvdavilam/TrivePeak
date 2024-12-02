<?php
include 'db_connection.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve each user and re-hash their password
$sql = "SELECT EmployeeID, Password FROM employee";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($user = $result->fetch_assoc()) {
        // Hash the plain-text password using password_hash
        $hashedPassword = password_hash($user['Password'], PASSWORD_DEFAULT);
        
        // Update the hashed password in the database
        $updateSql = "UPDATE employee SET Password = '$hashedPassword' WHERE EmployeeID = " . $user['EmployeeID'];
        $conn->query($updateSql);
    }
    echo "Passwords updated with secure hashing!";
} else {
    echo "No users found.";
}

// Close the connection
$conn->close();
?>
