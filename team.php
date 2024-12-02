<?php
include 'db_connection.php';
include ("inc/top.php");
include ("inc/check_user.php");

// Initialize variables
$hasDepartmentOne = false;
$loggedInEmployee = null;
$managerHierarchy = [];

// Function to get all employees for building hierarchy
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

// Recursive function to build the employee hierarchy
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

// Define the displayHierarchy function
function displayHierarchy($hierarchy) {
    echo '<ul class="hierarchy">';
    foreach ($hierarchy as $employee) {
        echo '<li>';
        echo '<div class="team-member">';
        echo '<div class="role-box">';
        echo '<p>' . $employee['JobRole'] . '</p>';
        echo '<span>' . $employee['Name'] . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Display subordinates if they exist
        if (!empty($employee['subordinates'])) {
            displayHierarchy($employee['subordinates']);
        }
        echo '</li>';
    }
    echo '</ul>';
}

// Check if the user is logged in
if (isset($_SESSION['user_id'])) {
    $userID = $_SESSION['user_id'];

    // Query to get the DepartmentID, EmployeeID, JobRole, and ManagerID for the logged-in user
    $query = "SELECT Employee.EmployeeID, Employee.Name, JobRole.JobRole AS JobRole, Employee.DepartmentID, Employee.ManagerID, Employee.Photo
            FROM Employee
            JOIN JobRole ON Employee.JobRoleID = JobRole.JobRoleID
            WHERE Employee.EmployeeID = $userID";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $loggedInEmployee = $result->fetch_assoc();

        // Set user photo path
        $userPhoto = !empty($loggedInEmployee['Photo']) ? $loggedInEmployee['Photo'] : 'images/default.png';

        // Check if DepartmentID is 1
        if ($loggedInEmployee['DepartmentID'] == 1) {
            $hasDepartmentOne = true;
        }

        // Check if the logged-in employee has a manager
        if ($loggedInEmployee['ManagerID']) {
            $managerID = $loggedInEmployee['ManagerID'];
            
            // Fetch the manager's details
            $managerQuery = "SELECT Employee.EmployeeID, Employee.Name, JobRole.JobRole AS JobRole, Employee.ManagerID
                            FROM Employee
                            JOIN JobRole ON Employee.JobRoleID = JobRole.JobRoleID
                            WHERE Employee.EmployeeID = $managerID";
            $managerResult = $conn->query($managerQuery);
            
            if ($managerResult && $managerResult->num_rows > 0) {
                $manager = $managerResult->fetch_assoc();
                
                // Set the logged-in employee as a subordinate of their manager
                $loggedInEmployee['subordinates'] = buildHierarchy(getEmployees($conn), $loggedInEmployee['EmployeeID']);
                $manager['subordinates'] = [$loggedInEmployee];
                
                // Set the hierarchy starting with the manager
                $managerHierarchy = [$manager];
            }
        } else {
            // If there is no manager, just display the logged-in employee's hierarchy
            $loggedInEmployee['subordinates'] = buildHierarchy(getEmployees($conn), $loggedInEmployee['EmployeeID']);
            $managerHierarchy = [$loggedInEmployee];
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThrivePeak Team Hierarchy</title>
    <link rel="stylesheet" href="css/team.css">
    <style>
/* Enhanced Modal Styling */
.modal {
    display: none; /* Hidden by default */
    position: fixed;
    z-index: 1000; /* Stay on top */
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.6); /* Black with a softer opacity */
}

.modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 20px 30px;
    border-radius: 12px; /* Subtle rounded corners */
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    width: 50%;
    max-width: 600px;
    font-family: 'Roboto', sans-serif;
    position: relative;
}

/* Close button styling */
.close {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 20px;
    font-weight: bold;
    color: #999;
    cursor: pointer;
    transition: color 0.3s;
}

.close:hover {
    color: #333;
}

/* Title styling */
.modal-content h2 {
    font-size: 1.5rem;
    text-align: center;
    color: #333;
    margin-bottom: 20px;
}

/* Form layout styling */
.modal form {
    display: grid;
    grid-template-columns: 1fr 1fr; /* Two-column layout */
    gap: 15px; /* Spacing between fields horizontally and vertically */
    row-gap: 20px; /* Ensures space between rows */
    align-items: center;
}

