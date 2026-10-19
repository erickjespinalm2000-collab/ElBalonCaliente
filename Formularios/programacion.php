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
if ($error === "liga") {
    $mensaje_error = "Elige una liga válida.";
} elseif ($error === "fechas") {
    $mensaje_error = "Revisa las fechas: la de inicio no puede ser posterior a la final.";
} elseif ($error === "horas") {
    $mensaje_error = "Marca al menos una hora en la que se puedan jugar los eventos.";
} elseif ($error === "sedes") {
    $mensaje_error = "Marca al menos una localidad disponible.";
} elseif ($error === "sin_equipos") {
    $mensaje_error = "Esa liga necesita al menos dos equipos en su disciplina para generar cruces.";
} elseif ($error === "sin_arbitros") {
    $mensaje_error = "No hay árbitros disponibles para esa disciplina. "
        . "Agrega alguno en <a href=\"arbitros.php\">Árbitros</a>.";
}

$mensaje_exito = "";
if (($_GET["exito"] ?? "") === "sorteado") {
    $creados = (int)($_GET["creados"] ?? 0);
    $fuera = (int)($_GET["fuera"] ?? 0);
    $mensaje_exito = "Se programaron {$creados} evento(s).";
    if ($fuera > 0) {
        $mensaje_exito .= " Quedaron {$fuera} sin lugar: amplía el rango de fechas, "
            . "agrega más horas o más localidades.";
    }
}

