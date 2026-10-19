<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../index.php");
    exit;
}

// Solo un administrador puede ejecutar esto. Se comprueba en el
// servidor, no basta con ocultar el botón en la pantalla.
require_once "permisos.php";
exigir_admin();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST["nombre"] ?? "");
    $icono = trim($_POST["icono"] ?? "");
    $requiere_equipo = isset($_POST["requiere_equipo"]) ? 1 : 0;

    // Sistema de puntuación. Se recorta el rango aunque el formulario ya lo
    // limite, porque el formulario se puede manipular.
    $admite_empate = isset($_POST["admite_empate"]) ? 1 : 0;
    $puntos_victoria = min(10, max(0, (int)($_POST["puntos_victoria"] ?? 3)));
    $puntos_derrota = min(10, max(0, (int)($_POST["puntos_derrota"] ?? 0)));
    // Si el deporte no admite empates, los puntos por empate no significan nada
    $puntos_empate = $admite_empate ? min(10, max(0, (int)($_POST["puntos_empate"] ?? 1))) : 0;

    if ($nombre === "") {
        header("Location: ../Formularios/deportes.php?error=vacio");
        exit;
    }

    $stmt = $conexion->prepare("SELECT id FROM deportes WHERE nombre = :nombre");
    $stmt->execute(["nombre" => $nombre]);
    if ($stmt->fetch()) {
        header("Location: ../Formularios/deportes.php?error=duplicado");
        exit;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO deportes
                (nombre, icono, requiere_equipo,
                 puntos_victoria, puntos_empate, puntos_derrota, admite_empate)
         VALUES (:nombre, :icono, :requiere_equipo,
                 :victoria, :empate, :derrota, :admite)"
    );
    $stmt->execute([
        "nombre" => $nombre,
        "icono" => $icono !== "" ? $icono : null,
        "requiere_equipo" => $requiere_equipo,
        "victoria" => $puntos_victoria,
        "empate" => $puntos_empate,
        "derrota" => $puntos_derrota,
        "admite" => $admite_empate,
    ]);

    header("Location: ../Formularios/deportes.php?exito=creado");
    exit;
}