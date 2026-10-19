<?php
/**
 * EL BALON CALIENTE - Pruebas de rendimiento
 * ---------------------------------------------------------------------------
 * Cubre el requisito de Redes: "servidor optimizado para consultas masivas".
 *
 * Que hace:
 *   1. Crea una base de datos aparte (el_balon_caliente_pruebas) con la misma
 *      estructura que la real. TUS DATOS NO SE TOCAN.
 *   2. Genera cientos de miles de registros de prueba.
 *   3. Mide las consultas reales del sistema SIN los indices nuevos.
 *   4. Crea los indices.
 *   5. Vuelve a medir y muestra la comparacion.
 *
 * Como ejecutarlo (desde la carpeta del proyecto, en la consola):
 *   C:\xampp\php\php.exe Pruebas\rendimiento.php
 *
 * Opciones:
 *   --limpiar     Borra la base de pruebas y termina.
 *   --escala=N    Multiplica el volumen de datos (por defecto 1).
 *
 * Al final imprime una tabla lista para pegar en el informe de Redes.
 */

if (PHP_SAPI !== "cli") {
    die("Este script solo se ejecuta desde la consola, no desde el navegador.\n");
}

// ---------------------------------------------------------------------------
//  Configuracion
// ---------------------------------------------------------------------------

$cfg = [
    "host"       => "localhost",
    "usuario"    => "root",
    "clave"      => "",
    "bd_origen"  => "el_balon_caliente",
    "bd_pruebas" => "el_balon_caliente_pruebas",

    // Volumen de datos (se multiplica por --escala)
    "deportes"             => 6,
    "ligas_por_deporte"    => 4,
    "tipos_por_deporte"    => 4,
    "equipos_por_deporte"  => 40,
    "jugadores_por_equipo" => 10,
    "eventos_por_deporte"  => 1000,

    // Cada consulta se corre varias veces y se toma la mediana, para que un
    // pico del sistema operativo no ensucie el resultado.
    "repeticiones" => 5,
];

$escala = 1;
$solo_limpiar = false;
foreach ($argv as $arg) {
    if ($arg === "--limpiar") {
        $solo_limpiar = true;
    }
    if (str_starts_with($arg, "--escala=")) {
        $escala = max(1, (int)substr($arg, 9));
    }
}

foreach (["equipos_por_deporte", "eventos_por_deporte"] as $clave) {
    $cfg[$clave] *= $escala;
}

// ---------------------------------------------------------------------------
//  Utilidades
// ---------------------------------------------------------------------------

function titulo(string $texto): void
{
    echo "\n" . str_repeat("=", 78) . "\n  " . $texto . "\n" . str_repeat("=", 78) . "\n";
}

function paso(string $texto): void
{
    echo "  - " . $texto . "\n";
}

