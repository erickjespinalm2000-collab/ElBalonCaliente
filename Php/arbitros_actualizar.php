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
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST["id"] ?? "";
    $nombre = trim($_POST["nombre"] ?? "");
    $deporte_id = trim((string)($_POST["deporte_id"] ?? ""));
    $telefono = trim($_POST["telefono"] ?? "");
    $activo = isset($_POST["activo"]) ? 1 : 0;

    if ($id === "" || !ctype_digit((string)$id)) {
        header("Location: ../Formularios/arbitros.php");
        exit;
    }

    if ($nombre === "") {
        header("Location: ../Formularios/arbitros.php?editar=" . (int)$id . "&error=vacio");
        exit;
    }

    $deporte = ($deporte_id !== "" && ctype_digit($deporte_id)) ? (int)$deporte_id : null;

    $stmt = $conexion->prepare(
        "UPDATE arbitros SET nombre = :n, deporte_id = :d, telefono = :t, activo = :a
         WHERE id = :id"
    );
    $stmt->execute([
        "n" => $nombre,
        "d" => $deporte,
        "t" => $telefono !== "" ? $telefono : null,
        "a" => $activo,
        "id" => $id,
    ]);

    header("Location: ../Formularios/arbitros.php?exito=actualizado");
    exit;
}