.modal form label {
    font-size: 0.9rem;
    color: #555;
    font-weight: bold;
    margin-top: 10px; /* Adds space above labels */
    margin-bottom: 5px; /* Adds space below labels */
}



.modal form input,
.modal form select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.9rem;
    background-color: #f9f9f9;
    transition: border-color 0.3s, background-color 0.3s;
}

.modal form input:focus,
.modal form select:focus {
    border-color: #3C99BD;
    background-color: #fff;
    outline: none;
}

/* Button styling */
.modal form button {
    grid-column: 1 / -1; /* Span both columns */
    background-color: #2c3e50; /* Updated button color */
    color: white;
    font-size: 1rem;
    font-weight: bold;
    padding: 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.3s;
    margin-top:8px;
}

.modal form button:hover {
    background-color: #1a242f; /* Darker shade for hover */
}

/* Radio button styling */
.modal form p {
    grid-column: 1 / -1; /* Span both columns */
    font-size: 0.9rem;
    color: #333;
    margin-bottom: 10px;
}

.modal form p input {
    margin-right: 5px;
}

        
    </style>
    <script>
        // Function to open the modal
        function openModal() {
            document.getElementById("addEmployeeModal").style.display = "block";
        }

        // Function to close the modal
        function closeModal() {
            document.getElementById("addEmployeeModal").style.display = "none";
        }

        // Submit the form via AJAX
        function submitAddEmployeeForm(event) {
            event.preventDefault(); // Prevent the form from submitting traditionally
            const formData = new FormData(document.getElementById("addEmployeeForm"));

            fetch("add_employee.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message); // Show success message
                    closeModal(); // Close the modal on success
                    location.reload(); // Reload the page to display the updated hierarchy
                } else {
                    alert("Error: " + data.message); // Show error message
                }
            })
            .catch(error => {
                console.error("Error:", error);
            });
        }

        // Function to render the team hierarchy
        function renderHierarchy(hierarchy) {
            const teamContainer = document.querySelector('.team-hierarchy');
            teamContainer.innerHTML = ''; // Clear existing hierarchy
            displayHierarchy(hierarchy, teamContainer); // Re-render the hierarchy
        }

        // Recursive function to display hierarchy
        function displayHierarchy(hierarchy, container) {
            const ul = document.createElement('ul');
            ul.classList.add('hierarchy');
            hierarchy.forEach(employee => {
                const li = document.createElement('li');
                const div = document.createElement('div');
                div.classList.add('team-member');

                const roleBox = document.createElement('div');
                roleBox.classList.add('role-box');
                const roleP = document.createElement('p');
                roleP.textContent = employee.JobRole;
                const nameSpan = document.createElement('span');
                nameSpan.textContent = employee.Name;

                roleBox.appendChild(roleP);
                roleBox.appendChild(nameSpan);
                div.appendChild(roleBox);
                li.appendChild(div);

                if (employee.subordinates && employee.subordinates.length > 0) {
                    displayHierarchy(employee.subordinates, li); // Recursively display subordinates
                }
                ul.appendChild(li);
            });
            container.appendChild(ul);
        }

        function openModal1() {
    document.getElementById("deleteEmployeeModal").style.display = "block";
    }

    // Function to close the modal
    function closeModal1() {
        document.getElementById("deleteEmployeeModal").style.display = "none";
    }

    function submitDeleteEmployeeForm(event) {
    event.preventDefault();

    const formData = new FormData(document.getElementById("deleteEmployeeForm"));

    fetch("delete_employee.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeModal();
            location.reload();
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => console.error("Error deleting employee:", error));
}

