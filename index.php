<?php
// Include the database connection
include 'db_connection.php';

// Function to load job roles for dropdowns
function loadJobRoles($conn) {
    $result = $conn->query("SELECT JobRoleID, JobRole FROM JobRole");
    $options = "<option value=''>Select Job Role</option>";
    while ($row = $result->fetch_assoc()) {
        $options .= "<option value='{$row['JobRoleID']}'>{$row['JobRole']}</option>";
    }
    return $options;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Evaluation System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .container {
            width: 80%;
            max-width: 600px;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        h1, h2 {
            color: #333;
            text-align: center;
        }

        .section {
            margin-bottom: 20px;
        }

        label, select, input[type="number"] {
            display: block;
            margin: 10px 0;
        }

        button {
            padding: 10px;
            background-color: #5cb85c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background-color: #4cae4c;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>Employee Evaluation System</h1>
        
        <!-- Section to Adjust Criteria Weights -->
        <div class="section">
            <h2>Adjust Criteria Weights</h2>
            <label for="job_role">Select Job Role:</label>
            <select id="job_role" name="job_role">
                <?php echo loadJobRoles($conn); ?>
            </select>
            
            <div id="criteria-weights">
                <!-- Criteria and weights will load here based on the selected job role -->
            </div>
            
            <button id="update_weights">Update Weights</button>
        </div>

        <!-- Section to Evaluate Employee -->
        <div class="section">
            <h2>Evaluate Employee</h2>
            <label for="employee_id">Employee ID:</label>
            <input type="number" id="employee_id" name="employee_id" required>
            
            <label for="job_role_evaluation">Select Job Role for Evaluation:</label>
            <select id="job_role_evaluation" name="job_role_evaluation">
                <?php echo loadJobRoles($conn); ?>
            </select>
            
            <div id="evaluation-criteria">
                <!-- Evaluation criteria inputs will load here based on the selected job role -->
            </div>
            
            <button id="submit_evaluation">Submit Evaluation</button>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Fetch and display criteria weights when job role is selected
            $('#job_role').change(function() {
                const jobRoleID = $(this).val();
                if (jobRoleID) {
                    $.post('index.php', { action: 'fetch_weights', job_role_id: jobRoleID }, function(data) {
                        $('#criteria-weights').html(data);
                    });
                }
            });

            // Update weights
            $('#update_weights').click(function() {
                const jobRoleID = $('#job_role').val();
                const weights = {};
                $('.weight-input').each(function() {
                    const criteriaID = $(this).data('criteria-id');
                    const weight = $(this).val();
                    weights[criteriaID] = weight;
                });
                $.post('index.php', { action: 'update_weights', job_role_id: jobRoleID, weights: weights }, function(response) {
                    alert(response);
                });
            });

            // Fetch evaluation criteria for the selected job role
            $('#job_role_evaluation').change(function() {
                const jobRoleID = $(this).val();
                if (jobRoleID) {
                    $.post('index.php', { action: 'fetch_criteria', job_role_id: jobRoleID }, function(data) {
                        $('#evaluation-criteria').html(data);
                    });
                }
            });

            // Submit evaluation
            $('#submit_evaluation').click(function() {
                const employeeID = $('#employee_id').val();
                const jobRoleID = $('#job_role_evaluation').val();
                const scores = {};
                $('.score-input').each(function() {
                    const criteriaID = $(this).data('criteria-id');
                    const score = $(this).val();
                    scores[criteriaID] = score;
                });
                $.post('index.php', { action: 'submit_evaluation', employee_id: employeeID, job_role_id: jobRoleID, scores: scores }, function(response) {
                    alert(response);
                });
            });
        });
    </script>

    <?php
    // Handle AJAX requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'];

        if ($action == 'fetch_weights') {
            // Fetch criteria weights for the selected job role
            $jobRoleID = $_POST['job_role_id'];
            $result = $conn->query("SELECT rcw.CriteriaID, c.CriteriaName, rcw.Weight 
                                    FROM RoleCriteriaWeight rcw 
                                    JOIN Criteria c ON rcw.CriteriaID = c.CriteriaID 
                                    WHERE rcw.JobRoleID = $jobRoleID");

            $data = "";
            while ($row = $result->fetch_assoc()) {
                $data .= "<label>{$row['CriteriaName']}:</label>";
                $data .= "<input type='number' class='weight-input' data-criteria-id='{$row['CriteriaID']}' value='{$row['Weight']}' step='0.01' min='0' max='1'><br>";
            }
            echo $data;

        } elseif ($action == 'update_weights') {
            // Update weights for the selected job role
            $jobRoleID = $_POST['job_role_id'];
            $weights = $_POST['weights'];

            foreach ($weights as $criteriaID => $weight) {
                $conn->query("UPDATE RoleCriteriaWeight SET Weight = $weight WHERE JobRoleID = $jobRoleID AND CriteriaID = $criteriaID");
            }

            echo "Weights updated successfully.";

        } elseif ($action == 'fetch_criteria') {
            // Fetch evaluation criteria for the selected job role
            $jobRoleID = $_POST['job_role_id'];
            $result = $conn->query("SELECT c.CriteriaID, c.CriteriaName 
                                    FROM RoleCriteriaWeight rcw 
                                    JOIN Criteria c ON rcw.CriteriaID = c.CriteriaID 
                                    WHERE rcw.JobRoleID = $jobRoleID");

            $data = "";
            while ($row = $result->fetch_assoc()) {
                $data .= "<label>{$row['CriteriaName']}:</label>";
                $data .= "<input type='number' class='score-input' data-criteria-id='{$row['CriteriaID']}' min='0' max='10'><br>";
            }
            echo $data;

        } elseif ($action == 'submit_evaluation') {
            // Submit the evaluation for the employee
            $employeeID = $_POST['employee_id'];
            $jobRoleID = $_POST['job_role_id'];
            $scores = $_POST['scores'];

            $conn->query("INSERT INTO EmployeeEvaluation (EmployeeID, JobRoleID, EvaluationDate) VALUES ($employeeID, $jobRoleID, NOW())");
            $evaluationID = $conn->insert_id;

            foreach ($scores as $criteriaID => $score) {
                $conn->query("INSERT INTO EvaluationCriteriaScore (EvaluationID, CriteriaID, Score) VALUES ($evaluationID, $criteriaID, $score)");
            }

            echo "Evaluation submitted successfully.";
        }
        exit;
    }
    ?>

</body>
</html>
