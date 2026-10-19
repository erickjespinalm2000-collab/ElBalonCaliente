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

function volver(string $id, string $codigo): void
{
    header("Location: ../Formularios/ligas.php?editar=" . urlencode($id) . "&error=" . $codigo);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST["id"] ?? "";
    $nombre = trim($_POST["nombre"] ?? "");
    $pais = trim($_POST["pais"] ?? "");
    $temporada = trim($_POST["temporada"] ?? "");

    $formato = $_POST["formato"] ?? "liga";
    if (!in_array($formato, ["liga", "eliminacion", "ambas"], true)) {
        $formato = "liga";
    }

    // Quitarle la eliminación directa a una liga que ya tiene cuadro
    // dejaría esa llave huérfana: seguiría en la base y en el listado,
    // pero perteneciendo a una liga que ya no admite llaves. Se rechaza
    // y se pide borrar el cuadro primero.
    if ($formato === "liga") {
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM llaves WHERE liga_id = :id");
        $stmt->execute(["id" => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            header("Location: ../Formularios/ligas.php?editar=" . (int)$id . "&error=tiene_llave");
            exit;
        }
    }

    if ($id === "" || !ctype_digit((string)$id)) {
        header("Location: ../Formularios/ligas.php");
        exit;
    }

    $stmt = $conexion->prepare("SELECT deporte_id FROM ligas WHERE id = :id");
    $stmt->execute(["id" => $id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) {
        header("Location: ../Formularios/ligas.php");
        exit;
    }

    if ($nombre === "") {
        volver($id, "vacio");
    }

    $stmt = $conexion->prepare("SELECT id FROM ligas WHERE nombre = :nombre AND deporte_id = :d AND id <> :id");
    $stmt->execute(["nombre" => $nombre, "d" => $actual["deporte_id"], "id" => $id]);
    if ($stmt->fetch()) {
        volver($id, "duplicado");
    }

    $stmt = $conexion->prepare("UPDATE ligas SET nombre = :nombre, pais = :pais, temporada = :temporada, formato = :formato WHERE id = :id");
    $stmt->execute([
        "nombre" => $nombre,
        "pais" => $pais !== "" ? $pais : null,
        "temporada" => $temporada !== "" ? $temporada : null,
        "id" => $id,
    ]);

    header("Location: ../Formularios/ligas.php?exito=actualizado");
    exit;
}