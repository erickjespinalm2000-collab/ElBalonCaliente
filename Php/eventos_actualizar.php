<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../index.php");
    exit;
}

function volver(string $id, string $codigo): void
{
    header("Location: ../Formularios/eventos.php?editar=" . urlencode($id) . "&error=" . $codigo);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST["id"] ?? "";
    $liga_id = $_POST["liga_id"] ?? "";
    $fecha_texto = trim($_POST["fecha"] ?? "");
    $nombre = trim($_POST["nombre"] ?? "");

    if ($id === "" || !ctype_digit((string)$id)) {
        header("Location: ../Formularios/eventos.php");
        exit;
    }

    $stmt = $conexion->prepare(
        "SELECT eventos.deporte_id, eventos.equipo_local_id, eventos.equipo_visitante_id,
                deportes.requiere_equipo, deportes.admite_empate
         FROM eventos
         JOIN deportes ON deportes.id = eventos.deporte_id
         WHERE eventos.id = :id"
    );
    $stmt->execute(["id" => $id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) {
        header("Location: ../Formularios/eventos.php");
        exit;
    }

    $fecha = DateTime::createFromFormat("Y-m-d\TH:i", $fecha_texto);
    if (!$fecha) {
        volver($id, $fecha_texto === "" ? "vacio" : "fecha");
    }

    $liga = null;
    if ($liga_id !== "") {
        if (!ctype_digit((string)$liga_id)) {
            volver($id, "liga_invalida");
        }
        $stmt = $conexion->prepare("SELECT id FROM ligas WHERE id = :l AND deporte_id = :d");
        $stmt->execute(["l" => $liga_id, "d" => $actual["deporte_id"]]);
        if (!$stmt->fetch()) {
            volver($id, "liga_invalida");
        }
        $liga = $liga_id;
    }

    $equipo_local = null;
    $equipo_visitante = null;
    $marcador_local = null;
    $marcador_visitante = null;

    if ((int)$actual["requiere_equipo"] === 1) {

        $stmt = $conexion->prepare("SELECT COUNT(*) FROM estadisticas_jugador WHERE evento_id = :id");
        $stmt->execute(["id" => $id]);
        $con_estadisticas = (int)$stmt->fetchColumn() > 0;

        if ($con_estadisticas) {
            $equipo_local = $actual["equipo_local_id"];
            $equipo_visitante = $actual["equipo_visitante_id"];
        } else {
            $local = $_POST["equipo_local_id"] ?? "";
            $visita = $_POST["equipo_visitante_id"] ?? "";

            if ($local === "" || $visita === "" || !ctype_digit((string)$local) || !ctype_digit((string)$visita)) {
                volver($id, "sin_equipos");
            }
            if ($local === $visita) {
                volver($id, "mismo_equipo");
            }

            $stmt = $conexion->prepare("SELECT COUNT(*) FROM equipos WHERE id IN (:a, :b) AND deporte_id = :d");
            $stmt->execute(["a" => $local, "b" => $visita, "d" => $actual["deporte_id"]]);
            if ((int)$stmt->fetchColumn() !== 2) {
                volver($id, "equipo_invalido");
            }

            $equipo_local = $local;
            $equipo_visitante = $visita;
        }

        $ml = $_POST["marcador_local"] ?? "";
        $mv = $_POST["marcador_visitante"] ?? "";
        if (!ctype_digit((string)$ml) || !ctype_digit((string)$mv)) {
            volver($id, "marcador");
        }
        $marcador_local = (int)$ml;
        $marcador_visitante = (int)$mv;

        // En disciplinas sin empate, como el baloncesto, un marcador igualado
        // no puede existir: significa que el partido no terminó.
        if ((int)$actual["admite_empate"] === 0 && $marcador_local === $marcador_visitante) {
            volver($id, "empate_no_permitido");
        }

    } else {
        if ($nombre === "") {
            volver($id, "sin_nombre");
        }
    }

    $stmt = $conexion->prepare(
        "UPDATE eventos
         SET liga_id = :liga, equipo_local_id = :local, equipo_visitante_id = :visita,
             nombre = :nombre, fecha = :fecha, marcador_local = :ml, marcador_visitante = :mv
         WHERE id = :id"
    );
    $stmt->execute([
        "liga" => $liga,
        "local" => $equipo_local,
        "visita" => $equipo_visitante,
        "nombre" => $nombre !== "" ? $nombre : null,
        "fecha" => $fecha->format("Y-m-d H:i:s"),
        "ml" => $marcador_local,
        "mv" => $marcador_visitante,
        "id" => $id,
    ]);

    header("Location: ../Formularios/eventos.php?exito=actualizado");
    exit;
}