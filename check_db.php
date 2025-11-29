<?php
require_once 'includes/config.php';
$db = getDB();

echo "<h2>Database Check</h2>";

$questionCount = $db->selectOne("SELECT COUNT(*) as cnt FROM quiz_questions", []);
echo "<p>Quiz questions in database: " . $questionCount['cnt'] . "</p>";

if ($questionCount['cnt'] > 0) {
    $questions = $db->select("SELECT quizID, COUNT(*) as cnt FROM quiz_questions GROUP BY quizID", []);
    echo "<h3>Questions per quiz:</h3><ul>";
    foreach ($questions as $q) {
        echo "<li>Quiz {$q['quizID']}: {$q['cnt']} questions</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>⚠️ No questions in database! Need to run the SQL file.</p>";
}
?>
