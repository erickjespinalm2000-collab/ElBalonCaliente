<?php
session_start();
require_once "../Php/conexion.php";

if (!isset($_SESSION["usuario_id"], $_SESSION["nombre_usuario"], $_SESSION["rol"])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}

function numero($v): string
{
    return rtrim(rtrim(number_format((float)$v, 2, ".", ""), "0"), ".");
}

function iniciales(string $nombre): string
{
    $palabras = array_values(array_filter(
        preg_split('/\s+/', trim($nombre)) ?: [],
        fn($p) => mb_strlen($p) > 2
    ));

    if (count($palabras) === 0) {
        return mb_strtoupper(mb_substr(trim($nombre), 0, 2));
    }

    if (count($palabras) === 1) {
        return mb_strtoupper(mb_substr($palabras[0], 0, 2));
    }

    return mb_strtoupper(mb_substr($palabras[0], 0, 1) . mb_substr($palabras[1], 0, 1));
}

$deportes = $conexion->query(
    "SELECT id, nombre, icono, requiere_equipo,
            puntos_victoria, puntos_empate, puntos_derrota, admite_empate
     FROM deportes ORDER BY nombre ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$deporte_id = $_GET["deporte_id"] ?? "";
$liga_id = $_GET["liga_id"] ?? "";

if ($deporte_id === "" || !ctype_digit((string)$deporte_id)) {
    $con_eventos = $conexion->query("SELECT deporte_id FROM eventos GROUP BY deporte_id ORDER BY COUNT(*) DESC LIMIT 1")->fetchColumn();
    $deporte_id = $con_eventos !== false ? (string)$con_eventos : (count($deportes) ? (string)$deportes[0]["id"] : "");
}

$deporte = null;
foreach ($deportes as $d) {
    if ((string)$d["id"] === (string)$deporte_id) {
        $deporte = $d;
    }
}

$ligas = [];
$posiciones = [];
$lideres = [];
$total_eventos = 0;

if ($deporte) {
    $stmt = $conexion->prepare("SELECT id, nombre, temporada FROM ligas WHERE deporte_id = :d ORDER BY nombre ASC");
    $stmt->execute(["d" => $deporte["id"]]);
    $ligas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $liga_valida = false;
    foreach ($ligas as $l) {
        if ((string)$l["id"] === (string)$liga_id) {
            $liga_valida = true;
        }
    }
    if (!$liga_valida) {
        $liga_id = "";
    }

    $filtro_liga = $liga_id !== "" ? " AND eventos.liga_id = :liga" : "";
    $params_base = ["d" => $deporte["id"]];
    if ($liga_id !== "") {
        $params_base["liga"] = $liga_id;
    }

    $stmt = $conexion->prepare("SELECT COUNT(*) FROM eventos WHERE eventos.deporte_id = :d" . $filtro_liga);
    $stmt->execute($params_base);
    $total_eventos = (int)$stmt->fetchColumn();

    if ((int)$deporte["requiere_equipo"] === 1) {
        $sql = "SELECT equipos.id, equipos.nombre, equipos.logo,
                       COUNT(t.equipo_id) AS pj,
                       COALESCE(SUM(t.gf > t.gc), 0) AS g,
                       COALESCE(SUM(t.gf = t.gc), 0) AS e,
                       COALESCE(SUM(t.gf < t.gc), 0) AS p,
                       COALESCE(SUM(t.gf), 0) AS gf,
                       COALESCE(SUM(t.gc), 0) AS gc
                FROM equipos
                JOIN (
                    SELECT eventos.equipo_local_id AS equipo_id, eventos.marcador_local AS gf, eventos.marcador_visitante AS gc
                    FROM eventos
                    WHERE eventos.deporte_id = :d AND eventos.marcador_local IS NOT NULL
                          AND eventos.equipo_local_id IS NOT NULL" . $filtro_liga . "
                    UNION ALL
                    SELECT eventos.equipo_visitante_id, eventos.marcador_visitante, eventos.marcador_local
                    FROM eventos
                    WHERE eventos.deporte_id = :d2 AND eventos.marcador_local IS NOT NULL
                          AND eventos.equipo_visitante_id IS NOT NULL" . str_replace(":liga", ":liga2", $filtro_liga) . "
                ) AS t ON t.equipo_id = equipos.id
                GROUP BY equipos.id, equipos.nombre, equipos.logo";

        $params = ["d" => $deporte["id"], "d2" => $deporte["id"]];
        if ($liga_id !== "") {
            $params["liga"] = $liga_id;
            $params["liga2"] = $liga_id;
        }
        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
        $posiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posiciones as &$fila) {
            $fila["dg"] = $fila["gf"] - $fila["gc"];

            $fila["pts"] = (int)$deporte["puntos_victoria"] * $fila["g"]
                         + (int)$deporte["puntos_empate"] * $fila["e"]
                         + (int)$deporte["puntos_derrota"] * $fila["p"];
        }
        unset($fila);

        usort($posiciones, function ($a, $b) {
            return [$b["pts"], $b["dg"], $b["gf"], $a["nombre"]] <=> [$a["pts"], $a["dg"], $a["gf"], $b["nombre"]];
        });
    }

    $stmt = $conexion->prepare("SELECT id, nombre, unidad, menor_es_mejor FROM tipos_estadistica WHERE deporte_id = :d ORDER BY nombre ASC");
    $stmt->execute(["d" => $deporte["id"]]);
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tipos as $t) {
        $menor = (int)$t["menor_es_mejor"] === 1;
        $agregado = $menor ? "MIN" : "SUM";
        $orden = $menor ? "ASC" : "DESC";

        $join_eventos = $liga_id !== ""
            ? "JOIN eventos ON eventos.id = estadisticas_jugador.evento_id"
            : "";

        $sql_lider = "SELECT agregado.jugador_id AS id, agregado.nombre,
                             equipos.nombre AS equipo,
                             agregado.total, agregado.promedio, agregado.eventos
                      FROM (
                          SELECT estadisticas_jugador.jugador_id,
                                 jugadores.nombre,
                                 jugadores.equipo_id,
                                 {$agregado}(estadisticas_jugador.valor) AS total,
                                 AVG(estadisticas_jugador.valor) AS promedio,
                                 COUNT(DISTINCT estadisticas_jugador.evento_id) AS eventos
                          FROM estadisticas_jugador
                          JOIN jugadores ON jugadores.id = estadisticas_jugador.jugador_id
                          {$join_eventos}
                          WHERE estadisticas_jugador.tipo_estadistica_id = :t" . $filtro_liga . "
                          GROUP BY estadisticas_jugador.jugador_id, jugadores.nombre, jugadores.equipo_id
                          HAVING {$agregado}(estadisticas_jugador.valor) > 0
                          ORDER BY total {$orden}, jugadores.nombre ASC
                          LIMIT 5
                      ) AS agregado
                      LEFT JOIN equipos ON equipos.id = agregado.equipo_id
                      ORDER BY agregado.total {$orden}, agregado.nombre ASC";

        $params = ["t" => $t["id"]];
        if ($liga_id !== "") {
            $params["liga"] = $liga_id;
        }
        $stmt_lider = $conexion->prepare($sql_lider);
        $stmt_lider->execute($params);
        $lideres[] = ["tipo" => $t, "filas" => $stmt_lider->fetchAll(PDO::FETCH_ASSOC)];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Rankings</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/rankings.css?v=9">
</head>
<body>

    <div class="rankings-contenedor">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Rankings de rendimiento</h1>
        <p class="subtitulo">Mejores equipos y mejores jugadores según los datos registrados</p>

        <?php if (count($deportes) === 0): ?>
            <p class="vacio">
                Todavía no hay deportes registrados.
                <a href="deportes.php">Agrega uno primero</a>.
            </p>
        <?php else: ?>

            <form method="GET" action="rankings.php" class="form-rankings">
                <div class="campo-doble">
                    <div class="campo">
                        <label for="deporte_id">Deporte</label>
                        <select id="deporte_id" name="deporte_id"
                                onchange="document.getElementById('liga_id').value=''; this.form.submit();">
                            <?php foreach ($deportes as $d): ?>
                                <option value="<?php echo (int)$d['id']; ?>"
                                    <?php echo ((string)$d['id'] === (string)$deporte_id) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars(trim(($d['icono'] ?? "") . " " . $d['nombre'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="liga_id">Liga</label>
                        <select id="liga_id" name="liga_id" onchange="this.form.submit()">
                            <option value="">Todas las ligas y eventos</option>
                            <?php foreach ($ligas as $l): ?>
                                <option value="<?php echo (int)$l['id']; ?>"
                                    <?php echo ((string)$l['id'] === (string)$liga_id) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($l['nombre'] . ($l['temporada'] ? " (" . $l['temporada'] . ")" : "")); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <noscript><button type="submit" class="btn-agregar">Aplicar</button></noscript>
            </form>

            <p class="resumen">
                Basado en <strong><?php echo $total_eventos; ?></strong>
                <?php echo $total_eventos === 1 ? "evento" : "eventos"; ?>.
            </p>

            <?php if ($total_eventos === 0): ?>
                <p class="vacio">
                    No hay eventos para este filtro.
                    <a href="eventos.php">Registra uno</a>.
                </p>
            <?php else: ?>

                <?php if ((int)$deporte["requiere_equipo"] === 1): ?>
                    <h2>Tabla de posiciones</h2>

                    <?php if (count($posiciones) === 0): ?>
                        <p class="vacio">Todavía no hay partidos con marcador.</p>
                    <?php else: ?>
                        <div class="tabla-scroll">
                            <table class="tabla-rankings">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Equipo</th>
                                        <th title="Partidos jugados">PJ</th>
                                        <th title="Ganados">G</th>
                                        <th title="Empatados">E</th>
                                        <th title="Perdidos">P</th>
                                        <th title="A favor">GF</th>
                                        <th title="En contra">GC</th>
                                        <th title="Diferencia">DG</th>
                                        <?php
                                            $regla = (int)$deporte["puntos_victoria"] . " por victoria, "
                                                   . (int)$deporte["puntos_derrota"] . " por derrota";
                                            if ((int)$deporte["admite_empate"] === 1) {
                                                $regla = (int)$deporte["puntos_victoria"] . " por victoria, "
                                                       . (int)$deporte["puntos_empate"] . " por empate, "
                                                       . (int)$deporte["puntos_derrota"] . " por derrota";
                                            }
                                        ?>
                                        <th title="Puntos: <?php echo htmlspecialchars($regla); ?>">Pts</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($posiciones as $i => $f): ?>
                                        <tr>
                                            <td class="pos"><?php echo $i + 1; ?></td>
                                            <td class="equipo">
                                                <span class="celda-equipo">
                                                    <span class="escudo">
                                                        <?php if (!empty($f["logo"])): ?>
                                                            <?php $src = str_starts_with($f["logo"], "http") ? $f["logo"] : "../" . $f["logo"]; ?>
                                                            <img src="<?php echo htmlspecialchars($src); ?>" alt=""
                                                                 class="logo-mini" width="26" height="26">
                                                        <?php else: ?>
                                                            <span class="escudo-vacio"><?php echo htmlspecialchars(iniciales($f["nombre"])); ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <?php echo htmlspecialchars($f["nombre"]); ?>
                                                </span>
                                            </td>
                                            <td><?php echo (int)$f["pj"]; ?></td>
                                            <td><?php echo (int)$f["g"]; ?></td>
                                            <td><?php echo (int)$f["e"]; ?></td>
                                            <td><?php echo (int)$f["p"]; ?></td>
                                            <td><?php echo (int)$f["gf"]; ?></td>
                                            <td><?php echo (int)$f["gc"]; ?></td>
                                            <td><?php echo $f["dg"] > 0 ? "+" . (int)$f["dg"] : (int)$f["dg"]; ?></td>
                                            <td class="pts"><?php echo (int)$f["pts"]; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="ayuda">
                            Puntos en <?php echo htmlspecialchars($deporte["nombre"]); ?>:
                            <?php echo htmlspecialchars($regla); ?>.
                            <?php if ((int)$deporte["admite_empate"] === 0): ?>
                                En esta disciplina no existe el empate.
                            <?php endif; ?>
                            Desempate por diferencia de anotación y luego anotación a favor.
                            Cada disciplina define su propio sistema desde
                            <a href="deportes.php">Deportes</a>.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>

                <h2>Líderes por estadística</h2>

                <?php if (count($lideres) === 0): ?>
                    <p class="vacio">
                        Este deporte todavía no tiene estadísticas definidas.
                        <a href="tipos_estadisticas.php">Defínelas</a>.
                    </p>
                <?php else: ?>
                    <div class="rejilla-lideres">
                        <?php foreach ($lideres as $bloque): ?>
                            <div class="tarjeta-lider">
                                <?php $menor = (int)$bloque["tipo"]["menor_es_mejor"] === 1; ?>
                                <h3>
                                    <?php echo htmlspecialchars($bloque["tipo"]["nombre"]); ?>
                                    <?php if (!empty($bloque["tipo"]["unidad"])): ?>
                                        <span class="unidad">(<?php echo htmlspecialchars($bloque["tipo"]["unidad"]); ?>)</span>
                                    <?php endif; ?>
                                    <?php if ($menor): ?>
                                        <span class="etiqueta-menor">mejor marca</span>
                                    <?php endif; ?>
                                </h3>

                                <?php if (count($bloque["filas"]) === 0): ?>
                                    <p class="sin-datos">Sin datos todavía.</p>
                                <?php else: ?>
                                    <ol class="lista-lideres">
                                        <?php foreach ($bloque["filas"] as $f): ?>
                                            <li>
                                                <span class="nombre">
                                                    <?php echo htmlspecialchars($f["nombre"]); ?>
                                                    <?php if (!empty($f["equipo"])): ?>
                                                        <span class="unidad"><?php echo htmlspecialchars($f["equipo"]); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="valor">
                                                    <strong><?php echo numero($f["total"]); ?></strong>
                                                    <span class="unidad">prom. <?php echo numero(round($f["promedio"], 1)); ?> · <?php echo (int)$f["eventos"]; ?> ev.</span>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="ayuda">
                        Por defecto se ordena por el total acumulado, de mayor a menor.
                        Las estadísticas marcadas como <strong>mejor marca</strong> (tiempos y posiciones)
                        muestran el mejor registro del jugador y se ordenan de menor a mayor,
                        porque ahí el valor más bajo es el mejor.
                        "prom." es el promedio por evento y "ev." la cantidad de eventos con ese dato.
                    </p>
                <?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>

    </div>

</body>
</html>