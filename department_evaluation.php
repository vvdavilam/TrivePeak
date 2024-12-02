<?php
session_start();
include 'db_connection.php';
include 'inc/check_user.php';
include 'inc/top.php';

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
        // Assign logged-in employee details to $loggedInEmployee
        $loggedInEmployee = $result->fetch_assoc();
        
        // Assign department ID only after $loggedInEmployee is fetched
        $departmentID = $loggedInEmployee['DepartmentID'];

        // Debugging: Ensure $departmentID is set
        if (empty($departmentID)) {
            echo "Error: departmentID is not set for user ID: $userID";
            exit;
        }

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
    } else {
        echo "Error: No employee data found for user ID: $userID";
        exit;
    }

    if (!empty($departmentID)) {
        $trimesterQuery = "
            SELECT 
                Trimester,
                Year,
                AverageScore
            FROM departmentevaluation
            WHERE DepartmentID = $departmentID
            ORDER BY Year, Trimester";
    
        $trimesterResult = $conn->query($trimesterQuery);
    
        if ($trimesterResult && $trimesterResult->num_rows > 0) {
            while ($row = $trimesterResult->fetch_assoc()) {
                $trimesterScores[] = [
                    'Trimester' => $row['Trimester'],
                    'Year' => $row['Year'],
                    'AverageScore' => round($row['AverageScore'], 2)
                ];
            }
        } else {
            echo "No data found for DepartmentID: $departmentID";
        }
    }
    

    $departments = [];
    if ($hasDepartmentOne) {
        $departmentQuery = "SELECT DepartmentID, Department FROM Department";
        $departmentResult = $conn->query($departmentQuery);

        while ($departmentRow = $departmentResult->fetch_assoc()) {
            $departments[] = $departmentRow;
        }
    }

     // Query to fetch department for logged-in user
     $query = "
     SELECT Employee.DepartmentID, Department.Department
     FROM Employee
     JOIN Department ON Employee.DepartmentID = Department.DepartmentID
     WHERE Employee.EmployeeID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        $defaultDepartmentName = 'N/A'; // Default fallback if no department is found
        $departmentID = null;

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $defaultDepartmentName = $row['Department'] ?? 'N/A';
            $departmentID = $row['DepartmentID'];
        }


    // Get the latest Year and Trimester for the department
    $latestQuery = "
        SELECT Year, Trimester
        FROM departmentevaluation
        WHERE DepartmentID = ?
        ORDER BY Year DESC, Trimester DESC
        LIMIT 1";
    $stmt = $conn->prepare($latestQuery);
    $stmt->bind_param("i", $departmentID);
    $stmt->execute();
    $latestResult = $stmt->get_result();

    if ($latestResult && $latestResult->num_rows > 0) {
        $latestRow = $latestResult->fetch_assoc();
        $latestYear = $latestRow['Year'];
        $latestTrimester = $latestRow['Trimester'];

        // Fetch the AverageScore for the latest Year and Trimester
        $scoreQuery = "
            SELECT AverageScore
            FROM departmentevaluation
            WHERE DepartmentID = ? AND Year = ? AND Trimester = ?";
        $stmt = $conn->prepare($scoreQuery);
        $stmt->bind_param("iii", $departmentID, $latestYear, $latestTrimester);
        $stmt->execute();
        $scoreResult = $stmt->get_result();

        if ($scoreResult && $scoreResult->num_rows > 0) {
            $row = $scoreResult->fetch_assoc();
            $averageScore = $row['AverageScore'];
        } else {
            $averageScore = "N/A";
        }

        // Fetch criteria scores for the latest Year and Trimester
        $criteriaScoresQuery = "
            SELECT 
                c.CriteriaName, 
                AVG(e.Score) AS AverageScoreCriteria
            FROM evaluationcriteriascore e
            JOIN criteria c ON e.CriteriaID = c.CriteriaID
            JOIN employeeevaluation ev ON e.EvaluationID = ev.EvaluationID
            JOIN employee ee ON ev.EmployeeID = ee.EmployeeID
            WHERE ee.DepartmentID = ? AND ev.Year = ? AND ev.Trimester = ?
            GROUP BY c.CriteriaName
            ORDER BY c.CriteriaName";
        $stmt = $conn->prepare($criteriaScoresQuery);
        $stmt->bind_param("iii", $departmentID, $latestYear, $latestTrimester);
        $stmt->execute();
        $criteriaScoresResult = $stmt->get_result();
        $criteriaScores = $criteriaScoresResult->fetch_all(MYSQLI_ASSOC);

        // Generate feedback and identify best and worst criteria
        $generatedFeedback = "";
        $bestCriteria = null;
        $worstCriteria = null;
        $highestScore = -1;
        $lowestScore = 11;

        foreach ($criteriaScores as $criteria) {
            $criteriaName = $criteria['CriteriaName'];
            $averageScoreCriteria = round($criteria['AverageScoreCriteria'], 2);

            if ($averageScoreCriteria >= 8) {
                $generatedFeedback .= "Excellent performance in $criteriaName with an average score of $averageScoreCriteria.\n";
            } elseif ($averageScoreCriteria >= 5) {
                $generatedFeedback .= "Good performance in $criteriaName with an average score of $averageScoreCriteria, but there is room for improvement.\n";
            } else {
                $generatedFeedback .= "$criteriaName requires significant improvement, as the average score is $averageScoreCriteria.\n";
            }

            if ($averageScoreCriteria > $highestScore) {
                $highestScore = $averageScoreCriteria;
                $bestCriteria = $criteriaName;
            }

            if ($averageScoreCriteria < $lowestScore) {
                $lowestScore = $averageScoreCriteria;
                $worstCriteria = $criteriaName;
            }
        }

        // Fetch trimester scores for the graph
        $scoresQuery = "
            SELECT Trimester, Year, AverageScore
            FROM departmentevaluation
            WHERE DepartmentID = ?
            ORDER BY Year, Trimester";
        $stmt = $conn->prepare($scoresQuery);
        $stmt->bind_param("i", $departmentID);
        $stmt->execute();
        $scoresResult = $stmt->get_result();
        $trimesterScores = $scoresResult->fetch_all(MYSQLI_ASSOC);


        // Prepare data for the frontend
        $defaultDepartmentData = [
            'averageScore' => $averageScore,
            'feedback' => $generatedFeedback,
            'departmentName' => $defaultDepartmentName,
            'bestCriteria' => [
                'name' => $bestCriteria,
                'score' => $highestScore
            ],
            'worstCriteria' => [
                'name' => $worstCriteria,
                'score' => $lowestScore
            ],
            'trimesterScores' => $trimesterScores
        ];
    } else {
        echo "No evaluations found for the department.";
        exit;
    }
} else {
    echo "Error: User not logged in.";
    exit;
}

