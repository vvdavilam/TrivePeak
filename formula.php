<?php
session_start();
include_once 'db_connection.php';

$hasDepartmentOne = false;

// Check if the user is logged in and retrieve their ID
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

        // Fetch subordinates for the logged-in employee
        $subordinateQuery = "SELECT EmployeeID, Name FROM Employee WHERE ManagerID = $userID";
        $subordinateResult = $conn->query($subordinateQuery);

        $subordinates = [];
        while ($subordinateRow = $subordinateResult->fetch_assoc()) {
            $subordinates[] = $subordinateRow;
        }

        // Add subordinates to $loggedInEmployee array
        $loggedInEmployee['subordinates'] = $subordinates;
    }
}

// Check if the function loadJobRoles is already defined
if (!function_exists('loadJobRoles')) {
    // Function to load job roles for dropdowns
    function loadJobRoles($conn) {
        $result = $conn->query("SELECT JobRoleID, JobRole FROM JobRole");
        $options = "<option value=''>Select Job Role</option>";
        while ($row = $result->fetch_assoc()) {
            $options .= "<option value='{$row['JobRoleID']}'>{$row['JobRole']}</option>";
        }
        return $options;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : null; // Safely initialize $action

    if ($action === 'fetch_weights') {
        $jobRoleID = $_POST['job_role_id'];
        $result = $conn->query("SELECT rcw.CriteriaID, c.CriteriaName, rcw.Weight 
                                FROM RoleCriteriaWeight rcw 
                                JOIN Criteria c ON rcw.CriteriaID = c.CriteriaID 
                                WHERE rcw.JobRoleID = $jobRoleID");
    
        $data = "<div class='criteria-container'>";
        $count = 0;

        while ($row = $result->fetch_assoc()) {
            if ($count % 2 == 0) {
                $data .= "<div class='criteria-row'>";
            }

            $data .= "
                <div class='criteria-item'>
                    <label>{$row['CriteriaName']}:</label>
                    <input type='number' class='weight-input' data-criteria-id='{$row['CriteriaID']}' value='{$row['Weight']}' step='0.01' min='0' max='1'>
                    <img src='images/trash.png' class='delete-icon' data-criteria-id='{$row['CriteriaID']}' alt='Delete' style='width: 20px; cursor: pointer; margin-left: 10px;'>
                </div>
            ";

            $count++;

            if ($count % 2 == 0) {
                $data .= "</div>";
            }
        }

        if ($count % 2 != 0) {
            $data .= "</div>";
        }

        $data .= "</div>";
        echo $data;

    } elseif ($action === 'update_weights') {
        $jobRoleID = $_POST['job_role_id'];
        $weights = $_POST['weights'];
    
        foreach ($weights as $criteriaID => $weight) {
            $conn->query("UPDATE RoleCriteriaWeight SET Weight = $weight WHERE JobRoleID = $jobRoleID AND CriteriaID = $criteriaID");
        }
    
        echo "Weights updated successfully.";
    }
    
    elseif ($action === 'add_criteria') {
        $jobRoleID = $_POST['job_role_id'];
        $criterionName = $conn->real_escape_string($_POST['criterion_name']);
        $criterionWeight = (float)$_POST['criterion_weight'];
    
        $conn->query("INSERT INTO Criteria (CriteriaName) VALUES ('$criterionName')");
        $criteriaID = $conn->insert_id;
    
        $conn->query("INSERT INTO RoleCriteriaWeight (JobRoleID, CriteriaID, Weight) VALUES ($jobRoleID, $criteriaID, $criterionWeight)");
        echo "New criterion added successfully.";

    } elseif ($action === 'delete_criteria') {
        $criteriaID = $_POST['criteria_id'];
        $jobRoleID = $_POST['job_role_id'];

        $deleteRoleCriteriaWeight = $conn->query("DELETE FROM RoleCriteriaWeight WHERE JobRoleID = $jobRoleID AND CriteriaID = $criteriaID");
        $checkIfUsedElsewhere = $conn->query("SELECT COUNT(*) AS count FROM RoleCriteriaWeight WHERE CriteriaID = $criteriaID");
        $row = $checkIfUsedElsewhere->fetch_assoc();

        if ($row['count'] == 0) {
            $deleteCriteria = $conn->query("DELETE FROM Criteria WHERE CriteriaID = $criteriaID");

            if ($deleteCriteria) {
                echo "Criterion deleted successfully from both tables.";
            } else {
                echo "Error deleting criterion from Criteria table: " . $conn->error;
            }
        } else {
            echo "Criterion deleted successfully from RoleCriteriaWeight table only.";
        }
    } else {
        echo "Invalid action.";
    }
    exit;
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThrivePeak Dashboard</title>
    <link rel="stylesheet" href="css/formula.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

    <div class="container">
    <button class="toggle-sidebar">☰</button>
        <!-- Sidebar -->
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
                    <li class="menu-item">
                        <img src="images/group.png" alt="team">
                        <a href="team.php">Team</a>
                    </li>
                    <li class="evaluation">
                        <li class="menu-item">
                            <img src="images/evaluation.png" alt="team">
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
                    <li class="menu-item active">
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

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <div class="user-info">
                    <!-- Display User's Photo -->
                    <img src="<?php echo htmlspecialchars($userPhoto); ?>" alt="User Photo" class="user-photo">
                    <span><?php echo htmlspecialchars($loggedInEmployee['Name']); ?></span>
                </div>
            </header>

 <!-- Adjust Criteria Weights Section -->
<section class="formula-section">
    <h2>Adjust Criteria Weights</h2>
    <p>Customize the evaluation criteria for each job role by entering weights between 0 and 1 in the input fields provided for each criterion.</p>
    <br>
    <li>A weight closer to 1 means the criterion is more relevant for the selected job role.</li>
    <li>A weight closer to 0 means the criterion is less significant.</li>
    <br>
    <label for="job_role">Select Job Role:</label>
    <select id="job_role">
        <?php echo loadJobRoles($conn); ?>
    </select>
    <br>

    <!-- Elements below this will only appear when a job role is selected -->
    <div id="criteria-container" style="display: none;">
        <br>
    <p>Adjust Weights Below:</p>
        <div id="total-weight-container">
            <label for="total-weight">Total Weight:</label>
            <progress id="total-weight" value="0" max="1" style="width: 100%;"></progress>
            <span id="total-weight-display">0.00</span>/1
        </div>
        
        
        <div id="criteria-weights">
            <!-- Criteria and weights will load here based on the selected job role -->
        </div>

        <button id="update_weights" class="primary-button">Update Weights</button>
        <h3>Add New Criterion</h3>
        <div class="criteria-form-row">
            <div class="form-group">
                <label for="new_criteria_name">Criterion Name:</label>
                <input type="text" id="new_criteria_name" placeholder="Enter Criterion Name">
            </div>
            <div class="form-group">
                <label for="new_criteria_weight">Weight:</label>
                <input type="number" id="new_criteria_weight" step="0.01" min="0" max="1" placeholder="Enter Weight (0-1)">
            </div>
        </div>
        <button id="add_criteria" class="primary-button">Add New Criterion</button>
    </div>

</section>
<!-- Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3>Are you sure you want to delete this criterion?</h3>
        <div class="modal-buttons">
            <button id="confirmDelete" class="primary-button">Yes</button>
            <button id="cancelDelete" class="secondary-button">No</button>
        </div>
    </div>
</div>

<!-- Modal CSS -->
<style>
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .modal-content {
        background: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    .modal-buttons {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 15px;
    }

    .secondary-button {
        background: #2c3e50;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    .primary-button:hover, .secondary-button:hover {
        opacity: 0.9;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
    const toggleButton = document.querySelector('.toggle-sidebar');
    const sidebar = document.querySelector('.sidebar');

    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }
});

    document.querySelector('.evaluation-link').addEventListener('click', function(event) {
    event.preventDefault();
    const submenu = document.querySelector('.submenu');
    submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
});

$(document).ready(function () {
    function updateTotalWeight() {
        let totalWeight = 0;

        // Sum the weights from the existing criteria inputs
        $('.weight-input').each(function () {
            const value = $(this).val();
            if (value.trim() !== '') {
                totalWeight += parseFloat(value) || 0;
            }
        });

        // Add the weight of the new criterion (if provided)
        const newWeight = parseFloat($('#new_criteria_weight').val()) || 0;
        totalWeight += newWeight;

        // Update progress bar and weight display
        $('#total-weight').val(totalWeight);
        $('#total-weight-display').text(totalWeight.toFixed(2));

        // Highlight if total weight is invalid
        if (totalWeight > 1 || totalWeight < 1) {
            $('#total-weight-container').css('color', 'red');
        } else {
            $('#total-weight-container').css('color', 'green');
        }

        return totalWeight;
    }

    // Update total weight dynamically when inputs are modified
    $('#criteria-weights').on('input', '.weight-input', function () {
        updateTotalWeight();
    });

    // Handle job role selection to fetch criteria
    $('#job_role').change(function () {
        const jobRoleID = $(this).val();
        if (jobRoleID) {
            $('#criteria-container').show(); // Show criteria container
            $.post('formula.php', { action: 'fetch_weights', job_role_id: jobRoleID }, function (data) {
                $('#criteria-weights').html(data);
                updateTotalWeight();
            });
        } else {
            $('#criteria-container').hide(); // Hide criteria container
            $('#criteria-weights').empty();
            updateTotalWeight();
        }
    });

    // Handle updating weights only
    $('#update_weights').click(function () {
        const jobRoleID = $('#job_role').val();
        const weights = {};
        const totalWeight = updateTotalWeight();

        // Validate total weight before saving
        if (totalWeight > 1 || totalWeight < 1) {
            alert('Total weight must be exactly 1. Please adjust the weights.');
            return;
        }

        // Collect weights from inputs
        $('.weight-input').each(function () {
            const criteriaID = $(this).data('criteria-id');
            const weight = $(this).val();
            weights[criteriaID] = weight;
        });

        // Send data to update weights
        $.post('formula.php', { action: 'update_weights', job_role_id: jobRoleID, weights: weights }, function (data) {
            alert(data);
            $('#job_role').trigger('change'); // Refresh criteria list
        });
    });

    // Handle adding a new criterion and optionally updating weights
    $('#add_criteria').click(function () {
        const jobRoleID = $('#job_role').val();
        const criterionName = $('#new_criteria_name').val();
        const criterionWeight = parseFloat($('#new_criteria_weight').val());

        // Validate new criterion input
        if (!criterionName || isNaN(criterionWeight) || criterionWeight < 0 || criterionWeight > 1) {
            alert('Enter a valid criterion name and weight between 0 and 1.');
            return;
        }

        // Validate total weight after adding the new criterion
        const totalWeight = updateTotalWeight();
        if (totalWeight > 1) {
            alert('Adding this criterion will make the total weight exceed 1. Please adjust the weights.');
            return;
        }

        // Collect weights from inputs (to optionally update them)
        const weights = {};
        $('.weight-input').each(function () {
            const criteriaID = $(this).data('criteria-id');
            const weight = $(this).val();
            weights[criteriaID] = weight;
        });

        // First update existing weights (if any), then add the new criterion
        $.post('formula.php', { action: 'update_weights', job_role_id: jobRoleID, weights: weights }, function (updateResponse) {
            console.log("Update Weights Response: ", updateResponse);

            // Add the new criterion
            $.post('formula.php', {
                action: 'add_criteria',
                job_role_id: jobRoleID,
                criterion_name: criterionName,
                criterion_weight: criterionWeight
            }, function (addResponse) {
                alert(addResponse);
                $('#job_role').trigger('change'); // Refresh criteria list
                $('#new_criteria_name').val('');
                $('#new_criteria_weight').val('');
            });
        });
    });

// Store the criterion to delete
let deleteCriteriaID = null;
let deleteJobRoleID = null;

// Show confirmation modal before deleting
$('#criteria-weights').on('click', '.delete-icon', function () {
    deleteCriteriaID = $(this).data('criteria-id');
    deleteJobRoleID = $('#job_role').val();

    // Show the modal
    $('#deleteModal').fadeIn();
});

// Handle confirm delete action
$('#confirmDelete').click(function () {
    // If confirmed, proceed with deletion
    $.post('formula.php', { action: 'delete_criteria', criteria_id: deleteCriteriaID, job_role_id: deleteJobRoleID }, function (data) {
        alert(data);
        $('#job_role').trigger('change'); // Refresh criteria list
        $('#deleteModal').fadeOut(); // Close the modal
    });
});

// Handle cancel delete action
$('#cancelDelete').click(function () {
    // If canceled, just close the modal
    $('#deleteModal').fadeOut();
});


    // Initially hide criteria container
    $('#criteria-container').hide();
});

    </script>

</body>
</html>