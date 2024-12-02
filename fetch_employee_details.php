<?php
header('Content-Type: application/json');
include 'db_connection.php';

if (isset($_GET['employeeID'])) {
    $employeeID = intval($_GET['employeeID']);

    // Fetch employee details including DepartmentID and Name
    $employeeQuery = "
        SELECT Employee.Name, Employee.DepartmentID
        FROM Employee
        WHERE Employee.EmployeeID = ?";
    $stmt = $conn->prepare($employeeQuery);
    $stmt->bind_param("i", $employeeID);
    $stmt->execute();
    $employeeResult = $stmt->get_result();

    if ($employeeResult && $employeeResult->num_rows > 0) {
        $employeeData = $employeeResult->fetch_assoc();
        $employeeName = $employeeData['Name'];
        $departmentID = $employeeData['DepartmentID'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }

    // Fetch the latest year and trimester for the employee's evaluations
    $latestEvaluationQuery = "
        SELECT MAX(ev.Year) AS LatestYear, MAX(ev.Trimester) AS LatestTrimester
        FROM employeeevaluation ev
        WHERE ev.EmployeeID = ?";
    $stmt = $conn->prepare($latestEvaluationQuery);
    $stmt->bind_param("i", $employeeID);
    $stmt->execute();
    $latestEvaluationResult = $stmt->get_result();
    $latestEvaluation = $latestEvaluationResult->fetch_assoc();

    $latestYear = $latestEvaluation['LatestYear'] ?? null;
    $latestTrimester = $latestEvaluation['LatestTrimester'] ?? null;

    if (!$latestYear || !$latestTrimester) {
        echo json_encode(['success' => false, 'message' => 'No evaluations found for the employee.']);
        exit;
    }

    // Fetch performance evaluations for the employee
    $scoresQuery = "SELECT Trimester, Year, TotalScore FROM EmployeeEvaluation WHERE EmployeeID = ? ORDER BY Year, Trimester";
    $stmt = $conn->prepare($scoresQuery);
    $stmt->bind_param("i", $employeeID);
    $stmt->execute();
    $scoresResult = $stmt->get_result();
    $scores = $scoresResult->fetch_all(MYSQLI_ASSOC);

    // Fetch the last score (most recent evaluation)
    $lastScoreQuery = "
        SELECT Trimester, Year, TotalScore 
        FROM EmployeeEvaluation 
        WHERE EmployeeID = ? 
        AND Year = ? AND Trimester = ?
        LIMIT 1";
    $stmt = $conn->prepare($lastScoreQuery);
    $stmt->bind_param("iii", $employeeID, $latestYear, $latestTrimester);
    $stmt->execute();
    $lastScoreResult = $stmt->get_result();
    $lastScore = $lastScoreResult->fetch_assoc();

    // Fetch trimester scores for the department
    $departmentScoresQuery = "SELECT Trimester, Year, AverageScore FROM departmentevaluation WHERE DepartmentID = ? ORDER BY Year, Trimester";
    $stmt = $conn->prepare($departmentScoresQuery);
    $stmt->bind_param("i", $departmentID);
    $stmt->execute();
    $departmentScoresResult = $stmt->get_result();
    $trimesterScores = $departmentScoresResult->fetch_all(MYSQLI_ASSOC);

    // Fetch criteria scores for the last year and trimester
    $criteriaScoresQuery = "
        SELECT 
            c.CriteriaName, 
            AVG(e.Score) AS AverageScoreCriteria
        FROM evaluationcriteriascore e
        JOIN criteria c ON e.CriteriaID = c.CriteriaID
        JOIN employeeevaluation ev ON e.EvaluationID = ev.EvaluationID
        WHERE ev.EmployeeID = ? AND ev.Year = ? AND ev.Trimester = ?
        GROUP BY c.CriteriaName
        ORDER BY c.CriteriaName";
    $stmt = $conn->prepare($criteriaScoresQuery);
    $stmt->bind_param("iii", $employeeID, $latestYear, $latestTrimester);
    $stmt->execute();
    $criteriaScoresResult = $stmt->get_result();
    $criteriaScores = $criteriaScoresResult->fetch_all(MYSQLI_ASSOC);

    // Generate feedback
    $generatedFeedback = "";
    $bestCriteria = null;
    $worstCriteria = null;
    $highestScore = -1; // Initialize with a very low value
    $lowestScore = 11; // Initialize with a very high value (assuming scores range from 0 to 10)

    foreach ($criteriaScores as $criteria) {
        $criteriaName = $criteria['CriteriaName'];
        $AverageScoreCriteria = round($criteria['AverageScoreCriteria'], 2);

        if ($AverageScoreCriteria >= 8) {
            $generatedFeedback .= "Excellent performance in $criteriaName with an average score of $AverageScoreCriteria.\n";
        } elseif ($AverageScoreCriteria >= 5) {
            $generatedFeedback .= "Good performance in $criteriaName with an average score of $AverageScoreCriteria, but there is room for improvement.\n";
        } else {
            $generatedFeedback .= "$criteriaName requires significant improvement, as the average score is $AverageScoreCriteria.\n";
        }

        // Check for the highest score
        if ($AverageScoreCriteria > $highestScore) {
            $highestScore = $AverageScoreCriteria;
            $bestCriteria = $criteriaName;
        }

        // Check for the lowest score
        if ($AverageScoreCriteria < $lowestScore) {
            $lowestScore = $AverageScoreCriteria;
            $worstCriteria = $criteriaName;
        }
    }

    // Combine and send response
    echo json_encode([
        'success' => true,
        'employeeName' => $employeeName,
        'scores' => $scores,
        'lastScore' => $lastScore,
        'trimesterScores' => $trimesterScores,
        'feedback' => $generatedFeedback,
        'bestCriteria' => [
            'name' => $bestCriteria,
            'score' => $highestScore
        ],
        'worstCriteria' => [
            'name' => $worstCriteria,
            'score' => $lowestScore
        ],
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}
?>
