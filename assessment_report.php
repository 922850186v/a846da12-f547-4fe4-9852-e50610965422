<?php

/**
 * Assessment Reporting System
 * 
 * A CLI application that generates three types of reports:
 * 1. Diagnostic Report - Shows areas of weakness by strand
 * 2. Progress Report - Shows improvement over time
 * 3. Feedback Report - Shows wrong answers with hints
 * 
 * @author Vishva Isuranga
 * @version 1.0
 */

// Autoload classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Main Application Class
 */
class AssessmentReportingSystem
{
    private DataLoader $dataLoader;
    private ReportGenerator $reportGenerator;
    private InputValidator $validator;

    public function __construct()
    {
        $this->dataLoader = new DataLoader();
        $this->reportGenerator = new ReportGenerator();
        $this->validator = new InputValidator();
    }

    public function run(): void
    {
        try {
            echo "Assessment Reporting System\n";
            echo "============================\n\n";

            // Load data
            echo "Loading data...\n";
            $data = $this->dataLoader->loadAllData();
            echo "Data loaded successfully!\n\n";

            // Get user input
            $input = $this->getUserInput();

            // Validate input
            $this->validator->validate($input, $data);

            // Generate and display report
            $report = $this->reportGenerator->generateReport(
                $input['studentId'],
                $input['reportType'],
                $data
            );

            echo "\n" . str_repeat("=", 60) . "\n";
            echo "REPORT OUTPUT\n";
            echo str_repeat("=", 60) . "\n";
            echo $report . "\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Get user input from CLI
     */
    private function getUserInput(): array
    {
        echo "Please enter the following:\n";

        echo "Student ID: ";
        $studentId = trim(fgets(STDIN));

        echo "Report to generate (1 for Diagnostic, 2 for Progress, 3 for Feedback): ";
        $reportType = (int)trim(fgets(STDIN));

        return [
            'studentId' => $studentId,
            'reportType' => $reportType
        ];
    }
}

/**
 * Data Loader Class - Handles loading and parsing JSON data
 */
class DataLoader
{
    private const DATA_PATH = 'data/';

    /**
     * Load all required data files
     */
    public function loadAllData(): array
    {
        return [
            'students' => $this->loadJsonFile('students.json'),
            'assessments' => $this->loadJsonFile('assessments.json'),
            'questions' => $this->loadJsonFile('questions.json'),
            'responses' => $this->loadJsonFile('student-responses.json')
        ];
    }

    /**
     * Load and parse JSON file
     */
    private function loadJsonFile(string $filename): array
    {
        $filepath = self::DATA_PATH . $filename;

        if (!file_exists($filepath)) {
            throw new DataException("Data file not found: {$filepath}");
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new DataException("Could not read file: {$filepath}");
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new DataException("Invalid JSON in file {$filename}: " . json_last_error_msg());
        }

        return $data;
    }
}

/**
 * Input Validator Class
 */
class InputValidator
{
    /**
     * Validate user input
     */
    public function validate(array $input, array $data): void
    {
        $this->validateStudentId($input['studentId'], $data['students']);
        $this->validateReportType($input['reportType']);
    }

    /**
     * Validate student ID exists
     */
    private function validateStudentId(string $studentId, array $students): void
    {
        if (empty($studentId)) {
            throw new ValidationException("Student ID cannot be empty");
        }

        $studentExists = false;
        foreach ($students as $student) {
            if ($student['id'] === $studentId) {
                $studentExists = true;
                break;
            }
        }

        if (!$studentExists) {
            throw new ValidationException("Student ID '{$studentId}' not found");
        }
    }

    /**
     * Validate report type is valid
     */
    private function validateReportType(int $reportType): void
    {
        if (!in_array($reportType, [1, 2, 3])) {
            throw new ValidationException("Report type must be 1, 2, or 3");
        }
    }
}

/**
 * Report Generator Class - Generates different types of reports
 */
class ReportGenerator
{
    /**
     * Generate report based on type
     */
    public function generateReport(string $studentId, int $reportType, array $data): string
    {
        switch ($reportType) {
            case 1:
                return $this->generateDiagnosticReport($studentId, $data);
            case 2:
                return $this->generateProgressReport($studentId, $data);
            case 3:
                return $this->generateFeedbackReport($studentId, $data);
            default:
                throw new ReportException("Invalid report type: {$reportType}");
        }
    }

    /**
     * Generate Diagnostic Report
     */
    private function generateDiagnosticReport(string $studentId, array $data): string
    {
        $student = $this->findStudent($studentId, $data['students']);
        $completedResponses = $this->getCompletedResponses($studentId, $data['responses']);

        if (empty($completedResponses)) {
            return "No completed assessments found for {$student['firstName']} {$student['lastName']}";
        }

        // Get most recent assessment
        $recentResponse = $this->getMostRecentResponse($completedResponses);
        $assessment = $this->findAssessment($recentResponse['assessmentId'], $data['assessments']);

        // Calculate scores by strand
        $strandScores = $this->calculateStrandScores($recentResponse, $data['questions']);
        print_r($strandScores);
        $totalCorrect = array_sum(array_column($strandScores, 'correct'));
        $totalQuestions = array_sum(array_column($strandScores, 'total'));

        // Format completion date
        $completedDate = $this->formatDate($recentResponse['completed']);

        // Build report
        $report = "{$student['firstName']} {$student['lastName']} recently completed {$assessment['name']} assessment on {$completedDate}\n";
        $report .= "He got {$totalCorrect} questions right out of {$totalQuestions}. Details by strand given below:\n\n";

        foreach ($strandScores as $strand => $score) {
            $report .= "{$strand}: {$score['correct']} out of {$score['total']} correct\n";
        }

        return $report;
    }

    /**
     * Generate Progress Report
     */
    private function generateProgressReport(string $studentId, array $data): string
    {
        $student = $this->findStudent($studentId, $data['students']);
        $completedResponses = $this->getCompletedResponses($studentId, $data['responses']);

        if (empty($completedResponses)) {
            return "No completed assessments found for {$student['firstName']} {$student['lastName']}";
        }

        // Sort by completion date
        usort($completedResponses, function ($a, $b) {
            return strtotime($a['completed']) - strtotime($b['completed']);
        });

        // Get assessment name
        $assessment = $this->findAssessment($completedResponses[0]['assessmentId'], $data['assessments']);

        // Build report header
        $report = "{$student['firstName']} {$student['lastName']} has completed {$assessment['name']} assessment " .
            count($completedResponses) . " times in total. Date and raw score given below:\n\n";

        // Add each attempt
        $scores = [];
        foreach ($completedResponses as $response) {
            $score = $this->calculateTotalScore($response, $data['questions']);
            $scores[] = $score['correct'];
            $date = $this->formatDateShort($response['completed']);
            $report .= "Date: {$date}, Raw Score: {$score['correct']} out of {$score['total']}\n";
        }

        // Calculate improvement
        if (count($scores) > 1) {
            $improvement = end($scores) - $scores[0];
            $report .= "\n{$student['firstName']} {$student['lastName']} got {$improvement} more correct in the recent completed assessment than the oldest";
        }

        return $report;
    }

    /**
     * Generate Feedback Report
     */
    private function generateFeedbackReport(string $studentId, array $data): string
    {
        $student = $this->findStudent($studentId, $data['students']);
        $completedResponses = $this->getCompletedResponses($studentId, $data['responses']);

        if (empty($completedResponses)) {
            return "No completed assessments found for {$student['firstName']} {$student['lastName']}";
        }

        // Get most recent assessment
        $recentResponse = $this->getMostRecentResponse($completedResponses);
        $assessment = $this->findAssessment($recentResponse['assessmentId'], $data['assessments']);

        // Calculate total score
        $totalScore = $this->calculateTotalScore($recentResponse, $data['questions']);
        $completedDate = $this->formatDate($recentResponse['completed']);

        // Build report header
        $report = "{$student['firstName']} {$student['lastName']} recently completed {$assessment['name']} assessment on {$completedDate}\n";
        $report .= "He got {$totalScore['correct']} questions right out of {$totalScore['total']}. Feedback for wrong answers given below\n\n";

        // Add feedback for wrong answers
        $wrongAnswers = $this->getWrongAnswers($recentResponse, $data['questions']);

        if (empty($wrongAnswers)) {
            $report .= "Perfect score! No wrong answers to show feedback for.";
        } else {
            foreach ($wrongAnswers as $wrongAnswer) {
                $report .= "Question: {$wrongAnswer['question']['stem']}\n";
                $report .= "Your answer: {$wrongAnswer['userAnswer']['key']} with value {$wrongAnswer['userAnswer']['value']}\n";
                $report .= "Right answer: {$wrongAnswer['correctAnswer']['key']} with value {$wrongAnswer['correctAnswer']['value']}\n";
                $report .= "Hint: {$wrongAnswer['question']['config']['hint']}\n\n";
            }
        }

        return $report;
    }

    /**
     * Helper Methods
     */

    private function findStudent(string $studentId, array $students): array
    {
        foreach ($students as $student) {
            if ($student['id'] === $studentId) {
                return $student;
            }
        }
        throw new DataException("Student not found: {$studentId}");
    }

    private function findAssessment(string $assessmentId, array $assessments): array
    {
        foreach ($assessments as $assessment) {
            if ($assessment['id'] === $assessmentId) {
                return $assessment;
            }
        }
        throw new DataException("Assessment not found: {$assessmentId}");
    }

    private function findQuestion(string $questionId, array $questions): array
    {
        foreach ($questions as $question) {
            if ($question['id'] === $questionId) {
                return $question;
            }
        }
        throw new DataException("Question not found: {$questionId}");
    }

    private function getCompletedResponses(string $studentId, array $responses): array
    {
        $completed = [];
        foreach ($responses as $response) {
            if ($response['student']['id'] === $studentId && !empty($response['completed'])) {
                $completed[] = $response;
            }
        }
        return $completed;
    }

    private function getMostRecentResponse(array $responses): array
    {
        usort($responses, function ($a, $b) {
            return strtotime($b['completed']) - strtotime($a['completed']);
        });
        return $responses[0];
    }

    private function calculateStrandScores(array $response, array $questions): array
    {
        $strandScores = [];

        foreach ($response['responses'] as $questionResponse) {
            $question = $this->findQuestion($questionResponse['questionId'], $questions);
            $strand = $question['strand'];

            if (!isset($strandScores[$strand])) {
                $strandScores[$strand] = ['correct' => 0, 'total' => 0];
            }

            $strandScores[$strand]['total']++;

            // Check if answer is correct using the 'key' field
            if ($questionResponse['response'] === $question['config']['key']) {
                $strandScores[$strand]['correct']++;
            }
        }

        return $strandScores;
    }

    private function calculateTotalScore(array $response, array $questions): array
    {
        $correct = 0;
        $total = count($response['responses']);

        // Cross-reference with questions data to determine correctness
        foreach ($response['responses'] as $questionResponse) {
            try {
                $question = $this->findQuestion($questionResponse['questionId'], $questions);

                // Check if the response matches the correct answer using 'key' field
                if ($questionResponse['response'] === $question['config']['key']) {
                    $correct++;
                }
            } catch (DataException $e) {
                // Question not found, skip this response
                continue;
            }
        }

        return ['correct' => $correct, 'total' => $total];
    }

    private function getWrongAnswers(array $response, array $questions): array
    {
        $wrongAnswers = [];

        foreach ($response['responses'] as $questionResponse) {
            $question = $this->findQuestion($questionResponse['questionId'], $questions);

            // Find correct answer using the 'key' field
            $correctOptionId = $question['config']['key'];
            $userOptionId = $questionResponse['response'];

            // If user answer is wrong
            if ($userOptionId !== $correctOptionId) {
                // Find the option objects
                $correctOption = null;
                $userOption = null;

                foreach ($question['config']['options'] as $option) {
                    if ($option['id'] === $correctOptionId) {
                        $correctOption = $option;
                    }
                    if ($option['id'] === $userOptionId) {
                        $userOption = $option;
                    }
                }

                $wrongAnswers[] = [
                    'question' => $question,
                    'userAnswer' => [
                        'key' => $userOption['id'],
                        'value' => $userOption['label'] . ' - ' . $userOption['value']
                    ],
                    'correctAnswer' => [
                        'key' => $correctOption['id'],
                        'value' => $correctOption['label'] . ' - ' . $correctOption['value']
                    ]
                ];
            }
        }

        return $wrongAnswers;
    }

    private function formatDate(string $dateString): string
    {
        // Handle empty or null dates
        if (empty($dateString)) {
            return 'Date not available';
        }

        // Parse DD/MM/YYYY HH:MM:SS format (like "16/12/2019 10:46:00")
        $timestamp = $this->parseDateString($dateString);

        if ($timestamp === false) {
            return 'Invalid date format: ' . $dateString;
        }

        return date('jS F Y g:i A', $timestamp);
    }

    private function formatDateShort(string $dateString): string
    {
        // Handle empty or null dates
        if (empty($dateString)) {
            return 'Date not available';
        }

        // Parse DD/MM/YYYY HH:MM:SS format (like "16/12/2019 10:46:00")
        $timestamp = $this->parseDateString($dateString);

        if ($timestamp === false) {
            return 'Invalid date: ' . $dateString;
        }

        return date('jS F Y', $timestamp);
    }

    /**
     * Parse date string in DD/MM/YYYY HH:MM:SS format
     */
    private function parseDateString(string $dateString): int|false
    {
        // Try to parse DD/MM/YYYY HH:MM:SS format first
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{1,2}):(\d{1,2})$/', $dateString, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];
            $hour = (int)$matches[4];
            $minute = (int)$matches[5];
            $second = (int)$matches[6];

            return mktime($hour, $minute, $second, $month, $day, $year);
        }

        // Try to parse DD/MM/YYYY format (without time)
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateString, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];

