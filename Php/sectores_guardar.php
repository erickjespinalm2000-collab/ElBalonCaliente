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

    $localidad_id = $_POST["localidad_id"] ?? "";
    $nombre = trim($_POST["nombre"] ?? "");
    $capacidad = $_POST["capacidad"] ?? "";
    $precio = $_POST["precio"] ?? "";

    if ($localidad_id === "" || $nombre === "" || !ctype_digit((string)$localidad_id)) {
        header("Location: ../Formularios/localidades.php?error=sector_vacio");
        exit;
    }

    // La capacidad tiene que ser entera y el precio un numero no negativo.
    // Un sector con capacidad 0 no aportaria nada y ensuciaria los calculos.
    if (!ctype_digit((string)$capacidad) || (int)$capacidad < 1
        || !is_numeric($precio) || (float)$precio < 0) {
        header("Location: ../Formularios/localidades.php?error=sector_numeros");
        exit;
    }

    $stmt = $conexion->prepare("SELECT id FROM localidades WHERE id = :id");
    $stmt->execute(["id" => $localidad_id]);
    if (!$stmt->fetch()) {
        header("Location: ../Formularios/localidades.php?error=sector_vacio");
        exit;
    }

    $stmt = $conexion->prepare("SELECT id FROM sectores WHERE localidad_id = :l AND nombre = :n");
    $stmt->execute(["l" => $localidad_id, "n" => $nombre]);
    if ($stmt->fetch()) {
        header("Location: ../Formularios/localidades.php?error=sector_duplicado");
        exit;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO sectores (localidad_id, nombre, capacidad, precio)
         VALUES (:l, :n, :c, :p)"
    );
    $stmt->execute([
        "l" => $localidad_id,
        "n" => $nombre,
        "c" => (int)$capacidad,
        "p" => (float)$precio,
    ]);

    header("Location: ../Formularios/localidades.php?exito=sector_creado");
    exit;
}