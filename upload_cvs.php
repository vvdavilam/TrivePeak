<?php
include 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $file = $_FILES['csvFile'];

    // Validate file type
    $allowedExtensions = ['csv'];
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (!in_array($fileExtension, $allowedExtensions)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only CSV files are allowed.']);
        exit;
    }

    // Move uploaded file to the uploads directory
    $filePath = 'uploads/' . $file['name'];
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload the file.']);
        exit;
    }

    try {
        // Open the file and process it
        $fileHandle = fopen($filePath, 'r');
        if ($fileHandle === false) {
            throw new Exception("Unable to open the CSV file.");
        }
    
        // Read and debug the header
        $header = fgetcsv($fileHandle);
        if (!$header || count($header) < 7) {
            error_log("Header: " . ($header ? implode(",", $header) : "Empty header"));
            throw new Exception("Invalid or incomplete CSV header. Ensure the CSV has the correct format.");
        }
    
        // Debug header contents
        error_log("CSV Header: " . implode(",", $header));
    
        // Prepare the SQL statement
        $stmt = $conn->prepare("
            INSERT INTO Employee (Name, Email, DepartmentID, JobRoleID, ManagerID, Password, Photo) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
    
        // Process each row in the CSV file
        while (($row = fgetcsv($fileHandle)) !== false) {
            // Validate CSV data
            if (count($row) < 7) {
                error_log("Invalid row: " . implode(",", $row));
                continue; // Skip rows with incomplete data
            }
    
            // Extract and sanitize values
            $name = trim($row[0]);
            $email = trim($row[1]);
            $departmentID = intval($row[2]);
            $jobRoleID = intval($row[3]);
            $managerID = empty($row[4]) ? null : intval($row[4]);
            $password = password_hash(trim($row[5]), PASSWORD_DEFAULT);
            $photo = trim($row[6]);
    
            // Bind parameters and execute the query
            $stmt->bind_param("ssiisss", $name, $email, $departmentID, $jobRoleID, $managerID, $password, $photo);
            if (!$stmt->execute()) {
                error_log("SQL Error: " . $stmt->error);
            }
        }
    
        fclose($fileHandle);
        $stmt->close();
    
        unlink($filePath);

    
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
}
?>
