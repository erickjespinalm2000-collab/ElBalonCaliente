<?php
session_start();
require_once "../Php/conexion.php";
require_once "../Php/paginacion.php";
require_once "../Php/permisos.php";

if (!isset($_SESSION["usuario_id"], $_SESSION["nombre_usuario"], $_SESSION["rol"])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}

function lps($v): string
{
    return "L " . number_format((float)$v, 0, ".", ",");
}

function pct($v): string
{
    return number_format((float)$v, 1, ".", ",") . "%";
}

function numero($v): string
{
    return rtrim(rtrim(number_format((float)$v, 2, ".", ""), "0"), ".");
}

$mensaje_exito = ($_GET["exito"] ?? "") === "guardado" ? "Supuestos comerciales actualizados." : "";

$conexion->exec(
    "INSERT IGNORE INTO parametros_comerciales
            (deporte_id, aforo, ocupacion, precio_boleto, patrocinio_evento, valor_base_jugador)
     SELECT id, 1000, 50.00, 80.00, 8000.00, 200000.00 FROM deportes"
);

$deportes = $conexion->query("SELECT id, nombre, icono, requiere_equipo FROM deportes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

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
$tipos = [];
$param = null;
$tipo_principal = null;
$menor_es_mejor = false;
$es_individual = false;
$n_eventos = 0;
$asistencia_evento = 0;
$asistencia_total = 0;
$boleteria = 0;
$patrocinio = 0;
$ingreso_total = 0;
$activos = [];
$planteles = [];
$valor_total = 0;
$disciplinas = [];
$total_eventos_global = 0;
$total_ingreso_global = 0;
$pag = null;

if ($deporte) {

    $es_individual = (int)$deporte["requiere_equipo"] === 0;

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
    $pl = $liga_id !== "" ? ["liga" => $liga_id] : [];

    $stmt = $conexion->prepare("SELECT * FROM parametros_comerciales WHERE deporte_id = :d");
    $stmt->execute(["d" => $deporte["id"]]);
    $param = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $conexion->prepare("SELECT id, nombre, unidad, menor_es_mejor FROM tipos_estadistica WHERE deporte_id = :d ORDER BY nombre ASC");
    $stmt->execute(["d" => $deporte["id"]]);
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tipos as $t) {
        if ((string)$t["id"] === (string)$param["tipo_principal_id"]) {
            $tipo_principal = $t;
            $menor_es_mejor = (int)$t["menor_es_mejor"] === 1;
        }
    }

    // ---------------- Ingresos por boletería ----------------
    //
    // Hay dos formas de calcularlos y el sistema usa la mejor que tenga
    // para cada evento:
    //
    //   1. Si el evento tiene sede asignada y esa sede tiene sectores,
    //      se suma sector por sector: capacidad x precio propio. Es el
    //      dato real, porque un palco no cuesta lo mismo que la general.
    //
    //   2. Si no tiene sede, se cae al supuesto de siempre: un aforo y
    //      un precio únicos para toda la disciplina.
    //
    // La ocupación promedio se aplica igual en los dos casos.

    $ocupacion = (float)$param["ocupacion"] / 100;
    $asistencia_estimada = (int)round($param["aforo"] * $ocupacion);

    $stmt = $conexion->prepare(
        "SELECT eventos.id,
                COALESCE(SUM(sectores.capacidad), 0)                   AS aforo,
                COALESCE(SUM(sectores.capacidad * sectores.precio), 0) AS lleno
         FROM eventos
         LEFT JOIN sectores ON sectores.localidad_id = eventos.localidad_id
         WHERE eventos.deporte_id = :d" . $filtro_liga . "
         GROUP BY eventos.id"
    );
    $stmt->execute(["d" => $deporte["id"]] + $pl);
    $filas_evento = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $n_eventos = count($filas_evento);
    $asistencia_total = 0;
    $boleteria = 0;
    $eventos_con_sectores = 0;

    foreach ($filas_evento as $fe) {
        if ((int)$fe["aforo"] > 0) {
            $eventos_con_sectores++;
            $asistencia_total += (int)round((int)$fe["aforo"] * $ocupacion);
            $boleteria += (float)$fe["lleno"] * $ocupacion;
        } else {
            $asistencia_total += $asistencia_estimada;
            $boleteria += $asistencia_estimada * (float)$param["precio_boleto"];
        }
    }

    $eventos_sin_sectores = $n_eventos - $eventos_con_sectores;
    $asistencia_evento = $n_eventos > 0 ? (int)round($asistencia_total / $n_eventos) : 0;

    $patrocinio = $n_eventos * (float)$param["patrocinio_evento"];
    $ingreso_total = $boleteria + $patrocinio;

    // Desglose por sector, para ver de dónde sale cada lempira
    $stmt = $conexion->prepare(
        "SELECT localidades.nombre AS sede, sectores.nombre AS sector,
                sectores.capacidad, sectores.precio,
                COUNT(eventos.id) AS eventos,
                SUM(sectores.capacidad * sectores.precio) AS lleno
         FROM eventos
         JOIN localidades ON localidades.id = eventos.localidad_id
         JOIN sectores    ON sectores.localidad_id = eventos.localidad_id
         WHERE eventos.deporte_id = :d" . $filtro_liga . "
         GROUP BY sectores.id
         ORDER BY lleno DESC"
    );
    $stmt->execute(["d" => $deporte["id"]] + $pl);
    $desglose_sectores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conexion->prepare(
        "SELECT jugadores.id, jugadores.nombre, jugadores.posicion, equipos.nombre AS equipo
         FROM jugadores
         LEFT JOIN equipos ON equipos.id = jugadores.equipo_id
         WHERE jugadores.deporte_id = :d
         ORDER BY jugadores.nombre ASC"
    );
    $stmt->execute(["d" => $deporte["id"]]);
    $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conexion->prepare(
        "SELECT estadisticas_jugador.jugador_id, COUNT(DISTINCT estadisticas_jugador.evento_id) AS eventos
         FROM estadisticas_jugador
         JOIN eventos ON eventos.id = estadisticas_jugador.evento_id
         WHERE eventos.deporte_id = :d" . $filtro_liga . "
         GROUP BY estadisticas_jugador.jugador_id"
    );
    $stmt->execute(["d" => $deporte["id"]] + $pl);
    $participacion = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $participacion[(int)$f["jugador_id"]] = (int)$f["eventos"];
    }

    $produccion = [];
    if ($tipo_principal !== null) {
        $agregado = $menor_es_mejor ? "MIN" : "SUM";
        $join_eventos = $liga_id !== ""
            ? "JOIN eventos ON eventos.id = estadisticas_jugador.evento_id"
            : "";
        $stmt = $conexion->prepare(
            "SELECT estadisticas_jugador.jugador_id, {$agregado}(estadisticas_jugador.valor) AS valor
             FROM estadisticas_jugador
             {$join_eventos}
             WHERE estadisticas_jugador.tipo_estadistica_id = :t" . $filtro_liga . "
             GROUP BY estadisticas_jugador.jugador_id"
        );
        $stmt->execute(["t" => $tipo_principal["id"]] + $pl);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $produccion[(int)$f["jugador_id"]] = (float)$f["valor"];
        }
    }

    $id_amarillas = null;
    $id_rojas = null;
    foreach ($tipos as $t) {
        if (stripos($t["nombre"], "amarilla") !== false) {
            $id_amarillas = (int)$t["id"];
        }
        if (stripos($t["nombre"], "roja") !== false) {
            $id_rojas = (int)$t["id"];
        }
    }

    $tarjetas = [];
    $ids_tarjeta = array_values(array_filter([$id_amarillas, $id_rojas]));
    if (count($ids_tarjeta) > 0) {
        $marcas = [];
        $params = ["d" => $deporte["id"]] + $pl;
        foreach ($ids_tarjeta as $i) {
            $marcas[] = ":t" . $i;
            $params["t" . $i] = $i;
        }
        $stmt = $conexion->prepare(
            "SELECT estadisticas_jugador.jugador_id,
                    estadisticas_jugador.tipo_estadistica_id AS tipo,
                    SUM(estadisticas_jugador.valor) AS total
             FROM estadisticas_jugador
             JOIN eventos ON eventos.id = estadisticas_jugador.evento_id
             WHERE eventos.deporte_id = :d" . $filtro_liga . "
                   AND estadisticas_jugador.tipo_estadistica_id IN (" . implode(",", $marcas) . ")
             GROUP BY estadisticas_jugador.jugador_id, estadisticas_jugador.tipo_estadistica_id"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $clave = (int)$f["tipo"] === $id_rojas ? "rojas" : "amarillas";
            $tarjetas[(int)$f["jugador_id"]][$clave] = (float)$f["total"];
        }
    }

    $grupo_de = [];
    foreach ($jugadores as $j) {
        $clave = "general";
        if ($es_individual) {
            $especialidad = trim((string)($j["posicion"] ?? ""));
            $clave = $especialidad !== "" ? $especialidad : "sin especialidad";
        }
        $grupo_de[(int)$j["id"]] = $clave;
    }

    $ref = [];
    foreach ($jugadores as $j) {
        $jid = (int)$j["id"];
        if (($participacion[$jid] ?? 0) === 0) {
            continue;
        }
        $g = $grupo_de[$jid];
        if (!isset($ref[$g])) {
            $ref[$g] = ["max_prod" => 0, "min_prod" => null, "max_part" => 0];
        }
        $v = $produccion[$jid] ?? 0;
        if ($v > $ref[$g]["max_prod"]) {
            $ref[$g]["max_prod"] = $v;
        }
        if ($v > 0 && ($ref[$g]["min_prod"] === null || $v < $ref[$g]["min_prod"])) {
            $ref[$g]["min_prod"] = $v;
        }
        if ($participacion[$jid] > $ref[$g]["max_part"]) {
            $ref[$g]["max_part"] = $participacion[$jid];
        }
    }

    foreach ($jugadores as $j) {
        $jid = (int)$j["id"];
        $eventos = $participacion[$jid] ?? 0;

        if ($eventos === 0) {
            continue;
        }

        $valor_principal = $produccion[$jid] ?? 0;
        $g = $grupo_de[$jid];

        if ($menor_es_mejor) {
            $min = $ref[$g]["min_prod"];
            $productividad = ($valor_principal > 0 && $min !== null && $min > 0)
                ? min(100, $min / $valor_principal * 100)
                : 0;
        } else {
            $max = $ref[$g]["max_prod"];
            $productividad = $max > 0 ? $valor_principal / $max * 100 : 0;
        }

        $participa = $ref[$g]["max_part"] > 0 ? $eventos / $ref[$g]["max_part"] * 100 : 0;

        $am = $tarjetas[$jid]["amarillas"] ?? 0;
        $ro = $tarjetas[$jid]["rojas"] ?? 0;
        $disciplina = max(0, 100 - min(100, $am * 5 + $ro * 20));

        $indice = 0.5 * $productividad + 0.3 * $participa + 0.2 * $disciplina;
        $valor = $indice / 100 * (float)$param["valor_base_jugador"];

        $activos[] = [
            "nombre" => $j["nombre"],
            "equipo" => $j["equipo"],
            "posicion" => $j["posicion"],
            "principal" => $valor_principal,
            "eventos" => $eventos,
            "productividad" => $productividad,
            "participacion" => $participa,
            "disciplina" => $disciplina,
            "indice" => $indice,
            "valor" => $valor,
        ];

        $valor_total += $valor;

        if (!empty($j["equipo"])) {
            if (!isset($planteles[$j["equipo"]])) {
                $planteles[$j["equipo"]] = ["jugadores" => 0, "valor" => 0, "indice" => 0];
            }
            $planteles[$j["equipo"]]["jugadores"]++;
            $planteles[$j["equipo"]]["valor"] += $valor;
            $planteles[$j["equipo"]]["indice"] += $indice;
        }
    }

    usort($activos, fn($a, $b) => [$b["indice"], $a["nombre"]] <=> [$a["indice"], $b["nombre"]]);

    foreach ($planteles as &$p) {
        $p["indice"] = $p["jugadores"] > 0 ? $p["indice"] / $p["jugadores"] : 0;
    }
    unset($p);
    uasort($planteles, fn($a, $b) => $b["valor"] <=> $a["valor"]);

    $pag = paginar_total(count($activos), 15);
    $activos_pagina = array_slice($activos, $pag["offset"], $pag["por_pagina"]);

    // Comparación entre disciplinas. Usa exactamente el mismo criterio que
    // los indicadores de arriba: sede con sectores donde la haya, supuesto
    // general donde no. Si aquí se usara solo el supuesto, la misma página
    // daría dos cifras distintas para la misma disciplina.
    $sql = "SELECT deportes.id, deportes.nombre, deportes.icono,
                   parametros_comerciales.aforo, parametros_comerciales.ocupacion,
                   parametros_comerciales.precio_boleto, parametros_comerciales.patrocinio_evento
            FROM deportes
            LEFT JOIN parametros_comerciales ON parametros_comerciales.deporte_id = deportes.id
            ORDER BY deportes.nombre ASC";

    // Los eventos de todas las disciplinas, con el aforo y la recaudación
    // a reventar de su sede. Una sola consulta en lugar de una por deporte.
    $por_deporte = [];
    $consulta_eventos = $conexion->query(
        "SELECT eventos.deporte_id,
                COALESCE(SUM(sectores.capacidad), 0)                   AS aforo,
                COALESCE(SUM(sectores.capacidad * sectores.precio), 0) AS lleno
         FROM eventos
         LEFT JOIN sectores ON sectores.localidad_id = eventos.localidad_id
         GROUP BY eventos.id, eventos.deporte_id"
    );
    foreach ($consulta_eventos->fetchAll(PDO::FETCH_ASSOC) as $e) {
        $por_deporte[(int)$e["deporte_id"]][] = $e;
    }

    foreach ($conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $f) {

        $suyos = $por_deporte[(int)$f["id"]] ?? [];
        $ev = count($suyos);

        $ocupacion_d = (float)($f["ocupacion"] ?? 0) / 100;
        $estimada = (int)round((float)($f["aforo"] ?? 0) * $ocupacion_d);

        $asist = 0;
        $bol = 0;

        foreach ($suyos as $e) {
            if ((int)$e["aforo"] > 0) {
                $asist += (int)round((int)$e["aforo"] * $ocupacion_d);
                $bol += (float)$e["lleno"] * $ocupacion_d;
            } else {
                $asist += $estimada;
                $bol += $estimada * (float)($f["precio_boleto"] ?? 0);
            }
        }

        $pat = $ev * (float)($f["patrocinio_evento"] ?? 0);

        $disciplinas[] = [
            "nombre" => $f["nombre"],
            "icono" => $f["icono"] ?? "",
            "eventos" => $ev,
            "asistencia" => $asist,
            "ingreso" => $bol + $pat,
            "boleteria" => $bol,
            "patrocinio" => $pat,
        ];
        $total_eventos_global += $ev;
        $total_ingreso_global += $bol + $pat;
    }

    usort($disciplinas, fn($a, $b) => $b["ingreso"] <=> $a["ingreso"]);
}

