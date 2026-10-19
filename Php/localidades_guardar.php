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
    $ciudad = trim($_POST["ciudad"] ?? "");
    $direccion = trim($_POST["direccion"] ?? "");
    $activa = isset($_POST["activa"]) ? 1 : 0;

    // La disciplina es opcional: sin ella la sede se considera multiusos.
    $deporte_id = trim((string)($_POST["deporte_id"] ?? ""));
    $deporte = ($deporte_id !== "" && ctype_digit($deporte_id)) ? (int)$deporte_id : null;

    if ($nombre === "") {
        header("Location: ../Formularios/localidades.php?error=vacio");
        exit;
    }

    $stmt = $conexion->prepare("SELECT id FROM localidades WHERE nombre = :nombre");
    $stmt->execute(["nombre" => $nombre]);
    if ($stmt->fetch()) {
        header("Location: ../Formularios/localidades.php?error=duplicado");
        exit;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO localidades (nombre, deporte_id, ciudad, direccion, activa)
         VALUES (:nombre, :deporte, :ciudad, :direccion, :activa)"
    );
    $stmt->execute([
        "nombre" => $nombre,
        "deporte" => $deporte,
        "ciudad" => $ciudad !== "" ? $ciudad : null,
        "direccion" => $direccion !== "" ? $direccion : null,
        "activa" => $activa,
    ]);

    header("Location: ../Formularios/localidades.php?exito=creada");
    exit;
}
