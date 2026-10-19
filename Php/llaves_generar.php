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

require_once "llaves_logica.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../Formularios/llaves.php");
    exit;
}

$liga_id = $_POST["liga_id"] ?? "";
$al_azar = isset($_POST["al_azar"]);

if ($liga_id === "" || !ctype_digit((string)$liga_id)) {
    header("Location: ../Formularios/llaves.php?error=liga");
    exit;
}

// El formato se comprueba aquí también, no solo al pintar el desplegable:
// si no, bastaría con mandar el id de una liga de solo tabla por la URL
// para crearle un cuadro que no le corresponde.
$stmt = $conexion->prepare(
    "SELECT ligas.id, ligas.nombre, ligas.deporte_id
     FROM ligas
     JOIN deportes ON deportes.id = ligas.deporte_id
     WHERE ligas.id = :id
       AND deportes.requiere_equipo = 1
       AND ligas.formato IN ('eliminacion', 'ambas')"
);
$stmt->execute(["id" => $liga_id]);
$liga = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$liga) {
    header("Location: ../Formularios/llaves.php?error=formato");
    exit;
}

// Una liga solo puede tener una llave. Rehacerla significa borrar la
// anterior, para no dejar dos cuadros contradictorios de lo mismo.
$stmt = $conexion->prepare("SELECT id FROM llaves WHERE liga_id = :id");
$stmt->execute(["id" => $liga_id]);
if ($stmt->fetch()) {
    header("Location: ../Formularios/llaves.php?error=existe");
    exit;
}

$stmt = $conexion->prepare("SELECT id FROM equipos WHERE deporte_id = :d ORDER BY nombre ASC");
$stmt->execute(["d" => $liga["deporte_id"]]);
$equipos = array_map("intval", $stmt->fetchAll(PDO::FETCH_COLUMN));

if (count($equipos) < 2) {
    header("Location: ../Formularios/llaves.php?error=pocos");
    exit;
}

if ($al_azar) {
    shuffle($equipos);
}

$tamano = tamano_llave(count($equipos));
$rondas = total_rondas($tamano);
$primera = resolver_pases_directos(emparejar_primera_ronda($equipos));

// Todo o nada: una llave a medio crear no sirve para nada.
$conexion->beginTransaction();

try {
    $stmt = $conexion->prepare(
        "INSERT INTO llaves (liga_id, nombre, rondas) VALUES (:liga, :nombre, :rondas)"
    );
    $stmt->execute([
        "liga" => $liga["id"],
        "nombre" => "Eliminatoria " . $liga["nombre"],
        "rondas" => $rondas,
    ]);
    $llave_id = (int)$conexion->lastInsertId();

    $insertar = $conexion->prepare(
        "INSERT INTO llave_partidos (llave_id, ronda, posicion, equipo_a_id, equipo_b_id, ganador_id)
         VALUES (:llave, :ronda, :posicion, :a, :b, :ganador)"
    );

    // Primera ronda, con sus pases directos ya resueltos
    foreach ($primera as $posicion => $p) {
        $insertar->execute([
            "llave" => $llave_id,
            "ronda" => 1,
            "posicion" => $posicion,
            "a" => $p[0],
            "b" => $p[1],
            "ganador" => $p["ganador"],
        ]);
    }

    // Las rondas siguientes nacen vacías y se van llenando con los ganadores
    $partidos = count($primera);
    for ($ronda = 2; $ronda <= $rondas; $ronda++) {
        $partidos = intdiv($partidos, 2);
        for ($posicion = 0; $posicion < $partidos; $posicion++) {
            $insertar->execute([
                "llave" => $llave_id,
                "ronda" => $ronda,
                "posicion" => $posicion,
                "a" => null,
                "b" => null,
                "ganador" => null,
            ]);
        }
    }

    // Subir de una vez a los que pasaron sin jugar
    $subir = $conexion->prepare(
        "UPDATE llave_partidos SET equipo_a_id = :equipo
         WHERE llave_id = :llave AND ronda = 2 AND posicion = :pos"
    );
    $subir_b = $conexion->prepare(
        "UPDATE llave_partidos SET equipo_b_id = :equipo
         WHERE llave_id = :llave AND ronda = 2 AND posicion = :pos"
    );

    if ($rondas >= 2) {
        foreach ($primera as $posicion => $p) {
            if ($p["ganador"] === null) {
                continue;
            }
            $destino = posicion_siguiente($posicion);
            $consulta = lado_siguiente($posicion) === "a" ? $subir : $subir_b;
            $consulta->execute([
                "equipo" => $p["ganador"],
                "llave" => $llave_id,
                "pos" => $destino,
            ]);
        }
    }

    $conexion->commit();

} catch (Exception $e) {
    $conexion->rollBack();
    header("Location: ../Formularios/llaves.php?error=liga");
    exit;
}

header("Location: ../Formularios/llaves.php?ver=" . $llave_id . "&exito=creada");
exit;
