<?php
require_once "conexion.php";
function volver(string $codigo, string $nombre, string $apellido, string $usuario): void
{
    $datos = http_build_query(["error" => $codigo, "nombre" => $nombre, "apellido" => $apellido, "usuario" => $usuario]);
    header("Location: ../Formularios/usuarios.php?" . $datos);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim(preg_replace('/\s+/u', " ", $_POST["nombre"] ?? ""));
    $apellido = trim(preg_replace('/\s+/u', " ", $_POST["apellido"] ?? ""));
    $usuario = trim($_POST["usuario"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";
    $confirmar = $_POST["confirmar_contrasena"] ?? "";

    if ($nombre === "" || $apellido === "" || $usuario === "" || $contrasena === "" || $confirmar === "") {
        volver("vacio", $nombre, $apellido, $usuario);
    }

    if (mb_strlen($nombre) > 50 || mb_strlen($apellido) > 50 || mb_strlen($usuario) > 50) {
        volver("largo", $nombre, $apellido, $usuario);
    }

    if (!preg_match('/^[A-Za-z0-9_.]+$/', $usuario)) {
        volver("usuario_invalido", $nombre, $apellido, $usuario);
    }

    if ($contrasena !== $confirmar) {
        volver("no_coinciden", $nombre, $apellido, $usuario);
    }

    if (strlen($contrasena) < 6) {
        volver("corta", $nombre, $apellido, $usuario);
    }

    $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE nombre_usuario = :usuario");
    $stmt->execute(["usuario" => $usuario]);
    if ($stmt->fetch()) {
        volver("usuario_existe", $nombre, $apellido, $usuario);
    }

    $hash = password_hash($contrasena, PASSWORD_DEFAULT);

    // Todo registro desde la pantalla crea un usuario normal. El
    // administrador se da de alta manualmente en la base de datos, que
    // es lo que se quiere: así nadie se vuelve administrador solo.
    $stmt = $conexion->prepare(
        "INSERT INTO usuarios (nombre, apellido, nombre_usuario, contrasena, rol)
         VALUES (:nombre, :apellido, :usuario, :contrasena, 'usuario')"
    );
    $stmt->execute([
        "nombre" => $nombre,
        "apellido" => $apellido,
        "usuario" => $usuario,
        "contrasena" => $hash,
    ]);

    header("Location: ../index.php?registro=exito");
    exit;
}