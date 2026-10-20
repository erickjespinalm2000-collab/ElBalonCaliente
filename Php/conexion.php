<?php
require_once __DIR__ . '/config.php';

if (ENTORNO === "produccion") {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
}

try {
    $dsn = "mysql:host=" . BD_HOST . ";port=" . BD_PUERTO . ";dbname=" . BD_NOMBRE . ";charset=utf8mb4";
    
    $conexion = new PDO($dsn, BD_USUARIO, BD_CLAVE);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    if (ENTORNO === "produccion") {
        error_log("Fallo de conexion a la base: " . $e->getMessage());
        http_response_code(500);
        die("El sistema no esta disponible en este momento. Intenta mas tarde.");
    }
    
    die("Error de conexion a la base de datos: " . $e->getMessage());
}
?>