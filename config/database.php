<?php
// Database connection settings.
// If the server has settings saved (production), we use them.
// If not, we use the default XAMPP settings (development).

function getDatabaseConnection()
{
    // Read the settings from the server
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $dbname = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');

    // If a setting is empty, use the local XAMPP default
    if (!$host) {
        $host = '127.0.0.1';
    }
    if (!$port) {
        $port = '4306';   // XAMPP MySQL runs on port 4306 on this machine
    }
    if (!$dbname) {
        $dbname = 'mobile_network_service_management';
    }
    if (!$user) {
        $user = 'root';
    }
    if (!$pass) {
        $pass = '';       // default XAMPP MySQL password is empty
    }

    // The connection string
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    // Connection options
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // show errors as exceptions
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // get rows as arrays
        PDO::ATTR_EMULATE_PREPARES => false                 // use real prepared statements
    ];

    // Connect and give the connection back
    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}