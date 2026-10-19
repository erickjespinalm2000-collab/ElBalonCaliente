<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../index.php");
    exit;
}

function volver(string $evento_id, string $clave, string $valor): void
{
    $destino = "../Formularios/estadisticas.php?";
    if ($evento_id !== "") {
        $destino .= "evento_id=" . urlencode($evento_id) . "&";
    }
    header("Location: " . $destino . $clave . "=" . $valor);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $evento_id = $_POST["evento_id"] ?? "";
    $valores = $_POST["valores"] ?? [];

    if ($evento_id === "" || !ctype_digit((string)$evento_id) || !is_array($valores)) {
        volver("", "error", "evento");
    }

    $stmt = $conexion->prepare(
        "SELECT eventos.deporte_id, eventos.equipo_local_id, eventos.equipo_visitante_id, deportes.requiere_equipo
         FROM eventos
         JOIN deportes ON deportes.id = eventos.deporte_id
         WHERE eventos.id = :id"
    );
    $stmt->execute(["id" => $evento_id]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$evento) {
        volver("", "error", "evento");
    }

    if ((int)$evento["requiere_equipo"] === 1) {
        $stmt = $conexion->prepare("SELECT id FROM jugadores WHERE equipo_id IN (:l, :v)");
        $stmt->execute(["l" => $evento["equipo_local_id"], "v" => $evento["equipo_visitante_id"]]);
    } else {
        $stmt = $conexion->prepare("SELECT id FROM jugadores WHERE deporte_id = :d AND equipo_id IS NULL");
        $stmt->execute(["d" => $evento["deporte_id"]]);
    }
    $jugadores_validos = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    $stmt = $conexion->prepare("SELECT id FROM tipos_estadistica WHERE deporte_id = :d");
    $stmt->execute(["d" => $evento["deporte_id"]]);
    $tipos_validos = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    $conexion->beginTransaction();

    try {
        $borrar = $conexion->prepare(
            "DELETE FROM estadisticas_jugador
             WHERE evento_id = :e AND jugador_id = :j AND tipo_estadistica_id = :t"
        );
        $guardar = $conexion->prepare(
            "INSERT INTO estadisticas_jugador (jugador_id, evento_id, tipo_estadistica_id, valor)
             VALUES (:j, :e, :t, :valor)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
        );

        foreach ($valores as $jugador_id => $fila) {
            if (!isset($jugadores_validos[$jugador_id]) || !is_array($fila)) {
                throw new RuntimeException("dato_invalido");
            }

            foreach ($fila as $tipo_id => $valor) {
                if (!isset($tipos_validos[$tipo_id])) {
                    throw new RuntimeException("dato_invalido");
                }

                $valor = str_replace(",", ".", trim((string)$valor));

                if ($valor === "") {
                    $borrar->execute(["e" => $evento_id, "j" => $jugador_id, "t" => $tipo_id]);
                    continue;
                }

                if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $valor)) {
                    throw new RuntimeException("valor");
                }

                $guardar->execute(["j" => $jugador_id, "e" => $evento_id, "t" => $tipo_id, "valor" => $valor]);
            }
        }

        $conexion->commit();

    } catch (RuntimeException $e) {
        $conexion->rollBack();
        volver($evento_id, "error", $e->getMessage());
    }

    volver($evento_id, "exito", "guardado");
}