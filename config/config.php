<?php
// config.php - Cargar variables de entorno
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("El archivo .env no existe");
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Saltar comentarios
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Remover comillas si existen
        $value = trim($value, '"\'');
        
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// Cargar el archivo .env
try {
    loadEnv(__DIR__ . '/.env');
} catch (Exception $e) {
    die("Error cargando configuración: " . $e->getMessage());
}

// Función helper para obtener variables de entorno
function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}
?>