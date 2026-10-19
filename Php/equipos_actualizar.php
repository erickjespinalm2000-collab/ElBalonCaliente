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

function volver(string $id, string $codigo): void
{
    header("Location: ../Formularios/equipos.php?editar=" . urlencode($id) . "&error=" . $codigo);
    exit;
}

function guardarLogoEquipo(array $archivo): array
{
    if (strtolower(pathinfo($archivo["name"], PATHINFO_EXTENSION)) !== "png") {
        return [null, "logo_no_png"];
    }
    if (mime_content_type($archivo["tmp_name"]) !== "image/png") {
        return [null, "logo_no_png"];
    }

    $carpeta = __DIR__ . "/../Logos/Equipos/";
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0755, true);
    }

    $nombre = "equipo_" . uniqid() . ".png";
    if (!move_uploaded_file($archivo["tmp_name"], $carpeta . $nombre)) {
        return [null, "logo_fallo"];
    }
    return ["Logos/Equipos/" . $nombre, null];
}

function borrarLogoSubido(?string $ruta): void
{
    if ($ruta === null || strpos($ruta, "Logos/Equipos/") !== 0 || strpos($ruta, "..") !== false) {
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
    $tipo_logo = $_POST["tipo_logo"] ?? "mantener";

    if ($id === "" || !ctype_digit((string)$id)) {
        header("Location: ../Formularios/equipos.php");
        exit;
    }

    $stmt = $conexion->prepare("SELECT deporte_id, logo FROM equipos WHERE id = :id");
    $stmt->execute(["id" => $id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) {
        header("Location: ../Formularios/equipos.php");
        exit;
    }

    if ($nombre === "") {
        volver($id, "vacio");
    }

    $stmt = $conexion->prepare("SELECT id FROM equipos WHERE nombre = :nombre AND deporte_id = :d AND id <> :id");
    $stmt->execute(["nombre" => $nombre, "d" => $actual["deporte_id"], "id" => $id]);
    if ($stmt->fetch()) {
        volver($id, "duplicado");
    }

    // ---- Categoría por edad ----
    $categoria_id = trim((string)($_POST["categoria_id"] ?? ""));

    if ($categoria_id !== "") {
        if (!ctype_digit($categoria_id)) {
            volver($id, "categoria_invalida");
        }
        $stmt = $conexion->prepare("SELECT edad_maxima FROM categorias WHERE id = :id AND deporte_id = :d");
        $stmt->execute(["id" => $categoria_id, "d" => $actual["deporte_id"]]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cat) {
            volver($id, "categoria_invalida");
        }

        // Cambiar la categoría de un equipo que ya tiene plantilla puede
        // dejar jugadores fuera de regla. En lugar de guardarlo y dejar
        // datos inconsistentes, se rechaza y se dice a quién afecta.
        $stmt = $conexion->prepare(
            "SELECT COUNT(*) FROM jugadores
             WHERE equipo_id = :id
               AND fecha_nacimiento IS NOT NULL
               AND TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) > :edad"
        );
        $stmt->execute(["id" => $id, "edad" => (int)$cat["edad_maxima"]]);
        if ((int)$stmt->fetchColumn() > 0) {
            volver($id, "categoria_choca");
        }
    }

    $logo = $actual["logo"];

    if ($tipo_logo === "quitar") {
        borrarLogoSubido($logo);
        $logo = null;

    } elseif ($tipo_logo === "url") {
        $url = trim($_POST["logo_url"] ?? "");
        if ($url !== "" && $url !== $logo) {
            borrarLogoSubido($logo);
            $logo = $url;
        }

    } elseif ($tipo_logo === "archivo"
              && isset($_FILES["logo_archivo"])
              && $_FILES["logo_archivo"]["error"] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES["logo_archivo"]["error"] !== UPLOAD_ERR_OK) {
            volver($id, "logo_fallo");
        }
        [$nueva, $codigo] = guardarLogoEquipo($_FILES["logo_archivo"]);
        if ($codigo !== null) {
            volver($id, $codigo);
        }
        borrarLogoSubido($logo);
        $logo = $nueva;
    }

    $stmt = $conexion->prepare(
        "UPDATE equipos SET nombre = :nombre, categoria_id = :categoria_id, logo = :logo WHERE id = :id"
    );
    $stmt->execute([
        "nombre" => $nombre,
        "categoria_id" => $categoria_id !== "" ? (int)$categoria_id : null,
        "logo" => $logo,
        "id" => $id,
    ]);

    header("Location: ../Formularios/equipos.php?exito=actualizado");
    exit;
}