# Dynamic Math Quiz System

## Overview
The dynamic math quiz system generates unique math questions every time a quiz is taken. This ensures students never see the same questions twice and provides infinite practice opportunities.

## Files Created

### 1. `/includes/QuestionGenerator.php`
Core class that generates math questions dynamically.

**Features:**
- Supports 5 question types: addition, subtraction, multiplication, division, percentage
- 3 difficulty levels: easy, medium, hard
- Generates multiple-choice questions with 4 options
- Smart wrong answer generation (plausible distractors)

### 2. `/api/questions.php`
API endpoint to serve dynamically generated questions.

**Endpoints:**
- `GET /api/questions.php?action=generate` - Generate questions
- `GET /api/questions.php?action=types` - Get available question types
- `GET /api/questions.php?action=difficulties` - Get difficulty levels

### 3. `/quiz-math-dynamic.html`
User interface for selecting difficulty level before starting the quiz.

### 4. `/quiz-take.html` (Modified)
Updated to support both static and dynamic question loading.

## How to Use

### For Students

#### Option 1: Direct Link (with difficulty selection page)
1. Navigate to `quiz-math-dynamic.html`
2. Choose a difficulty level (Easy, Medium, or Hard)
3. Take the quiz with fresh questions!

#### Option 2: Direct URL Parameters
Access the quiz directly with custom parameters:

```
quiz-take.html?id=2&dynamic=true&difficulty=easy
quiz-take.html?id=2&dynamic=true&difficulty=medium
quiz-take.html?id=2&dynamic=true&difficulty=hard
```

### For Developers

#### Generate Questions Programmatically

```php
require_once 'includes/QuestionGenerator.php';

$generator = new QuestionGenerator();

// Generate 10 medium difficulty questions
$quiz = $generator->generateQuiz(10, 'medium');

// Generate 5 easy addition and subtraction questions only
$quiz = $generator->generateQuiz(5, 'easy', ['addition', 'subtraction']);

// Generate 8 hard multiplication and percentage questions
$quiz = $generator->generateQuiz(8, 'hard', ['multiplication', 'percentage']);
```

#### API Usage

```javascript
// Generate 10 medium difficulty questions
fetch('api/questions.php?action=generate&count=10&difficulty=medium')
    .then(res => res.json())
    .then(data => console.log(data.quiz));

// Generate 5 easy addition questions only
fetch('api/questions.php?action=generate&count=5&difficulty=easy&types=addition')
    .then(res => res.json())
    .then(data => console.log(data.quiz));

// Get available question types
fetch('api/questions.php?action=types')
    .then(res => res.json())
    .then(data => console.log(data.types));
```

## Difficulty Levels

### Easy
- **Addition:** 1-20
- **Subtraction:** 1-20
- **Multiplication:** 1-10
- **Division:** 1-10
- **Percentage:** Simple values (10%, 20%, 25%, 50%, 75% of 20, 40, 50, 80, 100)
- **Rewards:** 80 XP, 40 Coins

### Medium
- **Addition:** 10-100
- **Subtraction:** 10-100
- **Multiplication:** 5-15
- **Division:** 5-15
- **Percentage:** Moderate values (15-75% of 50-200)
- **Rewards:** 100 XP, 50 Coins

### Hard
- **Addition:** 50-500
- **Subtraction:** 50-500
- **Multiplication:** 10-25
- **Division:** 10-25
- **Percentage:** Complex values (12-85% of 150-750)
- **Rewards:** 120 XP, 60 Coins

## Question Types

1. **Addition** - "What is X + Y?"
2. **Subtraction** - "What is X - Y?" (always positive results)
3. **Multiplication** - "What is X × Y?"
4. **Division** - "What is X ÷ Y?" (always whole number results)
5. **Percentage** - "What is X% of Y?"

## Customization

### Add New Question Types

Edit `/includes/QuestionGenerator.php`:

