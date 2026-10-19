<?php
session_start();
require_once "../Php/conexion.php";

if (!isset($_SESSION["usuario_id"], $_SESSION["nombre_usuario"], $_SESSION["rol"])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}

require_once "../Php/permisos.php";
exigir_admin("menu.php", true);

$error = $_GET["error"] ?? "";
$mensaje_error = "";
if ($error === "vacio") {
    $mensaje_error = "Debes elegir un deporte y escribir el nombre del equipo.";
} elseif ($error === "duplicado") {
    $mensaje_error = "Ya existe un equipo con ese nombre en ese deporte.";
} elseif ($error === "sin_deportes") {
    $mensaje_error = "Primero debes agregar al menos un deporte.";
} elseif ($error === "logo_no_png") {
    $mensaje_error = "El logo debe ser un archivo de imagen .png.";
} elseif ($error === "logo_fallo") {
    $mensaje_error = "Hubo un problema al subir la imagen, intenta de nuevo.";
} elseif ($error === "categoria_invalida") {
    $mensaje_error = "Esa categoría no pertenece al deporte elegido.";
} elseif ($error === "categoria_choca") {
    $mensaje_error = "No se puede asignar esa categoría: el equipo ya tiene jugadores que pasan de la edad máxima. "
        . "Sácalos del equipo primero, o elige una categoría mayor.";
}

$mensaje_exito = "";
if (($_GET["exito"] ?? "") === "creado") {
    $mensaje_exito = "Equipo agregado con éxito.";
} elseif (($_GET["exito"] ?? "") === "actualizado") {
    $mensaje_exito = "Equipo actualizado con éxito.";
} elseif (($_GET["exito"] ?? "") === "eliminado") {
    $mensaje_exito = "Equipo eliminado.";
}