$filtros_url = ["deporte_id" => $deporte_id];
if ($liga_id !== "") {
    $filtros_url["liga_id"] = $liga_id;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Análisis comercial</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/comercial.css?v=9">
</head>
<body>

    <div class="comercial-contenedor">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Análisis comercial</h1>
        <p class="subtitulo">
            Los jugadores como activos y la proyección de boletería y patrocinios &middot; enfoque B2B
        </p>

        <?php if ($mensaje_exito !== ""): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <?php if (count($deportes) === 0): ?>
            <p class="vacio">
                Todavía no hay deportes registrados.
                <a href="deportes.php">Agrega uno primero</a>.
            </p>
        <?php else: ?>

            <form method="GET" action="comercial.php" class="form-filtros">
                <div class="campo-doble">
                    <div class="campo">
                        <label for="deporte_id">Disciplina</label>
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

            <h2>Proyección de ingresos</h2>

            <?php if ($n_eventos === 0): ?>
                <p class="vacio">
                    No hay eventos para este filtro.
                    <a href="eventos.php">Registra uno</a>.
                </p>
            <?php else: ?>
                <div class="rejilla-kpis">
                    <div class="kpi">
                        <span class="kpi-rotulo">Boletería</span>
                        <strong class="kpi-valor"><?php echo lps($boleteria); ?></strong>
                        <span class="kpi-nota"><?php echo number_format($asistencia_total, 0, ".", ","); ?> asistentes en <?php echo $n_eventos; ?> eventos</span>
                    </div>
                    <div class="kpi">
                        <span class="kpi-rotulo">Patrocinios</span>
                        <strong class="kpi-valor"><?php echo lps($patrocinio); ?></strong>
                        <span class="kpi-nota"><?php echo lps($param["patrocinio_evento"]); ?> por evento</span>
                    </div>
                    <div class="kpi kpi-total">
                        <span class="kpi-rotulo">Ingreso proyectado</span>
                        <strong class="kpi-valor"><?php echo lps($ingreso_total); ?></strong>
                        <span class="kpi-nota">
                            <?php echo $ingreso_total > 0 ? pct($boleteria / $ingreso_total * 100) : "0%"; ?> boletería &middot;
                            <?php echo $ingreso_total > 0 ? pct($patrocinio / $ingreso_total * 100) : "0%"; ?> patrocinio
                        </span>
                    </div>
                    <div class="kpi">
                        <span class="kpi-rotulo">Valor de los jugadores</span>
                        <strong class="kpi-valor"><?php echo lps($valor_total); ?></strong>
                        <span class="kpi-nota"><?php echo count($activos); ?> valorados</span>
                    </div>
                </div>
                <p class="ayuda">
                    <?php if ($eventos_con_sectores > 0): ?>
                        Cómo sale: de los <?php echo $n_eventos; ?> eventos,
                        <strong><?php echo $eventos_con_sectores; ?></strong> se juegan en una sede
                        con sectores, y ahí la boletería se suma sector por sector con su precio
                        propio.
                        <?php if ($eventos_sin_sectores > 0): ?>
                            Los <?php echo $eventos_sin_sectores; ?> restantes no tienen sede asignada,
                            así que usan el supuesto general:
                            <?php echo number_format($param["aforo"], 0, ".", ","); ?> de aforo a
                            <?php echo lps($param["precio_boleto"]); ?> la entrada.
                        <?php endif; ?>
                        Sobre todo se aplica <?php echo pct($param["ocupacion"]); ?> de ocupación.
                    <?php else: ?>
                        Cómo sale: <?php echo number_format($param["aforo"], 0, ".", ","); ?> de aforo &times;
                        <?php echo pct($param["ocupacion"]); ?> de ocupación =
                        <?php echo number_format($asistencia_evento, 0, ".", ","); ?> asistentes por evento &times;
                        <?php echo lps($param["precio_boleto"]); ?> de entrada &times;
                        <?php echo $n_eventos; ?> eventos.
                        <br>
                        Ninguno de estos eventos tiene sede con sectores. Asígnales una en
                        <a href="programacion.php">Programar eventos</a> y el cálculo pasará a usar
                        los precios reales de cada <a href="localidades.php">sector</a>.
                    <?php endif; ?>
                </p>

                <!-- ============ Desglose por sector ============ -->

                <?php if (count($desglose_sectores) > 0): ?>
                    <h2>De dónde sale la boletería</h2>
                    <p class="subtitulo-seccion">
                        Cada sector aporta según su capacidad y su propio precio.
                        Las cifras ya llevan aplicada la ocupación de
                        <?php echo pct($param["ocupacion"]); ?>.
                    </p>

                    <div class="tabla-scroll">
                        <table class="tabla-comercial">
                            <thead>
                                <tr>
                                    <th>Sede</th>
                                    <th>Sector</th>
                                    <th>Capacidad</th>
                                    <th class="dinero">Precio</th>
                                    <th>Eventos</th>
                                    <th class="dinero">Asistencia</th>
                                    <th class="dinero">Aporta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $suma_sectores = 0; ?>
                                <?php foreach ($desglose_sectores as $s): ?>
                                    <?php
                                        $aporta = (float)$s["lleno"] * $ocupacion;
                                        $suma_sectores += $aporta;
                                        $asistentes = (int)round((int)$s["capacidad"] * $ocupacion) * (int)$s["eventos"];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($s["sede"]); ?></td>
                                        <td><strong><?php echo htmlspecialchars($s["sector"]); ?></strong></td>
                                        <td><?php echo number_format((int)$s["capacidad"], 0, ".", ","); ?></td>
                                        <td class="dinero"><?php echo lps($s["precio"]); ?></td>
                                        <td><?php echo (int)$s["eventos"]; ?></td>
                                        <td class="dinero"><?php echo number_format($asistentes, 0, ".", ","); ?></td>
                                        <td class="dinero"><?php echo lps($aporta); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="fila-total">
                                    <td colspan="6"><strong>Total por sectores</strong></td>
                                    <td class="dinero"><strong><?php echo lps($suma_sectores); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <h2>Los jugadores como activos</h2>

            <?php if ($tipo_principal === null): ?>
                <p class="vacio">
                    Esta disciplina no tiene definida su <strong>estadística principal</strong>,
                    la que mide la productividad del jugador. Elígela abajo, en los supuestos.
                </p>
            <?php elseif (count($activos) === 0): ?>
                <p class="vacio">
                    Todavía no hay estadísticas capturadas para este filtro.
                    <a href="estadisticas.php">Captura algunas</a>.
                </p>
            <?php else: ?>
                <div class="tabla-scroll">
                    <table class="tabla-comercial">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Jugador</th>
                                <th><?php echo htmlspecialchars($tipo_principal["nombre"]); ?></th>
                                <th title="Eventos con datos registrados">Ev.</th>
                                <th title="50% del índice: su producción frente al mejor de su grupo">Producción</th>
                                <th title="30% del índice: cuántos eventos jugó frente al que más jugó">Participación</th>
                                <th title="20% del índice: penaliza amarillas y rojas">Disciplina</th>
                                <th title="Índice de rendimiento, de 0 a 100">Índice</th>
                                <th>Valor estimado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activos_pagina as $i => $a): ?>
                                <tr>
                                    <td class="pos"><?php echo $pag["offset"] + $i + 1; ?></td>
                                    <td class="jugador">
                                        <?php echo htmlspecialchars($a["nombre"]); ?>
                                        <?php $detalle = trim(($a["equipo"] ?? "") . ($a["posicion"] ? " · " . $a["posicion"] : ""), " ·"); ?>
                                        <?php if ($detalle !== ""): ?>
                                            <span class="detalle"><?php echo htmlspecialchars($detalle); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo numero($a["principal"]); ?></td>
                                    <td><?php echo (int)$a["eventos"]; ?></td>
                                    <td><?php echo pct($a["productividad"]); ?></td>
                                    <td><?php echo pct($a["participacion"]); ?></td>
                                    <td><?php echo pct($a["disciplina"]); ?></td>
                                    <td class="indice">
                                        <span class="barra"><span class="barra-relleno" style="width: <?php echo round($a["indice"]); ?>%"></span></span>
                                        <?php echo number_format($a["indice"], 1, ".", ""); ?>
                                    </td>
                                    <td class="dinero"><?php echo lps($a["valor"]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php echo navegacion_paginas($pag, "comercial.php", $filtros_url); ?>

                <p class="ayuda">
                    Índice = 50% producción + 30% participación + 20% disciplina.
                    Cada componente se compara contra el mejor de su grupo, así que el índice va
                    de 0 a 100 y solo tiene sentido comparar jugadores de la misma disciplina.
                    El valor estimado es ese índice aplicado al valor base de
                    <?php echo lps($param["valor_base_jugador"]); ?>.
                    <?php if ($menor_es_mejor): ?>
                        Aquí la estadística principal es un tiempo, así que la producción se mide
                        contra la mejor marca: cuanto más bajo el tiempo, más alta la producción.
                    <?php endif; ?>
                    <?php if ($es_individual): ?>
                        Como es un deporte individual, cada atleta se compara solo contra los de su
                        misma especialidad, porque un tiempo de 100m y uno de 200m no son comparables.
                    <?php endif; ?>
                </p>

                <?php if (count($planteles) > 0): ?>
                    <h3>Valor por plantel</h3>
                    <div class="tabla-scroll">
                        <table class="tabla-comercial">
                            <thead>
                                <tr>
                                    <th>Equipo</th>
                                    <th>Jugadores</th>
                                    <th>Índice promedio</th>
                                    <th>Valor del plantel</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($planteles as $nombre => $p): ?>
                                    <tr>
                                        <td class="jugador"><?php echo htmlspecialchars($nombre); ?></td>
                                        <td><?php echo (int)$p["jugadores"]; ?></td>
                                        <td><?php echo number_format($p["indice"], 1, ".", ""); ?></td>
                                        <td class="dinero"><?php echo lps($p["valor"]); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <h2>Popularidad y reparto por disciplina</h2>
            <p class="subtitulo-seccion">
                Esta comparación no usa el filtro de arriba: enfrenta todas las disciplinas entre sí,
                que es lo que se necesita para decidir dónde conviene invertir.
            </p>

            <?php if ($total_eventos_global === 0): ?>
                <p class="vacio">Todavía no hay eventos registrados en ninguna disciplina.</p>
            <?php else: ?>
                <div class="tabla-scroll">
                    <table class="tabla-comercial">
                        <thead>
                            <tr>
                                <th>Disciplina</th>
                                <th>Eventos</th>
                                <th title="Qué parte de la actividad total representa">Popularidad</th>
                                <th>Asistencia</th>
                                <th>Boletería</th>
                                <th>Patrocinios</th>
                                <th>Ingreso</th>
                                <th title="Qué parte del ingreso total aporta">Aporte</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disciplinas as $x): ?>
                                <?php $pop = $total_eventos_global > 0 ? $x["eventos"] / $total_eventos_global * 100 : 0; ?>
                                <tr>
                                    <td class="jugador"><?php echo htmlspecialchars(trim($x["icono"] . " " . $x["nombre"])); ?></td>
                                    <td><?php echo $x["eventos"]; ?></td>
                                    <td class="indice">
                                        <span class="barra"><span class="barra-relleno" style="width: <?php echo round($pop); ?>%"></span></span>
                                        <?php echo pct($pop); ?>
                                    </td>
                                    <td><?php echo number_format($x["asistencia"], 0, ".", ","); ?></td>
                                    <td class="dinero"><?php echo lps($x["boleteria"]); ?></td>
                                    <td class="dinero"><?php echo lps($x["patrocinio"]); ?></td>
                                    <td class="dinero"><?php echo lps($x["ingreso"]); ?></td>
                                    <td><?php echo $total_ingreso_global > 0 ? pct($x["ingreso"] / $total_ingreso_global * 100) : "0%"; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="jugador">Total</td>
                                <td><?php echo $total_eventos_global; ?></td>
                                <td>100%</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="dinero"><?php echo lps($total_ingreso_global); ?></td>
                                <td>100%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>

            <!-- ================= Supuestos ================= -->

            <h2>Supuestos de <?php echo htmlspecialchars($deporte["nombre"]); ?></h2>

            <?php if (!es_admin()): ?>
                <div class="tabla-scroll">
                    <table class="tabla-comercial">
                        <thead>
                            <tr>
                                <th>Estadística principal</th>
                                <th>Aforo</th>
                                <th>Ocupación</th>
                                <th>Entrada</th>
                                <th>Patrocinio por evento</th>
                                <th>Valor base del jugador</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="jugador"><?php echo htmlspecialchars($tipo_principal["nombre"] ?? "Sin definir"); ?></td>
                                <td><?php echo number_format($param["aforo"], 0, ".", ","); ?></td>
                                <td><?php echo pct($param["ocupacion"]); ?></td>
                                <td class="dinero"><?php echo lps($param["precio_boleto"]); ?></td>
                                <td class="dinero"><?php echo lps($param["patrocinio_evento"]); ?></td>
                                <td class="dinero"><?php echo lps($param["valor_base_jugador"]); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="ayuda">Solo un administrador puede modificar estos supuestos.</p>
            <?php else: ?>

            <form action="../Php/comercial_guardar.php" method="POST" class="form-parametros">
                <input type="hidden" name="deporte_id" value="<?php echo (int)$deporte["id"]; ?>">
                <input type="hidden" name="liga_id" value="<?php echo htmlspecialchars($liga_id); ?>">

                <div class="campo-doble">
                    <div class="campo">
                        <label for="tipo_principal_id">Estadística principal</label>
                        <select id="tipo_principal_id" name="tipo_principal_id">
                            <option value="">-- Sin definir --</option>
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?php echo (int)$t['id']; ?>"
                                    <?php echo ((string)$t['id'] === (string)$param["tipo_principal_id"]) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($t['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="ayuda-campo">La que mide la productividad: goles, puntos, tiempo.</p>
                    </div>
                    <div class="campo">
                        <label for="valor_base_jugador">Valor base de un jugador (L)</label>
                        <input type="number" id="valor_base_jugador" name="valor_base_jugador" min="0" step="1000"
                               value="<?php echo (int)$param["valor_base_jugador"]; ?>" required>
                        <p class="ayuda-campo">Lo que valdría un jugador con índice 100.</p>
                    </div>
                </div>

                <div class="campo-doble">
                    <div class="campo">
                        <label for="ocupacion">Ocupación promedio (%)</label>
                        <input type="number" id="ocupacion" name="ocupacion" min="0" max="100" step="0.5"
                               value="<?php echo numero($param["ocupacion"]); ?>" required>
                        <p class="ayuda-campo">
                            Se aplica <strong>siempre</strong>, tanto a las sedes con sectores
                            como a los eventos sin sede.
                        </p>
                    </div>
                    <div class="campo">
                        <label for="patrocinio_evento">Patrocinio por evento (L)</label>
                        <input type="number" id="patrocinio_evento" name="patrocinio_evento" min="0" step="500"
                               value="<?php echo numero($param["patrocinio_evento"]); ?>" required>
                        <p class="ayuda-campo">Se aplica siempre, por cada evento.</p>
                    </div>
                </div>

                <div class="bloque-reserva">
                    <p class="reserva-titulo">
                        Solo para eventos sin sede asignada
                        <?php if ($eventos_sin_sectores === 0): ?>
                            <span class="etiqueta-apagada">· ahora mismo no se usan</span>
                        <?php else: ?>
                            <span class="etiqueta-activa">· afectan a <?php echo $eventos_sin_sectores; ?> evento(s)</span>
                        <?php endif; ?>
                    </p>
                    <p class="ayuda-campo">
                        Cuando un evento se juega en una
                        <a href="localidades.php">localidad con sectores</a>, la boletería sale
                        de la capacidad y el precio de cada sector, y estos dos campos se
                        ignoran. Sirven de reserva para los eventos que todavía no tienen sede.
                    </p>

                    <div class="campo-doble">
                        <div class="campo">
                            <label for="aforo">Aforo supuesto</label>
                            <input type="number" id="aforo" name="aforo" min="0" step="50"
                                   value="<?php echo (int)$param["aforo"]; ?>" required>
                        </div>
                        <div class="campo">
                            <label for="precio_boleto">Precio supuesto de la entrada (L)</label>
                            <input type="number" id="precio_boleto" name="precio_boleto" min="0" step="5"
                                   value="<?php echo numero($param["precio_boleto"]); ?>" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-agregar">Guardar supuestos</button>
            </form>

            <?php endif; ?>

            <p class="ayuda aviso-supuestos">
                Estos valores son <strong>supuestos</strong>, no datos medidos: el sistema registra
                rendimiento deportivo, no taquilla. Ajústalos con las cifras que acuerdes con tu
                cliente y decláralos como supuestos en la documentación. Si más adelante quieres
                proyecciones basadas en datos reales, el paso siguiente sería registrar la asistencia
                y el precio de cada evento, en lugar de estimarlos por disciplina.
            </p>

        <?php endif; ?>

    </div>

</body>
</html>
