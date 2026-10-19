<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../index.php");
    exit;
}

function volver(string $id, string $codigo): void
{
    header("Location: ../Formularios/jugadores.php?editar=" . urlencode($id) . "&error=" . $codigo);
    exit;
}

function guardarFotoJugador(array $archivo): array
{
    if (strtolower(pathinfo($archivo["name"], PATHINFO_EXTENSION)) !== "png") {
        return [null, "foto_no_png"];
    }
    if (mime_content_type($archivo["tmp_name"]) !== "image/png") {
        return [null, "foto_no_png"];
    }

    $carpeta = __DIR__ . "/../Fotos/Jugadores/";
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0755, true);
    }

    $nombre = "jugador_" . uniqid() . ".png";
    if (!move_uploaded_file($archivo["tmp_name"], $carpeta . $nombre)) {
        return [null, "foto_fallo"];
    }
    return ["Fotos/Jugadores/" . $nombre, null];
}

function borrarFotoSubida(?string $ruta): void
{
    if ($ruta === null || strpos($ruta, "Fotos/Jugadores/") !== 0 || strpos($ruta, "..") !== false) {
        return;
    }
    $completa = __DIR__ . "/../" . $ruta;
    if (is_file($completa)) {
        unlink($completa);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST["id"] ?? "";
    $nombre = trim($_POST["nombre"] ?? "");
    $equipo_id = $_POST["equipo_id"] ?? "";
    $posicion = trim($_POST["posicion"] ?? "");
    $fecha_nacimiento = trim($_POST["fecha_nacimiento"] ?? "");
    $tipo_foto = $_POST["tipo_foto"] ?? "mantener";

    if ($id === "" || !ctype_digit((string)$id)) {
        header("Location: ../Formularios/jugadores.php");
        exit;
    }

    $stmt = $conexion->prepare("SELECT deporte_id, foto FROM jugadores WHERE id = :id");
    $stmt->execute(["id" => $id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) {
        header("Location: ../Formularios/jugadores.php");
        exit;
    }

    if ($nombre === "") {
        volver($id, "vacio");
    }

    $stmt = $conexion->prepare("SELECT requiere_equipo FROM deportes WHERE id = :id");
    $stmt->execute(["id" => $actual["deporte_id"]]);
    $deporte = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($deporte && (int)$deporte["requiere_equipo"] === 1) {
        if ($equipo_id === "" || !ctype_digit((string)$equipo_id)) {
            volver($id, "sin_equipo");
        }
        $stmt = $conexion->prepare("SELECT id FROM equipos WHERE id = :e AND deporte_id = :d");
        $stmt->execute(["e" => $equipo_id, "d" => $actual["deporte_id"]]);
        if (!$stmt->fetch()) {
            volver($id, "equipo_invalido");
        }
    } else {
        $equipo_id = null;
    }

    // ---- Categoría por edad ----
    // Se revisa también al editar, porque aquí se puede cambiar de equipo
    // o corregir la fecha de nacimiento, y cualquiera de las dos cosas
    // puede dejar al jugador fuera de la categoría.
    require_once "categorias_reglas.php";

    $categoria = categoria_del_equipo($conexion, $equipo_id);
    $fallo = revisar_edad(edad_actual($fecha_nacimiento), $categoria);

    if ($fallo !== null) {
        header("Location: ../Formularios/jugadores.php?editar=" . (int)$id
            . "&error=" . $fallo
            . "&cat=" . urlencode($categoria["nombre"])
            . "&edad=" . (int)$categoria["edad_maxima"]);
        exit;
    }

    $foto = $actual["foto"];

    if ($tipo_foto === "quitar") {
        borrarFotoSubida($foto);
        $foto = null;

    } elseif ($tipo_foto === "url") {
        $url = trim($_POST["foto_url"] ?? "");
        if ($url !== "" && $url !== $foto) {
            borrarFotoSubida($foto);
            $foto = $url;
        }

    } elseif ($tipo_foto === "archivo"
              && isset($_FILES["foto_archivo"])
              && $_FILES["foto_archivo"]["error"] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES["foto_archivo"]["error"] !== UPLOAD_ERR_OK) {
            volver($id, "foto_fallo");
        }
        [$nueva, $codigo] = guardarFotoJugador($_FILES["foto_archivo"]);
        if ($codigo !== null) {
            volver($id, $codigo);
        }
        borrarFotoSubida($foto);
        $foto = $nueva;
    }

    $stmt = $conexion->prepare(
        "UPDATE jugadores
         SET nombre = :nombre, equipo_id = :equipo, posicion = :posicion, foto = :foto, fecha_nacimiento = :fecha
         WHERE id = :id"
    );
    $stmt->execute([
        "nombre" => $nombre,
        "equipo" => $equipo_id,
        "posicion" => $posicion !== "" ? $posicion : null,
        "foto" => $foto,
        "fecha" => $fecha_nacimiento !== "" ? $fecha_nacimiento : null,
        "id" => $id,
    ]);

    header("Location: ../Formularios/jugadores.php?exito=actualizado");
    exit;
}