<?php
header('Content-Type: application/json');
include 'db_connection.php';

// Check if the department ID is provided
if (isset($_GET['departmentID'])) {
    $departmentID = intval($_GET['departmentID']);

    // Retrieve the department name
    $departmentNameQuery = "SELECT Department FROM Department WHERE DepartmentID = ?";
    $stmt = $conn->prepare($departmentNameQuery);
    $stmt->bind_param("i", $departmentID);
    $stmt->execute();
    $departmentNameResult = $stmt->get_result();
    $departmentName = $departmentNameResult->fetch_assoc()['Department'] ?? 'Unknown Department';

    // Get the latest trimester and year
    $latestEvaluationQuery = "
        SELECT MAX(Year) AS LatestYear, MAX(Trimester) AS LatestTrimester
        FROM departmentevaluation
        WHERE DepartmentID = ?";
    $stmt = $conn->prepare($latestEvaluationQuery);
    $stmt->bind_param("i", $departmentID);
    $stmt->execute();
    $latestEvaluationResult = $stmt->get_result();
    $latestEvaluation = $latestEvaluationResult->fetch_assoc();

    if ($latestEvaluation) {
        $latestYear = $latestEvaluation['LatestYear'];
        $latestTrimester = $latestEvaluation['LatestTrimester'];

        // Fetch department's average performance score for the latest trimester and year
        $scoreQuery = "
            SELECT AverageScore 
            FROM departmentevaluation
            WHERE DepartmentID = ? AND Year = ? AND Trimester = ?";
        $stmt = $conn->prepare($scoreQuery);
        $stmt->bind_param("iii", $departmentID, $latestYear, $latestTrimester);
        $stmt->execute();
        $scoreResult = $stmt->get_result();
        $averageScore = $scoreResult->fetch_assoc()['AverageScore'] ?? null;

        // Fetch criteria scores for the latest trimester and year
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

        // Generate feedback for the latest trimester and year
        $generatedFeedback = "";
        $bestCriteria = null;
        $worstCriteria = null;
        $highestScore = -1; // Initialize with a very low value
        $lowestScore = 11; // Initialize with a very high value (scores are between 0 and 10)

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

            // Determine best and worst criteria
            if ($AverageScoreCriteria > $highestScore) {
                $highestScore = $AverageScoreCriteria;
                $bestCriteria = $criteriaName;
            }
            if ($AverageScoreCriteria < $lowestScore) {
                $lowestScore = $AverageScoreCriteria;
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

        // Output the department data
        echo json_encode([
            'success' => true,
            'departmentName' => $departmentName,
            'latestYear' => $latestYear,
            'latestTrimester' => $latestTrimester,
            'trimesterScores' => $trimesterScores,
            'averageScore' => $averageScore,
            'feedback' => $generatedFeedback,
            'bestCriteria' => [
                'name' => $bestCriteria,
                'score' => $highestScore
            ],
            'worstCriteria' => [
                'name' => $worstCriteria,
                'score' => $lowestScore
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No evaluations found for the department.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>



