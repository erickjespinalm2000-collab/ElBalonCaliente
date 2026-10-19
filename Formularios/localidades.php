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
    $mensaje_error = "Debes escribir el nombre de la localidad.";
} elseif ($error === "duplicado") {
    $mensaje_error = "Ya existe una localidad con ese nombre.";
} elseif ($error === "sector_vacio") {
    $mensaje_error = "Para agregar un sector elige la localidad y escribe su nombre.";
} elseif ($error === "sector_duplicado") {
    $mensaje_error = "Esa localidad ya tiene un sector con ese nombre.";
} elseif ($error === "sector_numeros") {
    $mensaje_error = "La capacidad debe ser un número entero y el precio no puede ser negativo.";
} elseif ($error === "en_uso") {
    $mensaje_error = "No se puede eliminar: hay eventos programados en esa localidad. "
        . "Cámbialos de sede primero, o desactívala.";
}

$exito = $_GET["exito"] ?? "";
$mensaje_exito = "";
if ($exito === "creada") {
    $mensaje_exito = "Localidad agregada con éxito.";
} elseif ($exito === "actualizada") {
    $mensaje_exito = "Localidad actualizada.";
} elseif ($exito === "eliminada") {
    $mensaje_exito = "Localidad eliminada junto con sus sectores.";
} elseif ($exito === "sector_creado") {
    $mensaje_exito = "Sector agregado con éxito.";
} elseif ($exito === "sector_eliminado") {
    $mensaje_exito = "Sector eliminado.";
}

