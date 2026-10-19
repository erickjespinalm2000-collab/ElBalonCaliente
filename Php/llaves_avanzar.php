<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../index.php");
    exit;
}

// Solo un administrador puede ejecutar esto. Se comprueba en el
// servidor, no basta con ocultar el boton en la pantalla.
require_once "permisos.php";
exigir_admin();

require_once "llaves_logica.php";

$partido_id = $_GET["partido"] ?? "";
$ganador_id = $_GET["ganador"] ?? "";

if (!ctype_digit((string)$partido_id) || !ctype_digit((string)$ganador_id)) {
    header("Location: ../Formularios/llaves.php");
    exit;
}

$stmt = $conexion->prepare(
    "SELECT lp.*, llaves.rondas
     FROM llave_partidos lp
     JOIN llaves ON llaves.id = lp.llave_id
     WHERE lp.id = :id"
);
$stmt->execute(["id" => $partido_id]);
$partido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$partido) {
    header("Location: ../Formularios/llaves.php");
    exit;
}

// El ganador tiene que ser uno de los dos que juegan ese partido.
// Sin esta comprobacion se podria colar cualquier equipo por la URL.
$en_juego = [(int)$partido["equipo_a_id"], (int)$partido["equipo_b_id"]];
if (!in_array((int)$ganador_id, $en_juego, true)) {
    header("Location: ../Formularios/llaves.php?ver=" . (int)$partido["llave_id"]);
    exit;
}

$conexion->beginTransaction();

try {
    $stmt = $conexion->prepare("UPDATE llave_partidos SET ganador_id = :g WHERE id = :id");
    $stmt->execute(["g" => $ganador_id, "id" => $partido_id]);

    // Subirlo a la ronda siguiente, si es que hay ronda siguiente
    $siguiente = (int)$partido["ronda"] + 1;

    if ($siguiente <= (int)$partido["rondas"]) {
        $destino = posicion_siguiente((int)$partido["posicion"]);
        $lado = lado_siguiente((int)$partido["posicion"]);
        $columna = $lado === "a" ? "equipo_a_id" : "equipo_b_id";

        $stmt = $conexion->prepare(
            "UPDATE llave_partidos SET {$columna} = :g
             WHERE llave_id = :llave AND ronda = :ronda AND posicion = :pos"
        );
        $stmt->execute([
            "g" => $ganador_id,
            "llave" => $partido["llave_id"],
            "ronda" => $siguiente,
            "pos" => $destino,
        ]);
    }

    $conexion->commit();

} catch (Exception $e) {
    $conexion->rollBack();
}

header("Location: ../Formularios/llaves.php?ver=" . (int)$partido["llave_id"] . "&exito=avanzo");
exit;