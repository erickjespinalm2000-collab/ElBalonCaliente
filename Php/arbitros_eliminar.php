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

    // Si ya dirigio eventos no se borra: esos eventos perderian su
    // arbitro. Se avisa para que lo desactive en lugar de eliminarlo.
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM eventos WHERE arbitro_id = :id");
    $stmt->execute(["id" => $id]);

    if ((int)$stmt->fetchColumn() > 0) {
        header("Location: ../Formularios/arbitros.php?error=en_uso");
        exit;
    }

    $stmt = $conexion->prepare("DELETE FROM arbitros WHERE id = :id");
    $stmt->execute(["id" => $id]);
}

header("Location: ../Formularios/arbitros.php?exito=eliminado");
exit;