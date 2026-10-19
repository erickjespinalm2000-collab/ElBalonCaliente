<?php
session_start();
require_once "../Php/conexion.php";

if (!isset($_SESSION["usuario_id"], $_SESSION["nombre_usuario"], $_SESSION["rol"])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}

$error = $_GET["error"] ?? "";
$mensaje_error = "";
if ($error === "evento") {
    $mensaje_error = "El evento elegido no existe.";
} elseif ($error === "valor") {
    $mensaje_error = "Los valores deben ser números de 0 en adelante, con hasta 2 decimales.";
} elseif ($error === "dato_invalido") {
    $mensaje_error = "Se recibió un jugador o una estadística que no corresponde a este evento.";
}

$mensaje_exito = "";
if (($_GET["exito"] ?? "") === "guardado") {
    $mensaje_exito = "Estadísticas guardadas con éxito.";
}

// ---- Filtros: primero disciplina, después liga ----
// Con decenas de eventos el desplegable se vuelve imposible de recorrer,
// así que se acota antes de elegir en cuál se capturan las estadísticas.
require_once "../Php/filtros.php";

$lista_deportes = filtros_deportes($conexion);
$lista_ligas = filtros_ligas($conexion);
$f = filtros_leer("liga");

$condiciones = [];
$parametros = [];

if ($f["deporte"] !== "") {
    $condiciones[] = "eventos.deporte_id = :deporte";
    $parametros["deporte"] = (int)$f["deporte"];
}
if ($f["segundo"] === "sin") {
    $condiciones[] = "eventos.liga_id IS NULL";
} elseif ($f["segundo"] !== "") {
    $condiciones[] = "eventos.liga_id = :liga";
    $parametros["liga"] = (int)$f["segundo"];
}

$donde = count($condiciones) > 0 ? " WHERE " . implode(" AND ", $condiciones) : "";

$sql = "SELECT eventos.id, eventos.fecha, eventos.nombre, deportes.nombre AS deporte,
               local.nombre AS local, visita.nombre AS visitante
        FROM eventos
        JOIN deportes ON deportes.id = eventos.deporte_id
        LEFT JOIN equipos AS local ON local.id = eventos.equipo_local_id
        LEFT JOIN equipos AS visita ON visita.id = eventos.equipo_visitante_id
        {$donde}
        ORDER BY eventos.fecha DESC";
$stmt = $conexion->prepare($sql);
$stmt->execute($parametros);
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

function etiquetaEvento(array $e): string
{
    $fecha = date("d/m/Y", strtotime($e["fecha"]));
    $detalle = $e["local"] !== null
        ? $e["local"] . " vs " . $e["visitante"]
        : ($e["nombre"] ?? "Sin nombre");
    return $fecha . " · " . $e["deporte"] . " · " . $detalle;
}

$evento_id = $_GET["evento_id"] ?? "";
$evento = null;
$jugadores = [];
$tipos = [];
$valores = [];

