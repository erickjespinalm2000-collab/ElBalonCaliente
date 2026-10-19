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
    $mensaje_error = "Debes escribir el nombre del deporte.";
} elseif ($error === "duplicado") {
    $mensaje_error = "Ese deporte ya existe.";
} elseif ($error === "cat_vacio") {
    $mensaje_error = "Para crear una categoría elige el deporte y escribe su nombre.";
} elseif ($error === "cat_edad") {
    $mensaje_error = "La edad máxima de la categoría debe estar entre 4 y 99 años.";
} elseif ($error === "cat_duplicado") {
    $mensaje_error = "Ese deporte ya tiene una categoría con ese nombre.";
}

$mensaje_exito = "";
if (($_GET["exito"] ?? "") === "creado") {
    $mensaje_exito = "Deporte agregado con éxito.";
} elseif (($_GET["exito"] ?? "") === "actualizado") {
    $mensaje_exito = "Deporte actualizado con éxito.";
} elseif (($_GET["exito"] ?? "") === "eliminado") {
    $mensaje_exito = "Deporte eliminado.";
} elseif (($_GET["exito"] ?? "") === "cat_creada") {
    $mensaje_exito = "Categoría agregada con éxito.";
} elseif (($_GET["exito"] ?? "") === "cat_eliminada") {
    $mensaje_exito = "Categoría eliminada. Los equipos que la tenían quedaron sin categoría.";
}

require_once "../Php/filtros.php";
$filtro_deporte = filtro_deporte_leer();

$campos = "id, nombre, icono, requiere_equipo,
           puntos_victoria, puntos_empate, puntos_derrota, admite_empate";

$stmt = $conexion->query("SELECT {$campos} FROM deportes ORDER BY nombre ASC");
$deportes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// La tabla de abajo se puede limitar a una disciplina. El desplegable del
// formulario de categorías sigue mostrándolas todas, porque ahí se eligen.
$deportes_tabla = $filtro_deporte === ""
    ? $deportes
    : array_values(array_filter($deportes, fn($d) => (string)$d["id"] === $filtro_deporte));

