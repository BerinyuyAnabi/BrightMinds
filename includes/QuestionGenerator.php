<?php
/**
 * Math Question Generator
 * Dynamically generates math quiz questions with varying difficulty levels
 */
class QuestionGenerator {

    private $difficulties = [
        'easy' => [
            'addition' => ['min' => 1, 'max' => 20],
            'subtraction' => ['min' => 1, 'max' => 20],
            'multiplication' => ['min' => 1, 'max' => 10],
            'division' => ['min' => 1, 'max' => 10],
            'percentage' => ['base' => [20, 40, 50, 80, 100], 'percent' => [10, 20, 25, 50, 75]]
        ],
        'medium' => [
            'addition' => ['min' => 10, 'max' => 100],
            'subtraction' => ['min' => 10, 'max' => 100],
            'multiplication' => ['min' => 5, 'max' => 15],
            'division' => ['min' => 5, 'max' => 15],
            'percentage' => ['base' => [50, 80, 120, 150, 200], 'percent' => [15, 20, 25, 30, 40, 60, 75]]
        ],
        'hard' => [
            'addition' => ['min' => 50, 'max' => 500],
            'subtraction' => ['min' => 50, 'max' => 500],
            'multiplication' => ['min' => 10, 'max' => 25],
            'division' => ['min' => 10, 'max' => 25],
            'percentage' => ['base' => [150, 240, 320, 500, 750], 'percent' => [12, 18, 22, 35, 45, 65, 85]]
        ]
    ];

    private $questionTypes = ['addition', 'subtraction', 'multiplication', 'division', 'percentage'];

    /**
     * Generate a set of math questions
     * @param int $count Number of questions to generate
     * @param string $difficulty Difficulty level (easy, medium, hard)
     * @param array $types Optional array of question types to include
     * @return array Array of question objects
     */
    public function generateQuestions($count = 10, $difficulty = 'medium', $types = null) {
        if (!isset($this->difficulties[$difficulty])) {
            $difficulty = 'medium';
        }

        if ($types === null || empty($types)) {
            $types = $this->questionTypes;
        }

        $questions = [];
        for ($i = 0; $i < $count; $i++) {
            $type = $types[array_rand($types)];
            $questions[] = $this->generateQuestion($type, $difficulty);
        }

        return $questions;
    }

    /**
     * Generate a single question based on type and difficulty
     */
    private function generateQuestion($type, $difficulty) {
        $method = 'generate' . ucfirst($type) . 'Question';
        if (method_exists($this, $method)) {
            return $this->$method($difficulty);
        }

        // Fallback to addition if method doesn't exist
        return $this->generateAdditionQuestion($difficulty);
    }

    /**
     * Generate an addition question
     */
    private function generateAdditionQuestion($difficulty) {
        $config = $this->difficulties[$difficulty]['addition'];
        $num1 = rand($config['min'], $config['max']);
        $num2 = rand($config['min'], $config['max']);
        $answer = $num1 + $num2;

        return [
            'question' => "What is {$num1} + {$num2}?",
            'correct' => $answer,
            'options' => $this->generateOptions($answer, 'number'),
            'type' => 'addition',
            'difficulty' => $difficulty
        ];
    }

    /**
     * Generate a subtraction question
     */
    private function generateSubtractionQuestion($difficulty) {
        $config = $this->difficulties[$difficulty]['subtraction'];
        $num1 = rand($config['min'], $config['max']);
        $num2 = rand($config['min'], $num1); // Ensure positive result
        $answer = $num1 - $num2;

        return [
            'question' => "What is {$num1} - {$num2}?",
            'correct' => $answer,
            'options' => $this->generateOptions($answer, 'number'),
            'type' => 'subtraction',
            'difficulty' => $difficulty
        ];
    }

    /**
     * Generate a multiplication question
     */
    private function generateMultiplicationQuestion($difficulty) {
        $config = $this->difficulties[$difficulty]['multiplication'];
        $num1 = rand($config['min'], $config['max']);
        $num2 = rand($config['min'], $config['max']);
        $answer = $num1 * $num2;

        return [
            'question' => "What is {$num1} × {$num2}?",
            'correct' => $answer,
            'options' => $this->generateOptions($answer, 'number'),
            'type' => 'multiplication',
            'difficulty' => $difficulty
        ];
    }

    /**
     * Generate a division question
     */
    private function generateDivisionQuestion($difficulty) {
        $config = $this->difficulties[$difficulty]['division'];
        $divisor = rand($config['min'], $config['max']);
        $answer = rand($config['min'], $config['max']);
        $dividend = $divisor * $answer; // Ensure whole number result

        return [
            'question' => "What is {$dividend} ÷ {$divisor}?",
            'correct' => $answer,
            'options' => $this->generateOptions($answer, 'number'),
            'type' => 'division',
            'difficulty' => $difficulty
        ];
    }

    /**
     * Generate a percentage question
     */
    private function generatePercentageQuestion($difficulty) {
        $config = $this->difficulties[$difficulty]['percentage'];
        $base = $config['base'][array_rand($config['base'])];
        $percent = $config['percent'][array_rand($config['percent'])];
        $answer = ($percent / 100) * $base;

        return [
            'question' => "What is {$percent}% of {$base}?",
            'correct' => $answer,
            'options' => $this->generateOptions($answer, 'number'),
            'type' => 'percentage',
            'difficulty' => $difficulty
        ];
    }

    /**
     * Generate multiple choice options with one correct answer
     */
    private function generateOptions($correctAnswer, $type = 'number') {
        $options = [$correctAnswer];

        // Generate 3 plausible wrong answers
        $attempts = 0;
        while (count($options) < 4 && $attempts < 20) {
            $offset = rand(-10, 10);
            if ($offset == 0) {
                $offset = rand(1, 5);
            }

            $wrongAnswer = $correctAnswer + $offset;

            // Ensure positive numbers and no duplicates
            if ($wrongAnswer > 0 && !in_array($wrongAnswer, $options)) {
                $options[] = $wrongAnswer;
            }

            $attempts++;
        }

        // Fill with random values if we couldn't generate enough
        while (count($options) < 4) {
            $random = rand(max(1, $correctAnswer - 20), $correctAnswer + 20);
            if (!in_array($random, $options)) {
                $options[] = $random;
            }
        }

        // Shuffle options so correct answer isn't always first
        shuffle($options);

        // Find the index of correct answer for return
        $correctIndex = array_search($correctAnswer, $options);

        return [
            'choices' => $options,
            'correctIndex' => $correctIndex
        ];
    }

    /**
     * Generate a quiz with questions formatted for the quiz system
     * @param int $questionCount Number of questions
     * @param string $difficulty Difficulty level
     * @param array $types Question types to include
     * @return array Formatted quiz data
     */
    public function generateQuiz($questionCount = 10, $difficulty = 'medium', $types = null) {
        $questions = $this->generateQuestions($questionCount, $difficulty, $types);

        // Format questions for the frontend quiz system
        $formattedQuestions = [];
        foreach ($questions as $q) {
            $formattedQuestions[] = [
                'question' => $q['question'],
                'options' => $q['options']['choices'],
                'correct' => $q['options']['correctIndex'],
                'type' => $q['type'],
                'difficulty' => $q['difficulty']
            ];
        }

        return [
            'questions' => $formattedQuestions,
            'metadata' => [
                'difficulty' => $difficulty,
                'questionCount' => $questionCount,
                'generated' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Get available difficulty levels
     */
    public function getDifficultyLevels() {
        return array_keys($this->difficulties);
    }

    /**
     * Get available question types
     */
    public function getQuestionTypes() {
        return $this->questionTypes;
    }
}