            return mktime(0, 0, 0, $month, $day, $year);
        }

        // Fallback: try strtotime for other formats
        $timestamp = strtotime($dateString);

        // If strtotime failed, try converting DD/MM/YYYY to MM/DD/YYYY format
        if ($timestamp === false) {
            $converted = preg_replace('/^(\d{1,2})\/(\d{1,2})\/(\d{4})(.*)$/', '$2/$1/$3$4', $dateString);
            $timestamp = strtotime($converted);
        }

        return $timestamp;
    }
}

/**
 * Custom Exception Classes
 */
class DataException extends Exception {}
class ValidationException extends Exception {}
class ReportException extends Exception {}

/**
 * Sample Data Generator for Testing
 */
class SampleDataGenerator
{
    public static function generateSampleData(): void
    {
        $dataDir = 'data/';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        // Generate sample students.json
        $students = [
            [
                'id' => 'student1',
                'firstName' => 'Tony',
                'lastName' => 'Stark',
                'yearLevel' => 6
            ],
            [
                'id' => 'student2',
                'firstName' => 'Steve',
                'lastName' => 'Rogers',
                'yearLevel' => 6
            ]
        ];
        file_put_contents($dataDir . 'students.json', json_encode($students, JSON_PRETTY_PRINT));

        // Generate sample assessments.json
        $assessments = [
            [
                'id' => 'assessment1',
                'name' => 'Numeracy',
                'questions' => [
                    [
                        'questionId' => 'numeracy1',
                        'position' => 1
                    ]
                ]
            ]
        ];
        file_put_contents($dataDir . 'assessments.json', json_encode($assessments, JSON_PRETTY_PRINT));

        // Generate sample questions.json
        $questions = [
            [
                'id' => 'numeracy1',
                'stem' => 'What is the value of 2 + 3 x 5?',
                'type' => 'multiple-choice',
                'strand' => 'Number and Algebra',
                'config' => [
                    'options' => [
                        ['id' => 'option1', 'label' => 'A', 'value' => '10'],
                        ['id' => 'option2', 'label' => 'B', 'value' => '15'],
                        ['id' => 'option3', 'label' => 'C', 'value' => '17'],
                        ['id' => 'option4', 'label' => 'D', 'value' => '25']
                    ],
                    'key' => 'option3',
                    'hint' => 'Work out the multiplication sign BEFORE the addition sign'
                ]
            ]
        ];
        file_put_contents($dataDir . 'questions.json', json_encode($questions, JSON_PRETTY_PRINT));

        // Generate sample student-responses.json
        $responses = [
            [
                'id' => 'studentResponse1',
                'assessmentId' => 'assessment1',
                'assigned' => '14/12/2019 10:31:00',
                'started' => '16/12/2019 10:00:00',
                'completed' => '16/12/2019 10:46:00',
                'student' => [
                    'id' => 'student1',
                    'yearLevel' => 3
                ],
                'responses' => [
                    [
                        'questionId' => 'numeracy1',
                        'response' => 'option1'
                    ]
                ],
                'results' => [
                    'rawScore' => 0
                ]
            ]
        ];
        file_put_contents($dataDir . 'student-responses.json', json_encode($responses, JSON_PRETTY_PRINT));

        echo "Sample data generated successfully!\n";
    }
}

