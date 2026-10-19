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
    $mensaje_error = "Debes escribir el nombre del árbitro.";
} elseif ($error === "duplicado") {
    $mensaje_error = "Ya tienes un árbitro con ese nombre en esa disciplina.";
} elseif ($error === "en_uso") {
    $mensaje_error = "No se puede eliminar: tiene eventos asignados. "
        . "Reasígnalos primero, o márcalo como no disponible.";
}

$exito = $_GET["exito"] ?? "";
$mensaje_exito = "";
if ($exito === "creado") {
    $mensaje_exito = "Árbitro agregado con éxito.";
} elseif ($exito === "actualizado") {
    $mensaje_exito = "Árbitro actualizado.";
} elseif ($exito === "eliminado") {
    $mensaje_exito = "Árbitro eliminado.";
}

$deportes = $conexion->query("SELECT id, nombre, icono FROM deportes ORDER BY nombre ASC")
                     ->fetchAll(PDO::FETCH_ASSOC);

// ---- Filtro por disciplina ----
// Los árbitros no pertenecen a un equipo, así que aquí el segundo filtro
// no aplica: solo se filtra por disciplina. Los comodines (sin disciplina
// asignada) aparecen siempre, porque pueden dirigir en cualquiera.
$filtro_deporte = trim((string)($_GET["deporte"] ?? ""));
if ($filtro_deporte !== "" && !ctype_digit($filtro_deporte)) {
    $filtro_deporte = "";
}

$donde = "";
$parametros = [];
if ($filtro_deporte !== "") {
    $donde = " WHERE (arbitros.deporte_id = :deporte OR arbitros.deporte_id IS NULL)";
    $parametros["deporte"] = (int)$filtro_deporte;
}

$stmt = $conexion->prepare(
    "SELECT arbitros.*, deportes.nombre AS deporte,
            (SELECT COUNT(*) FROM eventos WHERE eventos.arbitro_id = arbitros.id) AS eventos
     FROM arbitros
     LEFT JOIN deportes ON deportes.id = arbitros.deporte_id"
     . $donde .
    " ORDER BY arbitros.activo DESC, arbitros.nombre ASC"
);
$stmt->execute($parametros);
$arbitros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$editando = null;
$editar_id = $_GET["editar"] ?? "";
if ($editar_id !== "" && ctype_digit((string)$editar_id)) {
    $stmt = $conexion->prepare("SELECT * FROM arbitros WHERE id = :id");
    $stmt->execute(["id" => $editar_id]);
    $editando = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Árbitros</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/fase2.css?v=9">
</head>
<body>

    <div class="pantalla">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Árbitros</h1>
        <p class="subtitulo">
            Quiénes pueden dirigir los eventos. El
            <a href="programacion.php">sorteo de eventos</a> solo asigna
            árbitros disponibles, y nunca dos partidos a la misma hora
            a la misma persona.
        </p>

        <?php if ($mensaje_error !== ""): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <?php if ($mensaje_exito !== ""): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <form action="../Php/<?php echo $editando ? "arbitros_actualizar" : "arbitros_guardar"; ?>.php"
              method="POST" class="formulario">

            <?php if ($editando): ?>
                <p class="aviso-edicion">Editando: <strong><?php echo htmlspecialchars($editando["nombre"]); ?></strong></p>
                <input type="hidden" name="id" value="<?php echo (int)$editando["id"]; ?>">
            <?php endif; ?>

            <div class="campo">
                <label for="nombre">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" maxlength="100" required
                       placeholder="Ej. Óscar Moncada"
                       value="<?php echo htmlspecialchars($editando["nombre"] ?? ""); ?>">
            </div>

            <div class="campo-doble">
                <div class="campo">
                    <label for="deporte_id">Disciplina</label>
                    <select id="deporte_id" name="deporte_id">
                        <option value="">Cualquiera</option>
                        <?php foreach ($deportes as $d): ?>
                            <option value="<?php echo (int)$d["id"]; ?>"
                                <?php echo ($editando && (int)$editando["deporte_id"] === (int)$d["id"]) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars(trim(($d["icono"] ?? "") . " " . $d["nombre"])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="telefono">Teléfono</label>
                    <input type="text" id="telefono" name="telefono" maxlength="30"
                           placeholder="Opcional"
                           value="<?php echo htmlspecialchars($editando["telefono"] ?? ""); ?>">
                </div>
            </div>

            <div class="campo">
                <label class="opcion">
                    <input type="checkbox" name="activo" value="1"
                        <?php echo (!$editando || $editando["activo"]) ? "checked" : ""; ?>>
                    Disponible para dirigir
                </label>
                <p class="ayuda">
                    Si lo desmarcas deja de entrar en el sorteo, pero conserva
                    los eventos que ya dirigió. Déjale la disciplina en
                    «Cualquiera» si puede arbitrar en varias.
                </p>
            </div>

            <button type="submit" class="btn-agregar">
                <?php echo $editando ? "Guardar cambios" : "Agregar árbitro"; ?>
            </button>
            <?php if ($editando): ?>
                <a href="arbitros.php" class="btn-cancelar">Cancelar</a>
            <?php endif; ?>
        </form>

        <h2>Árbitros registrados</h2>

        <form method="GET" action="arbitros.php" class="barra-filtros">
            <div class="filtro">
                <label for="deporte">Disciplina</label>
                <select id="deporte" name="deporte" onchange="this.form.submit();">
                    <option value="">Todas</option>
                    <?php foreach ($deportes as $d): ?>
                        <option value="<?php echo (int)$d["id"]; ?>"
                            <?php echo $filtro_deporte === (string)$d["id"] ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars(trim(($d["icono"] ?? "") . " " . $d["nombre"])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filtro_deporte !== ""): ?>
                <a href="arbitros.php" class="btn-limpiar-filtro">Quitar filtro</a>
            <?php endif; ?>
        </form>

        <p class="conteo-filtro">
            <?php echo count($arbitros); ?> árbitro(s)<?php echo $filtro_deporte !== "" ? ", incluidos los que dirigen cualquier disciplina" : ""; ?>
        </p>

        <?php if (count($arbitros) === 0): ?>
            <p class="vacio">
                <?php if ($filtro_deporte !== ""): ?>
                    No hay árbitros para esa disciplina.
                    <a href="arbitros.php">Ver todos</a>.
                <?php else: ?>
                    Todavía no has agregado ningún árbitro.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Disciplina</th>
                            <th>Teléfono</th>
                            <th class="numero">Eventos</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($arbitros as $a): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($a["nombre"]); ?></strong></td>
                                <td><?php echo htmlspecialchars($a["deporte"] ?? "Cualquiera"); ?></td>
                                <td><?php echo htmlspecialchars($a["telefono"] ?: "—"); ?></td>
                                <td class="numero"><?php echo (int)$a["eventos"]; ?></td>
                                <td>
                                    <?php if ($a["activo"]): ?>
                                        <span class="etiqueta etiqueta-activa">disponible</span>
                                    <?php else: ?>
                                        <span class="etiqueta etiqueta-apagada">no disponible</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="arbitros.php?editar=<?php echo (int)$a["id"]; ?>" class="btn-editar">Editar</a>
                                    <a href="../Php/arbitros_eliminar.php?id=<?php echo (int)$a["id"]; ?>"
                                       class="btn-eliminar"
                                       onclick="return confirm('¿Eliminar a <?php echo htmlspecialchars($a["nombre"]); ?>?');">
                                       Eliminar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
