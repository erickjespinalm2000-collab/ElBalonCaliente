<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../index.php");
    exit;
}

/**
 * Valida y guarda la foto subida, exigiendo que sea realmente un PNG
 * (revisa la extensión y el tipo real del archivo).
 * Devuelve [ruta_guardada, codigo_error].
 */
function guardarFotoJugador(array $archivo): array
{
    $extension = strtolower(pathinfo($archivo["name"], PATHINFO_EXTENSION));
    if ($extension !== "png") {
        return [null, "foto_no_png"];
    }

    if (mime_content_type($archivo["tmp_name"]) !== "image/png") {
        return [null, "foto_no_png"];
    }

    $carpetaDestino = __DIR__ . "/../Fotos/Jugadores/";
    if (!is_dir($carpetaDestino)) {
        mkdir($carpetaDestino, 0755, true);
    }

    $nombreArchivo = "jugador_" . uniqid() . ".png";

    if (!move_uploaded_file($archivo["tmp_name"], $carpetaDestino . $nombreArchivo)) {
        return [null, "foto_fallo"];
    }

    return ["Fotos/Jugadores/" . $nombreArchivo, null];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $deporte_id = $_POST["deporte_id"] ?? "";
    $equipo_id = $_POST["equipo_id"] ?? "";
    $nombre = trim($_POST["nombre"] ?? "");
    $posicion = trim($_POST["posicion"] ?? "");
    $fecha_nacimiento = trim($_POST["fecha_nacimiento"] ?? "");
    $tipo_foto = $_POST["tipo_foto"] ?? "url";

    if ($deporte_id === "" || $nombre === "" || !ctype_digit((string)$deporte_id)) {
        header("Location: ../Formularios/jugadores.php?error=vacio");
        exit;
    }

    // Consultar el deporte para saber si requiere equipo (no confiamos solo en el formulario)
    $stmt = $conexion->prepare("SELECT requiere_equipo FROM deportes WHERE id = :id");
    $stmt->execute(["id" => $deporte_id]);
    $deporte = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$deporte) {
        header("Location: ../Formularios/jugadores.php?error=vacio");
        exit;
    }

    if ((int)$deporte["requiere_equipo"] === 1) {
        // Deporte por equipos: el equipo es obligatorio y debe pertenecer a ese deporte
        if ($equipo_id === "" || !ctype_digit((string)$equipo_id)) {
            header("Location: ../Formularios/jugadores.php?error=sin_equipo");
            exit;
        }

        $stmt = $conexion->prepare("SELECT id FROM equipos WHERE id = :equipo_id AND deporte_id = :deporte_id");
        $stmt->execute(["equipo_id" => $equipo_id, "deporte_id" => $deporte_id]);
        if (!$stmt->fetch()) {
            header("Location: ../Formularios/jugadores.php?error=equipo_invalido");
            exit;
        }
    } else {
        // Deporte individual: no lleva equipo
        $equipo_id = null;
    }

    // ---- Categoría por edad ----
    // Si el equipo pertenece a una categoría, el jugador no puede pasar de
    // su edad máxima. Los menores sí pueden: jugar hacia arriba está
    // permitido, jugar hacia abajo no.
    require_once "categorias_reglas.php";

    $categoria = categoria_del_equipo($conexion, $equipo_id);
    $fallo = revisar_edad(edad_actual($fecha_nacimiento), $categoria);

    if ($fallo !== null) {
        header("Location: ../Formularios/jugadores.php?error=" . $fallo
            . "&cat=" . urlencode($categoria["nombre"])
            . "&edad=" . (int)$categoria["edad_maxima"]);
        exit;
    }

    // Resolver la foto según la opción elegida
    $foto = null;

    if ($tipo_foto === "archivo" && isset($_FILES["foto_archivo"]) && $_FILES["foto_archivo"]["error"] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES["foto_archivo"]["error"] !== UPLOAD_ERR_OK) {
            header("Location: ../Formularios/jugadores.php?error=foto_fallo");
            exit;
        }

        [$rutaGuardada, $codigoError] = guardarFotoJugador($_FILES["foto_archivo"]);

        if ($codigoError !== null) {
            header("Location: ../Formularios/jugadores.php?error=" . $codigoError);
            exit;
        }

        $foto = $rutaGuardada;

    } else {
        $fotoUrl = trim($_POST["foto_url"] ?? "");
        $foto = $fotoUrl !== "" ? $fotoUrl : null;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO jugadores (nombre, equipo_id, deporte_id, posicion, foto, fecha_nacimiento)
         VALUES (:nombre, :equipo_id, :deporte_id, :posicion, :foto, :fecha_nacimiento)"
    );
    $stmt->execute([
        "nombre" => $nombre,
        "equipo_id" => $equipo_id,
        "deporte_id" => $deporte_id,
        "posicion" => $posicion !== "" ? $posicion : null,
        "foto" => $foto,
        "fecha_nacimiento" => $fecha_nacimiento !== "" ? $fecha_nacimiento : null,
    ]);

    header("Location: ../Formularios/jugadores.php?exito=creado");
    exit;
}