function loadJobRoles(departmentId) {
    // Clear the jobRole dropdown and reset employee dropdown
    const jobRoleDropdown = document.getElementById("jobRole1");
    const employeeDropdown = document.getElementById("employee1");
    jobRoleDropdown.innerHTML = '<option value="">Select Job Role</option>';
    employeeDropdown.innerHTML = '<option value="">Select Employee</option>';

    // Ensure departmentId is not empty
    if (!departmentId) {
        return; // Exit if no department is selected
    }

    // Fetch data from the backend
    fetch(`getData.php?department=${departmentId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json(); // Parse the JSON response
        })
        .then(data => {
            if (data.success && Array.isArray(data.jobRoles)) {
                // Populate the jobRole dropdown
                data.jobRoles.forEach(role => {
                    const option = document.createElement("option");
                    option.value = role.JobRoleID;
                    option.textContent = role.JobRole;
                    jobRoleDropdown.appendChild(option);
                });
            } else {
                alert("No job roles found for the selected department.");
            }
        })
        .catch(error => {
            console.error("Error fetching job roles:", error);
            alert("Failed to load job roles. Please try again.");
        });
}

function toggleForm(method) {
    const manualForm = document.getElementById('addEmployeeForm');
    const excelForm = document.querySelector('form[action="team.php"]'); // The CSV upload form

    if (method === 'manual') {
        manualForm.style.display = 'block'; // Show manual form
        excelForm.style.display = 'none';  // Hide CSV upload form
    } else if (method === 'excel') {
        manualForm.style.display = 'none'; // Hide manual form
        excelForm.style.display = 'block'; // Show CSV upload form
    }
}


        function uploadExcel(event) {
            event.preventDefault(); // Prevent default form submission

            const formData = new FormData(document.getElementById('uploadExcelForm'));

            fetch('upload_cvs.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeModal(); // Close the modal
                    location.reload(); // Reload to reflect the new data
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while uploading the Excel file.');
            });
        }

        function loadEmployees(jobRoleId) {
    const employeeDropdown = document.getElementById("employee1");
    const departmentId = document.getElementById("department1").value;

    // Reset employee dropdown
    employeeDropdown.innerHTML = '<option value="">Select Employee</option>';

    if (!jobRoleId || !departmentId) {
        return; // Exit if no jobRole or department is selected
    }

    fetch(`getData.php?department=${departmentId}&jobRole=${jobRoleId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && Array.isArray(data.employees)) {
                data.employees.forEach(employee => {
                    const option = document.createElement("option");
                    option.value = employee.EmployeeID;
                    option.textContent = employee.Name;
                    employeeDropdown.appendChild(option);
                });
            } else {
                alert("No employees found for the selected department and job role.");
            }
        })
        .catch(error => {
            console.error("Error loading employees:", error);
            alert("An error occurred while loading employees. Please try again.");
        });
}


document.addEventListener("DOMContentLoaded", function () {
    // Select the evaluation link and submenu
    const submenuLink = document.querySelector('.evaluation-link');
    const submenu = document.querySelector('.submenu');

    if (submenuLink && submenu) {
        submenuLink.addEventListener('click', function (event) {
            event.preventDefault(); // Prevent default anchor behavior
            // Toggle the submenu's display property
            if (submenu.style.display === 'block') {
                submenu.style.display = 'none';
            } else {
                submenu.style.display = 'block';
            }
        });
    } else {
        console.error("Submenu or submenu link not found!");
    }
});

    </script>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <div class="logo">
            <img src="images/thrivepeak_text_logo.png" alt="ThrivePeak Logo">
        </div>
        <nav class="menu">
            <ul>
                <li class="menu-item">
                    <img src="images/home.png" alt="dashboard">
                    <a href="dashboard_company.php">Dashboard</a>
                </li>
                <li class="menu-item active">
                    <img src="images/group.png" alt="team">
                    <a href="#">Team</a>
                </li>
                <li class="evaluation">
                    <li class="menu-item">
                        <img src="images/evaluation.png" alt="evaluation">
                        <a href="#" class="evaluation-link">Evaluation</a>
                    </li>
                    <ul class="submenu">
                        <li><a href="individual_evaluation.php">Individual</a></li>
                        <li><a href="department_evaluation.php">Department</a></li>
                    </ul>
                </li>
                <?php if (!empty($loggedInEmployee['subordinates'])): ?>
                    <li class="menu-item">
                        <img src="images/evaluate.png" alt="team">
                        <a href="evaluation_form.php">Evaluate</a>
                    </li>
                <?php endif; ?>
                <?php if ($hasDepartmentOne): ?>
                    <li class="menu-item">
                        <img src="images/formula.png" alt="team">
                        <a href="formula.php">Formula</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="logout">
            <a href="autentica.php?logout=true">Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="header">
            <div class="user-info">
                <!-- Display User's Photo -->
                <img src="<?php echo htmlspecialchars($userPhoto); ?>" alt="User Photo" class="user-photo">
                <span><?php echo htmlspecialchars($loggedInEmployee['Name']); ?></span>
            </div>
        </header>

        <section class="team-hierarchy">
            <h2>Team</h2>
            <?php if ($hasDepartmentOne): ?>
                <div class="dashboard-header">
                    <img src="images/user_plus.png" alt="Add User" onclick="openModal()">
                    <img src="images/user_minus.png" alt="Remove User"  onclick="openModal1()">
                </div>
            <?php endif; ?>
            <?php displayHierarchy($managerHierarchy); ?> <!-- Display the current hierarchy -->
        </section>
    </main>
