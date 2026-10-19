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

/**
 * Valida y guarda el logo subido como archivo, exigiendo que sea realmente un PNG
 * (revisa tanto la extensión como el tipo real del archivo, no solo el nombre).
 *
 * Devuelve [ruta_guardada, codigo_error]. Si todo sale bien, codigo_error es null.
 */
function guardarLogoEquipo(array $archivo): array
{
    $extension = strtolower(pathinfo($archivo["name"], PATHINFO_EXTENSION));
    if ($extension !== "png") {
        return [null, "logo_no_png"];
    }

    $tipoReal = mime_content_type($archivo["tmp_name"]);
    if ($tipoReal !== "image/png") {
        return [null, "logo_no_png"];
    }

    $carpetaDestino = __DIR__ . "/../Logos/Equipos/";
    if (!is_dir($carpetaDestino)) {
        mkdir($carpetaDestino, 0755, true);
    }

    $nombreArchivo = "equipo_" . uniqid() . ".png";
    $rutaCompleta = $carpetaDestino . $nombreArchivo;

    if (!move_uploaded_file($archivo["tmp_name"], $rutaCompleta)) {
        return [null, "logo_fallo"];
    }

    // Ruta relativa a la raíz del proyecto, para guardar en la base de datos
    return ["Logos/Equipos/" . $nombreArchivo, null];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $deporte_id = $_POST["deporte_id"] ?? "";
    $nombre = trim($_POST["nombre"] ?? "");
    $tipo_logo = $_POST["tipo_logo"] ?? "url";
    $categoria_id = trim((string)($_POST["categoria_id"] ?? ""));

    if ($deporte_id === "" || $nombre === "" || !ctype_digit((string)$deporte_id)) {
        header("Location: ../Formularios/equipos.php?error=vacio");
        exit;
    }

    // La categoría es opcional, pero si viene tiene que pertenecer a este
    // mismo deporte: una categoría de natación no puede terminar en un
    // equipo de fútbol.
    if ($categoria_id !== "") {
        if (!ctype_digit($categoria_id)) {
            header("Location: ../Formularios/equipos.php?error=categoria_invalida");
            exit;
        }
        $stmt = $conexion->prepare("SELECT id FROM categorias WHERE id = :id AND deporte_id = :d");
        $stmt->execute(["id" => $categoria_id, "d" => $deporte_id]);
        if (!$stmt->fetch()) {
            header("Location: ../Formularios/equipos.php?error=categoria_invalida");
            exit;
        }
    }

    // Verificar que no exista ya un equipo con ese nombre en ese deporte
    $stmt = $conexion->prepare("SELECT id FROM equipos WHERE nombre = :nombre AND deporte_id = :deporte_id");
    $stmt->execute(["nombre" => $nombre, "deporte_id" => $deporte_id]);
    if ($stmt->fetch()) {
        header("Location: ../Formularios/equipos.php?error=duplicado");
        exit;
    }

    // Resolver el logo según la opción elegida
    $logo = null;

    if ($tipo_logo === "archivo" && isset($_FILES["logo_archivo"]) && $_FILES["logo_archivo"]["error"] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES["logo_archivo"]["error"] !== UPLOAD_ERR_OK) {
            header("Location: ../Formularios/equipos.php?error=logo_fallo");
            exit;
        }

        [$rutaGuardada, $codigoError] = guardarLogoEquipo($_FILES["logo_archivo"]);

        if ($codigoError !== null) {
            header("Location: ../Formularios/equipos.php?error=" . $codigoError);
            exit;
        }

        $logo = $rutaGuardada;

    } else {
        $logoUrl = trim($_POST["logo_url"] ?? "");
        $logo = $logoUrl !== "" ? $logoUrl : null;
    }

    $stmt = $conexion->prepare(
        "INSERT INTO equipos (nombre, deporte_id, categoria_id, logo)
         VALUES (:nombre, :deporte_id, :categoria_id, :logo)"
    );
    $stmt->execute([
        "nombre" => $nombre,
        "deporte_id" => $deporte_id,
        "categoria_id" => $categoria_id !== "" ? (int)$categoria_id : null,
        "logo" => $logo,
    ]);

    header("Location: ../Formularios/equipos.php?exito=creado");
    exit;
}