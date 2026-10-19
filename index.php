<?php
session_start();

if (isset($_SESSION["usuario_id"])) {
    header("Location: Formularios/menu.php");
    exit;
}

$error = $_GET["error"] ?? "";
$mensaje_error = "";

if ($error === "vacio") {
    $mensaje_error = "Debes llenar todos los campos.";
} elseif ($error === "credenciales") {
    $mensaje_error = "Usuario o contraseña incorrectos.";
}

$mensaje_exito = "";
if (($_GET["registro"] ?? "") === "exito") {
    $mensaje_exito = "Cuenta creada con éxito. Ya puedes iniciar sesión.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente — Sistema de Estadísticas Deportivas</title>
    <meta name="description" content="Sistema web para registrar y analizar estadísticas deportivas de cualquier disciplina: tabla de posiciones, rankings, categorías por edad, programación de eventos y análisis comercial.">
    <link rel="icon" type="image/png" href="Informacion/Logo.png">
    <link rel="stylesheet" href="Css/index.css?v=3">
</head>
<body>

    <div class="login-contenedor">
        <img src="Informacion/Logo.png" alt="El Balón Caliente - Centro de estadísticas deportivas" class="logo-login" width="270">
        <p class="subtitulo">Sistema de Estadísticas Deportivas</p>

        <?php if ($mensaje_error !== ""): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <?php if ($mensaje_exito !== ""): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <form action="Php/login.php" method="POST">
            <div class="campo">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" autocomplete="username" required>
            </div>

            <div class="campo">
                <label for="contrasena">Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn-ingresar">Ingresar</button>
        </form>

        <p class="link-registro">
            ¿No tienes cuenta? <a href="Formularios/usuarios.php">Crear un nuevo usuario</a>
        </p>

        <p class="link-registro">
            <a href="acerca.php">¿Qué es El Balón Caliente?</a>
        </p>

        <p class="pie-login">Proyecto final - Bachillerato en Informática</p>
    </div>

</body>
</html>