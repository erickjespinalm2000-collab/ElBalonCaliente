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

    $nombre = trim($_POST["nombre"] ?? "");
    $deporte_id = trim((string)($_POST["deporte_id"] ?? ""));
    $telefono = trim($_POST["telefono"] ?? "");
    $activo = isset($_POST["activo"]) ? 1 : 0;

    if ($nombre === "") {
        header("Location: ../Formularios/arbitros.php?error=vacio");
        exit;
    }

    $deporte = ($deporte_id !== "" && ctype_digit($deporte_id)) ? (int)$deporte_id : null;

    // Mismo nombre y misma disciplina se considera repetido. El mismo
    // nombre en disciplinas distintas se permite: pueden ser tocayos.
    if ($deporte === null) {
        $stmt = $conexion->prepare("SELECT id FROM arbitros WHERE nombre = :n AND deporte_id IS NULL");
        $stmt->execute(["n" => $nombre]);
    } else {
        $stmt = $conexion->prepare("SELECT id FROM arbitros WHERE nombre = :n AND deporte_id = :d");
        $stmt->execute(["n" => $nombre, "d" => $deporte]);
    }
    if ($stmt->fetch()) {
        header("Location: ../Formularios/arbitros.php?error=duplicado");
        exit;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO arbitros (nombre, deporte_id, telefono, activo)
         VALUES (:n, :d, :t, :a)"
    );
    $stmt->execute([
        "n" => $nombre,
        "d" => $deporte,
        "t" => $telefono !== "" ? $telefono : null,
        "a" => $activo,
    ]);

    header("Location: ../Formularios/arbitros.php?exito=creado");
    exit;
}