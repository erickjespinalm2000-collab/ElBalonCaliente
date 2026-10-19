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
    header("Location: ../Formularios/deportes.php?editar=" . urlencode($id) . "&error=" . $codigo);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST["id"] ?? "";
    $nombre = trim($_POST["nombre"] ?? "");
    $icono = trim($_POST["icono"] ?? "");

    if ($id === "" || !ctype_digit((string)$id)) {
        header("Location: ../Formularios/deportes.php");
        exit;
    }

    $stmt = $conexion->prepare("SELECT requiere_equipo FROM deportes WHERE id = :id");
    $stmt->execute(["id" => $id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) {
        header("Location: ../Formularios/deportes.php");
        exit;
    }

    if ($nombre === "") {
        volver($id, "vacio");
    }

    $stmt = $conexion->prepare("SELECT id FROM deportes WHERE nombre = :nombre AND id <> :id");
    $stmt->execute(["nombre" => $nombre, "id" => $id]);
    if ($stmt->fetch()) {
        volver($id, "duplicado");
    }

    $stmt = $conexion->prepare(
        "SELECT (SELECT COUNT(*) FROM equipos WHERE deporte_id = :a)
              + (SELECT COUNT(*) FROM jugadores WHERE deporte_id = :b)
              + (SELECT COUNT(*) FROM eventos WHERE deporte_id = :c)"
    );
    $stmt->execute(["a" => $id, "b" => $id, "c" => $id]);
    $bloqueado = (int)$stmt->fetchColumn() > 0;

    $requiere_equipo = $bloqueado
        ? (int)$actual["requiere_equipo"]
        : (isset($_POST["requiere_equipo"]) ? 1 : 0);

    // Sistema de puntuación. Se recorta el rango aunque el formulario ya lo
    // limite, porque el formulario se puede manipular.
    $admite_empate = isset($_POST["admite_empate"]) ? 1 : 0;
    $puntos_victoria = min(10, max(0, (int)($_POST["puntos_victoria"] ?? 3)));
    $puntos_derrota = min(10, max(0, (int)($_POST["puntos_derrota"] ?? 0)));
    // Si el deporte no admite empates, los puntos por empate no significan nada
    $puntos_empate = $admite_empate ? min(10, max(0, (int)($_POST["puntos_empate"] ?? 1))) : 0;

    $stmt = $conexion->prepare(
        "UPDATE deportes
            SET nombre = :nombre, icono = :icono, requiere_equipo = :requiere,
                puntos_victoria = :victoria, puntos_empate = :empate,
                puntos_derrota = :derrota, admite_empate = :admite
          WHERE id = :id"
    );
    $stmt->execute([
        "nombre" => $nombre,
        "icono" => $icono !== "" ? $icono : null,
        "requiere" => $requiere_equipo,
        "victoria" => $puntos_victoria,
        "empate" => $puntos_empate,
        "derrota" => $puntos_derrota,
        "admite" => $admite_empate,
        "id" => $id,
    ]);

    header("Location: ../Formularios/deportes.php?exito=actualizado");
    exit;
}