function conectar(array $cfg, ?string $bd = null): PDO
{
    $dsn = "mysql:host={$cfg['host']};charset=utf8mb4";
    if ($bd !== null) {
        $dsn .= ";dbname={$bd}";
    }
    $pdo = new PDO($dsn, $cfg["usuario"], $cfg["clave"]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Sin esto, una sentencia que devuelve filas (como ANALYZE TABLE) deja la
    // conexion ocupada y la consulta siguiente falla con el error 2014.
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    return $pdo;
}

/** Corre una consulta varias veces y devuelve la mediana en milisegundos. */
function medir(PDO $pdo, string $sql, array $params, int $veces): array
{
    $tiempos = [];
    $filas = 0;
    for ($i = 0; $i < $veces; $i++) {
        $inicio = microtime(true);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tiempos[] = (microtime(true) - $inicio) * 1000;
        $filas = count($resultado);
    }
    sort($tiempos);
    $mediana = $tiempos[(int)floor(count($tiempos) / 2)];
    return ["ms" => $mediana, "filas" => $filas];
}

function formato(float $ms): string
{
    return number_format($ms, 1, ".", ",") . " ms";
}

// ---------------------------------------------------------------------------
//  Limpieza
// ---------------------------------------------------------------------------

$pdo = conectar($cfg);

if ($solo_limpiar) {
    $pdo->exec("DROP DATABASE IF EXISTS `{$cfg['bd_pruebas']}`");
    echo "Base de pruebas '{$cfg['bd_pruebas']}' eliminada.\n";
    exit(0);
}

// ---------------------------------------------------------------------------
//  1. Crear la base de pruebas con la misma estructura que la real
// ---------------------------------------------------------------------------

titulo("1. Preparando la base de pruebas");

$pdo->exec("DROP DATABASE IF EXISTS `{$cfg['bd_pruebas']}`");
$pdo->exec("CREATE DATABASE `{$cfg['bd_pruebas']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
paso("Base '{$cfg['bd_pruebas']}' creada");

$origen = conectar($cfg, $cfg["bd_origen"]);
$pruebas = conectar($cfg, $cfg["bd_pruebas"]);

// El orden importa: primero las tablas de las que dependen las demas
$tablas = ["deportes", "ligas", "equipos", "jugadores", "tipos_estadistica", "eventos", "estadisticas_jugador"];

$pruebas->exec("SET FOREIGN_KEY_CHECKS = 0");
foreach ($tablas as $tabla) {
    $crear = $origen->query("SHOW CREATE TABLE `{$tabla}`")->fetch(PDO::FETCH_ASSOC);
    $sql_crear = $crear["Create Table"];

    // Los indices de rendimiento (idx_...) se quitan del CREATE TABLE, para
    // que la base de pruebas arranque en el escenario original y se pueda
    // medir el antes y el despues. No se pueden borrar con DROP INDEX una vez
    // creados, porque InnoDB los toma como soporte de una llave foranea.
    $sql_crear = preg_replace('/,\s*\n\s*KEY `idx_[^`]+`\s*\([^)]*\)/', '', $sql_crear);

    $pruebas->exec($sql_crear);
}
$pruebas->exec("SET FOREIGN_KEY_CHECKS = 1");
paso("Estructura clonada: " . count($tablas) . " tablas, sin los indices de rendimiento");

// Los indices que vamos a probar se quitan, para medir el escenario original
$indices = [
    ["estadisticas_jugador", "idx_est_tipo_jugador_evento", "(tipo_estadistica_id, jugador_id, evento_id, valor)"],
    ["eventos",              "idx_eventos_dep_liga",    "(deporte_id, liga_id)"],
    ["eventos",              "idx_eventos_dep_fecha",   "(deporte_id, fecha)"],
    ["eventos",              "idx_eventos_fecha",       "(fecha)"],
    ["jugadores",            "idx_jugadores_dep_nombre", "(deporte_id, nombre)"],
];

$sobrantes = (int)$pruebas->query(
    "SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME LIKE 'idx_%'"
)->fetchColumn();
if ($sobrantes > 0) {
    echo "  !! Quedaron {$sobrantes} indices idx_ en la base de pruebas; la comparacion no sera valida.\n";
}
paso("Escenario inicial: sin los indices nuevos");

// ---------------------------------------------------------------------------
//  2. Generar los datos masivos
// ---------------------------------------------------------------------------

titulo("2. Generando datos de prueba");

$d  = $cfg["deportes"];
$lg = $cfg["ligas_por_deporte"];
$tp = $cfg["tipos_por_deporte"];
$eq = $cfg["equipos_por_deporte"];
$jg = $cfg["jugadores_por_equipo"];
$ev = $cfg["eventos_por_deporte"];

$inicio_carga = microtime(true);
$pruebas->exec("SET unique_checks = 0");
$pruebas->exec("SET foreign_key_checks = 0");

// Deportes: la mitad por equipos, la mitad individuales
$pruebas->exec(
    "INSERT INTO deportes (id, nombre, requiere_equipo)
     SELECT seq, CONCAT('Deporte ', seq), IF(seq <= {$d}, 1, 1) FROM seq_1_to_{$d}"
);

// Ligas
$pruebas->exec(
    "INSERT INTO ligas (id, nombre, deporte_id, pais, temporada)
     SELECT seq, CONCAT('Liga ', seq), ((seq - 1) DIV {$lg}) + 1, 'Honduras', '2026'
     FROM seq_1_to_" . ($d * $lg)
);

// Tipos de estadistica
$pruebas->exec(
    "INSERT INTO tipos_estadistica (id, deporte_id, nombre, unidad)
     SELECT seq, ((seq - 1) DIV {$tp}) + 1, CONCAT('Estadistica ', seq), 'cantidad'
     FROM seq_1_to_" . ($d * $tp)
);

// Equipos
$pruebas->exec(
    "INSERT INTO equipos (id, nombre, deporte_id)
     SELECT seq, CONCAT('Equipo ', seq), ((seq - 1) DIV {$eq}) + 1
     FROM seq_1_to_" . ($d * $eq)
);

// Jugadores
$pruebas->exec(
    "INSERT INTO jugadores (id, nombre, equipo_id, deporte_id, posicion)
     SELECT seq,
            CONCAT('Jugador ', LPAD(seq, 6, '0')),
            ((seq - 1) DIV {$jg}) + 1,
            ((((seq - 1) DIV {$jg})) DIV {$eq}) + 1,
            ELT(1 + ((seq - 1) MOD 4), 'Portero', 'Defensa', 'Mediocampista', 'Delantero')
     FROM seq_1_to_" . ($d * $eq * $jg)
);

// Eventos: cada uno entre dos equipos distintos de su mismo deporte
$pruebas->exec(
    "INSERT INTO eventos (id, deporte_id, liga_id, equipo_local_id, equipo_visitante_id,
                          nombre, fecha, marcador_local, marcador_visitante)
     SELECT seq,
            dep.d,
            (dep.d - 1) * {$lg} + 1 + ((dep.n - 1) MOD {$lg}),
            (dep.d - 1) * {$eq} + 1 + ((dep.n - 1) MOD {$eq}),
            (dep.d - 1) * {$eq} + 1 + ((dep.n + 17) MOD {$eq}),
            CONCAT('Jornada ', 1 + ((dep.n - 1) MOD 38)),
            DATE_ADD('2024-01-01', INTERVAL (dep.n MOD 900) DAY),
            (dep.n * 7) MOD 5,
            (dep.n * 11) MOD 5
     FROM (
        SELECT seq, ((seq - 1) DIV {$ev}) + 1 AS d, ((seq - 1) MOD {$ev}) + 1 AS n
        FROM seq_1_to_" . ($d * $ev) . "
     ) AS dep"
);

$n_est = 0;
// Las estadisticas se cargan por deporte, para no armar una transaccion gigante
for ($deporte = 1; $deporte <= $d; $deporte++) {
    $pruebas->exec(
        "INSERT INTO estadisticas_jugador (jugador_id, evento_id, tipo_estadistica_id, valor)
         SELECT j.id, e.id, t.id, ((e.id + j.id + t.id) MOD 11)
         FROM eventos e
         JOIN jugadores j
              ON j.equipo_id IN (e.equipo_local_id, e.equipo_visitante_id)
         JOIN tipos_estadistica t
              ON t.deporte_id = e.deporte_id
         WHERE e.deporte_id = {$deporte}"
    );
    $n_est = (int)$pruebas->query("SELECT COUNT(*) FROM estadisticas_jugador")->fetchColumn();
    echo "    deporte {$deporte}/{$d}: " . number_format($n_est, 0, ".", ",") . " estadisticas\r";
}
echo "\n";

$pruebas->exec("SET unique_checks = 1");
$pruebas->exec("SET foreign_key_checks = 1");
$segundos_carga = microtime(true) - $inicio_carga;

$conteos = [];
foreach ($tablas as $tabla) {
    $conteos[$tabla] = (int)$pruebas->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn();
}

foreach ($conteos as $tabla => $n) {
    printf("  %-24s %12s filas\n", $tabla, number_format($n, 0, ".", ","));
}
paso("Carga completa en " . number_format($segundos_carga, 1) . " s");

// Hay que correr ANALYZE antes de leer el tamano: information_schema devuelve
// estadisticas en cache y, recien cargada la tabla, reporta cifras absurdas.
$pruebas->query("ANALYZE TABLE " . implode(", ", array_map(fn($t) => "`{$t}`", $tablas)))->fetchAll();

$tamano = $pruebas->query(
    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1)
       FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
)->fetchColumn();
paso("Tamano en disco: {$tamano} MB");

