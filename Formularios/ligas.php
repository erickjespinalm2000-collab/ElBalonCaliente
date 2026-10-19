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
    $mensaje_error = "Debes elegir un deporte y escribir el nombre de la liga.";
} elseif ($error === "duplicado") {
    $mensaje_error = "Ya existe una liga con ese nombre en ese deporte.";
} elseif ($error === "sin_deportes") {
    $mensaje_error = "Primero debes agregar al menos un deporte.";
} elseif ($error === "tiene_llave") {
    $mensaje_error = "Esa liga ya tiene un cuadro de eliminación generado, así que no puede "
        . "pasar a «solo fase de liga». Elimina primero el cuadro en Llaves de eliminación.";
}

$mensaje_exito = "";
if (($_GET["exito"] ?? "") === "creado") {
    $mensaje_exito = "Liga agregada con éxito.";
} elseif (($_GET["exito"] ?? "") === "actualizado") {
    $mensaje_exito = "Liga actualizado con éxito.";
} elseif (($_GET["exito"] ?? "") === "eliminado") {
    $mensaje_exito = "Liga eliminada.";
}

$deportes = $conexion->query("SELECT id, nombre, icono FROM deportes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// ---- Filtro por disciplina ----
require_once "../Php/filtros.php";
$filtro_deporte = filtro_deporte_leer();
$donde = $filtro_deporte !== "" ? " WHERE ligas.deporte_id = :deporte" : "";
$parametros = $filtro_deporte !== "" ? ["deporte" => (int)$filtro_deporte] : [];

$sql = "SELECT ligas.id, ligas.nombre, ligas.pais, ligas.temporada, ligas.formato, deportes.nombre AS deporte
        FROM ligas
        JOIN deportes ON deportes.id = ligas.deporte_id
        {$donde}
        ORDER BY deportes.nombre ASC, ligas.nombre ASC";
$stmt = $conexion->prepare($sql);
$stmt->execute($parametros);
$ligas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$editando = null;
$editar_id = $_GET["editar"] ?? "";
if ($editar_id !== "" && ctype_digit((string)$editar_id)) {
    $stmt = $conexion->prepare("SELECT id, nombre, deporte_id, pais, temporada, formato FROM ligas WHERE id = :id");
    $stmt->execute(["id" => $editar_id]);
    $editando = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Ligas</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/ligas.css?v=9">
</head>
<body>

    <div class="ligas-contenedor">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Ligas</h1>
        <p class="subtitulo">Registra las ligas o competencias de cada deporte</p>

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
            <form action="../Php/<?php echo $editando ? "ligas_actualizar" : "ligas_guardar"; ?>.php" method="POST" class="form-liga">
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
                    <label for="nombre">Nombre de la liga/competencia</label>
                    <input type="text" id="nombre" name="nombre" placeholder="Ej. Liga Nacional de Honduras" required value="<?php echo htmlspecialchars($editando["nombre"] ?? ""); ?>">
                </div>

                <div class="campo-doble">
                    <div class="campo">
                        <label for="pais">País (opcional)</label>
                        <input type="text" id="pais" name="pais" placeholder="Honduras" value="<?php echo htmlspecialchars($editando["pais"] ?? ""); ?>">
                    </div>
                    <div class="campo">
                        <label for="temporada">Temporada (opcional)</label>
                        <input type="text" id="temporada" name="temporada" placeholder="2026-2027" value="<?php echo htmlspecialchars($editando["temporada"] ?? ""); ?>">
                    </div>
                </div>

                <div class="campo">
                    <label for="formato">Formato de la competencia</label>
                    <select id="formato" name="formato" required>
                        <?php
                            $formatos = [
                                "liga" => "Fase de liga — todos contra todos, tabla de posiciones",
                                "eliminacion" => "Eliminación directa — cuadro de llaves",
                                "ambas" => "Ambas — fase de liga y luego eliminatoria",
                            ];
                            $actual = $editando["formato"] ?? "liga";
                        ?>
                        <?php foreach ($formatos as $clave => $texto): ?>
                            <option value="<?php echo $clave; ?>" <?php echo $actual === $clave ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($texto); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="ayuda-liga">
                        Solo las ligas con eliminación directa pueden tener
                        <a href="llaves.php">cuadro de llaves</a>. Si eliges «Ambas»,
                        tendrás la tabla de posiciones y además el cuadro.
                    </p>
                </div>

                <button type="submit" class="btn-agregar"><?php echo $editando ? "Guardar cambios" : "Agregar liga"; ?></button>
                <?php if ($editando): ?><a href="ligas.php" class="btn-cancelar">Cancelar</a><?php endif; ?>
            </form>
        <?php endif; ?>

        <h2>Ligas registradas</h2>

        <?php filtro_deporte_pintar("ligas.php", $deportes, $filtro_deporte, count($ligas), "liga(s)"); ?>

        <?php if (count($ligas) === 0): ?>
            <p class="vacio">Todavía no has agregado ninguna liga.</p>
        <?php else: ?>
            <table class="tabla-ligas">
                <thead>
                    <tr>
                        <th>Deporte</th>
                        <th>Liga</th>
                        <th>País</th>
                        <th>Temporada</th>
                        <th>Formato</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ligas as $l): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($l["deporte"]); ?></td>
                            <td><?php echo htmlspecialchars($l["nombre"]); ?></td>
                            <td><?php echo htmlspecialchars($l["pais"] ?? "—"); ?></td>
                            <td><?php echo htmlspecialchars($l["temporada"] ?? "—"); ?></td>
                            <td>
                                <?php
                                    $rotulos = ["liga" => "Fase de liga", "eliminacion" => "Eliminación directa", "ambas" => "Ambas"];
                                    $tiene_llave = in_array($l["formato"], ["eliminacion", "ambas"], true);
                                ?>
                                <span class="etiqueta-formato <?php echo $tiene_llave ? "formato-llave" : ""; ?>">
                                    <?php echo htmlspecialchars($rotulos[$l["formato"]] ?? $l["formato"]); ?>
                                </span>
                            </td>
                            <td>
                                <a href="ligas.php?editar=<?php echo (int)$l['id']; ?>" class="btn-editar">Editar</a>
                                <a href="../Php/ligas_eliminar.php?id=<?php echo (int)$l['id']; ?>"
                                   class="btn-eliminar"
                                   onclick="return confirm('¿Eliminar esta liga? Los eventos que la tengan asignada quedarán sin liga.');">
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