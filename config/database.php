<?php
// database.php - Versión con configuración incluida
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Cargar configuración desde variables de entorno
        $this->loadEnvConfig();
        
        $this->host = $this->getEnv('DB_HOST', 'localhost');
        $this->db_name = $this->getEnv('DB_NAME', 'crm');
        $this->username = $this->getEnv('DB_USERNAME', 'root');
        $this->password = $this->getEnv('DB_PASSWORD', '');
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            );
        } catch(PDOException $exception) {
            throw new Exception("Error de conexión a la base de datos: " . $exception->getMessage());
        }

        return $this->conn;
    }

    private function loadEnvConfig() {
        $envFile = __DIR__ . '/.env';
        
        if (!file_exists($envFile)) {
            // Si no existe el archivo .env, usar valores por defecto
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue; // Saltar comentarios
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remover comillas si existen
                $value = trim($value, '"\'');
                
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    private function getEnv($key, $default = null) {
        return $_ENV[$key] ?? $default;
    }
}

// Función global helper para compatibilidad
if (!function_exists('env')) {
    function env($key, $default = null) {
        return $_ENV[$key] ?? $default;
    }
}
?>