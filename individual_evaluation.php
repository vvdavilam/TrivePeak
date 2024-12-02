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
    }

    // Fetch the last trimester and year the employee was evaluated
    $lastEvaluationQuery = "
        SELECT Year, Trimester 
        FROM employeeevaluation 
        WHERE EmployeeID = $userID 
        ORDER BY Year DESC, Trimester DESC 
        LIMIT 1";
    $lastEvaluationResult = $conn->query($lastEvaluationQuery);

    $lastYear = null;
    $lastTrimester = null;

    if ($lastEvaluationResult && $lastEvaluationResult->num_rows > 0) {
        $lastEvaluation = $lastEvaluationResult->fetch_assoc();
        $lastYear = $lastEvaluation['Year'];
        $lastTrimester = $lastEvaluation['Trimester'];
    }

    // Retrieve individual criteria scores for the last evaluation
    $criteriaScores = [];
    $bestSkill = null;
    $needsImprovementSkill = null;

    if ($lastYear !== null && $lastTrimester !== null) {
        $criteriaQuery = "
            SELECT Criteria.CriteriaName, evaluationcriteriascore.Score
            FROM evaluationcriteriascore
            JOIN criteria ON evaluationcriteriascore.criteriaID = criteria.criteriaID
            JOIN employeeevaluation ON evaluationcriteriascore.EvaluationID = employeeevaluation.EvaluationID
            WHERE employeeevaluation.EmployeeID = $userID
              AND employeeevaluation.Year = $lastYear
              AND employeeevaluation.Trimester = $lastTrimester
            ORDER BY evaluationcriteriascore.Score DESC
        ";
        $criteriaResult = $conn->query($criteriaQuery);

        if ($criteriaResult) {
            while ($row = $criteriaResult->fetch_assoc()) {
                $criteriaScores[] = $row;
            }
        }

        // Fetch the highest and lowest scores
        if (!empty($criteriaScores)) {
            $bestSkill = $criteriaScores[0]; // First row: highest score
            $needsImprovementSkill = $criteriaScores[count($criteriaScores) - 1]; // Last row: lowest score
        }
    }

    

    // Generate feedback based on scores for the last trimester
    $generatedFeedback = "";

    if ($lastYear !== null && $lastTrimester !== null) {
        // Fetch total score for the last trimester
        $scoreQuery = "
            SELECT TotalScore, ManagerFeedback 
            FROM employeeevaluation 
            WHERE EmployeeID = $userID 
              AND Year = $lastYear 
              AND Trimester = $lastTrimester
            LIMIT 1";
        $scoreResult = $conn->query($scoreQuery);
        $finalScore = null;
        $managerFeedback = null;

        if ($scoreResult && $scoreResult->num_rows > 0) {
            $scoreData = $scoreResult->fetch_assoc();
            $finalScore = $scoreData['TotalScore'];
            $managerFeedback = $scoreData['ManagerFeedback'];
        }

        if ($finalScore !== null) {
            if ($finalScore >= 8) {
                $generatedFeedback .= "Excellent performance in the last trimester, keep up the good work!\n";
            } elseif ($finalScore >= 5) {
                $generatedFeedback .= "Good performance with some areas for improvement in the last trimester.\n";
            } else {
                $generatedFeedback .= "Needs improvement based on the last trimester. Focus on weaker areas.\n";
            }
        }

        // Generate feedback for individual criteria
        foreach ($criteriaScores as $criteria) {
            $criteriaName = $criteria['CriteriaName'];
            $score = $criteria['Score'];

            if ($score >= 8) {
                $generatedFeedback .= "Great job on $criteriaName in the last trimester. Keep it up!\n";
            } elseif ($score < 5) {
                $generatedFeedback .= "Consider improving in $criteriaName to enhance your performance for the next evaluation.\n";
            }
        }
    }

    // Fetch scores per trimester for the evolution chart
    $evolutionScores = [];
    $evolutionQuery = "SELECT Trimester, Year, TotalScore 
                       FROM employeeevaluation 
                       WHERE EmployeeID = $userID 
                       ORDER BY Year, Trimester";
    $evolutionResult = $conn->query($evolutionQuery);

    if ($evolutionResult) {
        while ($row = $evolutionResult->fetch_assoc()) {
            $evolutionScores[] = [
                'trimester' => $row['Trimester'],
                'year' => $row['Year'],
                'score' => $row['TotalScore']
            ];
        }
    }
}
?>
<script>
    const evolutionData = <?php echo json_encode($evolutionScores); ?>;
