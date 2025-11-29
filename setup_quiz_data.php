<?php
/**
 * Setup Quiz Data - Populate quiz questions and options
 */

require_once 'includes/config.php';

$db = getDB();
$conn = $db->getConnection();

echo "<h2>Quiz Data Setup</h2>";
echo "<p>This will populate the quiz questions and options tables.</p><hr>";

// Check current state
echo "<h3>Current State:</h3>";
$quizCount = $conn->query("SELECT COUNT(*) as cnt FROM quizzes")->fetch_assoc()['cnt'];
$questionCount = $conn->query("SELECT COUNT(*) as cnt FROM quiz_questions")->fetch_assoc()['cnt'];
$optionCount = $conn->query("SELECT COUNT(*) as cnt FROM quiz_options")->fetch_assoc()['cnt'];

echo "Quizzes: $quizCount<br>";
echo "Questions: $questionCount<br>";
echo "Options: $optionCount<br><br>";

if ($questionCount > 0) {
    echo "<p style='color: orange;'>⚠️ Questions already exist. Delete them first? <a href='?delete=1'>Yes, delete and repopulate</a></p>";

    if (isset($_GET['delete'])) {
        echo "<h3>Deleting existing data...</h3>";
        $conn->query("DELETE FROM quiz_options");
        $conn->query("DELETE FROM quiz_questions");
        echo "✅ Deleted<br><br>";
    } else {
        exit;
    }
}

echo "<h3>Inserting Quiz Questions...</h3>";

// Quiz 1: Animal Kingdom (5 questions)
$questions = [
    [1, 'What is the largest animal in the ocean?', 'multiple_choice', 'A', 'The blue whale can grow up to 100 feet long!', 10,
        ['Blue Whale', 'Shark', 'Dolphin', 'Octopus']],
    [1, 'How many legs does a spider have?', 'multiple_choice', 'B', 'All spiders have 8 legs!', 10,
        ['6', '8', '10', '4']],
    [1, 'Which animal is known as the King of the Jungle?', 'multiple_choice', 'A', 'Lions are called the King of the Jungle!', 10,
        ['Lion', 'Tiger', 'Elephant', 'Bear']],
    [1, 'What do pandas mainly eat?', 'multiple_choice', 'C', 'Pandas eat bamboo almost exclusively!', 10,
        ['Meat', 'Fish', 'Bamboo', 'Fruit']],
    [1, 'Which bird cannot fly?', 'multiple_choice', 'D', 'Penguins are excellent swimmers but cannot fly!', 10,
        ['Eagle', 'Sparrow', 'Owl', 'Penguin']],

    // Quiz 2: Math Champions (5 questions)
    [2, 'What is 15 + 28?', 'multiple_choice', 'B', '15 + 28 = 43', 10,
        ['42', '43', '44', '45']],
    [2, 'What is 7 × 8?', 'multiple_choice', 'A', '7 × 8 = 56', 10,
        ['56', '54', '48', '63']],
    [2, 'What is half of 100?', 'multiple_choice', 'D', 'Half of 100 is 50', 10,
        ['25', '75', '40', '50']],
    [2, 'What is 100 - 37?', 'multiple_choice', 'C', '100 - 37 = 63', 10,
        ['73', '62', '63', '64']],
    [2, 'How many sides does a triangle have?', 'multiple_choice', 'A', 'A triangle has 3 sides', 10,
        ['3', '4', '5', '6']],

    // Quiz 3: World Geography (5 questions)
    [3, 'What is the capital of France?', 'multiple_choice', 'A', 'Paris is the capital of France', 10,
        ['Paris', 'London', 'Rome', 'Berlin']],
    [3, 'Which continent is Egypt in?', 'multiple_choice', 'C', 'Egypt is in Africa', 10,
        ['Asia', 'Europe', 'Africa', 'Australia']],
    [3, 'What is the largest ocean?', 'multiple_choice', 'B', 'The Pacific Ocean is the largest', 10,
        ['Atlantic', 'Pacific', 'Indian', 'Arctic']],
    [3, 'How many continents are there?', 'multiple_choice', 'D', 'There are 7 continents', 10,
        ['5', '6', '8', '7']],
    [3, 'Which country is known for the Eiffel Tower?', 'multiple_choice', 'A', 'The Eiffel Tower is in France', 10,
        ['France', 'Italy', 'Spain', 'Germany']],

    // Quiz 4: Space Adventures (5 questions)
    [4, 'Which planet has rings?', 'multiple_choice', 'C', 'Saturn is famous for its rings', 10,
        ['Mars', 'Venus', 'Saturn', 'Mercury']],
    [4, 'What is the closest star to Earth?', 'multiple_choice', 'B', 'The Sun is our closest star', 10,
        ['Moon', 'Sun', 'Mars', 'Venus']],
    [4, 'How long does Earth take to orbit the Sun?', 'multiple_choice', 'B', 'Earth takes 1 year to orbit the Sun', 10,
        ['1 month', '1 year', '1 day', '1 week']],
    [4, 'Which planet is closest to the Sun?', 'multiple_choice', 'B', 'Mercury is the closest planet to the Sun', 10,
        ['Venus', 'Mercury', 'Earth', 'Mars']],
    [4, 'What galaxy do we live in?', 'multiple_choice', 'B', 'We live in the Milky Way galaxy', 10,
        ['Andromeda', 'Milky Way', 'Whirlpool', 'Sombrero']],
];

$insertedQuestions = 0;
$insertedOptions = 0;

foreach ($questions as $q) {
    list($quizID, $questionText, $questionType, $correctAnswer, $explanation, $points, $options) = $q;

    // Insert question
    $stmt = $conn->prepare("INSERT INTO quiz_questions (quizID, question_text, question_type, correct_answer, explanation, points, order_num) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $orderNum = $insertedQuestions + 1;
    $stmt->bind_param("issssii", $quizID, $questionText, $questionType, $correctAnswer, $explanation, $points, $orderNum);
    $stmt->execute();
    $questionID = $conn->insert_id;
    $insertedQuestions++;

    echo "✅ Question $insertedQuestions: $questionText<br>";

    // Insert options
    $optionLetters = ['A', 'B', 'C', 'D'];
    foreach ($options as $idx => $optionText) {
        $isCorrect = ($optionLetters[$idx] === $correctAnswer) ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO quiz_options (questionID, option_text, is_correct, order_num) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isii", $questionID, $optionText, $isCorrect, $idx);
        $stmt->execute();
        $insertedOptions++;
    }
}

echo "<br><h3>✅ Setup Complete!</h3>";
echo "<p>Inserted $insertedQuestions questions and $insertedOptions options</p>";
echo "<hr>";
echo "<p><a href='quiz.html'>Go to Quizzes</a> | <a href='dashboard.php'>Go to Dashboard</a></p>";
?>