// Localidades con el resumen que sale de sus sectores: cuánta gente cabe
// en total y cuánto se recauda si se llena. Ese ingreso máximo es la
// cifra que después usa el análisis comercial.
// ---- Filtro por disciplina ----
$lista_deportes = $conexion->query(
    "SELECT id, nombre, icono FROM deportes ORDER BY nombre ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$filtro_deporte = trim((string)($_GET["deporte"] ?? ""));
if ($filtro_deporte !== "" && !ctype_digit($filtro_deporte)) {
    $filtro_deporte = "";
}

$donde = "";
$parametros = [];
if ($filtro_deporte !== "") {
    // Las multiusos (sin disciplina) salen siempre, porque sirven para todas
    $donde = " WHERE (localidades.deporte_id = :deporte OR localidades.deporte_id IS NULL)";
    $parametros["deporte"] = (int)$filtro_deporte;
}

$stmt = $conexion->prepare(
    "SELECT localidades.*, deportes.nombre AS deporte,
            (SELECT COUNT(*)            FROM sectores WHERE sectores.localidad_id = localidades.id) AS sectores,
            (SELECT COALESCE(SUM(capacidad), 0)          FROM sectores WHERE sectores.localidad_id = localidades.id) AS aforo,
            (SELECT COALESCE(SUM(capacidad * precio), 0) FROM sectores WHERE sectores.localidad_id = localidades.id) AS ingreso_lleno,
            (SELECT COUNT(*) FROM eventos WHERE eventos.localidad_id = localidades.id) AS eventos
     FROM localidades
     LEFT JOIN deportes ON deportes.id = localidades.deporte_id"
     . $donde .
    " ORDER BY localidades.nombre ASC"
);
$stmt->execute($parametros);
$localidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sectores = $conexion->query(
    "SELECT * FROM sectores ORDER BY localidad_id ASC, precio DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$sectores_por_localidad = [];
foreach ($sectores as $s) {
    $sectores_por_localidad[(int)$s["localidad_id"]][] = $s;
}

$editando = null;
$editar_id = $_GET["editar"] ?? "";
if ($editar_id !== "" && ctype_digit((string)$editar_id)) {
    $stmt = $conexion->prepare("SELECT * FROM localidades WHERE id = :id");
    $stmt->execute(["id" => $editar_id]);
    $editando = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function lps($n): string
{
    return "L " . number_format((float)$n, 0, ".", ",");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Localidades</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/fase2.css?v=9">
</head>
<body>

    <div class="pantalla pantalla-ancha">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Localidades</h1>
        <p class="subtitulo">
            Las sedes donde se juegan los eventos. Cada una se divide en sectores
            con su propia capacidad y su propio precio de boleto, y de ahí sale
            el cálculo de boletería del <a href="comercial.php">análisis comercial</a>.
        </p>

        <?php if ($mensaje_error !== ""): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <?php if ($mensaje_exito !== ""): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <form action="../Php/<?php echo $editando ? "localidades_actualizar" : "localidades_guardar"; ?>.php"
              method="POST" class="formulario">

            <?php if ($editando): ?>
                <p class="aviso-edicion">Editando: <strong><?php echo htmlspecialchars($editando["nombre"]); ?></strong></p>
                <input type="hidden" name="id" value="<?php echo (int)$editando["id"]; ?>">
            <?php endif; ?>

            <div class="campo">
                <label for="nombre">Nombre de la localidad</label>
                <input type="text" id="nombre" name="nombre" maxlength="100" required
                       placeholder="Ej. Estadio Olímpico Metropolitano"
                       value="<?php echo htmlspecialchars($editando["nombre"] ?? ""); ?>">
            </div>

            <div class="campo">
                <label for="deporte_id">Disciplina</label>
                <select id="deporte_id" name="deporte_id">
                    <option value="">Multiusos — sirve para cualquier disciplina</option>
                    <?php foreach ($lista_deportes as $d): ?>
                        <option value="<?php echo (int)$d["id"]; ?>"
                            <?php echo ($editando && (int)$editando["deporte_id"] === (int)$d["id"]) ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars(trim(($d["icono"] ?? "") . " " . $d["nombre"])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="ayuda">
                    Una sede dedicada a una disciplina solo aparecerá al programar
                    eventos de esa disciplina. Déjala en multiusos si sirve para varias.
                </p>
            </div>

            <div class="campo-doble">
                <div class="campo">
                    <label for="ciudad">Ciudad</label>
                    <input type="text" id="ciudad" name="ciudad" maxlength="80"
                           placeholder="Ej. San Pedro Sula"
                           value="<?php echo htmlspecialchars($editando["ciudad"] ?? ""); ?>">
                </div>
                <div class="campo">
                    <label for="direccion">Dirección</label>
                    <input type="text" id="direccion" name="direccion" maxlength="200"
                           placeholder="Opcional"
                           value="<?php echo htmlspecialchars($editando["direccion"] ?? ""); ?>">
                </div>
            </div>

            <div class="campo">
                <label class="opcion">
                    <input type="checkbox" name="activa" value="1"
                        <?php echo (!$editando || $editando["activa"]) ? "checked" : ""; ?>>
                    Disponible para programar eventos
                </label>
                <p class="ayuda">
                    Desmárcala si la sede está en remodelación: dejará de aparecer
                    en el sorteo de eventos, pero no se pierde su historial.
                </p>
            </div>

            <button type="submit" class="btn-agregar">
                <?php echo $editando ? "Guardar cambios" : "Agregar localidad"; ?>
            </button>
            <?php if ($editando): ?>
                <a href="localidades.php" class="btn-cancelar">Cancelar</a>
            <?php endif; ?>
        </form>

        <h2>Localidades registradas</h2>

        <form method="GET" action="localidades.php" class="barra-filtros">
            <div class="filtro">
                <label for="filtro-deporte">Disciplina</label>
                <select id="filtro-deporte" name="deporte" onchange="this.form.submit();">
                    <option value="">Todas</option>
                    <?php foreach ($lista_deportes as $d): ?>
                        <option value="<?php echo (int)$d["id"]; ?>"
                            <?php echo $filtro_deporte === (string)$d["id"] ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars(trim(($d["icono"] ?? "") . " " . $d["nombre"])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filtro_deporte !== ""): ?>
                <a href="localidades.php" class="btn-limpiar-filtro">Quitar filtro</a>
            <?php endif; ?>
        </form>

        <p class="conteo-filtro">
            <?php echo count($localidades); ?> localidad(es)<?php echo $filtro_deporte !== "" ? ", incluidas las multiusos" : ""; ?>
        </p>

        <?php if (count($localidades) === 0): ?>
            <p class="vacio">
                <?php if ($filtro_deporte !== ""): ?>
                    No hay localidades para esa disciplina.
                    <a href="localidades.php">Ver todas</a>.
                <?php else: ?>
                    Todavía no has agregado ninguna localidad.
                <?php endif; ?>
            </p>
        <?php else: ?>

            <?php foreach ($localidades as $l): ?>
                <?php $suyos = $sectores_por_localidad[(int)$l["id"]] ?? []; ?>
                <div class="bloque">
                    <div class="bloque-cabecera">
                        <div>
                            <span class="bloque-titulo"><?php echo htmlspecialchars($l["nombre"]); ?></span>
                            <?php if (!$l["activa"]): ?>
                                <span class="etiqueta etiqueta-apagada">no disponible</span>
                            <?php endif; ?>
                            <p class="bloque-nota">
                                <span class="etiqueta"><?php echo htmlspecialchars($l["deporte"] ?? "multiusos"); ?></span>
                                &middot; <?php echo htmlspecialchars($l["ciudad"] ?: "sin ciudad"); ?>
                                &middot; <?php echo (int)$l["sectores"]; ?> sector(es)
                                &middot; aforo <?php echo number_format((int)$l["aforo"], 0, ".", ","); ?>
                                &middot; lleno recauda <?php echo lps($l["ingreso_lleno"]); ?>
                                &middot; <?php echo (int)$l["eventos"]; ?> evento(s)
                            </p>
                        </div>
                        <div>
                            <a href="localidades.php?editar=<?php echo (int)$l["id"]; ?>" class="btn-editar">Editar</a>
                            <a href="../Php/localidades_eliminar.php?id=<?php echo (int)$l["id"]; ?>"
                               class="btn-eliminar"
                               onclick="return confirm('Se eliminarán también sus <?php echo count($suyos); ?> sector(es). ¿Continuar?');">
                               Eliminar
                            </a>
                        </div>
                    </div>

                    <?php if (count($suyos) === 0): ?>
                        <p class="vacio">Sin sectores todavía. Agrégale al menos uno para que genere ingresos.</p>
                    <?php else: ?>
                        <div class="tabla-scroll">
                            <table class="tabla">
                                <thead>
                                    <tr>
                                        <th>Sector</th>
                                        <th class="numero">Capacidad</th>
                                        <th class="dinero">Precio</th>
                                        <th class="dinero">Si se llena</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($suyos as $s): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($s["nombre"]); ?></strong></td>
                                            <td class="numero"><?php echo number_format((int)$s["capacidad"], 0, ".", ","); ?></td>
                                            <td class="dinero"><?php echo lps($s["precio"]); ?></td>
                                            <td class="dinero"><?php echo lps((int)$s["capacidad"] * (float)$s["precio"]); ?></td>
                                            <td>
                                                <a href="../Php/sectores_eliminar.php?id=<?php echo (int)$s["id"]; ?>"
                                                   class="btn-eliminar"
                                                   onclick="return confirm('¿Eliminar el sector <?php echo htmlspecialchars($s["nombre"]); ?>?');">
                                                   Eliminar
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <form action="../Php/sectores_guardar.php" method="POST" class="formulario" style="margin-top:14px;">
                        <input type="hidden" name="localidad_id" value="<?php echo (int)$l["id"]; ?>">
                        <div class="campo-triple">
                            <div class="campo">
                                <label>Nuevo sector</label>
                                <input type="text" name="nombre" maxlength="60" required placeholder="Ej. Preferencia">
                            </div>
                            <div class="campo">
                                <label>Capacidad</label>
                                <input type="number" name="capacidad" min="1" max="500000" required placeholder="2000">
                            </div>
                            <div class="campo">
                                <label>Precio del boleto (L)</label>
                                <input type="number" name="precio" min="0" step="0.01" required placeholder="150">
                            </div>
                        </div>
                        <button type="submit" class="btn-secundario">Agregar sector</button>
                    </form>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</body>
</html>
