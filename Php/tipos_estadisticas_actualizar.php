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
    header("Location: ../Formularios/tipos_estadisticas.php?editar=" . urlencode($id) . "&error=" . $codigo);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST["id"] ?? "";
    $nombre = trim($_POST["nombre"] ?? "");
    $unidad = trim($_POST["unidad"] ?? "");
    $menor_es_mejor = isset($_POST["menor_es_mejor"]) ? 1 : 0;

    if ($id === "" || !ctype_digit((string)$id)) {
        header("Location: ../Formularios/tipos_estadisticas.php");
        exit;
    }

    $stmt = $conexion->prepare("SELECT deporte_id FROM tipos_estadistica WHERE id = :id");
    $stmt->execute(["id" => $id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) {
        header("Location: ../Formularios/tipos_estadisticas.php");
        exit;
    }

    if ($nombre === "") {
        volver($id, "vacio");
    }

    $stmt = $conexion->prepare(
        "SELECT id FROM tipos_estadistica WHERE nombre = :nombre AND deporte_id = :d AND id <> :id"
    );
    $stmt->execute(["nombre" => $nombre, "d" => $actual["deporte_id"], "id" => $id]);
    if ($stmt->fetch()) {
        volver($id, "duplicado");
    }

    $stmt = $conexion->prepare(
        "UPDATE tipos_estadistica
            SET nombre = :nombre, unidad = :unidad, menor_es_mejor = :menor
          WHERE id = :id"
    );
    $stmt->execute([
        "nombre" => $nombre,
        "unidad" => $unidad !== "" ? $unidad : null,
        "menor" => $menor_es_mejor,
        "id" => $id,
    ]);

    header("Location: ../Formularios/tipos_estadisticas.php?exito=actualizado");
    exit;
}