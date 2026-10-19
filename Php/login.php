<?php
session_start();
require_once "conexion.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $usuario = trim($_POST["usuario"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";

    if ($usuario === "" || $contrasena === "") {
        header("Location: ../index.php?error=vacio");
        exit;
    }

    $sql = "SELECT id, nombre_usuario, contrasena, rol
            FROM usuarios
            WHERE nombre_usuario = :usuario
            LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->execute(["usuario" => $usuario]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fila && password_verify($contrasena, $fila["contrasena"])) {

        $_SESSION["usuario_id"] = $fila["id"];
        $_SESSION["nombre_usuario"] = $fila["nombre_usuario"];
        $_SESSION["rol"] = $fila["rol"];

        header("Location: ../Formularios/menu.php");
        exit;

    } else {
        header("Location: ../index.php?error=credenciales");
        exit;
    }
}