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

    $deporte_id = $_POST["deporte_id"] ?? "";
    $nombre = trim($_POST["nombre"] ?? "");
    $unidad = trim($_POST["unidad"] ?? "");
    $menor_es_mejor = isset($_POST["menor_es_mejor"]) ? 1 : 0;

    if ($deporte_id === "" || $nombre === "" || !ctype_digit((string)$deporte_id)) {
        header("Location: ../Formularios/tipos_estadisticas.php?error=vacio");
        exit;
    }

    // Que el deporte exista realmente
    $stmt = $conexion->prepare("SELECT id FROM deportes WHERE id = :id");
    $stmt->execute(["id" => $deporte_id]);
    if (!$stmt->fetch()) {
        header("Location: ../Formularios/tipos_estadisticas.php?error=vacio");
        exit;
    }

    // Que no exista ya esa estadística en ese deporte
    $stmt = $conexion->prepare("SELECT id FROM tipos_estadistica WHERE nombre = :nombre AND deporte_id = :deporte_id");
    $stmt->execute(["nombre" => $nombre, "deporte_id" => $deporte_id]);
    if ($stmt->fetch()) {
        header("Location: ../Formularios/tipos_estadisticas.php?error=duplicado");
        exit;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO tipos_estadistica (deporte_id, nombre, unidad, menor_es_mejor)
         VALUES (:deporte_id, :nombre, :unidad, :menor)"
    );
    $stmt->execute([
        "deporte_id" => $deporte_id,
        "nombre" => $nombre,
        "unidad" => $unidad !== "" ? $unidad : null,
        "menor" => $menor_es_mejor,
    ]);

    header("Location: ../Formularios/tipos_estadisticas.php?exito=creado");
    exit;
}