<?php
require 'vendor/autoload.php';
use Dotenv\Dotenv;
try {
    echo "Current directory: " . __DIR__ . "\n";
    echo "Checking .env.test file: " . (file_exists('/Applications/XAMPP/xamppfiles/htdocs/Home/.env.test') ? 'Exists' : 'Not found') . "\n";
    echo "Checking .env file: " . (file_exists('/Applications/XAMPP/xamppfiles/htdocs/Home/.env') ? 'Exists' : 'Not found') . "\n";
    $dotenv = Dotenv::createImmutable('/Applications/XAMPP/xamppfiles/htdocs/Home', '.env.test');
    $dotenv->safeLoad();
    echo "Test Key: " . (isset($_ENV['TEST_KEY']) ? $_ENV['TEST_KEY'] : 'Not found') . "\n";
    $dotenv = Dotenv::createImmutable('/Applications/XAMPP/xamppfiles/htdocs/Home');
    $dotenv->safeLoad();
    echo "EasyParcel: " . (isset($_ENV['EASYPARCEL_API_KEY']) ? $_ENV['EASYPARCEL_API_KEY'] : 'Not found') . "\n";
    echo "SMTP Host: " . (isset($_ENV['SMTP_HOST']) ? $_ENV['SMTP_HOST'] : 'Not found') . "\n";
    echo "SMTP Username: " . (isset($_ENV['SMTP_USERNAME']) ? $_ENV['SMTP_USERNAME'] : 'Not found') . "\n";
    echo "SMTP Password: " . (isset($_ENV['SMTP_PASSWORD']) ? $_ENV['SMTP_PASSWORD'] : 'Not found') . "\n";
    echo "Twilio SID: " . (isset($_ENV['TWILIO_SID']) ? $_ENV['TWILIO_SID'] : 'Not found') . "\n";
} catch (Exception $e) {
    echo "Dotenv error: " . $e->getMessage() . "\n";
}
?>