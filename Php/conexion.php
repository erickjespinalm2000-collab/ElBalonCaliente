<?php

require_once __DIR__ . "/config.php";

// En producción los errores no se muestran: se registran. Un mensaje de
// PDO en pantalla le dice a cualquier visitante la ruta del servidor, el
// nombre de la base y a veces el del usuario.
if (ENTORNO === "produccion") {
    ini_set("display_errors", "0");
    error_reporting(E_ALL);
} else {
    ini_set("display_errors", "1");
    error_reporting(E_ALL);
}

// La sesión viaja en una cookie. Estas tres marcas evitan que se la roben:
// httponly la esconde de JavaScript, samesite corta el envío desde otros
// sitios, y secure la limita a HTTPS cuando la conexión ya es segura.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        "httponly" => true,
        "samesite" => "Lax",
        "secure"   => !empty($_SERVER["HTTPS"]),
    ]);
}

try {

    $conexion = new PDO(
        "mysql:host=" . BD_HOST . ";dbname=" . BD_NOMBRE . ";charset=utf8mb4",
        BD_USUARIO,
        BD_CLAVE
    );
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {

    // El detalle solo se ve en la máquina de desarrollo.
    if (ENTORNO === "produccion") {
        error_log("Fallo de conexion a la base: " . $e->getMessage());
        http_response_code(503);
        die("El sistema no está disponible en este momento. Intenta más tarde.");
    }

    die("Error de conexión a la base de datos: " . $e->getMessage());
}
