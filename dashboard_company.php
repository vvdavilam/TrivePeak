<?php
session_start();
include 'db_connection.php';
include 'inc/top.php';

$hasDepartmentOne = false;
$userPhoto = '';


if (isset($_SESSION['user_id'])) {
    $userID = $_SESSION['user_id'];

    // Query to get the DepartmentID, EmployeeID, JobRole, ManagerID, and Photo for the logged-in user
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

        // Fetch scores per trimester for the evolution chart
        $evolutionScores = [];
        $evolutionQuery = "SELECT Trimester, Year, AverageScore AS AverageScore
                            FROM companyevaluation
                            ORDER BY Year, Trimester";
        $evolutionResult = $conn->query($evolutionQuery);
    
        if ($evolutionResult) {
            while ($row = $evolutionResult->fetch_assoc()) {
        
                $evolutionScores[] = [
                    'trimester' => $row['Trimester'],
                    'year' => $row['Year'],
                    'score' => $row['AverageScore'] // Use the exact key from print_r output
                ];
            }
        }

    }

?>
<script>
    // Pass PHP data to JavaScript
    const evolutionData = <?php echo json_encode($evolutionScores); ?>;
</script>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThrivePeak Dashboard</title>
    <link rel="stylesheet" href="css/dashboard_company.css">
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
                <li class="menu-item active">
                    <img src="images/home.png" alt="dashboard">
                    <a href="#">Dashboard</a>
                </li>
                <li class="menu-item">
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

    <!-- Main Content -->
    <main class="main-content">
        <header class="header">
            <div class="user-info">
                <!-- Display User's Photo -->
                <img src="<?php echo htmlspecialchars($userPhoto); ?>" alt="User Photo" class="user-photo">
                <span><?php echo htmlspecialchars($loggedInEmployee['Name']); ?></span>
            </div>
        </header>

        <section class="dashboard">
            <div class="dashboard-header">
                <h1>Welcome Back, <?php echo htmlspecialchars($loggedInEmployee['Name']); ?>!</h1>
            </div>
        </section>

        <div class="company-details">
            <h3>Average Employees Scores</h3>
            <div class="chart">
                <canvas id="companyChart"></canvas>
            </div>
        </div>

    </main>
   

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

    document.addEventListener('DOMContentLoaded', () => {
    if (evolutionData && evolutionData.length > 0) {
        const labels = evolutionData.map(item => `Trimester ${item.trimester} - ${item.year}`);
        const data = evolutionData.map(item => item.score);

        const ctx = document.getElementById('companyChart').getContext('2d');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Performance Evolution',
                    data: data,
                    borderColor: 'blue',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: 10,
                        title: {
                            display: true,
                            text: 'Performance Score'
                        }
                    }
                }
            }
        });
    } else {
        console.error('No data available for the chart.');
    }});

    document.querySelector('.evaluation-link').addEventListener('click', function(event) {
        event.preventDefault();
        const submenu = document.querySelector('.submenu');
        submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
    });


</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>