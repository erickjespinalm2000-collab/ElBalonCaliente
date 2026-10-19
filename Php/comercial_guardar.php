<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../index.php");
    exit;
}

require_once "permisos.php";
exigir_admin();

function volver(string $deporte_id, string $liga_id, string $extra = ""): void
{
    $url = "../Formularios/comercial.php?deporte_id=" . urlencode($deporte_id);
    if ($liga_id !== "") {
        $url .= "&liga_id=" . urlencode($liga_id);
    }
    header("Location: " . $url . $extra);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $deporte_id = $_POST["deporte_id"] ?? "";
    $liga_id = $_POST["liga_id"] ?? "";

    if (!ctype_digit((string)$deporte_id)) {
        header("Location: ../Formularios/comercial.php");
        exit;
    }

    $stmt = $conexion->prepare("SELECT id FROM deportes WHERE id = :id");
    $stmt->execute(["id" => $deporte_id]);
    if (!$stmt->fetch()) {
        header("Location: ../Formularios/comercial.php");
        exit;
    }

    $tipo_principal_id = $_POST["tipo_principal_id"] ?? "";
    if ($tipo_principal_id !== "" && ctype_digit((string)$tipo_principal_id)) {
        $stmt = $conexion->prepare("SELECT id FROM tipos_estadistica WHERE id = :t AND deporte_id = :d");
        $stmt->execute(["t" => $tipo_principal_id, "d" => $deporte_id]);
        if (!$stmt->fetch()) {
            $tipo_principal_id = null;
        }
    } else {
        $tipo_principal_id = null;
    }

    $aforo = max(0, (int)($_POST["aforo"] ?? 0));
    $ocupacion = min(100, max(0, (float)($_POST["ocupacion"] ?? 0)));
    $precio_boleto = max(0, (float)($_POST["precio_boleto"] ?? 0));
    $patrocinio_evento = max(0, (float)($_POST["patrocinio_evento"] ?? 0));
    $valor_base_jugador = max(0, (float)($_POST["valor_base_jugador"] ?? 0));

    $stmt = $conexion->prepare(
        "INSERT INTO parametros_comerciales
                (deporte_id, tipo_principal_id, aforo, ocupacion, precio_boleto,
                 patrocinio_evento, valor_base_jugador)
         VALUES (:d, :t, :aforo, :ocupacion, :precio, :patrocinio, :valor)
         ON DUPLICATE KEY UPDATE
                tipo_principal_id = VALUES(tipo_principal_id),
                aforo = VALUES(aforo),
                ocupacion = VALUES(ocupacion),
                precio_boleto = VALUES(precio_boleto),
                patrocinio_evento = VALUES(patrocinio_evento),
                valor_base_jugador = VALUES(valor_base_jugador)"
    );
    $stmt->execute([
        "d" => $deporte_id,
        "t" => $tipo_principal_id,
        "aforo" => $aforo,
        "ocupacion" => $ocupacion,
        "precio" => $precio_boleto,
        "patrocinio" => $patrocinio_evento,
        "valor" => $valor_base_jugador,
    ]);

    volver((string)$deporte_id, (string)$liga_id, "&exito=guardado");
}

header("Location: ../Formularios/comercial.php");
exit;