?>


<script>
    // Pass the default department data to JavaScript
    const defaultDepartmentData = <?php echo json_encode($defaultDepartmentData); ?>
    const trimesterScores = <?php echo json_encode($trimesterScores); ?>;
</script>


<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThrivePeak Dashboard</title>
    <link rel="stylesheet" href="css/department_evaluation.css">
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
                        <li class="menu-item active">
                            <img src="images/evaluation.png" alt="team">
                            <a href="#" class="evaluation-link">Evaluation</a>
                        </li>
                        <ul class="submenu">
                            <li><a href="individual_evaluation.php">Individual</a></li>
                            <li><a href="individual_evaluation.php" class="active">Department</a></li>
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

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <div class="user-info">
                    <img src="<?php echo htmlspecialchars($_SESSION['userPhoto']); ?>" alt="User Photo" class="user-photo">
                    <span><?php echo htmlspecialchars($_SESSION['userName']); ?></span>
                </div>
            </header> 
            
        <section class="content">
            <section class="filters">
                <?php if ($hasDepartmentOne || !empty($loggedInEmployee['subordinates'])): ?>
                    <!-- Display Department Selector only for HR -->
                    <?php if ($hasDepartmentOne): ?>
                        <div class="filter">
                            <select id="department" onchange="fetchDepartmentData(this.value)">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['DepartmentID']; ?>">
                                        <?php echo htmlspecialchars($department['Department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Display Employee Selector only for Managers -->
                    <?php if (!empty($loggedInEmployee['subordinates'])): ?>
                        <div class="filter">
                            <select id="employee" onchange="fetchEmployeeData(this.value)">
                                <option value="">Select Employee</option>
                                <?php foreach ($subordinates as $subordinate): ?>
                                    <option value="<?php echo $subordinate['EmployeeID']; ?>">
                                        <?php echo htmlspecialchars($subordinate['Name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
            <h2 id="department-title">
                Department Evaluation: <?php echo htmlspecialchars($defaultDepartmentName); ?>
            </h2>


            <p id="employee-name" style="font-size: 16px; color: #555;"></p> <!-- Placeholder for employee name -->
            <section class="dashboard">
                <div class="dashboard-header">
                    <div class="dashboard-stat">
                        <h3>Best Criteria</h3>
                        <p id="bestCriteria"><?php echo htmlspecialchars($bestCriteria); ?> - <?php echo htmlspecialchars($highestScore); ?></p>
                    </div>
                    <div class="dashboard-stat">
                        <h3>Needs Improvement Criteria</h3>
                        <p id="worstCriteria"><?php echo htmlspecialchars($worstCriteria); ?> - <?php echo htmlspecialchars($lowestScore); ?></p>

                    </div>
                    <div class="dashboard-stat">
                        <h3>Department Score</h3>
                        <p class="departmentScore"><?php echo htmlspecialchars($averageScore); ?></p>
                    </div>
                </div>
                <div class="feedback-section">
                    <h4>Recommendation</h4>
                    <div class="recommendation-box">
                        <p class="feedback"><?php echo htmlspecialchars($generatedFeedback); ?></p>
                    </div>
                </div>
                
                <div class="company-details">
                    <h3>Company Details</h3>
                    <div class="chart">
                        <canvas id="companyChart"></canvas>
                    </div>
                </div>


            </section>
            </section>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
    // Toggle visibility of the submenu "Evaluation"
    document.querySelector('.evaluation-link').addEventListener('click', function(event) {
        event.preventDefault();
        const submenu = document.querySelector('.submenu');
        submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
    });

    document.addEventListener('DOMContentLoaded', () => {
    const departmentTitle = document.getElementById('department-title');
    const departmentSelect = document.getElementById('department'); // Department dropdown

    // Check if the user has department filter access
    if (departmentSelect) {
        // Update department title when a department is selected
        departmentSelect.addEventListener('change', () => {
            const selectedOption = departmentSelect.options[departmentSelect.selectedIndex];
            departmentTitle.textContent = `Department Evaluation: ${selectedOption.text || 'N/A'}`;
        });
    } else {
        // If no department filter, ensure the title is set by PHP
        console.log('No department filter available. Using PHP-provided department name.');
    }
});



    document.addEventListener("DOMContentLoaded", function () {
    console.log("Default Trimester Scores:", trimesterScores);
    if (trimesterScores && trimesterScores.length > 0) {
        updateDepartmentGraph(trimesterScores);
    } else {
        console.error("No data available for the default chart.");
    }
});

    // Global chart instance
    let myChart = null;

    // Pass PHP data to JavaScript
    const trimesterScores = <?php echo json_encode($trimesterScores); ?>;

    // Debugging: Check the data in the browser console
    console.log(trimesterScores);
    

    function updateDepartmentGraph(trimesterScores) {
    const labels = trimesterScores.map(score => `${score.Trimester} - ${score.Year}`);
    const data = trimesterScores.map(score => parseFloat(score.AverageScore));

    const ctx = document.getElementById('companyChart').getContext('2d');

    if (myChart) {
        myChart.destroy(); // Destroy existing chart
    }

    myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Average Department Score by Trimester',
                data: data,
                borderColor: 'blue',
                backgroundColor: 'rgba(0, 0, 255, 0.2)',
                fill: true,
                pointRadius: 4,
                pointBackgroundColor: 'blue',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Trimester'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Average Score'
                    },
                    beginAtZero: true,
                    max: 10
                }
            }
        }
    });
}


