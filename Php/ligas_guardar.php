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
    $pais = trim($_POST["pais"] ?? "");
    $temporada = trim($_POST["temporada"] ?? "");

    // Solo se aceptan los tres formatos conocidos. Cualquier otra cosa
    // que llegue por el formulario se trata como fase de liga.
    $formato = $_POST["formato"] ?? "liga";
    if (!in_array($formato, ["liga", "eliminacion", "ambas"], true)) {
        $formato = "liga";
    }

    if ($deporte_id === "" || $nombre === "" || !ctype_digit((string)$deporte_id)) {
        header("Location: ../Formularios/ligas.php?error=vacio");
        exit;
    }

    $stmt = $conexion->prepare("SELECT id FROM ligas WHERE nombre = :nombre AND deporte_id = :deporte_id");
    $stmt->execute(["nombre" => $nombre, "deporte_id" => $deporte_id]);
    if ($stmt->fetch()) {
        header("Location: ../Formularios/ligas.php?error=duplicado");
        exit;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO ligas (nombre, deporte_id, pais, temporada, formato)
         VALUES (:nombre, :deporte_id, :pais, :temporada, :formato)"
    );
    $stmt->execute([
        "nombre" => $nombre,
        "deporte_id" => $deporte_id,
        "pais" => $pais !== "" ? $pais : null,
        "temporada" => $temporada !== "" ? $temporada : null,
    ]);

    header("Location: ../Formularios/ligas.php?exito=creado");
    exit;
}