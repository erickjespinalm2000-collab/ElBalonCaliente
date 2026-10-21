<?php
$base_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$base_url = rtrim($base_url, '/\\');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente — Sistema de Estadísticas Deportivas</title>
    <meta name="description" content="Sistema web para registrar y analizar estadísticas deportivas de cualquier disciplina: tabla de posiciones, rankings, categorías por edad, programación de eventos y análisis comercial.">
    <link rel="icon" type="image/png" href="<?php echo $base_url; ?>/Informacion/Logo.png">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/Css/index.css?v=3">
</head>
<body>

    <div class="login-contenedor">
        <img src="<?php echo $base_url; ?>/Informacion/Logo.png" alt="El Balón Caliente - Centro de estadísticas deportivas" class="logo-login" width="270">
        <p class="subtitulo">Sistema de Estadísticas Deportivas</p>

        <?php if ($mensaje_error !== ""): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <?php if ($mensaje_exito !== ""): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <form action="<?php echo $base_url; ?>/Php/login.php" method="POST">
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
            ¿No tienes cuenta? <a href="<?php echo $base_url; ?>/Formularios/usuarios.php">Crear un nuevo usuario</a>
        </p>

        <p class="link-registro">
            <a href="<?php echo $base_url; ?>/acerca.php">¿Qué es El Balón Caliente?</a>
        </p>

        <p class="pie-login">Proyecto final - Bachillerato en Informática</p>
    </div>

</body>
</html>