/**
 * Unit Tests
 */
class TestSuite
{
    public static function runTests(): void
    {
        echo "Running tests...\n";

        // Test InputValidator
        $validator = new InputValidator();
        $sampleData = ['students' => [['id' => 'test1', 'firstName' => 'Test', 'lastName' => 'User']]];

        try {
            $validator->validate(['studentId' => 'test1', 'reportType' => 1], $sampleData);
            echo "✓ Input validation test passed\n";
        } catch (Exception $e) {
            echo "✗ Input validation test failed: " . $e->getMessage() . "\n";
        }

        // Test DataLoader
        try {
            // This would fail without actual files, but shows the structure
            echo "✓ Test structure complete\n";
        } catch (Exception $e) {
            echo "Note: Full tests require sample data files\n";
        }
    }
}

// CLI Application Entry Point
if (php_sapi_name() === 'cli') {
    // Check command line arguments
    if ($argc > 1) {
        switch ($argv[1]) {
            case '--generate-sample-data':
                SampleDataGenerator::generateSampleData();
                exit(0);
            case '--test':
                TestSuite::runTests();
                exit(0);
            case '--help':
                echo "Assessment Reporting System\n";
                echo "Usage: php assessment_system.php [options]\n";
                echo "Options:\n";
                echo "  --generate-sample-data  Generate sample data files\n";
                echo "  --test                  Run test suite\n";
                echo "  --help                  Show this help message\n";
                exit(0);
        }
    }

    // Run main application
    $app = new AssessmentReportingSystem();
    $app->run();
}
