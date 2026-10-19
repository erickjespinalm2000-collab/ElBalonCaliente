<?php
session_start();
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION["usuario_id"])) {
    responder(401, ["error" => "sesion"]);
}

require_once "conexion.php";

$deporte_id = $_GET["deporte_id"] ?? "";
$liga_id = $_GET["liga_id"] ?? "";

if (!ctype_digit((string)$deporte_id)) {
    responder(400, ["error" => "deporte"]);
}

$stmt = $conexion->prepare(
    "SELECT id, nombre, requiere_equipo,
            puntos_victoria, puntos_empate, puntos_derrota, admite_empate
     FROM deportes WHERE id = :id"
);
$stmt->execute(["id" => $deporte_id]);
$deporte = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$deporte) {
    responder(404, ["error" => "deporte"]);
}

$liga = null;
if ($liga_id !== "" && ctype_digit((string)$liga_id)) {
    $stmt = $conexion->prepare("SELECT id FROM ligas WHERE id = :l AND deporte_id = :d");
    $stmt->execute(["l" => $liga_id, "d" => $deporte["id"]]);
    if ($stmt->fetch()) {
        $liga = $liga_id;
    }
}

$fl = $liga !== null ? " AND eventos.liga_id = :liga" : "";  
$pl = $liga !== null ? ["liga" => $liga] : [];                
$es_equipos = (int)$deporte["requiere_equipo"] === 1;

$stmt = $conexion->prepare("SELECT COUNT(*) FROM eventos WHERE eventos.deporte_id = :d" . $fl);
$stmt->execute(["d" => $deporte["id"]] + $pl);
$n_eventos = (int)$stmt->fetchColumn();

$stmt = $conexion->prepare("SELECT COUNT(*) FROM equipos WHERE deporte_id = :d");
$stmt->execute(["d" => $deporte["id"]]);
$n_equipos = (int)$stmt->fetchColumn();

$stmt = $conexion->prepare("SELECT COUNT(*) FROM jugadores WHERE deporte_id = :d");
$stmt->execute(["d" => $deporte["id"]]);
$n_jugadores = (int)$stmt->fetchColumn();

$stmt = $conexion->prepare(
    "SELECT COUNT(*) FROM estadisticas_jugador
     JOIN eventos ON eventos.id = estadisticas_jugador.evento_id
     WHERE eventos.deporte_id = :d" . $fl
);
$stmt->execute(["d" => $deporte["id"]] + $pl);
$n_registros = (int)$stmt->fetchColumn();

$equipos = [];
$resultados = ["local" => 0, "empate" => 0, "visitante" => 0];
$tendencia = [];

if ($es_equipos) {
    $sql = "SELECT equipos.nombre,
                   COUNT(t.equipo_id) AS pj,
                   COALESCE(SUM(t.gf > t.gc), 0) AS g,
                   COALESCE(SUM(t.gf = t.gc), 0) AS e,
                   COALESCE(SUM(t.gf), 0) AS gf,
                   COALESCE(SUM(t.gc), 0) AS gc
            FROM equipos
            JOIN (
                SELECT eventos.equipo_local_id AS equipo_id, eventos.marcador_local AS gf, eventos.marcador_visitante AS gc
                FROM eventos
                WHERE eventos.deporte_id = :d AND eventos.marcador_local IS NOT NULL
                      AND eventos.equipo_local_id IS NOT NULL" . $fl . "
                UNION ALL
                SELECT eventos.equipo_visitante_id, eventos.marcador_visitante, eventos.marcador_local
                FROM eventos
                WHERE eventos.deporte_id = :d2 AND eventos.marcador_local IS NOT NULL
                      AND eventos.equipo_visitante_id IS NOT NULL" . str_replace(":liga", ":liga2", $fl) . "
            ) AS t ON t.equipo_id = equipos.id
            GROUP BY equipos.id, equipos.nombre";

    $params = ["d" => $deporte["id"], "d2" => $deporte["id"]];
    if ($liga !== null) {
        $params["liga"] = $liga;
        $params["liga2"] = $liga;
    }
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $equipos[] = [
            "nombre" => $f["nombre"],
            "pj" => (int)$f["pj"],
            "gf" => (int)$f["gf"],
            "gc" => (int)$f["gc"],
            // Mismo sistema configurado por disciplina que usa rankings.php
            "pts" => (int)$deporte["puntos_victoria"] * (int)$f["g"]
                   + (int)$deporte["puntos_empate"] * (int)$f["e"]
                   + (int)$deporte["puntos_derrota"] * ((int)$f["pj"] - (int)$f["g"] - (int)$f["e"]),
        ];
    }
    usort($equipos, function ($a, $b) {
        return [$b["pts"], $b["gf"] - $b["gc"], $a["nombre"]] <=> [$a["pts"], $a["gf"] - $a["gc"], $b["nombre"]];
    });

    $stmt = $conexion->prepare(
        "SELECT COALESCE(SUM(marcador_local > marcador_visitante), 0) AS l,
                COALESCE(SUM(marcador_local = marcador_visitante), 0) AS e,
                COALESCE(SUM(marcador_local < marcador_visitante), 0) AS v
         FROM eventos
         WHERE eventos.deporte_id = :d AND marcador_local IS NOT NULL AND marcador_visitante IS NOT NULL" . $fl
    );
    $stmt->execute(["d" => $deporte["id"]] + $pl);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $resultados = ["local" => (int)$r["l"], "empate" => (int)$r["e"], "visitante" => (int)$r["v"]];

    $stmt = $conexion->prepare(
        "SELECT DATE(eventos.fecha) AS dia, SUM(marcador_local + marcador_visitante) AS total
         FROM eventos
         WHERE eventos.deporte_id = :d AND marcador_local IS NOT NULL AND marcador_visitante IS NOT NULL" . $fl . "
         GROUP BY DATE(eventos.fecha)
         ORDER BY dia ASC"
    );
    $stmt->execute(["d" => $deporte["id"]] + $pl);
    foreach (array_slice($stmt->fetchAll(PDO::FETCH_ASSOC), -30) as $f) {
        $tendencia[] = ["dia" => date("d/m/Y", strtotime($f["dia"])), "total" => (int)$f["total"]];
    }
}