function updateEmployeeGraph(Scores, departmentScores) {
    // Handle missing employee scores
    if (!Scores || Scores.length === 0) {
        console.warn("No data available for employee graph.");
        Scores = [{ Trimester: "N/A", Year: "N/A", TotalScore: 0 }];
    }

    // Handle missing department scores
    if (!departmentScores || departmentScores.length === 0) {
        console.warn("No data available for department graph.");
        departmentScores = [{ Trimester: "N/A", Year: "N/A", AverageScore: 0 }];
    }

    // Extract labels and data for employee scores
    const employeeLabels = Scores.map(score => `${score.Trimester} - ${score.Year}`);
    const employeeData = Scores.map(score => parseFloat(score.TotalScore || 0));

    // Extract data for department scores (assumes same labels as employee scores for simplicity)
    const departmentData = departmentScores.map(score => parseFloat(score.AverageScore || 0));

    const ctx = document.getElementById('companyChart').getContext('2d');

    // Destroy the previous chart instance if it exists
    if (myChart) {
        myChart.destroy();
        myChart = null; // Clear the chart instance
    }

    // Create a new chart instance with two datasets
    myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: employeeLabels, // Use the labels from employee scores
            datasets: [
                {
                    label: 'Employee Performance Scores by Trimester',
                    data: employeeData,
                    borderColor: 'green',
                    backgroundColor: 'rgba(0, 255, 0, 0.2)',
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: 'green'
                },
                {
                    label: 'Average Department Score by Trimester',
                    data: departmentData,
                    borderColor: 'blue',
                    backgroundColor: 'rgba(0, 0, 255, 0.2)',
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: 'blue'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Trimester'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Score'
                    },
                    beginAtZero: true,
                    max: 10
                }
            }
        }
    });
}

    // Initial chart rendering (if data is available)
    if (trimesterScores.length === 0) {
        console.error("No data available to render the chart.");
    } else {
        updateDepartmentGraph(trimesterScores); // Use the updateGraph function
    }

    // Department filter change event
    document.getElementById('department').addEventListener('change', function () {
    console.log('Department filter changed: ', this.value);
    fetchEmployeesByDepartment(this.value);
    });

    // Event listener for employee selection
    document.getElementById('employee').addEventListener('change', function () {
        console.log('Employee filter changed: ', this.value);
        fetchEmployeeData(this.value);
    });