// Una pasada en vacio para que las dos mediciones (sin y con indices) arranquen
// con la cache del motor igual de caliente y la comparacion sea justa.
$pruebas->query("SELECT COUNT(*) FROM estadisticas_jugador")->fetchAll();

// ---------------------------------------------------------------------------
//  3. Las consultas reales del sistema
// ---------------------------------------------------------------------------

$tipo_medio = (int)ceil($d * $tp / 2);
$liga_media = (int)ceil($d * $lg / 2);

$consultas = [
    "Lideres por estadistica (rankings y dashboard)" => [
        "sql" => "SELECT jugadores.id, jugadores.nombre, equipos.nombre AS equipo,
                         SUM(estadisticas_jugador.valor) AS total,
                         AVG(estadisticas_jugador.valor) AS promedio,
                         COUNT(DISTINCT estadisticas_jugador.evento_id) AS eventos
                  FROM estadisticas_jugador
                  JOIN jugadores ON jugadores.id = estadisticas_jugador.jugador_id
                  JOIN eventos ON eventos.id = estadisticas_jugador.evento_id
                  LEFT JOIN equipos ON equipos.id = jugadores.equipo_id
                  WHERE estadisticas_jugador.tipo_estadistica_id = :t
                  GROUP BY jugadores.id, jugadores.nombre, equipos.nombre
                  HAVING SUM(estadisticas_jugador.valor) > 0
                  ORDER BY total DESC, jugadores.nombre ASC
                  LIMIT 5",
        "params" => ["t" => $tipo_medio],
    ],

    "Lideres filtrando por liga" => [
        "sql" => "SELECT jugadores.id, jugadores.nombre,
                         SUM(estadisticas_jugador.valor) AS total
                  FROM estadisticas_jugador
                  JOIN jugadores ON jugadores.id = estadisticas_jugador.jugador_id
                  JOIN eventos ON eventos.id = estadisticas_jugador.evento_id
                  WHERE estadisticas_jugador.tipo_estadistica_id = :t
                        AND eventos.liga_id = :liga
                  GROUP BY jugadores.id, jugadores.nombre
                  HAVING SUM(estadisticas_jugador.valor) > 0
                  ORDER BY total DESC
                  LIMIT 10",
        "params" => ["t" => $tipo_medio, "liga" => $liga_media],
    ],

    // Las dos siguientes son la version reescrita que quedo en el sistema:
    // se agrupa primero sobre la tabla grande y despues se buscan los nombres,
    // y la union con eventos solo se usa cuando hay filtro de liga.
    "Lideres OPTIMIZADA (la que quedo en el sistema)" => [
        "sql" => "SELECT agregado.jugador_id AS id, agregado.nombre,
                         equipos.nombre AS equipo,
                         agregado.total, agregado.promedio, agregado.eventos
                  FROM (
                      SELECT estadisticas_jugador.jugador_id,
                             jugadores.nombre, jugadores.equipo_id,
                             SUM(estadisticas_jugador.valor) AS total,
                             AVG(estadisticas_jugador.valor) AS promedio,
                             COUNT(DISTINCT estadisticas_jugador.evento_id) AS eventos
                      FROM estadisticas_jugador
                      JOIN jugadores ON jugadores.id = estadisticas_jugador.jugador_id
                      WHERE estadisticas_jugador.tipo_estadistica_id = :t
                      GROUP BY estadisticas_jugador.jugador_id, jugadores.nombre, jugadores.equipo_id
                      HAVING SUM(estadisticas_jugador.valor) > 0
                      ORDER BY total DESC, jugadores.nombre ASC
                      LIMIT 5
                  ) AS agregado
                  LEFT JOIN equipos ON equipos.id = agregado.equipo_id
                  ORDER BY agregado.total DESC, agregado.nombre ASC",
        "params" => ["t" => $tipo_medio],
    ],

    "Lideres OPTIMIZADA + filtro de liga" => [
        "sql" => "SELECT agregado.jugador_id AS id, agregado.nombre, agregado.total
                  FROM (
                      SELECT estadisticas_jugador.jugador_id,
                             jugadores.nombre,
                             SUM(estadisticas_jugador.valor) AS total
                      FROM estadisticas_jugador
                      JOIN jugadores ON jugadores.id = estadisticas_jugador.jugador_id
                      JOIN eventos ON eventos.id = estadisticas_jugador.evento_id
                      WHERE estadisticas_jugador.tipo_estadistica_id = :t
                            AND eventos.liga_id = :liga
                      GROUP BY estadisticas_jugador.jugador_id, jugadores.nombre
                      HAVING SUM(estadisticas_jugador.valor) > 0
                      ORDER BY total DESC, jugadores.nombre ASC
                      LIMIT 10
                  ) AS agregado
                  ORDER BY agregado.total DESC, agregado.nombre ASC",
        "params" => ["t" => $tipo_medio, "liga" => $liga_media],
    ],

    "Tabla de posiciones" => [
        "sql" => "SELECT equipos.id, equipos.nombre,
                         COUNT(t.equipo_id) AS pj,
                         COALESCE(SUM(t.gf > t.gc), 0) AS g,
                         COALESCE(SUM(t.gf), 0) AS gf,
                         COALESCE(SUM(t.gc), 0) AS gc
                  FROM equipos
                  JOIN (
                      SELECT eventos.equipo_local_id AS equipo_id,
                             eventos.marcador_local AS gf, eventos.marcador_visitante AS gc
                      FROM eventos
                      WHERE eventos.deporte_id = :d AND eventos.marcador_local IS NOT NULL
                            AND eventos.liga_id = :liga
                      UNION ALL
                      SELECT eventos.equipo_visitante_id, eventos.marcador_visitante, eventos.marcador_local
                      FROM eventos
                      WHERE eventos.deporte_id = :d2 AND eventos.marcador_local IS NOT NULL
                            AND eventos.liga_id = :liga2
                  ) AS t ON t.equipo_id = equipos.id
                  GROUP BY equipos.id, equipos.nombre",
        "params" => ["d" => 1, "d2" => 1, "liga" => 1, "liga2" => 1],
    ],

    "Indicador de datos registrados (dashboard)" => [
        "sql" => "SELECT COUNT(*) AS n
                  FROM estadisticas_jugador
                  JOIN eventos ON eventos.id = estadisticas_jugador.evento_id
                  WHERE eventos.deporte_id = :d",
        "params" => ["d" => 2],
    ],

    "Tendencia de anotacion por fecha" => [
        "sql" => "SELECT DATE(eventos.fecha) AS dia,
                         SUM(marcador_local + marcador_visitante) AS total
                  FROM eventos
                  WHERE eventos.deporte_id = :d AND marcador_local IS NOT NULL
                  GROUP BY DATE(eventos.fecha)
                  ORDER BY dia ASC",
        "params" => ["d" => 3],
    ],

    "Listado de eventos, pagina 50 (paginacion)" => [
        "sql" => "SELECT eventos.id, eventos.nombre, eventos.fecha,
                         deportes.nombre AS deporte
                  FROM eventos
                  JOIN deportes ON deportes.id = eventos.deporte_id
                  ORDER BY eventos.fecha DESC, eventos.id DESC
                  LIMIT 20 OFFSET 1000",
        "params" => [],
    ],

    "Listado de jugadores por deporte (paginacion)" => [
        "sql" => "SELECT jugadores.id, jugadores.nombre, equipos.nombre AS equipo
                  FROM jugadores
                  LEFT JOIN equipos ON equipos.id = jugadores.equipo_id
                  WHERE jugadores.deporte_id = :d
                  ORDER BY jugadores.nombre ASC
                  LIMIT 20 OFFSET 400",
        "params" => ["d" => 4],
    ],
];

