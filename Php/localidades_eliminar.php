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

    // Si hay eventos en esa sede no se borra: perderian su localidad y
    // el analisis comercial dejaria de cuadrar. Se avisa en lugar de
    // destruir informacion en silencio.
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM eventos WHERE localidad_id = :id");
    $stmt->execute(["id" => $id]);

    if ((int)$stmt->fetchColumn() > 0) {
        header("Location: ../Formularios/localidades.php?error=en_uso");
        exit;
    }

    // Los sectores se van solos por la llave foranea ON DELETE CASCADE.
    $stmt = $conexion->prepare("DELETE FROM localidades WHERE id = :id");
    $stmt->execute(["id" => $id]);
}

header("Location: ../Formularios/localidades.php?exito=eliminada");
exit;