</div>

<div id="addEmployeeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2 style="text-align: center; color: #333; margin-bottom: 20px;">Add New Employee</h2>

        <p>
            <input type="radio" id="manualEntry" name="addMethod" value="manual" checked onclick="toggleForm('manual')">
            <label for="manualEntry">Add Manually</label>
        </p>
        <p>
            <input type="radio" id="excelUpload" name="addMethod" value="excel" onclick="toggleForm('excel')">
            <label for="excelUpload">Add through Excel</label>
        </p>

        <!-- Manual Entry Form -->
        <form id="addEmployeeForm" onsubmit="submitAddEmployeeForm(event)" style="display: block;">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name" placeholder="Enter employee's full name" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" placeholder="Enter employee's email" required>

            <label for="department">Department:</label>
            <select id="department" name="department">
                <?php
                $departments = $conn->query("SELECT DepartmentID, Department FROM Department");
                while ($row = $departments->fetch_assoc()) {
                    echo '<option value="'.$row['DepartmentID'].'">'.$row['Department'].'</option>';
                }
                ?>
            </select>

            <label for="jobRole">Job Role:</label>
            <select id="jobRole" name="jobRole">
                <?php
                $jobRoles = $conn->query("SELECT JobRoleID, JobRole FROM JobRole");
                while ($row = $jobRoles->fetch_assoc()) {
                    echo '<option value="'.$row['JobRoleID'].'">'.$row['JobRole'].'</option>';
                }
                ?>
            </select>

            <label for="managerID">Manager:</label>
            <select id="managerID" name="managerID">
                <option value="">None</option>
                <?php
                $employees = $conn->query("SELECT EmployeeID, Name FROM Employee");
                while ($row = $employees->fetch_assoc()) {
                    echo '<option value="'.$row['EmployeeID'].'">'.$row['Name'].'</option>';
                }
                ?>
            </select>

            <button type="submit">Add Employee</button>
        </form>

        <!-- CSV Upload Form -->
        <form action="team.php" method="POST" enctype="multipart/form-data" style="display: none;">
            <label for="csvFile">Upload Employee CSV:</label>
            <input type="file" id="csvFile" name="csvFile" accept=".csv" required>
            <button type="submit">Upload</button>
        </form>
    </div>
</div>


<div id="deleteEmployeeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal1()">&times;</span>
        <h2>Delete Employee</h2>
        <form id="deleteEmployeeForm" onsubmit="submitDeleteEmployeeForm(event)">
            <!-- Department Dropdown -->
            <label for="department1">Department:</label>
            <select id="department1" name="department1" onchange="loadJobRoles(this.value)" required>
                <option value="">Select Department</option>
                <?php
                $departments = $conn->query("SELECT DepartmentID, Department FROM Department");
                while ($row = $departments->fetch_assoc()) {
                    echo '<option value="'.$row['DepartmentID'].'">'.$row['Department'].'</option>';
                }
                ?>
            </select>

            <!-- Job Role Dropdown -->
            <label for="jobRole1">Job Role:</label>
            <select id="jobRole1" name="jobRole1" onchange="loadEmployees(this.value)" required>
                <option value="">Select Job Role</option>
            </select>

            <!-- Employee Dropdown -->
            <label for="employee1">Employee:</label>
            <select id="employee1" name="employee1" required>
                <option value="">Select Employee</option>
            </select>

            <!-- Submit Button -->
            <button type="submit">Delete Employee</button>
        </form>
    </div>
</div>

</body>
</html>