$ligas = $conexion->query(
    "SELECT ligas.id, ligas.nombre, ligas.temporada, deportes.nombre AS deporte, deportes.id AS deporte_id,
            (SELECT COUNT(*) FROM equipos WHERE equipos.deporte_id = deportes.id) AS equipos
     FROM ligas
     JOIN deportes ON deportes.id = ligas.deporte_id
     WHERE deportes.requiere_equipo = 1
     ORDER BY deportes.nombre ASC, ligas.nombre ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$localidades = $conexion->query(
    "SELECT id, nombre, ciudad, deporte_id FROM localidades WHERE activa = 1 ORDER BY nombre ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$arbitros_total = (int)$conexion->query("SELECT COUNT(*) FROM arbitros WHERE activo = 1")->fetchColumn();

// ---- Filtros del calendario de abajo: disciplina y despues liga ----
require_once "../Php/filtros.php";
$lista_deportes = filtros_deportes($conexion);
$lista_ligas = filtros_ligas($conexion);
$f = filtros_leer("liga");

$condiciones = ["eventos.localidad_id IS NOT NULL"];
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
$donde = " WHERE " . implode(" AND ", $condiciones);

// Lo que ya está programado hacia adelante, para que se vea el calendario
$stmt = $conexion->prepare(
    "SELECT eventos.id, eventos.fecha, eventos.duracion_minutos,
            local.nombre AS local, visita.nombre AS visita,
            ligas.nombre AS liga, localidades.nombre AS sede, arbitros.nombre AS arbitro
     FROM eventos
     LEFT JOIN equipos AS local  ON local.id  = eventos.equipo_local_id
     LEFT JOIN equipos AS visita ON visita.id = eventos.equipo_visitante_id
     LEFT JOIN ligas       ON ligas.id = eventos.liga_id
     LEFT JOIN localidades ON localidades.id = eventos.localidad_id
     LEFT JOIN arbitros    ON arbitros.id = eventos.arbitro_id
     " . $donde . "
     ORDER BY eventos.fecha ASC
     LIMIT 60"
);
$stmt->execute($parametros);
$programados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Programación de eventos</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/fase2.css?v=9">
</head>
<body>

    <div class="pantalla pantalla-ancha">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Programación automática de eventos</h1>
        <p class="subtitulo">
            Genera el calendario de una liga al azar. El sistema arma todos los
            cruces, los reparte entre los días y horas que le indiques, y
            comprueba tres cosas en cada uno: que la sede esté libre, que el
            árbitro esté libre y que ningún equipo juegue dos veces a la vez.
        </p>

        <?php if ($mensaje_error !== ""): ?>
            <div class="mensaje-error"><?php echo $mensaje_error; ?></div>
        <?php endif; ?>

        <?php if ($mensaje_exito !== ""): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <?php if (count($ligas) === 0): ?>
            <p class="vacio">
                No hay ligas de deportes por equipos.
                <a href="ligas.php">Crea una primero</a>.
            </p>
        <?php elseif (count($localidades) === 0): ?>
            <p class="vacio">
                No hay localidades disponibles.
                <a href="localidades.php">Agrega al menos una</a>.
            </p>
        <?php elseif ($arbitros_total === 0): ?>
            <p class="vacio">
                No hay árbitros disponibles.
                <a href="arbitros.php">Agrega al menos uno</a>.
            </p>
        <?php else: ?>

            <form action="../Php/programacion_sortear.php" method="POST" class="formulario">

                <div class="campo">
                    <label for="liga_id">Liga</label>
                    <select id="liga_id" name="liga_id" required>
                        <option value="">-- Selecciona la liga --</option>
                        <?php foreach ($ligas as $l): ?>
                            <option value="<?php echo (int)$l["id"]; ?>" data-deporte="<?php echo (int)$l["deporte_id"]; ?>">
                                <?php echo htmlspecialchars($l["deporte"] . " · " . $l["nombre"]
                                    . ($l["temporada"] ? " (" . $l["temporada"] . ")" : "")
                                    . " — " . $l["equipos"] . " equipos"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="ayuda">Se usan todos los equipos de la disciplina de esa liga.</p>
                </div>

                <div class="campo-triple">
                    <div class="campo">
                        <label for="desde">Desde</label>
                        <input type="date" id="desde" name="desde" required
                               value="<?php echo date("Y-m-d"); ?>">
                    </div>
                    <div class="campo">
                        <label for="hasta">Hasta</label>
                        <input type="date" id="hasta" name="hasta" required
                               value="<?php echo date("Y-m-d", strtotime("+30 days")); ?>">
                    </div>
                    <div class="campo">
                        <label for="duracion">Duración (minutos)</label>
                        <input type="number" id="duracion" name="duracion" min="15" max="600" value="120" required>
                    </div>
                </div>

                <div class="campo">
                    <label>Horas en las que se puede jugar</label>
                    <div class="rejilla-horas">
                        <?php foreach (range(8, 21) as $h): ?>
                            <label class="casilla-hora">
                                <input type="checkbox" name="horas[]" value="<?php echo $h; ?>"
                                    <?php echo in_array($h, [14, 16, 18], true) ? "checked" : ""; ?>>
                                <?php echo sprintf("%02d:00", $h); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="ayuda">
                        Cuantas más horas marques, más partidos caben. Si la duración
                        es de 120 minutos, dos horas seguidas se estorban entre sí.
                    </p>
                </div>

                <div class="campo">
                    <label>Localidades disponibles</label>
                    <div class="rejilla-sedes">
                        <?php foreach ($localidades as $loc): ?>
                            <label class="casilla-sede" data-deporte="<?php echo (int)($loc["deporte_id"] ?? 0); ?>">
                                <input type="checkbox" name="sedes[]" value="<?php echo (int)$loc["id"]; ?>" checked>
                                <?php echo htmlspecialchars($loc["nombre"]); ?>
                                <?php if ($loc["ciudad"]): ?>
                                    <span class="bloque-nota"><?php echo htmlspecialchars($loc["ciudad"]); ?></span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="campo">
                    <label class="opcion">
                        <input type="checkbox" name="ida_vuelta" value="1">
                        Ida y vuelta (cada pareja juega dos veces)
                    </label>
                </div>

                <button type="submit" class="btn-agregar"
                        onclick="return confirm('Se crearán eventos nuevos en el calendario. ¿Continuar?');">
                    Sortear y programar
                </button>
            </form>

        <?php endif; ?>

        <script>
            // Al elegir la liga se ocultan las sedes dedicadas a otra
            // disciplina. Las multiusos (data-deporte="0") quedan siempre.
            (function () {
                var liga = document.getElementById("liga_id");
                if (!liga) { return; }
                var sedes = document.querySelectorAll(".casilla-sede");

                liga.addEventListener("change", function () {
                    var elegida = liga.selectedOptions[0];
                    var deporte = elegida ? (elegida.getAttribute("data-deporte") || "") : "";

                    sedes.forEach(function (s) {
                        var suyo = s.getAttribute("data-deporte");
                        var sirve = deporte === "" || suyo === "0" || suyo === deporte;
                        s.style.display = sirve ? "" : "none";
                        var casilla = s.querySelector("input");
                        if (!sirve) { casilla.checked = false; }
                        else if (!casilla.dataset.tocada) { casilla.checked = true; }
                    });
                });

                // Si el usuario desmarca algo a mano, respetarlo despues
                sedes.forEach(function (s) {
                    s.querySelector("input").addEventListener("change", function () {
                        this.dataset.tocada = "1";
                    });
                });
            })();
        </script>

        <h2>Eventos ya programados con sede</h2>

        <?php filtros_pintar("programacion.php", $lista_deportes, $lista_ligas, $f, "liga", "Sin liga — amistosos"); ?>
        <p class="conteo-filtro"><?php echo count($programados); ?> evento(s) con sede asignada</p>

        <?php if (count($programados) === 0): ?>
            <p class="vacio">Todavía no hay eventos con localidad asignada.</p>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Fecha y hora</th>
                            <th>Partido</th>
                            <th>Liga</th>
                            <th>Sede</th>
                            <th>Árbitro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($programados as $p): ?>
                            <tr>
                                <td><?php echo date("d/m/Y H:i", strtotime($p["fecha"])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($p["local"] ?? "?"); ?></strong>
                                    vs
                                    <strong><?php echo htmlspecialchars($p["visita"] ?? "?"); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($p["liga"] ?? "—"); ?></td>
                                <td><?php echo htmlspecialchars($p["sede"] ?? "—"); ?></td>
                                <td><?php echo htmlspecialchars($p["arbitro"] ?? "—"); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
