<?php
/**
 * Bright Minds - Dynamic Question Generator API
 * Generates math quiz questions dynamically
 */

require_once '../includes/config.php';
require_once '../includes/QuestionGenerator.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'generate':
        handleGenerate();
        break;
    case 'types':
        handleGetTypes();
        break;
    case 'difficulties':
        handleGetDifficulties();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

/**
 * Generate dynamic math quiz questions
 */
function handleGenerate() {
    $generator = new QuestionGenerator();

    // Get parameters from request
    $count = intval($_GET['count'] ?? 10);
    $difficulty = $_GET['difficulty'] ?? 'medium';
    $types = isset($_GET['types']) ? explode(',', $_GET['types']) : null;

    // Validate count
    if ($count < 1 || $count > 50) {
        $count = 10;
    }

    // Validate difficulty
    $validDifficulties = $generator->getDifficultyLevels();
    if (!in_array($difficulty, $validDifficulties)) {
        $difficulty = 'medium';
    }

    // Validate types
    if ($types !== null) {
        $validTypes = $generator->getQuestionTypes();
        $types = array_filter($types, function($type) use ($validTypes) {
            return in_array(trim($type), $validTypes);
        });

        if (empty($types)) {
            $types = null; // Use all types if invalid types provided
        }
    }

    // Generate quiz
    $quiz = $generator->generateQuiz($count, $difficulty, $types);

    jsonResponse([
        'success' => true,
        'quiz' => $quiz,
        'params' => [
            'count' => $count,
            'difficulty' => $difficulty,
            'types' => $types ?? $generator->getQuestionTypes()
        ]
    ]);
}

/**
 * Get available question types
 */
function handleGetTypes() {
    $generator = new QuestionGenerator();

    jsonResponse([
        'success' => true,
        'types' => $generator->getQuestionTypes()
    ]);
}

/**
 * Get available difficulty levels
 */
function handleGetDifficulties() {
    $generator = new QuestionGenerator();

    jsonResponse([
        'success' => true,
        'difficulties' => $generator->getDifficultyLevels()
    ]);
}

?>
