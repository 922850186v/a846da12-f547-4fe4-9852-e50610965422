FROM php:8.1-cli

# Set the working directory
WORKDIR /app

# Copy the application files
COPY assessment_report.php ./
COPY data/ ./data/

# Set the command to run the application
CMD ["php", "assessment_report.php"]