<?php
$error = $_GET["error"] ?? "";
$mensaje_error = "";

switch ($error) {
    case "vacio":
        $mensaje_error = "Debes llenar todos los campos.";
        break;
    case "no_coinciden":
        $mensaje_error = "Las contraseñas no coinciden.";
        break;
    case "usuario_existe":
        $mensaje_error = "Ese nombre de usuario ya está en uso.";
        break;
    case "usuario_invalido":
        $mensaje_error = "El usuario solo puede tener letras, números, punto y guion bajo (sin espacios ni tildes).";
        break;
    case "largo":
        $mensaje_error = "El nombre, el apellido y el usuario admiten hasta 50 caracteres.";
        break;
    case "corta":
        $mensaje_error = "La contraseña debe tener al menos 6 caracteres.";
        break;
}
$v_nombre = trim($_GET["nombre"] ?? "");
$v_apellido = trim($_GET["apellido"] ?? "");
$v_usuario = trim($_GET["usuario"] ?? "");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Crear cuenta</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/usuarios.css?v=9">
</head>
<body>

    <div class="login-contenedor">
        <img src="../Informacion/Logo.png" alt="El Balón Caliente" class="logo-registro" width="150">
        <h1>Crear cuenta</h1>
        <p class="subtitulo">El Balón Caliente</p>

        <?php if ($mensaje_error !== ""): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <form action="../Php/registrar_ususarios.php" method="POST">
            <div class="campo-doble">
                <div class="campo">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" autocomplete="given-name" maxlength="50" required
                           value="<?php echo htmlspecialchars($v_nombre); ?>">
                </div>
                <div class="campo">
                    <label for="apellido">Apellido</label>
                    <input type="text" id="apellido" name="apellido" autocomplete="family-name" maxlength="50" required
                           value="<?php echo htmlspecialchars($v_apellido); ?>">
                </div>
            </div>

            <div class="campo">
                <label for="usuario">Nombre de usuario</label>
                <input type="text" id="usuario" name="usuario" autocomplete="username" maxlength="50" required
                       placeholder="nombre.apellido" value="<?php echo htmlspecialchars($v_usuario); ?>">
                <p class="estado-usuario" id="estado-usuario" aria-live="polite"></p>
                <div class="sugerencias" id="sugerencias" hidden>
                    <span class="sugerencias-titulo">Sugerencias de usuario:</span>
                    <div class="sugerencias-lista" id="sugerencias-lista"></div>
                </div>
            </div>

            <div class="campo">
                <label for="contrasena">Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" autocomplete="new-password" required minlength="6">
            </div>

            <div class="campo">
                <label for="confirmar_contrasena">Confirmar contraseña</label>
                <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" autocomplete="new-password" required minlength="6">
            </div>

            <button type="submit" class="btn-ingresar">Crear cuenta</button>
        </form>

        <p class="link-registro">
            ¿Ya tienes cuenta? <a href="../index.php">Iniciar sesión</a>
        </p>
    </div>

    <script src="../Js/sugerencias_usuario.js?v=1" defer></script>

</body>
</html>