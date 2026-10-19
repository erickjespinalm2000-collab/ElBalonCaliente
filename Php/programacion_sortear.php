<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../index.php");
    exit;
}

// Solo un administrador puede ejecutar esto. Se comprueba en el
// servidor, no basta con ocultar el botón en la pantalla.
require_once "permisos.php";
exigir_admin();

require_once "programacion_logica.php";

function volver(string $codigo): void
{
    header("Location: ../Formularios/programacion.php?error=" . $codigo);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../Formularios/programacion.php");
    exit;
}

// ---------------------- Leer y validar lo que llega ----------------------

$liga_id = $_POST["liga_id"] ?? "";
$desde = trim($_POST["desde"] ?? "");
$hasta = trim($_POST["hasta"] ?? "");
$duracion = $_POST["duracion"] ?? "120";
$ida_vuelta = isset($_POST["ida_vuelta"]);
$horas = $_POST["horas"] ?? [];
$sedes = $_POST["sedes"] ?? [];

if ($liga_id === "" || !ctype_digit((string)$liga_id)) {
    volver("liga");
}

$stmt = $conexion->prepare(
    "SELECT ligas.id, ligas.deporte_id FROM ligas
     JOIN deportes ON deportes.id = ligas.deporte_id
     WHERE ligas.id = :id AND deportes.requiere_equipo = 1"
);
$stmt->execute(["id" => $liga_id]);
$liga = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$liga) {
    volver("liga");
}

if ($desde === "" || $hasta === "" || strtotime($desde) === false
    || strtotime($hasta) === false || strtotime($desde) > strtotime($hasta)) {
    volver("fechas");
}

// Solo horas entre 0 y 23, sin repetidas
$horas = array_values(array_unique(array_filter(
    array_map("intval", (array)$horas),
    fn($h) => $h >= 0 && $h <= 23
)));
if (count($horas) === 0) {
    volver("horas");
}

$duracion = ctype_digit((string)$duracion) ? (int)$duracion : 120;
$duracion = max(15, min(600, $duracion));

// ---------------------- Sedes, equipos y árbitros ----------------------

$sedes = array_values(array_unique(array_map("intval", (array)$sedes)));
if (count($sedes) === 0) {
    volver("sedes");
}

// Que las sedes existan, estén activas y sirvan para esta disciplina.
// Una sede dedicada a otro deporte se descarta aquí aunque llegue
// marcada desde el formulario; las multiusos (sin disciplina) valen.
$marcas = implode(",", array_fill(0, count($sedes), "?"));
$stmt = $conexion->prepare(
    "SELECT id FROM localidades
     WHERE activa = 1
       AND id IN ({$marcas})
       AND (deporte_id IS NULL OR deporte_id = ?)"
);
$stmt->execute(array_merge($sedes, [(int)$liga["deporte_id"]]));
$sedes = array_map("intval", $stmt->fetchAll(PDO::FETCH_COLUMN));
if (count($sedes) === 0) {
    volver("sedes");
}

$stmt = $conexion->prepare("SELECT id FROM equipos WHERE deporte_id = :d ORDER BY id ASC");
$stmt->execute(["d" => $liga["deporte_id"]]);
$equipos = array_map("intval", $stmt->fetchAll(PDO::FETCH_COLUMN));
if (count($equipos) < 2) {
    volver("sin_equipos");
}

// Árbitros de esa disciplina o comodines (deporte_id NULL)
$stmt = $conexion->prepare(
    "SELECT id FROM arbitros
     WHERE activo = 1 AND (deporte_id = :d OR deporte_id IS NULL)
     ORDER BY id ASC"
);
$stmt->execute(["d" => $liga["deporte_id"]]);
$arbitros = array_map("intval", $stmt->fetchAll(PDO::FETCH_COLUMN));
if (count($arbitros) === 0) {
    volver("sin_arbitros");
}

// ---------------------- Lo que ya está ocupado ----------------------
// Se lee el calendario existente para no pisarlo. Sin esto el sorteo
// programaría encima de partidos que ya estaban puestos a mano.

$ocupado = ["localidades" => [], "arbitros" => [], "equipos" => []];

$ventana = $conexion->prepare(
    "SELECT fecha, localidad_id, arbitro_id, equipo_local_id, equipo_visitante_id
     FROM eventos
     WHERE fecha >= :desde AND fecha < DATE_ADD(:hasta, INTERVAL 1 DAY)"
);
$ventana->execute(["desde" => $desde, "hasta" => $hasta]);

foreach ($ventana->fetchAll(PDO::FETCH_ASSOC) as $e) {
    if ($e["localidad_id"] !== null) {
        $ocupado["localidades"][(int)$e["localidad_id"]][] = $e["fecha"];
    }
    if ($e["arbitro_id"] !== null) {
        $ocupado["arbitros"][(int)$e["arbitro_id"]][] = $e["fecha"];
    }
    foreach (["equipo_local_id", "equipo_visitante_id"] as $campo) {
        if ($e[$campo] !== null) {
            $ocupado["equipos"][(int)$e[$campo]][] = $e["fecha"];
        }
    }
}

// ---------------------- Sortear ----------------------

$cruces = generar_cruces($equipos, $ida_vuelta);
$franjas = generar_franjas($desde, $hasta, $horas, $sedes);
$resultado = repartir($cruces, $franjas, $arbitros, $duracion, $ocupado);

// ---------------------- Guardar ----------------------
// Todo dentro de una transacción: o se crea el calendario completo o no
// se crea nada. Un calendario a medias sería peor que ninguno.

$conexion->beginTransaction();

try {
    $insertar = $conexion->prepare(
        "INSERT INTO eventos
            (deporte_id, liga_id, equipo_local_id, equipo_visitante_id,
             fecha, localidad_id, arbitro_id, duracion_minutos)
         VALUES
            (:deporte, :liga, :local, :visita, :fecha, :sede, :arbitro, :duracion)"
    );

    foreach ($resultado["programados"] as $p) {
        $insertar->execute([
            "deporte" => $liga["deporte_id"],
            "liga" => $liga["id"],
            "local" => $p["equipo_local_id"],
            "visita" => $p["equipo_visitante_id"],
            "fecha" => $p["fecha"],
            "sede" => $p["localidad_id"],
            "arbitro" => $p["arbitro_id"],
            "duracion" => $duracion,
        ]);
    }

    $conexion->commit();

} catch (Exception $e) {
    $conexion->rollBack();
    header("Location: ../Formularios/programacion.php?error=liga");
    exit;
}

header("Location: ../Formularios/programacion.php?exito=sorteado"
    . "&creados=" . count($resultado["programados"])
    . "&fuera=" . count($resultado["sin_lugar"]));
exit;
