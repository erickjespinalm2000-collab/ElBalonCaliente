<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../index.php");
    exit;
}

function volver(string $codigo): void
{
    header("Location: ../Formularios/eventos.php?error=" . $codigo);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $deporte_id = $_POST["deporte_id"] ?? "";
    $liga_id = $_POST["liga_id"] ?? "";
    $fecha_texto = trim($_POST["fecha"] ?? "");
    $nombre = trim($_POST["nombre"] ?? "");

    if ($deporte_id === "" || !ctype_digit((string)$deporte_id) || $fecha_texto === "") {
        volver("vacio");
    }

    $fecha = DateTime::createFromFormat("Y-m-d\TH:i", $fecha_texto);
    if (!$fecha) {
        volver("fecha");
    }
    $fecha_sql = $fecha->format("Y-m-d H:i:s");

    $stmt = $conexion->prepare("SELECT requiere_equipo, admite_empate FROM deportes WHERE id = :id");
    $stmt->execute(["id" => $deporte_id]);
    $deporte = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$deporte) {
        volver("vacio");
    }

    $liga = null;
    if ($liga_id !== "") {
        if (!ctype_digit((string)$liga_id)) {
            volver("liga_invalida");
        }
        $stmt = $conexion->prepare("SELECT id FROM ligas WHERE id = :liga_id AND deporte_id = :deporte_id");
        $stmt->execute(["liga_id" => $liga_id, "deporte_id" => $deporte_id]);
        if (!$stmt->fetch()) {
            volver("liga_invalida");
        }
        $liga = $liga_id;
    }

    $equipo_local = null;
    $equipo_visitante = null;
    $marcador_local = null;
    $marcador_visitante = null;

    if ((int)$deporte["requiere_equipo"] === 1) {
        $local = $_POST["equipo_local_id"] ?? "";
        $visita = $_POST["equipo_visitante_id"] ?? "";

        if ($local === "" || $visita === "" || !ctype_digit((string)$local) || !ctype_digit((string)$visita)) {
            volver("sin_equipos");
        }
        if ($local === $visita) {
            volver("mismo_equipo");
        }

        $stmt = $conexion->prepare("SELECT COUNT(*) FROM equipos WHERE id IN (:a, :b) AND deporte_id = :deporte_id");
        $stmt->execute(["a" => $local, "b" => $visita, "deporte_id" => $deporte_id]);
        if ((int)$stmt->fetchColumn() !== 2) {
            volver("equipo_invalido");
        }

        $ml = $_POST["marcador_local"] ?? "";
        $mv = $_POST["marcador_visitante"] ?? "";
        if (!ctype_digit((string)$ml) || !ctype_digit((string)$mv)) {
            volver("marcador");
        }

        $equipo_local = $local;
        $equipo_visitante = $visita;
        $marcador_local = (int)$ml;
        $marcador_visitante = (int)$mv;

        // En disciplinas sin empate, como el baloncesto, un marcador igualado
        // no puede existir: significa que el partido no terminó.
        if ((int)$deporte["admite_empate"] === 0 && $marcador_local === $marcador_visitante) {
            volver("empate_no_permitido");
        }

    } else {
        if ($nombre === "") {
            volver("sin_nombre");
        }
    }

    $stmt = $conexion->prepare(
        "INSERT INTO eventos (deporte_id, liga_id, equipo_local_id, equipo_visitante_id, nombre, fecha, marcador_local, marcador_visitante)
         VALUES (:deporte_id, :liga_id, :local, :visita, :nombre, :fecha, :ml, :mv)"
    );
    $stmt->execute([
        "deporte_id" => $deporte_id,
        "liga_id" => $liga,
        "local" => $equipo_local,
        "visita" => $equipo_visitante,
        "nombre" => $nombre !== "" ? $nombre : null,
        "fecha" => $fecha_sql,
        "ml" => $marcador_local,
        "mv" => $marcador_visitante,
    ]);

    header("Location: ../Formularios/eventos.php?exito=creado");
    exit;
}