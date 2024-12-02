<?php
session_start();
include 'db_connection.php';

$hasDepartmentOne = false;

if (isset($_SESSION['user_id'])) {
    $userID = $_SESSION['user_id'];

    // Query to get the DepartmentID, EmployeeID, JobRole, ManagerID, and Subordinates for the logged-in user
    $query = "SELECT Employee.EmployeeID, Employee.Name, JobRole.JobRole AS JobRole, Employee.DepartmentID, Employee.ManagerID 
              FROM Employee
              JOIN JobRole ON Employee.JobRoleID = JobRole.JobRoleID
              WHERE Employee.EmployeeID = $userID";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $loggedInEmployee = $result->fetch_assoc();

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
    }}


if (!isset($_GET['employeeID']) || !isset($_GET['trimester']) || !isset($_GET['year'])) {
    die("Invalid access. Please provide employeeID, trimester, and year in the URL.");
}

$employeeID = $_GET['employeeID'];
$trimester = $_GET['trimester'];
$year = $_GET['year'];

// Query to get the job role of the employee
$jobRoleQuery = $conn->query("SELECT JobRoleID FROM Employee WHERE EmployeeID = $employeeID");
$jobRoleData = $jobRoleQuery->fetch_assoc();

if (!$jobRoleData) {
    die("Employee not found.");
}

$jobRoleID = $jobRoleData['JobRoleID'];

// Fetch criteria based on the employee's job role
$criteriaQuery = $conn->query("
    SELECT c.CriteriaID, c.CriteriaName 
    FROM RoleCriteriaWeight rcw 
    JOIN Criteria c ON rcw.CriteriaID = c.CriteriaID 
    WHERE rcw.JobRoleID = $jobRoleID
");

$criteriaList = [];
while ($criteriaRow = $criteriaQuery->fetch_assoc()) {
    $criteriaList[] = $criteriaRow;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Evaluation System</title>
    <link rel="stylesheet" href="css/evaluate_employee.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<button class="toggle-sidebar">☰</button>
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
                <a href="team.php">Team</a>
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

<div class="container">
    <header class="header">
        <div class="user-info">
            <img src="<?php echo htmlspecialchars($_SESSION['userPhoto']); ?>" alt="User Photo" class="user-photo">
            <span><?php echo htmlspecialchars($_SESSION['userName']); ?></span>
        </div>
    </header>

    <!-- Section to Evaluate Employee -->
    <div class="section">
        <h2>Evaluate Subordinate</h2>
        <h3>(Employee ID: <?php echo htmlspecialchars($employeeID); ?>, Trimester: <?php echo htmlspecialchars($trimester); ?>, Year: <?php echo htmlspecialchars($year); ?>)</h3>
        <h6>Evalaute from 0-10 each criteria</h6>
        <div class="classifications">
            <p>0 - Bad in the skill</p>
            <p>5 - Medium in the skill</p>
            <p>10 - Really good in the skill</p>´
        </div>
        <div id="evaluation-criteria">
    <div class="criteria-row">
        <?php foreach ($criteriaList as $index => $criteria): ?>
            <div class="criteria-item">
                <label for="criteria-<?php echo $criteria['CriteriaID']; ?>">
                    <?php echo htmlspecialchars($criteria['CriteriaName']); ?>:
                </label>
                <input 
                    type="number" 
                    id="criteria-<?php echo $criteria['CriteriaID']; ?>" 
                    class="score-input" 
                    data-criteria-id="<?php echo $criteria['CriteriaID']; ?>" 
                    min="0" 
                    max="10">
            </div>
            <!-- Add a new row after every two criteria -->
            <?php if (($index + 1) % 2 === 0): ?>
                </div><div class="criteria-row">
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <div class="feedback-section">
        <label for="feedback">Manager's Feedback (Optional):</label>
        <textarea id="feedback" name="feedback" rows="4" cols="50" placeholder="Enter any additional feedback here..."></textarea>
    </div>
</div>
    
        <button id="submit_evaluation">Submit Evaluation</button>
    </div>
</div>

<!-- Modal HTML -->
<div id="confirmationModal" class="modal">
    <div class="modal-content">
        <h3>Are you sure you want to submit this evaluation?</h3>
        <div class="modal-buttons">
            <button id="confirmSubmit" class="confirm-button">Yes</button>
            <button id="cancelSubmit" class="cancel-button">No</button>
        </div>
    </div>
</div>

<style>
    .modal {
        display:none;
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

    $(document).ready(function() {
        $('#confirmationModal').hide();
    // Show modal on submit button click
    $('#submit_evaluation').click(function() {
        // Show confirmation modal
        $('#confirmationModal').show();
    });

    // If user clicks "Yes", submit the form
    $('#confirmSubmit').click(function() {
        const scores = {};
        const feedback = $('#feedback').val(); // Capture feedback

        // Collect scores for each criterion
        $('.score-input').each(function() {
            const criteriaID = $(this).data('criteria-id');
            const score = $(this).val();
            scores[criteriaID] = score;
        });

        // Send data to the server via AJAX
        $.post('', {
            action: 'submit_evaluation',
            employee_id: <?php echo $employeeID; ?>,
            job_role_id: <?php echo $jobRoleID; ?>,
            trimester: <?php echo $trimester; ?>,
            year: <?php echo $year; ?>,
            scores: scores,
            feedback: feedback
        }, function(response) {
            // Redirect to evaluation form page
            $('#confirmationModal').hide();
            window.location.href = "evaluation_form.php";

        });

        // Close the modal
        $('#confirmationModal').hide();
    });

    // If user clicks "No", close the modal
    $('#cancelSubmit').click(function() {
        $('#confirmationModal').hide();
    });
});

</script>
</body>
</html>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_evaluation') {
    $employeeID = $_POST['employee_id'];
    $jobRoleID = $_POST['job_role_id'];
    $trimester = $_POST['trimester'];
    $year = $_POST['year'];
    $scores = $_POST['scores'];
    $managerFeedback = isset($_POST['feedback']) ? mysqli_real_escape_string($conn, $_POST['feedback']) : '';

    // Insert into EmployeeEvaluation with the year parameter
    $evaluationQuery = "INSERT INTO EmployeeEvaluation (EmployeeID, JobRoleID, Trimester, Year, EvaluationDate, ManagerFeedback) 
                        VALUES ($employeeID, $jobRoleID, $trimester, $year, NOW(), '$managerFeedback')";
    if ($conn->query($evaluationQuery) === TRUE) {
        $evaluationID = $conn->insert_id; // Get the last inserted ID for EvaluationID

        // Insert each score into EvaluationCriteriaScore
        foreach ($scores as $criteriaID => $score) {
            $scoreQuery = "INSERT INTO EvaluationCriteriaScore (EvaluationID, CriteriaID, Score, EvaluationDate, Trimester, Year) 
                           VALUES ($evaluationID, $criteriaID, $score, NOW(), $trimester, $year)";
            if (!$conn->query($scoreQuery)) {
                echo "Error: " . $conn->error;
                exit;
            }
        }

        echo "Evaluation submitted successfully!";
    } else {
        echo "Error: " . $conn->error;
    }

    exit;
}

?>
