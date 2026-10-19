<?php
session_start();
require_once "../Php/conexion.php";

if (!isset($_SESSION["usuario_id"], $_SESSION["nombre_usuario"], $_SESSION["rol"])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}

require_once "../Php/categorias_reglas.php";

$texto = trim((string)($_GET["q"] ?? ""));
$que = $_GET["que"] ?? "todo";     // todo | equipos | jugadores

// Filtro por disciplina, igual que en el resto de las pantallas
$filtro_deporte = trim((string)($_GET["deporte"] ?? ""));
if ($filtro_deporte !== "" && !ctype_digit($filtro_deporte)) {
    $filtro_deporte = "";
}

$lista_deportes = $conexion->query(
    "SELECT id, nombre, icono FROM deportes ORDER BY nombre ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$equipos = [];
$jugadores = [];
$busco = $texto !== "";

if ($busco) {

    $como = "%" . $texto . "%";

    $filtro_sql = $filtro_deporte !== "" ? " AND deportes.id = :deporte" : "";
    $filtro_par = $filtro_deporte !== "" ? ["deporte" => (int)$filtro_deporte] : [];

    // ---------------- Equipos ----------------
    // Las estadísticas salen de los eventos con marcador. Se cuenta dos
    // veces cada evento, una desde el lado local y otra desde el
    // visitante, y por eso la consulta usa dos sumas separadas.
    if ($que === "todo" || $que === "equipos") {
        $stmt = $conexion->prepare(
            "SELECT equipos.id, equipos.nombre, equipos.logo,
                    deportes.nombre AS deporte,
                    deportes.puntos_victoria, deportes.puntos_empate, deportes.puntos_derrota,
                    categorias.nombre AS categoria,

                    (SELECT COUNT(*) FROM jugadores WHERE jugadores.equipo_id = equipos.id) AS plantilla,

                    (SELECT COUNT(*) FROM eventos e
                      WHERE (e.equipo_local_id = equipos.id OR e.equipo_visitante_id = equipos.id)
                        AND e.marcador_local IS NOT NULL) AS jugados,

                    (SELECT COUNT(*) FROM eventos e
                      WHERE (e.equipo_local_id = equipos.id AND e.marcador_local > e.marcador_visitante)
                         OR (e.equipo_visitante_id = equipos.id AND e.marcador_visitante > e.marcador_local)) AS ganados,

                    (SELECT COUNT(*) FROM eventos e
                      WHERE (e.equipo_local_id = equipos.id OR e.equipo_visitante_id = equipos.id)
                        AND e.marcador_local IS NOT NULL
                        AND e.marcador_local = e.marcador_visitante) AS empatados,

                    (SELECT COUNT(*) FROM eventos e
                      WHERE (e.equipo_local_id = equipos.id AND e.marcador_local < e.marcador_visitante)
                         OR (e.equipo_visitante_id = equipos.id AND e.marcador_visitante < e.marcador_local)) AS perdidos,

                    (SELECT COALESCE(SUM(CASE WHEN e.equipo_local_id = equipos.id THEN e.marcador_local
                                              ELSE e.marcador_visitante END), 0)
                       FROM eventos e
                      WHERE (e.equipo_local_id = equipos.id OR e.equipo_visitante_id = equipos.id)
                        AND e.marcador_local IS NOT NULL) AS a_favor,

                    (SELECT COALESCE(SUM(CASE WHEN e.equipo_local_id = equipos.id THEN e.marcador_visitante
                                              ELSE e.marcador_local END), 0)
                       FROM eventos e
                      WHERE (e.equipo_local_id = equipos.id OR e.equipo_visitante_id = equipos.id)
                        AND e.marcador_local IS NOT NULL) AS en_contra

             FROM equipos
             JOIN deportes ON deportes.id = equipos.deporte_id
             LEFT JOIN categorias ON categorias.id = equipos.categoria_id
             WHERE equipos.nombre LIKE :q" . $filtro_sql . "
             ORDER BY equipos.nombre ASC
             LIMIT 25"
        );
        $stmt->execute(["q" => $como] + $filtro_par);
        $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------- Jugadores ----------------
    if ($que === "todo" || $que === "jugadores") {
        $stmt = $conexion->prepare(
            "SELECT jugadores.id, jugadores.nombre, jugadores.foto, jugadores.posicion,
                    jugadores.fecha_nacimiento,
                    deportes.nombre AS deporte,
                    equipos.nombre AS equipo, equipos.logo AS escudo,
                    categorias.nombre AS categoria,
                    (SELECT COUNT(DISTINCT evento_id) FROM estadisticas_jugador
                      WHERE estadisticas_jugador.jugador_id = jugadores.id) AS eventos
             FROM jugadores
             JOIN deportes ON deportes.id = jugadores.deporte_id
             LEFT JOIN equipos ON equipos.id = jugadores.equipo_id
             LEFT JOIN categorias ON categorias.id = equipos.categoria_id
             WHERE jugadores.nombre LIKE :q" . $filtro_sql . "
             ORDER BY jugadores.nombre ASC
             LIMIT 25"
        );
        $stmt->execute(["q" => $como] + $filtro_par);
        $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Los totales de cada jugador, en una sola consulta en lugar de
        // una por jugador. Con 25 jugadores serían 25 viajes a la base.
        if (count($jugadores) > 0) {
            $ids = array_column($jugadores, "id");
            $marcas = implode(",", array_fill(0, count($ids), "?"));

            $stmt = $conexion->prepare(
                "SELECT ej.jugador_id, te.nombre AS estadistica, te.unidad,
                        SUM(ej.valor) AS total
                 FROM estadisticas_jugador ej
                 JOIN tipos_estadistica te ON te.id = ej.tipo_estadistica_id
                 WHERE ej.jugador_id IN ({$marcas})
                 GROUP BY ej.jugador_id, te.id
                 ORDER BY te.nombre ASC"
            );
            $stmt->execute($ids);

            $totales = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
                $totales[(int)$t["jugador_id"]][] = $t;
            }

            foreach ($jugadores as $i => $j) {
                $jugadores[$i]["totales"] = $totales[(int)$j["id"]] ?? [];
            }
        }
    }
}

/** Dibuja una imagen de equipo o jugador, local o de internet. */
function imagen(?string $ruta, string $clase): string
{
    if ($ruta === null || $ruta === "") {
        return '<span class="' . $clase . ' img-vacia"></span>';
    }
    $src = str_starts_with($ruta, "http") ? $ruta : "../" . $ruta;
    return '<img class="' . $clase . '" src="' . htmlspecialchars($src) . '" alt="">';
}

function numero($n): string
{
    $n = (float)$n;
    return $n == (int)$n
        ? number_format($n, 0, ".", ",")
        : number_format($n, 2, ".", ",");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Buscador</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/fase2.css?v=9">
    <link rel="stylesheet" href="../Css/buscador.css?v=9">
</head>
<body>

    <div class="pantalla pantalla-ancha">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Buscador</h1>
        <p class="subtitulo">
            Busca cualquier equipo o jugador por su nombre y mira sus
            estadísticas acumuladas.
        </p>

        <form method="GET" action="buscador.php" class="caja-busqueda">
            <input type="search" name="q" id="q" autofocus
                   placeholder="Escribe un nombre: Real Madrid, Erick, Barcelona..."
                   value="<?php echo htmlspecialchars($texto); ?>">
            <select name="deporte">
                <option value="">Toda disciplina</option>
                <?php foreach ($lista_deportes as $d): ?>
                    <option value="<?php echo (int)$d["id"]; ?>"
                        <?php echo $filtro_deporte === (string)$d["id"] ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars(trim(($d["icono"] ?? "") . " " . $d["nombre"])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="que">
                <option value="todo"      <?php echo $que === "todo" ? "selected" : ""; ?>>Todo</option>
                <option value="equipos"   <?php echo $que === "equipos" ? "selected" : ""; ?>>Solo equipos</option>
                <option value="jugadores" <?php echo $que === "jugadores" ? "selected" : ""; ?>>Solo jugadores</option>
            </select>
            <button type="submit" class="btn-agregar">Buscar</button>
        </form>

        <?php if (!$busco): ?>

            <p class="vacio">Escribe algo para empezar.</p>

        <?php elseif (count($equipos) === 0 && count($jugadores) === 0): ?>

            <p class="vacio">
                No encontré nada que se llame «<?php echo htmlspecialchars($texto); ?>».
                Prueba con una parte del nombre.
            </p>

        <?php else: ?>

            <!-- ================= Equipos ================= -->

            <?php if (count($equipos) > 0): ?>
                <h2>Equipos (<?php echo count($equipos); ?>)</h2>

                <?php foreach ($equipos as $e): ?>
                    <?php
                        $puntos = (int)$e["ganados"] * (int)$e["puntos_victoria"]
                                + (int)$e["empatados"] * (int)$e["puntos_empate"]
                                + (int)$e["perdidos"] * (int)$e["puntos_derrota"];
                        $dif = (int)$e["a_favor"] - (int)$e["en_contra"];
                    ?>
                    <div class="tarjeta-resultado">
                        <div class="tarjeta-cabecera">
                            <?php echo imagen($e["logo"], "escudo-res"); ?>
                            <div>
                                <span class="res-nombre"><?php echo htmlspecialchars($e["nombre"]); ?></span>
                                <p class="res-meta">
                                    <?php echo htmlspecialchars($e["deporte"]); ?>
                                    <?php if ($e["categoria"]): ?>
                                        &middot; <span class="etiqueta"><?php echo htmlspecialchars($e["categoria"]); ?></span>
                                    <?php endif; ?>
                                    &middot; <?php echo (int)$e["plantilla"]; ?> jugador(es)
                                </p>
                            </div>
                            <a class="res-enlace" href="jugadores.php?equipo=<?php echo (int)$e["id"]; ?>">Ver plantilla &rarr;</a>
                        </div>

                        <?php if ((int)$e["jugados"] === 0): ?>
                            <p class="vacio">Todavía no ha jugado ningún evento con marcador.</p>
                        <?php else: ?>
                            <div class="cifras">
                                <div class="cifra"><span class="cifra-valor"><?php echo (int)$e["jugados"]; ?></span><span class="cifra-rotulo">Jugados</span></div>
                                <div class="cifra"><span class="cifra-valor"><?php echo (int)$e["ganados"]; ?></span><span class="cifra-rotulo">Ganados</span></div>
                                <div class="cifra"><span class="cifra-valor"><?php echo (int)$e["empatados"]; ?></span><span class="cifra-rotulo">Empatados</span></div>
                                <div class="cifra"><span class="cifra-valor"><?php echo (int)$e["perdidos"]; ?></span><span class="cifra-rotulo">Perdidos</span></div>
                                <div class="cifra"><span class="cifra-valor"><?php echo (int)$e["a_favor"]; ?></span><span class="cifra-rotulo">A favor</span></div>
                                <div class="cifra"><span class="cifra-valor"><?php echo (int)$e["en_contra"]; ?></span><span class="cifra-rotulo">En contra</span></div>
                                <div class="cifra"><span class="cifra-valor"><?php echo ($dif > 0 ? "+" : "") . $dif; ?></span><span class="cifra-rotulo">Diferencia</span></div>
                                <div class="cifra cifra-destacada"><span class="cifra-valor"><?php echo $puntos; ?></span><span class="cifra-rotulo">Puntos</span></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- ================= Jugadores ================= -->

            <?php if (count($jugadores) > 0): ?>
                <h2>Jugadores (<?php echo count($jugadores); ?>)</h2>

                <?php foreach ($jugadores as $j): ?>
                    <div class="tarjeta-resultado">
                        <div class="tarjeta-cabecera">
                            <?php echo imagen($j["foto"], "foto-res"); ?>
                            <div>
                                <span class="res-nombre"><?php echo htmlspecialchars($j["nombre"]); ?></span>
                                <p class="res-meta">
                                    <?php if ($j["equipo"] !== null): ?>
                                        <?php echo imagen($j["escudo"], "escudo-mini"); ?>
                                        <strong><?php echo htmlspecialchars($j["equipo"]); ?></strong>
                                    <?php else: ?>
                                        <strong>Atleta individual</strong>
                                    <?php endif; ?>
                                    &middot; <?php echo htmlspecialchars($j["deporte"]); ?>
                                    <?php if ($j["posicion"]): ?>
                                        &middot; <?php echo htmlspecialchars($j["posicion"]); ?>
                                    <?php endif; ?>
                                    <?php if ($j["categoria"]): ?>
                                        &middot; <span class="etiqueta"><?php echo htmlspecialchars($j["categoria"]); ?></span>
                                    <?php endif; ?>
                                    <?php $edad = edad_actual($j["fecha_nacimiento"]); ?>
                                    <?php if ($edad !== null): ?>
                                        &middot; <?php echo $edad; ?> años
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <?php if (count($j["totales"] ?? []) === 0): ?>
                            <p class="vacio">Todavía no tiene estadísticas capturadas.</p>
                        <?php else: ?>
                            <div class="cifras">
                                <div class="cifra"><span class="cifra-valor"><?php echo (int)$j["eventos"]; ?></span><span class="cifra-rotulo">Eventos</span></div>
                                <?php foreach ($j["totales"] as $t): ?>
                                    <div class="cifra">
                                        <span class="cifra-valor"><?php echo numero($t["total"]); ?></span>
                                        <span class="cifra-rotulo">
                                            <?php echo htmlspecialchars($t["estadistica"]); ?>
                                            <?php if ($t["unidad"]): ?>
                                                <span class="cifra-unidad"><?php echo htmlspecialchars($t["unidad"]); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php endif; ?>

    </div>

</body>
</html>
