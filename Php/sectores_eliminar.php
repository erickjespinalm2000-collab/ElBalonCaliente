<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../index.php");
    exit;
}

// Solo un administrador puede ejecutar esto. Se comprueba en el
// servidor, no basta con ocultar el boton en la pantalla.
require_once "permisos.php";
exigir_admin();

$id = $_GET["id"] ?? null;

if ($id !== null && ctype_digit((string)$id)) {
    $stmt = $conexion->prepare("DELETE FROM sectores WHERE id = :id");
    $stmt->execute(["id" => $id]);
}

header("Location: ../Formularios/localidades.php?exito=sector_eliminado");
exit;