function fetchEmployeesByDepartment(departmentID) {
    console.log('Fetching employees for department:', departmentID);
    fetch(`fetch_employees.php?departmentID=${departmentID}`)
        .then(response => {
            if (!response.ok) {
                console.error('Error fetching employees:', response.statusText);
                return [];
            }
            return response.json();
        })
        .then(data => {
            console.log('Employees fetched:', data);
            const employeeDropdown = document.getElementById('employee');
            employeeDropdown.innerHTML = '<option value="">Select Employee</option>';
            data.forEach(employee => {
                const option = document.createElement('option');
                option.value = employee.EmployeeID;
                option.textContent = employee.Name;
                employeeDropdown.appendChild(option);
            });
        })
        .catch(error => console.error('Error in fetchEmployeesByDepartment:', error));
}

document.getElementById('department').addEventListener('change', function () {
    const departmentID = this.value;

    if (departmentID) {
        // Fetch and display data for the selected department
        fetchDepartmentData(departmentID);
    } else {
        // If no department is selected, return to the default department
        fetchDefaultDepartmentData();
    }
});

document.getElementById('employee').addEventListener('change', function () {
    const employeeID = this.value;

    if (employeeID) {
        // Fetch and display data for the selected employee
        fetchEmployeeData(employeeID);
    } else {
        // If no employee is selected, return to the current department evaluation
        const selectedDepartmentID = document.getElementById('department').value;

        if (selectedDepartmentID) {
            fetchDepartmentData(selectedDepartmentID);
        } else {
            fetchDefaultDepartmentData();
        }

        // Clear the employee-specific data explicitly
        clearEmployeeData();
    }
});

    // Fetch department-specific data and update the UI
    function fetchDepartmentData(departmentID) {
        console.log('Fetching data for department:', departmentID);

        if (!departmentID) {
            console.warn('No department selected.');
            clearDepartmentData();
            return;
        }

        fetch(`fetch_department_data.php?departmentID=${departmentID}`)
        .then(response => response.json())
        .then(data => {
            console.log("Fetched Data:", data); // Ensure this shows the full data
            if (data.success) {
                console.log("Feedback from response:", data.feedback); // Check feedback explicitly
                updateUI(data);

                // Update Department Title
                const departmentTitle = document.getElementById('department-title');
                departmentTitle.textContent = `Department Evaluation: ${data.departmentName || 'N/A'}`;

                // Clear Employee Name
                const employeeName = document.getElementById('employee-name');
                employeeName.textContent = '';
            } else {
                console.error("Error in response:", data.message);
            }
        })
        .catch(error => console.error("Fetch error:", error));

        }

    // Clear department data from the UI
    function clearDepartmentData() {

        const scoreElement = document.querySelector('.departmentScore');
        if (scoreElement) {
            scoreElement.innerHTML = "<p>Department Score: N/A</p>";
        }
        

        const feedbackElement = document.querySelector('.feedback');
        if (feedbackElement) {
            feedbackElement.innerHTML = "<p>Department Score: N/A</p>";
        }

        const departmentTitle = document.getElementById('department-title');
    departmentTitle.textContent = "Department Evaluation: N/A";

    const employeeName = document.getElementById('employee-name');
    employeeName.textContent = '';

        // Clear the graph
        if (myChart) {
            myChart.destroy();
            myChart = null; // Reset the chart instance
        }
    }

    // Update the UI with fetched data
    function updateUI(data) {
        // Update Department Average Score
        const departmentScoreElement = document.querySelector('.departmentScore');
        if (departmentScoreElement) {
            if (data.averageScore !== undefined) {
                departmentScoreElement.textContent = `${parseFloat(data.averageScore).toFixed(2)}`;
            } else {
                departmentScoreElement.textContent = 'Department Score: N/A';
            }
        } else {
            console.error('Department score element not found.');
        }


        const feedbackElement = document.querySelector('.feedback');
        if (data.feedback && feedbackElement) {
            console.log('Feedback content:', data.feedback); // Debug feedback
            feedbackElement.innerHTML = ''; // Limpa o feedback anterior
            const feedbackLines = data.feedback.split('\n');
            feedbackLines.forEach(line => {
                if (line.trim() !== '') {
                    const p = document.createElement('p');
                    p.textContent = line;
                    feedbackElement.appendChild(p);
                }
            });
        } else {
            console.warn('Feedback not available.');
        }

        if (data.trimesterScores && data.trimesterScores.length > 0) {
            console.log('Updating graph with new scores:', data.trimesterScores); // Debug log
            updateDepartmentGraph(data.trimesterScores);
        } else {
            console.warn('No data available for the graph.');
            updateDepartmentGraph([]); // Clear the graph if no data
        }

        // Update Best and Worst Criteria
        const bestCriteriaElement = document.querySelector('#bestCriteria');
        const worstCriteriaElement = document.querySelector('#worstCriteria');

        if (data.bestCriteria && bestCriteriaElement) {
            bestCriteriaElement.textContent = `${data.bestCriteria.name} - ${data.bestCriteria.score}`;
        }

        if (data.worstCriteria && worstCriteriaElement) {
            worstCriteriaElement.textContent = `${data.worstCriteria.name} - ${data.worstCriteria.score}`;
        }

        // Update Graph
        if (data.trimesterScores && data.trimesterScores.length > 0) {
            updateDepartmentGraph(data.trimesterScores);
        } else {
            updateDepartmentGraph([]); // Clear the graph if no data
        }
    
    }

    // Employee change
    function fetchEmployeeData(employeeID) {
    console.log('Fetching data for employee:', employeeID);

    if (!employeeID) {
        console.warn('No employee selected.');
        clearEmployeeData();
        return;
    }

    fetch(`fetch_employee_details.php?employeeID=${employeeID}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Fetched employee data:', data);
            if (data.success) {
                updateEmployeeUI(data); // Call update function with the fetched data
                updateEmployeeGraph(data.scores, data.trimesterScores); // Call the employee graph function
                 // Update Employee Name
            const employeeName = document.getElementById('employee-name');
            employeeName.textContent = `Employee: ${data.employeeName || 'N/A'}`;
            } else {
                console.error('Error message from server:', data.message);
                clearEmployeeData(); // Clear UI in case of error
            }
        })
        .catch(error => {
            console.error('Error fetching employee data:', error);
        });
}


    function clearEmployeeData() {

        // Clear employee-specific socre
        const scoreElement = document.querySelector('.departmentScore');
            if (scoreElement) {
                scoreElement.innerHTML = "<p>Employee Score: N/A</p>";
            }
        
            // Reset to the department's feedback
            if (feedbackElement) {
            feedbackElement.innerHTML = ''; // Clear existing feedback
            const feedbackLines = defaultDepartmentData.feedback.split('\n');
            feedbackLines.forEach(line => {
                if (line.trim() !== '') {
                    const p = document.createElement('p');
                    p.textContent = line;
                    feedbackElement.appendChild(p);
                }
            });
        }

            const employeeName = document.getElementById('employee-name');
            employeeName.textContent = '';
            

        // Clear employee-specific chart
        if (myChart) {
            myChart.destroy();
            myChart = null; // Reset chart instance
        }


    }

    // Function to update the UI with employee-specific data
    function updateEmployeeUI(data) {
                // Update Best and Worst Criteria
                const bestCriteriaElement = document.querySelector('#bestCriteria');
        const worstCriteriaElement = document.querySelector('#worstCriteria');

        if (data.bestCriteria && bestCriteriaElement) {
            bestCriteriaElement.textContent = `${data.bestCriteria.name} - ${data.bestCriteria.score}`;
        }

        if (data.worstCriteria && worstCriteriaElement) {
            worstCriteriaElement.textContent = `${data.worstCriteria.name} - ${data.worstCriteria.score}`;
        }

        // Update Graph
        if (data.trimesterScores && data.trimesterScores.length > 0) {
            updateDepartmentGraph(data.trimesterScores);
        } else {
            updateDepartmentGraph([]); // Clear the graph if no data
        }
    

        // Display the last score
        const lastScoreElement = document.querySelector('.departmentScore');
        if (data.lastScore && lastScoreElement) {
            console.log('Updating last score:', data.lastScore); // Debug: Log the last score
            lastScoreElement.innerHTML = `
                <p>${data.lastScore.TotalScore}</p>
            `;
        } else if (lastScoreElement) {
            lastScoreElement.innerHTML = '<p>Last Score: N/A</p>';
        }

            const feedbackElement = document.querySelector('.feedback');
        if (data.feedback && feedbackElement) {
            console.log('Feedback content:', data.feedback); // Debug feedback
            feedbackElement.innerHTML = ''; // Limpa o feedback anterior
            const feedbackLines = data.feedback.split('\n');
            feedbackLines.forEach(line => {
                if (line.trim() !== '') {
                    const p = document.createElement('p');
                    p.textContent = line;
                    feedbackElement.appendChild(p);
                }
            });
        } else {
            console.warn('Feedback not available.');
        }

        // Update the graph with new scores
        if (data.scores && data.scores.length > 0 && data.departmentScores && data.departmentScores.length > 0) {
            console.log('Updating graph with employee scores:', data.scores);
            console.log('Updating graph with department scores:', data.departmentScores);
            updateEmployeeGraph(data.scores, data.departmentScores); // Pass both employee and department scores
        } else {
            console.warn('No data available for the graph.');
            updateEmployeeGraph([], []); // Clear the graph if no data
        } 
    } 
</script>

</body>
</html>