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
    $ciudad = trim($_POST["ciudad"] ?? "");
    $direccion = trim($_POST["direccion"] ?? "");
    $activa = isset($_POST["activa"]) ? 1 : 0;

    // La disciplina es opcional: sin ella la sede se considera multiusos.
    $deporte_id = trim((string)($_POST["deporte_id"] ?? ""));
    $deporte = ($deporte_id !== "" && ctype_digit($deporte_id)) ? (int)$deporte_id : null;

    if ($id === "" || !ctype_digit((string)$id)) {
        header("Location: ../Formularios/localidades.php");
        exit;
    }

    if ($nombre === "") {
        header("Location: ../Formularios/localidades.php?editar=" . (int)$id . "&error=vacio");
        exit;
    }

    $stmt = $conexion->prepare("SELECT id FROM localidades WHERE nombre = :nombre AND id <> :id");
    $stmt->execute(["nombre" => $nombre, "id" => $id]);
    if ($stmt->fetch()) {
        header("Location: ../Formularios/localidades.php?editar=" . (int)$id . "&error=duplicado");
        exit;
    }

    $stmt = $conexion->prepare(
        "UPDATE localidades
         SET nombre = :nombre, deporte_id = :deporte, ciudad = :ciudad,
             direccion = :direccion, activa = :activa
         WHERE id = :id"
    );
    $stmt->execute([
        "nombre" => $nombre,
        "deporte" => $deporte,
        "ciudad" => $ciudad !== "" ? $ciudad : null,
        "direccion" => $direccion !== "" ? $direccion : null,
        "activa" => $activa,
        "id" => $id,
    ]);

    header("Location: ../Formularios/localidades.php?exito=actualizada");
    exit;
}