titulo("3. Midiendo SIN los indices nuevos");

$antes = [];
foreach ($consultas as $nombre => $c) {
    $r = medir($pruebas, $c["sql"], $c["params"], $cfg["repeticiones"]);
    $antes[$nombre] = $r;
    printf("  %-48s %12s\n", substr($nombre, 0, 48), formato($r["ms"]));
}

// ---------------------------------------------------------------------------
//  4. Crear los indices
// ---------------------------------------------------------------------------

titulo("4. Creando los indices");

foreach ($indices as [$tabla, $nombre, $columnas]) {
    $inicio = microtime(true);
    $pruebas->exec("ALTER TABLE `{$tabla}` ADD INDEX `{$nombre}` {$columnas}");
    printf("  %-28s %-42s %8s\n", $tabla, $nombre . " " . $columnas,
           number_format((microtime(true) - $inicio) * 1000, 0) . " ms");
}

// ANALYZE TABLE devuelve filas, asi que hay que consumirlas con query()+fetchAll
$pruebas->query("ANALYZE TABLE " . implode(", ", array_map(fn($t) => "`{$t}`", $tablas)))->fetchAll();
paso("Estadisticas de las tablas actualizadas (ANALYZE TABLE)");

$tamano_final = $pruebas->query(
    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1)
       FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
)->fetchColumn();
paso("Tamano en disco: {$tamano} MB  ->  {$tamano_final} MB");