$stmt = $conexion->prepare("SELECT id, nombre, unidad, menor_es_mejor FROM tipos_estadistica WHERE deporte_id = :d ORDER BY nombre ASC");
$stmt->execute(["d" => $deporte["id"]]);
$tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lideres = [];
foreach ($tipos as $t) {
    // En tiempos y posiciones gana el valor más bajo: se muestra la mejor
    // marca de cada jugador (el mínimo) y se ordena de menor a mayor.
    $menor = (int)$t["menor_es_mejor"] === 1;
    $agregado = $menor ? "MIN" : "SUM";
    $orden = $menor ? "ASC" : "DESC";

    // Mismo criterio de rendimiento que en rankings.php: se agrupa primero
    // sobre la tabla grande y despues se buscan los nombres. Esta consulta se
    // repite una vez por estadistica y el dashboard se refresca cada 15 s,
    // asi que es la que mas se beneficia.
    $join_eventos = $liga !== null
        ? "JOIN eventos ON eventos.id = estadisticas_jugador.evento_id"
        : "";

    $stmt_lider = $conexion->prepare(
        "SELECT agregado.nombre, agregado.total
         FROM (
             SELECT jugadores.nombre,
                    {$agregado}(estadisticas_jugador.valor) AS total
             FROM estadisticas_jugador
             JOIN jugadores ON jugadores.id = estadisticas_jugador.jugador_id
             {$join_eventos}
             WHERE estadisticas_jugador.tipo_estadistica_id = :t" . $fl . "
             GROUP BY estadisticas_jugador.jugador_id, jugadores.nombre
             HAVING {$agregado}(estadisticas_jugador.valor) > 0
             ORDER BY total {$orden}, jugadores.nombre ASC
             LIMIT 10
         ) AS agregado
         ORDER BY agregado.total {$orden}, agregado.nombre ASC"
    );
    $stmt_lider->execute(["t" => $t["id"]] + $pl);

    $filas = [];
    foreach ($stmt_lider->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $filas[] = ["nombre" => $f["nombre"], "total" => (float)$f["total"]];
    }
    $lideres[] = [
        "id" => (int)$t["id"],
        "nombre" => $t["nombre"],
        "unidad" => $t["unidad"],
        "menor_es_mejor" => $menor,
        "filas" => $filas,
    ];
}

$disciplinas = [];
$sql = "SELECT deportes.nombre, deportes.icono, COUNT(eventos.id) AS eventos
        FROM deportes
        LEFT JOIN eventos ON eventos.deporte_id = deportes.id
        GROUP BY deportes.id, deportes.nombre, deportes.icono
        ORDER BY eventos DESC, deportes.nombre ASC";
foreach ($conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $f) {
    $disciplinas[] = ["nombre" => $f["nombre"], "icono" => $f["icono"] ?? "", "eventos" => (int)$f["eventos"]];
}

responder(200, [
    "es_equipos" => $es_equipos,
    "generado" => date("H:i:s"),
    "kpis" => [
        "eventos" => $n_eventos,
        "equipos" => $n_equipos,
        "jugadores" => $n_jugadores,
        "registros" => $n_registros,
    ],
    "equipos" => $equipos,
    "resultados" => $resultados,
    "tendencia" => $tendencia,
    "lideres" => $lideres,
    "disciplinas" => $disciplinas,
]);