```php
// 1. Add to question types array
private $questionTypes = ['addition', 'subtraction', 'multiplication', 'division', 'percentage', 'YOUR_NEW_TYPE'];

// 2. Add difficulty configuration
private $difficulties = [
    'easy' => [
        // ... existing types
        'YOUR_NEW_TYPE' => ['min' => 1, 'max' => 20]
    ]
];

// 3. Create generation method
private function generateYourNewTypeQuestion($difficulty) {
    $config = $this->difficulties[$difficulty]['YOUR_NEW_TYPE'];
    // ... your logic here
    return [
        'question' => "Your question?",
        'correct' => $answer,
        'options' => $this->generateOptions($answer, 'number'),
        'type' => 'YOUR_NEW_TYPE',
        'difficulty' => $difficulty
    ];
}
```

### Adjust Difficulty Ranges

Edit the `$difficulties` array in `/includes/QuestionGenerator.php`:

```php
private $difficulties = [
    'easy' => [
        'addition' => ['min' => 1, 'max' => 50], // Changed from 20 to 50
        // ... other types
    ]
];
```

### Change Rewards

Edit `/quiz-take.html` in the `loadDynamicMathQuiz()` function:

```javascript
currentQuiz = {
    // ...
    xpReward: difficulty === 'easy' ? 100 : difficulty === 'hard' ? 150 : 125, // Increased rewards
    coinReward: difficulty === 'easy' ? 50 : difficulty === 'hard' ? 75 : 60
};
```

## Testing

### Test Question Generation (Command Line)

```bash
cd /Applications/MAMP/htdocs/bright-minds
/Applications/MAMP/bin/php/php8.4.1/bin/php -r "
require_once 'includes/QuestionGenerator.php';
\$generator = new QuestionGenerator();
\$quiz = \$generator->generateQuiz(5, 'medium');
echo json_encode(\$quiz, JSON_PRETTY_PRINT);
"
```

### Test API Endpoint

1. Start MAMP
2. Open browser: `http://localhost:8888/bright-minds/api/questions.php?action=generate&count=5&difficulty=easy`

### Test Full Quiz Flow

1. Navigate to `http://localhost:8888/bright-minds/quiz-math-dynamic.html`
2. Select a difficulty
3. Complete the quiz
4. Verify unique questions on each attempt

## Integration with Existing System

The dynamic quiz system is fully compatible with the existing quiz infrastructure:

- Uses the same quiz-take.html interface
- Works with existing timer, scoring, and reward systems
- Integrates with XP and coin award mechanisms
- Compatible with the review/results screen

### Switching Between Static and Dynamic

**Static (original hardcoded questions):**
```
quiz-take.html?id=2
```

**Dynamic (generated questions):**
```
quiz-take.html?id=2&dynamic=true&difficulty=medium
```

## Future Enhancements

Potential improvements you could add:

1. **Word Problems** - Generate contextual math problems
2. **Fractions** - "What is 1/2 + 1/4?"
3. **Decimals** - "What is 3.5 × 2.2?"
4. **Order of Operations** - "What is (5 + 3) × 2?"
5. **Algebra** - "If x + 5 = 12, what is x?"
6. **Save Question History** - Track which questions were shown to prevent recent repeats
7. **Adaptive Difficulty** - Adjust difficulty based on student performance
8. **Topic-Specific Quizzes** - Allow filtering by single operation type

## Troubleshooting

**Questions not loading?**
- Check MAMP is running
- Verify file paths are correct
- Check browser console for errors
- Ensure QuestionGenerator.php has no syntax errors

**Same questions appearing?**
- This is expected occasionally due to random generation
- Questions are generated fresh each time, but may coincidentally be similar
- Consider implementing question history tracking to prevent recent duplicates

**Wrong answers marked as correct?**
- Verify the `correct` index matches the option array index
- Check that options array is properly shuffled and indexed

## Questions?

For issues or questions, check the browser console and PHP error logs in MAMP.
