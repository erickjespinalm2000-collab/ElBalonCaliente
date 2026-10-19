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
    $edad_maxima = $_POST["edad_maxima"] ?? "";

    if ($deporte_id === "" || $nombre === "" || !ctype_digit((string)$deporte_id)) {
        header("Location: ../Formularios/deportes.php?error=cat_vacio");
        exit;
    }

    // La edad tiene que ser un número razonable: una categoría de 0 o de
    // 200 años no significa nada y arruinaría la validación de jugadores.
    if (!ctype_digit((string)$edad_maxima) || (int)$edad_maxima < 4 || (int)$edad_maxima > 99) {
        header("Location: ../Formularios/deportes.php?error=cat_edad");
        exit;
    }

    // Que el deporte exista realmente
    $stmt = $conexion->prepare("SELECT id FROM deportes WHERE id = :id");
    $stmt->execute(["id" => $deporte_id]);
    if (!$stmt->fetch()) {
        header("Location: ../Formularios/deportes.php?error=cat_vacio");
        exit;
    }

    // Que no exista ya esa categoría en ese deporte
    $stmt = $conexion->prepare("SELECT id FROM categorias WHERE nombre = :nombre AND deporte_id = :deporte_id");
    $stmt->execute(["nombre" => $nombre, "deporte_id" => $deporte_id]);
    if ($stmt->fetch()) {
        header("Location: ../Formularios/deportes.php?error=cat_duplicado");
        exit;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO categorias (deporte_id, nombre, edad_maxima)
         VALUES (:deporte_id, :nombre, :edad)"
    );
    $stmt->execute([
        "deporte_id" => $deporte_id,
        "nombre" => $nombre,
        "edad" => (int)$edad_maxima,
    ]);

    header("Location: ../Formularios/deportes.php?exito=cat_creada");
    exit;
}