$tipo_bloqueado = false;
$editando = null;
$editar_id = $_GET["editar"] ?? "";
if ($editar_id !== "" && ctype_digit((string)$editar_id)) {
    $stmt = $conexion->prepare("SELECT {$campos} FROM deportes WHERE id = :id");
    $stmt->execute(["id" => $editar_id]);
    $editando = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($editando) {
        $stmt = $conexion->prepare("SELECT (SELECT COUNT(*) FROM equipos WHERE deporte_id = :a)
                                     + (SELECT COUNT(*) FROM jugadores WHERE deporte_id = :b)
                                     + (SELECT COUNT(*) FROM eventos WHERE deporte_id = :c)");
        $stmt->execute(["a" => $editando["id"], "b" => $editando["id"], "c" => $editando["id"]]);
        $tipo_bloqueado = (int)$stmt->fetchColumn() > 0;
    }
}

// Categorías por edad, agrupadas por deporte para pintarlas junto a cada uno.
// Se cuenta cuántos equipos usa cada una para avisar antes de borrarla.
$categorias = $conexion->query(
    "SELECT categorias.id, categorias.deporte_id, categorias.nombre, categorias.edad_maxima,
            deportes.nombre AS deporte,
            (SELECT COUNT(*) FROM equipos WHERE equipos.categoria_id = categorias.id) AS equipos
     FROM categorias
     JOIN deportes ON deportes.id = categorias.deporte_id
     ORDER BY deportes.nombre ASC, categorias.edad_maxima ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$categorias_por_deporte = [];
foreach ($categorias as $c) {
    $categorias_por_deporte[(int)$c["deporte_id"]][] = $c;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Deportes</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/deportes.css?v=9">
</head>
<body>

    <div class="deportes-contenedor">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Deportes</h1>
        <p class="subtitulo">Agrega los deportes que manejará el sistema</p>

        <?php if ($mensaje_error !== ""): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <?php if ($mensaje_exito !== ""): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <form action="../Php/<?php echo $editando ? "deportes_actualizar" : "deportes_guardar"; ?>.php" method="POST" class="form-deporte">
                <?php if ($editando): ?>
                    <p class="aviso-edicion">Editando: <strong><?php echo htmlspecialchars($editando["nombre"]); ?></strong></p>
                    <input type="hidden" name="id" value="<?php echo (int)$editando["id"]; ?>">
                <?php endif; ?>
            <div class="campo">
                <label for="nombre">Nombre del deporte</label>
                <input type="text" id="nombre" name="nombre" placeholder="Ej. Fútbol" required value="<?php echo htmlspecialchars($editando["nombre"] ?? ""); ?>">
            </div>
            <div class="campo">
                <label for="icono">Ícono (emoji o texto corto, opcional)</label>
                <input type="text" id="icono" name="icono" placeholder="⚽" value="<?php echo htmlspecialchars($editando["icono"] ?? ""); ?>">
            </div>

            <div class="campo campo-checkbox">
                <label class="opcion-checkbox">
                    <input type="checkbox" name="requiere_equipo" value="1" <?php echo (!$editando || $editando["requiere_equipo"]) ? "checked" : ""; ?> <?php echo $tipo_bloqueado ? "disabled" : ""; ?>>
                    Este deporte se juega en equipos<?php if ($tipo_bloqueado): ?> (bloqueado: ya tiene equipos, jugadores o eventos)<?php endif; ?>
                </label>
                <p class="ayuda-checkbox">
                    Desmárcalo para deportes individuales como atletismo, natación o ciclismo.
                </p>
            </div>

            <div id="bloque-puntuacion" class="campo-puntuacion">
                <p style="color: orange;" class="puntuacion-titulo">Sistema de puntuación de la tabla de posiciones</p>

                <div class="campo-triple">
                    <div class="campo">
                        <label for="puntos_victoria">Puntos por victoria</label>
                        <input type="number" id="puntos_victoria" name="puntos_victoria" min="0" max="10" required
                               value="<?php echo (int)($editando["puntos_victoria"] ?? 3); ?>">
                    </div>
                    <div class="campo">
                        <label for="puntos_empate">Puntos por empate</label>
                        <input type="number" id="puntos_empate" name="puntos_empate" min="0" max="10" required
                               value="<?php echo (int)($editando["puntos_empate"] ?? 1); ?>">
                    </div>
                    <div class="campo">
                        <label for="puntos_derrota">Puntos por derrota</label>
                        <input type="number" id="puntos_derrota" name="puntos_derrota" min="0" max="10" required
                               value="<?php echo (int)($editando["puntos_derrota"] ?? 0); ?>">
                    </div>
                </div>

                <label class="opcion-checkbox">
                    <input type="checkbox" name="admite_empate" value="1"
                        <?php echo (!$editando || $editando["admite_empate"]) ? "checked" : ""; ?>>
                    En este deporte puede haber empate
                </label>
                <p class="ayuda-checkbox">
                    Fútbol: 3, 1, 0 con empate. Baloncesto: 2, 0, 1 sin empate, según la FIBA.
                    Si desmarcas el empate, el sistema rechazará los eventos con marcador igualado.
                </p>
            </div>

            <button type="submit" class="btn-agregar"><?php echo $editando ? "Guardar cambios" : "Agregar deporte"; ?></button>
                <?php if ($editando): ?><a href="deportes.php" class="btn-cancelar">Cancelar</a><?php endif; ?>
        </form>

        <h2>Deportes registrados</h2>

        <?php filtro_deporte_pintar("deportes.php", $deportes, $filtro_deporte, count($deportes_tabla), "disciplina(s)"); ?>

        <?php if (count($deportes_tabla) === 0): ?>
            <p class="vacio">
                <?php if ($filtro_deporte !== ""): ?>
                    Esa disciplina ya no existe. <a href="deportes.php">Ver todas</a>.
                <?php else: ?>
                    Todavía no has agregado ningún deporte.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <table class="tabla-deportes">
                <thead>
                    <tr>
                        <th>Ícono</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Puntuación</th>
                        <th>Categorías</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deportes_tabla as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d["icono"] ?? ""); ?></td>
                            <td><?php echo htmlspecialchars($d["nombre"]); ?></td>
                            <td>
                                <?php echo $d["requiere_equipo"] ? "Por equipos" : "Individual"; ?>
                            </td>
                            <td>
                                <?php if ($d["requiere_equipo"]): ?>
                                    <?php echo (int)$d["puntos_victoria"]; ?> &middot;
                                    <?php echo (int)$d["puntos_empate"]; ?> &middot;
                                    <?php echo (int)$d["puntos_derrota"]; ?>
                                    <span class="detalle-puntuacion">
                                        victoria · empate · derrota<?php echo $d["admite_empate"] ? "" : " · sin empates"; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="detalle-puntuacion">no aplica</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $suyas = $categorias_por_deporte[(int)$d["id"]] ?? []; ?>
                                <?php if (count($suyas) === 0): ?>
                                    <span class="detalle-puntuacion">sin categorías</span>
                                <?php else: ?>
                                    <?php foreach ($suyas as $c): ?>
                                        <span class="etiqueta-categoria" title="Hasta <?php echo (int)$c['edad_maxima']; ?> años">
                                            <?php echo htmlspecialchars($c["nombre"]); ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="deportes.php?editar=<?php echo (int)$d['id']; ?>" class="btn-editar">Editar</a>
                                <a href="../Php/deportes_eliminar.php?id=<?php echo (int)$d['id']; ?>"
                                   class="btn-eliminar"
                                   onclick="return confirm('Esto también borrará los equipos, jugadores y estadísticas de este deporte. ¿Continuar?');">
                                   Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- ================= Categorías por edad ================= -->

        <h2>Categorías por edad</h2>
        <p class="subtitulo">
            Define las divisiones de cada deporte: Sub-15, Sub-17, Libre...
            Después se le asigna una a cada equipo, y el sistema impedirá
            inscribir jugadores que pasen de esa edad.
        </p>

        <?php if (count($deportes) === 0): ?>
            <p class="vacio">Primero agrega un deporte.</p>
        <?php else: ?>

            <form action="../Php/categorias_guardar.php" method="POST" class="form-deporte">
                <div class="campo-triple">
                    <div class="campo">
                        <label for="cat_deporte">Deporte</label>
                        <select id="cat_deporte" name="deporte_id" required>
                            <option value="">-- Selecciona --</option>
                            <?php foreach ($deportes as $d): ?>
                                <option value="<?php echo (int)$d["id"]; ?>">
                                    <?php echo htmlspecialchars(trim(($d["icono"] ?? "") . " " . $d["nombre"])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="cat_nombre">Nombre de la categoría</label>
                        <input type="text" id="cat_nombre" name="nombre" placeholder="Ej. Sub-15" required maxlength="50">
                    </div>
                    <div class="campo">
                        <label for="cat_edad">Edad máxima</label>
                        <input type="number" id="cat_edad" name="edad_maxima" min="4" max="99" value="15" required>
                    </div>
                </div>

                <p class="ayuda-checkbox">
                    En Sub-15 la edad máxima es 15. Un jugador de 14 sí puede inscribirse,
                    porque los menores siempre pueden jugar en una categoría mayor;
                    uno de 16 no. Para una categoría sin tope usa 99.
                </p>

                <button type="submit" class="btn-agregar">Agregar categoría</button>
            </form>

            <?php
                $categorias_tabla = $filtro_deporte === ""
                    ? $categorias
                    : array_values(array_filter($categorias, fn($c) => (string)$c["deporte_id"] === $filtro_deporte));
            ?>
            <?php if (count($categorias_tabla) === 0): ?>
                <p class="vacio">Todavía no has creado ninguna categoría.</p>
            <?php else: ?>
                <table class="tabla-deportes">
                    <thead>
                        <tr>
                            <th>Deporte</th>
                            <th>Categoría</th>
                            <th>Edad máxima</th>
                            <th>Equipos</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias_tabla as $c): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c["deporte"]); ?></td>
                                <td><strong><?php echo htmlspecialchars($c["nombre"]); ?></strong></td>
                                <td><?php echo (int)$c["edad_maxima"]; ?> años</td>
                                <td><?php echo (int)$c["equipos"]; ?></td>
                                <td>
                                    <a href="../Php/categorias_eliminar.php?id=<?php echo (int)$c['id']; ?>"
                                       class="btn-eliminar"
                                       onclick="return confirm('<?php echo (int)$c['equipos']; ?> equipo(s) quedarán sin categoría. Los jugadores no se borran. ¿Continuar?');">
                                       Eliminar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <script>
        (function () {
            const casilla = document.querySelector('input[name="requiere_equipo"]');
            const bloque = document.getElementById("bloque-puntuacion");
            if (!casilla || !bloque) {
                return;
            }
            function ajustar() {
                bloque.style.display = casilla.checked ? "" : "none";
            }
            casilla.addEventListener("change", ajustar);
            ajustar();
        })();
    </script>

</body>
</html>