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
    $mensaje_error = "Debes elegir un deporte y escribir el nombre de la estadística.";
} elseif ($error === "duplicado") {
    $mensaje_error = "Ese deporte ya tiene una estadística con ese nombre.";
}

$mensaje_exito = "";
if (($_GET["exito"] ?? "") === "creado") {
    $mensaje_exito = "Estadística agregada con éxito.";
} elseif (($_GET["exito"] ?? "") === "actualizado") {
    $mensaje_exito = "Estadística actualizado con éxito.";
} elseif (($_GET["exito"] ?? "") === "eliminado") {
    $mensaje_exito = "Estadística eliminada.";
}

$deportes = $conexion->query("SELECT id, nombre, icono FROM deportes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// ---- Filtro por disciplina ----
require_once "../Php/filtros.php";
$filtro_deporte = filtro_deporte_leer();
$donde = $filtro_deporte !== "" ? " WHERE tipos_estadistica.deporte_id = :deporte" : "";
$parametros = $filtro_deporte !== "" ? ["deporte" => (int)$filtro_deporte] : [];

$sql = "SELECT tipos_estadistica.id, tipos_estadistica.nombre, tipos_estadistica.unidad,
               tipos_estadistica.menor_es_mejor,
               deportes.nombre AS deporte
        FROM tipos_estadistica
        JOIN deportes ON deportes.id = tipos_estadistica.deporte_id
        {$donde}
        ORDER BY deportes.nombre ASC, tipos_estadistica.nombre ASC";
$stmt = $conexion->prepare($sql);
$stmt->execute($parametros);
$tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$editando = null;
$editar_id = $_GET["editar"] ?? "";
if ($editar_id !== "" && ctype_digit((string)$editar_id)) {
    $stmt = $conexion->prepare("SELECT id, nombre, deporte_id, unidad, menor_es_mejor FROM tipos_estadistica WHERE id = :id");
    $stmt->execute(["id" => $editar_id]);
    $editando = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Tipos de estadística</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/tipos_estadisticas.css?v=9">
</head>
<body>

    <div class="tipos-contenedor">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Tipos de estadística</h1>
        <p class="subtitulo">Define qué se mide en cada deporte (goles, asistencias, tiempo, distancia...)</p>

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
            <form action="../Php/<?php echo $editando ? "tipos_estadisticas_actualizar" : "tipos_estadisticas_guardar"; ?>.php" method="POST" class="form-tipo">
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

                <div class="campo-doble">
                    <div class="campo">
                        <label for="nombre">Nombre de la estadística</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Ej. Goles" required value="<?php echo htmlspecialchars($editando["nombre"] ?? ""); ?>">
                    </div>

                    <div class="campo">
                        <label for="unidad">Unidad (opcional)</label>
                        <input type="text" id="unidad" name="unidad" list="unidades" placeholder="Ej. cantidad" value="<?php echo htmlspecialchars($editando["unidad"] ?? ""); ?>">
                        <datalist id="unidades">
                            <option value="cantidad">
                            <option value="segundos">
                            <option value="minutos">
                            <option value="metros">
                            <option value="kilómetros">
                            <option value="puntos">
                        </datalist>
                    </div>
                </div>

                <div class="campo campo-checkbox">
                    <label class="opcion-checkbox">
                        <input type="checkbox" name="menor_es_mejor" value="1"
                            <?php echo ($editando && (int)$editando["menor_es_mejor"] === 1) ? "checked" : ""; ?>>
                        En esta estadística, el valor más bajo es el mejor
                    </label>
                    <p style="color: orange;"class="ayuda-checkbox" >
                        Márcalo para tiempos y posiciones: en los 100 metros libres gana quien
                        nada en menos segundos, y el mejor lugar es el 1. Déjalo sin marcar para
                        goles, puntos o rebotes, donde más es mejor.
                    </p>
                </div>

                <button type="submit" class="btn-agregar"><?php echo $editando ? "Guardar cambios" : "Agregar estadística"; ?></button>
                <?php if ($editando): ?><a href="tipos_estadisticas.php" class="btn-cancelar">Cancelar</a><?php endif; ?>
            </form>
        <?php endif; ?>

        <h2>Estadísticas registradas</h2>

        <?php filtro_deporte_pintar("tipos_estadisticas.php", $deportes, $filtro_deporte, count($tipos), "tipo(s) de estadística"); ?>

        <?php if (count($tipos) === 0): ?>
            <p class="vacio">Todavía no has definido ninguna estadística.</p>
        <?php else: ?>
            <table class="tabla-tipos">
                <thead>
                    <tr>
                        <th>Deporte</th>
                        <th>Estadística</th>
                        <th>Unidad</th>
                        <th>Criterio</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tipos as $t): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t["deporte"]); ?></td>
                            <td><?php echo htmlspecialchars($t["nombre"]); ?></td>
                            <td><?php echo htmlspecialchars($t["unidad"] ?? "—"); ?></td>
                            <td>
                                <?php if ((int)$t["menor_es_mejor"] === 1): ?>
                                    <span class="criterio criterio-menor">El menor valor</span>
                                <?php else: ?>
                                    <span class="criterio">El mayor valor</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="tipos_estadisticas.php?editar=<?php echo (int)$t['id']; ?>" class="btn-editar">Editar</a>
                                <a href="../Php/tipos_estadisticas_eliminar.php?id=<?php echo (int)$t['id']; ?>"
                                   class="btn-eliminar"
                                   onclick="return confirm('Esto también borrará todos los valores registrados con esta estadística. ¿Continuar?');">
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