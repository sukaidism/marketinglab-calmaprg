<?php
declare(strict_types=1);

namespace Lab;

use PDO;

final class Database
{
    public function connect(): PDO
    {
        $host = Settings::optional('DB_HOST', '127.0.0.1');
        $port = Settings::optional('DB_PORT', '3306');
        $name = Settings::optional('DB_NAME', 'marketing_lab');
        if (!preg_match('/^[A-Za-z0-9.:-]+$/', $host)) {
            throw new \InvalidArgumentException('DB_HOST contains invalid characters.');
        }
        if (!preg_match('/^[0-9]{1,5}$/', $port) || (int) $port < 1 || (int) $port > 65535) {
            throw new \InvalidArgumentException('DB_PORT must be a valid TCP port.');
        }
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            throw new \InvalidArgumentException('DB_NAME contains invalid characters.');
        }
        $db = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            Settings::required('DB_USER'),
            getenv('DB_PASSWORD') ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $db->exec("SET time_zone = '+00:00'");
        return $db;
    }
}

?>