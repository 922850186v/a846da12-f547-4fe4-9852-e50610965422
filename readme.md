# Assessment Reporting System

## Overview
The Assessment Reporting System is a command-line application designed to generate various types of reports based on student assessments. It provides insights into student performance through diagnostic, progress, and feedback reports.

## Project Structure
```
assessment-report-app
├── assessment_report.php       # Main application logic
├── data                        # Directory containing JSON data files
│   ├── assessments.json        # Assessment data
│   ├── questions.json          # Question data
│   ├── students.json           # Student data
│   └── student-responses.json  # Student responses data
├── Dockerfile                  # Dockerfile for building the application image
├── README.md                   # Project documentation
└── composer.json               # Project environment file
```

## Setup Instructions

### Prerequisites
- PHP 7.4 or higher
- Composer (for dependency management, if needed)
- Docker (for containerization)

### Running the Application

1. **Clone the repository:**
   ```
   git clone <repository-url>
   cd assessment-report-app
   ```

2. **Install dependencies (if applicable):**
   ```
   composer install
   ```

3. **Run the application:**
   ```
   php assessment_report.php
   ```

4. **Generate sample data (optional):**
   ```
   php assessment_report.php --generate-sample-data
   ```

5. **Run tests (optional):**
   ```
   php assessment_report.php --test
   ```

## Docker Instructions

To run the application in a Docker container, follow these steps:

1. **Build the Docker image:**
   ```
   docker build -t assessment-report-app .
   ```

2. **Run the Docker container:**
   ```
   docker run -it assessment-report-app
   ```

## Usage
When prompted, enter the Student ID and the type of report you wish to generate (1 for Diagnostic, 2 for Progress, 3 for Feedback). The application will load the necessary data and display the requested report.

## License
This project is licensed under the MIT License. See the LICENSE file for more details.
