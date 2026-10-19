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
    // Los equipos que estuvieran en esta categoría quedan sin categoría,
    // no se borran. Lo hace la llave foránea con ON DELETE SET NULL.
    $stmt = $conexion->prepare("DELETE FROM categorias WHERE id = :id");
    $stmt->execute(["id" => $id]);
}

header("Location: ../Formularios/deportes.php?exito=cat_eliminada");
exit;