</script>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Individual Evaluation</title>
    <link rel="stylesheet" href="css/individual_evaluation.css">
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
                    <img src="<?php echo htmlspecialchars($_SESSION['userPhoto']); ?>" alt="User Photo" class="user-photo">
                    <span><?php echo htmlspecialchars($_SESSION['userName']); ?></span>
                </div>
            </header>

            <section class="content">

            <!-- Profile Card -->
                <div class="profile-card">
                    <img src="<?php echo htmlspecialchars($_SESSION['userPhoto']); ?>" alt="Profile Picture" style="width:60px; height:60px; border-radius:50%;">
                    <div class="profile-details">
                        <h3><?php echo htmlspecialchars($_SESSION['userName']); ?></h3>
                        <p><?php echo htmlspecialchars($_SESSION['userJobRole']); ?></p>
                        <p><?php echo htmlspecialchars($_SESSION['userEmail']); ?></p>
                    </div>
                </div>
                <br>
                <!-- Evaluation Details -->
                <h2>Individual Evaluation</h2>
                <div class="overview-cards">
                    <div class="overview-card">
                        <?php if ($bestSkill): ?>
                            <h4><strong>Best Skill:</strong> <?php echo htmlspecialchars($bestSkill['CriteriaName']); ?></h4>
                            <p><?php echo htmlspecialchars($bestSkill['Score']); ?></p>
                        <?php else: ?>
                            <h4>Best Skill</h4>
                            <p>N/A</p>
                        <?php endif; ?>
                    </div>
                    <div class="overview-card">
                        <?php if ($needsImprovementSkill): ?>
                            <h4><strong>Needs Improvement:</strong> <?php echo htmlspecialchars($needsImprovementSkill['CriteriaName']); ?></h4>
                            <p><?php echo htmlspecialchars($needsImprovementSkill['Score']); ?></p>
                        <?php else: ?>
                            <h4>Needs Improvement</h4>
                            <p>N/A</p>
                        <?php endif; ?>
                    </div>
                    <div class="overview-card">
                        <h4>Overall Rating</h4>
                        <?php if ($finalScore !== null): ?>
                            <p class="<?php echo ($finalScore >= 5) ? 'score-green' : 'score-red'; ?>">
                                <?php echo htmlspecialchars($finalScore); ?>
                            </p>
                        <?php else: ?>
                            <p>N/A</p>
                        <?php endif; ?>
                    </div>
                </div>


                <div class="feedback-section">
                    <h4>Manager's Feedback</h4>
                    <div class="feedback-box">
                        <p><?php echo htmlspecialchars($managerFeedback ?? 'No feedback available'); ?></p>
                    </div>
                    <h4>Recommendation</h4>
                    <div class="recommendation-box">
                        <p><?php echo nl2br(htmlspecialchars($generatedFeedback)); ?></p>
                    </div>
                </div>

                <!-- Evolution Chart -->
                <div class="graph">
                    <h4>Evolution</h4>
                    <canvas id="evolutionChart"></canvas>
                </div>
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
        document.querySelector('.evaluation-link').addEventListener('click', function(event) {
        event.preventDefault();
        const submenu = document.querySelector('.submenu');
        submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
    });

    const labels = evolutionData.map(item => `Trimester ${item.trimester} - ${item.year}`);
    const data = evolutionData.map(item => item.score);

    const ctx = document.getElementById('evolutionChart').getContext('2d');
    const evolutionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Performance Evolution',
                data: data,
                borderColor: 'blue',
                fill: true,
                backgroundColor: 'rgba(0, 123, 255, 0.1)'
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    suggestedMax: 10
                }
            }
        }
    });
</script>
</body>
</html>