if ($evento_id !== "" && ctype_digit((string)$evento_id)) {
    $stmt = $conexion->prepare(
        "SELECT eventos.*, deportes.nombre AS deporte, deportes.requiere_equipo,
                local.nombre AS local, visita.nombre AS visitante
         FROM eventos
         JOIN deportes ON deportes.id = eventos.deporte_id
         LEFT JOIN equipos AS local ON local.id = eventos.equipo_local_id
         LEFT JOIN equipos AS visita ON visita.id = eventos.equipo_visitante_id
         WHERE eventos.id = :id"
    );
    $stmt->execute(["id" => $evento_id]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($evento) {
        $stmt = $conexion->prepare("SELECT id, nombre, unidad FROM tipos_estadistica WHERE deporte_id = :d ORDER BY nombre ASC");
        $stmt->execute(["d" => $evento["deporte_id"]]);
        $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ((int)$evento["requiere_equipo"] === 1) {
            $stmt = $conexion->prepare(
                "SELECT jugadores.id, jugadores.nombre, jugadores.posicion, equipos.nombre AS equipo
                 FROM jugadores
                 JOIN equipos ON equipos.id = jugadores.equipo_id
                 WHERE jugadores.equipo_id IN (:l, :v)
                 ORDER BY equipos.nombre ASC, jugadores.nombre ASC"
            );
            $stmt->execute(["l" => $evento["equipo_local_id"], "v" => $evento["equipo_visitante_id"]]);
        } else {
            $stmt = $conexion->prepare(
                "SELECT jugadores.id, jugadores.nombre, jugadores.posicion, NULL AS equipo
                 FROM jugadores
                 WHERE jugadores.deporte_id = :d AND jugadores.equipo_id IS NULL
                 ORDER BY jugadores.nombre ASC"
            );
            $stmt->execute(["d" => $evento["deporte_id"]]);
        }
        $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conexion->prepare("SELECT jugador_id, tipo_estadistica_id, valor FROM estadisticas_jugador WHERE evento_id = :e");
        $stmt->execute(["e" => $evento["id"]]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $valores[$fila["jugador_id"]][$fila["tipo_estadistica_id"]] = rtrim(rtrim($fila["valor"], "0"), ".");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Estadísticas</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/estadisticas.css?v=9">
</head>
<body>

    <div class="estadisticas-contenedor">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Captura de estadísticas</h1>
        <p class="subtitulo">Elige un evento y carga los valores de cada jugador</p>

        <?php if ($mensaje_error !== ""): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <?php if ($mensaje_exito !== ""): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <?php filtros_pintar("estadisticas.php", $lista_deportes, $lista_ligas, $f, "liga", "Sin liga — amistosos"); ?>

        <?php if (count($eventos) === 0): ?>
            <p class="vacio">
                <?php if ($f["deporte"] !== "" || $f["segundo"] !== ""): ?>
                    No hay eventos que cumplan esos filtros.
                    <a href="estadisticas.php">Ver todos</a>.
                <?php else: ?>
                    Todavía no hay eventos registrados.
                    <a href="eventos.php">Registra uno primero</a>.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="conteo-filtro"><?php echo count($eventos); ?> evento(s) para elegir</p>

            <form method="GET" action="estadisticas.php" class="form-estadisticas">
                <?php if ($f["deporte"] !== ""): ?>
                    <input type="hidden" name="deporte" value="<?php echo htmlspecialchars($f["deporte"]); ?>">
                <?php endif; ?>
                <?php if ($f["segundo"] !== ""): ?>
                    <input type="hidden" name="liga" value="<?php echo htmlspecialchars($f["segundo"]); ?>">
                <?php endif; ?>
                <div class="campo">
                    <label for="evento_id">Evento</label>
                    <select id="evento_id" name="evento_id" required onchange="this.form.submit()">
                        <option value="">-- Selecciona un evento --</option>
                        <?php foreach ($eventos as $e): ?>
                            <option value="<?php echo (int)$e['id']; ?>"
                                <?php echo ((string)$e['id'] === (string)$evento_id) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars(etiquetaEvento($e)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <noscript><button type="submit" class="btn-agregar">Cargar evento</button></noscript>
            </form>
        <?php endif; ?>

        <?php if ($evento_id !== "" && !$evento): ?>
            <div class="mensaje-error">El evento elegido no existe.</div>
        <?php endif; ?>

        <?php if ($evento): ?>

            <h2>
                <?php if ($evento["local"] !== null): ?>
                    <?php echo htmlspecialchars($evento["local"]); ?>
                    <span class="marcador"><?php echo (int)$evento["marcador_local"]; ?> - <?php echo (int)$evento["marcador_visitante"]; ?></span>
                    <?php echo htmlspecialchars($evento["visitante"]); ?>
                <?php else: ?>
                    <?php echo htmlspecialchars($evento["nombre"] ?? "Evento"); ?>
                <?php endif; ?>
            </h2>
            <p class="detalle-evento">
                <?php echo htmlspecialchars($evento["deporte"]); ?> ·
                <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($evento["fecha"]))); ?>
            </p>

            <?php if (count($tipos) === 0): ?>
                <p class="vacio">
                    Este deporte todavía no tiene estadísticas definidas.
                    <a href="tipos_estadisticas.php">Defínelas primero</a>.
                </p>
            <?php elseif (count($jugadores) === 0): ?>
                <p class="vacio">
                    <?php if ((int)$evento["requiere_equipo"] === 1): ?>
                        Los equipos de este evento todavía no tienen jugadores.
                    <?php else: ?>
                        Este deporte todavía no tiene atletas registrados.
                    <?php endif; ?>
                    <a href="jugadores.php">Agrega jugadores</a>.
                </p>
            <?php else: ?>
                <form action="../Php/estadisticas_guardar.php" method="POST">
                    <input type="hidden" name="evento_id" value="<?php echo (int)$evento['id']; ?>">

                    <div class="tabla-scroll">
                        <table class="tabla-estadisticas">
                            <thead>
                                <tr>
                                    <th>Jugador</th>
                                    <?php if ((int)$evento["requiere_equipo"] === 1): ?>
                                        <th>Equipo</th>
                                    <?php endif; ?>
                                    <?php foreach ($tipos as $t): ?>
                                        <th>
                                            <?php echo htmlspecialchars($t["nombre"]); ?>
                                            <?php if (!empty($t["unidad"])): ?>
                                                <span class="unidad">(<?php echo htmlspecialchars($t["unidad"]); ?>)</span>
                                            <?php endif; ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jugadores as $j): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($j["nombre"]); ?>
                                            <?php if (!empty($j["posicion"])): ?>
                                                <span class="unidad"><?php echo htmlspecialchars($j["posicion"]); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ((int)$evento["requiere_equipo"] === 1): ?>
                                            <td><?php echo htmlspecialchars($j["equipo"]); ?></td>
                                        <?php endif; ?>
                                        <?php foreach ($tipos as $t): ?>
                                            <td>
                                                <input type="number" min="0" step="any"
                                                       name="valores[<?php echo (int)$j['id']; ?>][<?php echo (int)$t['id']; ?>]"
                                                       value="<?php echo htmlspecialchars($valores[$j['id']][$t['id']] ?? ""); ?>">
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="ayuda">Deja una casilla vacía si ese jugador no registró esa estadística. Al guardar, las casillas vacías se borran.</p>

                    <button type="submit" class="btn-agregar">Guardar estadísticas</button>
                </form>
            <?php endif; ?>

        <?php endif; ?>

    </div>

</body>
</html>