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

$id = $_GET["id"] ?? null;

if ($id !== null && ctype_digit((string)$id)) {
    $stmt = $conexion->prepare("DELETE FROM tipos_estadistica WHERE id = :id");
    $stmt->execute(["id" => $id]);
}

header("Location: ../Formularios/tipos_estadisticas.php?exito=eliminado");
exit;