$deportes = $conexion->query(
    "SELECT id, nombre FROM deportes WHERE requiere_equipo = 1 ORDER BY nombre ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Todas las categorías con su deporte. La lista se entrega al navegador
// para que el desplegable de categorías se filtre solo al cambiar de
// deporte, sin tener que recargar la página.
$categorias = $conexion->query(
    "SELECT id, deporte_id, nombre, edad_maxima FROM categorias
     ORDER BY deporte_id ASC, edad_maxima ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// ---- Filtros: primero disciplina, después el equipo concreto ----
require_once "../Php/filtros.php";

$lista_deportes = filtros_deportes($conexion);
$lista_equipos = filtros_equipos($conexion);
$f = filtros_leer("equipo");

$condiciones = [];
$parametros = [];

if ($f["deporte"] !== "") {
    $condiciones[] = "equipos.deporte_id = :deporte";
    $parametros["deporte"] = (int)$f["deporte"];
}
if ($f["segundo"] !== "" && $f["segundo"] !== "sin") {
    $condiciones[] = "equipos.id = :equipo";
    $parametros["equipo"] = (int)$f["segundo"];
}

$donde = count($condiciones) > 0 ? " WHERE " . implode(" AND ", $condiciones) : "";

$sql = "SELECT equipos.id, equipos.nombre, equipos.logo, deportes.nombre AS deporte,
               categorias.nombre AS categoria, categorias.edad_maxima,
               (SELECT COUNT(*) FROM jugadores WHERE jugadores.equipo_id = equipos.id) AS jugadores
        FROM equipos
        JOIN deportes ON deportes.id = equipos.deporte_id
        LEFT JOIN categorias ON categorias.id = equipos.categoria_id
        {$donde}
        ORDER BY deportes.nombre ASC, equipos.nombre ASC";
$stmt = $conexion->prepare($sql);
$stmt->execute($parametros);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$editando = null;
$editar_id = $_GET["editar"] ?? "";
if ($editar_id !== "" && ctype_digit((string)$editar_id)) {
    $stmt = $conexion->prepare("SELECT id, nombre, deporte_id, categoria_id, logo FROM equipos WHERE id = :id");
    $stmt->execute(["id" => $editar_id]);
    $editando = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Equipos</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/equipos.css?v=9">
    <script src="../Js/imagenes_en_linea.js?v=3" defer></script>
</head>
<body>

    <div class="equipos-contenedor">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Equipos</h1>
        <p class="subtitulo">Registra los equipos de cada deporte</p>

        <?php if ($mensaje_error !== ""): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <?php if ($mensaje_exito !== ""): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <?php if (count($deportes) === 0): ?>
            <p class="vacio">
                Todavía no hay deportes registrados.
                <a href="deportes.php">Agrega uno primero</a>.
            </p>
        <?php else: ?>
            <form action="../Php/<?php echo $editando ? "equipos_actualizar" : "equipos_guardar"; ?>.php" method="POST" class="form-equipo" enctype="multipart/form-data">
                <?php if ($editando): ?>
                    <p class="aviso-edicion">Editando: <strong><?php echo htmlspecialchars($editando["nombre"]); ?></strong></p>
                    <input type="hidden" name="id" value="<?php echo (int)$editando["id"]; ?>">
                <?php endif; ?>
                <div class="campo">
                    <label for="deporte_id">Deporte</label>
                    <select id="deporte_id" name="deporte_id" required <?php echo $editando ? "disabled" : ""; ?>>
                        <option value="">-- Selecciona un deporte --</option>
                        <?php foreach ($deportes as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>" <?php echo ($editando && (int)$editando["deporte_id"] === (int)$d['id']) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($d['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="nombre">Nombre del equipo</label>
                    <input type="text" id="nombre" name="nombre" placeholder="Ej. Real Honduras" required value="<?php echo htmlspecialchars($editando["nombre"] ?? ""); ?>">
                </div>

                <div class="campo">
                    <label for="categoria_id">Categoría por edad (opcional)</label>
                    <select id="categoria_id" name="categoria_id"
                            data-elegida="<?php echo (int)($editando["categoria_id"] ?? 0); ?>">
                        <option value="">Sin categoría — cualquier edad</option>
                    </select>
                    <p class="ayuda-campo">
                        Si eliges una, el sistema no dejará inscribir en este equipo
                        a jugadores que pasen de esa edad.
                        Las categorías se crean en <a href="deportes.php">Deportes</a>.
                    </p>
                </div>

                <div class="campo">
                    <label>Logo (opcional)</label>
                    <?php if ($editando && !empty($editando["logo"])): ?>
                        <?php $logo_actual = str_starts_with($editando["logo"], "http") ? $editando["logo"] : "../" . $editando["logo"]; ?>
                        <p class="logo-actual">Logo actual: <img src="<?php echo htmlspecialchars($logo_actual); ?>" alt="" class="logo-mini"></p>
                    <?php endif; ?>

                    <div class="opciones-logo">
                        <?php if ($editando): ?>
                            <label class="opcion-radio"><input type="radio" name="tipo_logo" value="mantener" checked onchange="mostrarCampoLogo('mantener')"> Mantener el actual</label>
                            <label class="opcion-radio"><input type="radio" name="tipo_logo" value="quitar" onchange="mostrarCampoLogo('quitar')"> Quitar logo</label>
                        <?php endif; ?>
                        <label class="opcion-radio">
                            <input type="radio" name="tipo_logo" value="url" <?php echo $editando ? "" : "checked"; ?> onchange="mostrarCampoLogo('url')">
                            Insertar imagen desde internet
                        </label>
                        <label class="opcion-radio">
                            <input type="radio" name="tipo_logo" value="archivo" onchange="mostrarCampoLogo('archivo')">
                            Subir imagen .png
                        </label>
                    </div>

                    <div id="campo-logo-url" <?php echo $editando ? 'style="display:none;"' : ""; ?>>
                        <input type="hidden" name="logo_url" id="logo_url" value="<?php echo htmlspecialchars(($editando && str_starts_with((string)$editando["logo"], "http")) ? $editando["logo"] : ""); ?>">
                        <div class="imagen-linea" data-imagen-linea data-campo="logo_url" data-consulta="#nombre" data-sufijo=" logo" data-incluir-jpg="0"></div>
                    </div>

                    <div id="campo-logo-archivo" style="display:none;">
                        <input type="file" name="logo_archivo" accept=".png,image/png">
                    </div>
                </div>

                <button type="submit" class="btn-agregar"><?php echo $editando ? "Guardar cambios" : "Agregar equipo"; ?></button>
                <?php if ($editando): ?><a href="equipos.php" class="btn-cancelar">Cancelar</a><?php endif; ?>
            </form>

            <script>
                function mostrarCampoLogo(tipo) {
                    document.getElementById('campo-logo-url').style.display = (tipo === 'url') ? 'block' : 'none';
                    document.getElementById('campo-logo-archivo').style.display = (tipo === 'archivo') ? 'block' : 'none';
                }

                // El desplegable de categorías solo muestra las del deporte elegido.
                (function () {
                    var todas = <?php echo json_encode($categorias, JSON_UNESCAPED_UNICODE); ?>;
                    var selDeporte = document.getElementById("deporte_id");
                    var selCategoria = document.getElementById("categoria_id");
                    if (!selDeporte || !selCategoria) { return; }

                    var elegida = selCategoria.getAttribute("data-elegida");

                    function llenar() {
                        var deporte = selDeporte.value;
                        var previa = selCategoria.value || elegida;
                        selCategoria.innerHTML = '<option value="">Sin categoría — cualquier edad</option>';
                        todas.filter(function (c) {
                            return String(c.deporte_id) === String(deporte);
                        }).forEach(function (c) {
                            var o = document.createElement("option");
                            o.value = c.id;
                            o.textContent = c.nombre + " (hasta " + c.edad_maxima + " años)";
                            if (String(c.id) === String(previa)) { o.selected = true; }
                            selCategoria.appendChild(o);
                        });
                    }

                    selDeporte.addEventListener("change", llenar);
                    llenar();
                })();
            </script>
        <?php endif; ?>

        <h2>Equipos registrados</h2>

        <?php filtros_pintar("equipos.php", $lista_deportes, $lista_equipos, $f, "equipo"); ?>

        <p class="conteo-filtro"><?php echo count($equipos); ?> equipo(s) con estos filtros</p>

        <?php if (count($equipos) === 0): ?>
            <p class="vacio">
                <?php if ($f["deporte"] !== "" || $f["segundo"] !== ""): ?>
                    No hay equipos que cumplan esos filtros.
                    <a href="equipos.php">Ver todos</a>.
                <?php else: ?>
                    Todavía no has agregado ningún equipo.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <table class="tabla-equipos">
                <thead>
                    <tr>
                        <th>Logo</th>
                        <th>Deporte</th>
                        <th>Equipo</th>
                        <th>Categoría</th>
                        <th>Jugadores</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipos as $e): ?>
                        <tr>
                            <td>
                                <?php if (!empty($e["logo"])): ?>
                                    <?php
                                        $src = str_starts_with($e["logo"], "http")
                                            ? $e["logo"]
                                            : "../" . $e["logo"];
                                    ?>
                                    <img src="<?php echo htmlspecialchars($src); ?>" alt="" class="logo-mini">
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($e["deporte"]); ?></td>
                            <td><?php echo htmlspecialchars($e["nombre"]); ?></td>
                            <td>
                                <?php if ($e["categoria"] !== null): ?>
                                    <span class="etiqueta-categoria" title="Hasta <?php echo (int)$e['edad_maxima']; ?> años">
                                        <?php echo htmlspecialchars($e["categoria"]); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="sin-categoria">cualquier edad</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int)$e["jugadores"]; ?></td>
                            <td>
                                <a href="equipos.php?editar=<?php echo (int)$e['id']; ?>" class="btn-editar">Editar</a>
                                <a href="../Php/equipos_eliminar.php?id=<?php echo (int)$e['id']; ?>"
                                   class="btn-eliminar"
                                   onclick="return confirm('Esto también borrará los jugadores y estadísticas de este equipo. ¿Continuar?');">
                                   Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>

</body>
</html>