// ---------------------------------------------------------------------------
//  5. Medir de nuevo y comparar
// ---------------------------------------------------------------------------

titulo("5. Midiendo CON los indices");

$despues = [];
foreach ($consultas as $nombre => $c) {
    $r = medir($pruebas, $c["sql"], $c["params"], $cfg["repeticiones"]);
    $despues[$nombre] = $r;
    printf("  %-48s %12s\n", substr($nombre, 0, 48), formato($r["ms"]));
}

titulo("RESULTADOS");

echo "  Volumen: " . number_format($conteos["estadisticas_jugador"], 0, ".", ",") . " estadisticas, "
   . number_format($conteos["eventos"], 0, ".", ",") . " eventos, "
   . number_format($conteos["jugadores"], 0, ".", ",") . " jugadores\n";
echo "  Cada consulta se corrio {$cfg['repeticiones']} veces; se reporta la mediana.\n";
echo "  Los tiempos absolutos cambian entre corridas segun la carga de la maquina y\n";
echo "  la cache del motor. Lo que vale es la comparacion dentro de una misma corrida.\n";
echo "  Las diferencias por debajo de ~5 ms estan dentro del margen de ruido.\n\n";

printf("  %-46s %11s %11s %9s\n", "Consulta", "Sin indice", "Con indice", "Mejora");
echo "  " . str_repeat("-", 80) . "\n";

foreach ($consultas as $nombre => $c) {
    $a = $antes[$nombre]["ms"];
    $b = $despues[$nombre]["ms"];
    $veces = $b > 0 ? $a / $b : 0;
    $etiqueta = $veces >= 1.15
        ? number_format($veces, 1) . "x"
        : ($veces <= 0.87 ? "peor" : "igual");
    printf("  %-46s %11s %11s %9s\n", substr($nombre, 0, 46), formato($a), formato($b), $etiqueta);
}

$total_antes = array_sum(array_column($antes, "ms"));
$total_despues = array_sum(array_column($despues, "ms"));
echo "  " . str_repeat("-", 80) . "\n";
printf("  %-46s %11s %11s %9s\n", "TOTAL", formato($total_antes), formato($total_despues),
       $total_despues > 0 ? number_format($total_antes / $total_despues, 1) . "x" : "-");

echo "\n  La base de pruebas '{$cfg['bd_pruebas']}' queda disponible para que la revises.\n";
echo "  Para borrarla:  C:\\xampp\\php\\php.exe Pruebas\\rendimiento.php --limpiar\n\n";
