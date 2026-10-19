<?php
session_start();
require_once "../Php/conexion.php";

if (!isset($_SESSION["usuario_id"], $_SESSION["nombre_usuario"], $_SESSION["rol"])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}

require_once "../Php/permisos.php";
require_once "../Php/llaves_logica.php";
require_once "../Php/filtros.php";

$filtro_deporte = filtro_deporte_leer();
$lista_deportes = filtros_deportes($conexion);

$error = $_GET["error"] ?? "";
$mensaje_error = "";
if ($error === "liga") {
    $mensaje_error = "Elige una liga válida.";
} elseif ($error === "formato") {
    $mensaje_error = "Esa liga no admite eliminación directa. Cambia su formato en Ligas.";
} elseif ($error === "pocos") {
    $mensaje_error = "Hacen falta al menos 2 equipos en la disciplina para armar una llave.";
} elseif ($error === "existe") {
    $mensaje_error = "Esa liga ya tiene una llave. Elimínala primero si quieres rehacerla.";
}

$exito = $_GET["exito"] ?? "";
$mensaje_exito = "";
if ($exito === "creada") {
    $mensaje_exito = "Llave generada con éxito.";
} elseif ($exito === "eliminada") {
    $mensaje_exito = "Llave eliminada.";
} elseif ($exito === "avanzo") {
    $mensaje_exito = "Ganador registrado. El cuadro se actualizó.";
}

// Solo las ligas cuyo formato contempla eliminación directa pueden tener
// cuadro. Una liga de solo tabla de posiciones no tiene llaves que dibujar.
$ligas = $conexion->query(
    "SELECT ligas.id, ligas.nombre, ligas.temporada, ligas.formato,
            deportes.nombre AS deporte,
            (SELECT COUNT(*) FROM equipos WHERE equipos.deporte_id = ligas.deporte_id) AS equipos,
            (SELECT COUNT(*) FROM llaves WHERE llaves.liga_id = ligas.id) AS tiene_llave
     FROM ligas
     JOIN deportes ON deportes.id = ligas.deporte_id
     WHERE deportes.requiere_equipo = 1
       AND ligas.formato IN ('eliminacion', 'ambas')
     ORDER BY deportes.nombre ASC, ligas.nombre ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Cuántas ligas hay en total, para distinguir "no hay ligas" de
// "hay ligas pero ninguna es de eliminación".
$ligas_totales = (int)$conexion->query(
    "SELECT COUNT(*) FROM ligas
     JOIN deportes ON deportes.id = ligas.deporte_id
     WHERE deportes.requiere_equipo = 1"
)->fetchColumn();

// ---- La llave que se está mirando ----
$llave = null;
$cuadro = [];
$ver = $_GET["ver"] ?? "";

if ($ver !== "" && ctype_digit((string)$ver)) {
    $stmt = $conexion->prepare(
        "SELECT llaves.*, ligas.nombre AS liga, deportes.nombre AS deporte
         FROM llaves
         JOIN ligas ON ligas.id = llaves.liga_id
         JOIN deportes ON deportes.id = ligas.deporte_id
         WHERE llaves.id = :id"
    );
    $stmt->execute(["id" => $ver]);
    $llave = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($llave) {
    $stmt = $conexion->prepare(
        "SELECT lp.*,
                a.nombre AS nombre_a, a.logo AS logo_a,
                b.nombre AS nombre_b, b.logo AS logo_b,
                g.nombre AS nombre_ganador
         FROM llave_partidos lp
         LEFT JOIN equipos a ON a.id = lp.equipo_a_id
         LEFT JOIN equipos b ON b.id = lp.equipo_b_id
         LEFT JOIN equipos g ON g.id = lp.ganador_id
         WHERE lp.llave_id = :id
         ORDER BY lp.ronda ASC, lp.posicion ASC"
    );
    $stmt->execute(["id" => $llave["id"]]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $cuadro[(int)$p["ronda"]][] = $p;
    }
}

$donde_llaves = $filtro_deporte !== "" ? " WHERE deportes.id = :deporte" : "";
$stmt = $conexion->prepare(
    "SELECT llaves.id, llaves.nombre, llaves.rondas, ligas.nombre AS liga,
            deportes.nombre AS deporte, ligas.formato
     FROM llaves
     JOIN ligas ON ligas.id = llaves.liga_id
     JOIN deportes ON deportes.id = ligas.deporte_id"
     . $donde_llaves .
    " ORDER BY llaves.fecha_creacion DESC"
);
$stmt->execute($filtro_deporte !== "" ? ["deporte" => (int)$filtro_deporte] : []);
$llaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

/** Dibuja el logo del equipo, o un hueco si no tiene. */
function escudo(?string $logo): string
{
    if ($logo === null || $logo === "") {
        return '<span class="escudo-vacio"></span>';
    }
    $src = str_starts_with($logo, "http") ? $logo : "../" . $logo;
    return '<img class="escudo-llave" src="' . htmlspecialchars($src) . '" alt="">';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Llaves de eliminación</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/fase2.css?v=9">
    <link rel="stylesheet" href="../Css/llaves.css?v=9">
</head>
<body>

    <div class="pantalla pantalla-ancha">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Llaves de eliminación directa</h1>
        <p class="subtitulo">
            El organigrama de cruces de una liga. Los equipos se emparejan
            el primero contra el último, de modo que los dos mejores
            sembrados solo pueden encontrarse en la final. Si el número de
            equipos no es potencia de dos, los que sobran pasan directo.
        </p>

        <?php if ($mensaje_error !== ""): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <?php if ($mensaje_exito !== ""): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <?php if ($llave): ?>

            <!-- ================= El organigrama ================= -->

            <div class="bloque-cabecera">
                <div>
                    <span class="bloque-titulo"><?php echo htmlspecialchars($llave["nombre"]); ?></span>
                    <p class="bloque-nota">
                        <?php echo htmlspecialchars($llave["deporte"] . " · " . $llave["liga"]); ?>
                        &middot; <?php echo (int)$llave["rondas"]; ?> rondas
                    </p>
                </div>
                <a href="llaves.php" class="btn-cancelar">Ver todas las llaves</a>
            </div>

            <div class="cuadro-scroll">
                <div class="cuadro">
                    <?php for ($r = 1; $r <= (int)$llave["rondas"]; $r++): ?>
                        <div class="ronda">
                            <h3 class="ronda-titulo"><?php echo htmlspecialchars(nombre_ronda($r, (int)$llave["rondas"])); ?></h3>

                            <?php foreach ($cuadro[$r] ?? [] as $p): ?>
                                <?php
                                    $gana_a = $p["ganador_id"] !== null && (int)$p["ganador_id"] === (int)$p["equipo_a_id"];
                                    $gana_b = $p["ganador_id"] !== null && (int)$p["ganador_id"] === (int)$p["equipo_b_id"];
                                    $listo = $p["equipo_a_id"] !== null && $p["equipo_b_id"] !== null;
                                ?>
                                <div class="partido <?php echo $p["ganador_id"] !== null ? "partido-resuelto" : ""; ?>">

                                    <div class="lado <?php echo $gana_a ? "lado-gana" : ($p["ganador_id"] !== null ? "lado-pierde" : ""); ?>">
                                        <?php echo escudo($p["logo_a"]); ?>
                                        <span class="lado-nombre">
                                            <?php echo htmlspecialchars($p["nombre_a"] ?? "Por definir"); ?>
                                        </span>
                                        <?php if ($listo && $p["ganador_id"] === null && es_admin()): ?>
                                            <a class="btn-gana"
                                               href="../Php/llaves_avanzar.php?partido=<?php echo (int)$p["id"]; ?>&ganador=<?php echo (int)$p["equipo_a_id"]; ?>"
                                               onclick="return confirm('¿Marcar a <?php echo htmlspecialchars($p["nombre_a"]); ?> como ganador?');">gana</a>
                                        <?php endif; ?>
                                    </div>

                                    <div class="lado <?php echo $gana_b ? "lado-gana" : ($p["ganador_id"] !== null ? "lado-pierde" : ""); ?>">
                                        <?php echo escudo($p["logo_b"]); ?>
                                        <span class="lado-nombre">
                                            <?php echo htmlspecialchars($p["nombre_b"] ?? ($p["equipo_a_id"] !== null && $r === 1 ? "Pase directo" : "Por definir")); ?>
                                        </span>
                                        <?php if ($listo && $p["ganador_id"] === null && es_admin()): ?>
                                            <a class="btn-gana"
                                               href="../Php/llaves_avanzar.php?partido=<?php echo (int)$p["id"]; ?>&ganador=<?php echo (int)$p["equipo_b_id"]; ?>"
                                               onclick="return confirm('¿Marcar a <?php echo htmlspecialchars($p["nombre_b"]); ?> como ganador?');">gana</a>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endfor; ?>

                    <?php
                        $final = end($cuadro) ?: [];
                        $campeon = $final[0]["nombre_ganador"] ?? null;
                    ?>
                    <div class="ronda ronda-campeon">
                        <h3 class="ronda-titulo">Campeón</h3>
                        <div class="partido partido-campeon">
                            <div class="lado lado-gana">
                                <span class="lado-nombre">
                                    <?php echo $campeon !== null ? htmlspecialchars($campeon) : "Por definir"; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

        <!-- ================= Crear y listar ================= -->

        <?php if (es_admin()): ?>
            <h2>Generar una llave nueva</h2>

            <?php if ($ligas_totales === 0): ?>
                <p class="vacio">
                    No hay ligas de deportes por equipos.
                    <a href="ligas.php">Crea una primero</a>.
                </p>
            <?php elseif (count($ligas) === 0): ?>
                <p class="vacio">
                    Tienes <?php echo $ligas_totales; ?> liga(s), pero ninguna está configurada
                    con eliminación directa, así que no hay cuadro que dibujar.
                    <br><br>
                    Entra en <a href="ligas.php">Ligas</a>, edita la que quieras y cambia su
                    <strong>formato</strong> a «Eliminación directa» o «Ambas».
                </p>
            <?php else: ?>
                <form action="../Php/llaves_generar.php" method="POST" class="formulario">
                    <div class="campo">
                        <label for="liga_id">Liga</label>
                        <select id="liga_id" name="liga_id" required>
                            <option value="">-- Selecciona la liga --</option>
                            <?php foreach ($ligas as $l): ?>
                                <option value="<?php echo (int)$l["id"]; ?>" <?php echo $l["tiene_llave"] ? "disabled" : ""; ?>>
                                    <?php echo htmlspecialchars($l["deporte"] . " · " . $l["nombre"]
                                        . " — " . $l["equipos"] . " equipos"
                                        . ($l["formato"] === "ambas" ? " · liga + eliminatoria" : " · eliminación directa")
                                        . ($l["tiene_llave"] ? " (ya tiene llave)" : "")); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label class="opcion">
                            <input type="checkbox" name="al_azar" value="1" checked>
                            Sortear el orden de los equipos
                        </label>
                        <p class="ayuda">
                            Si lo desmarcas, se siembran por orden alfabético
                            en lugar de al azar.
                        </p>
                    </div>

                    <button type="submit" class="btn-agregar">Generar llave</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <h2>Llaves existentes</h2>

        <?php filtro_deporte_pintar("llaves.php", $lista_deportes, $filtro_deporte, count($llaves), "llave(s)"); ?>

        <?php if (count($llaves) === 0): ?>
            <p class="vacio">Todavía no hay ninguna llave.</p>
        <?php else: ?>
            <div class="tabla-scroll">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Llave</th>
                            <th>Disciplina</th>
                            <th>Liga</th>
                            <th class="numero">Rondas</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($llaves as $l): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($l["nombre"]); ?></strong></td>
                                <td><?php echo htmlspecialchars($l["deporte"]); ?></td>
                                <td><?php echo htmlspecialchars($l["liga"]); ?></td>
                                <td class="numero"><?php echo (int)$l["rondas"]; ?></td>
                                <td>
                                    <a href="llaves.php?ver=<?php echo (int)$l["id"]; ?>" class="btn-editar">Ver cuadro</a>
                                    <?php if (es_admin()): ?>
                                        <a href="../Php/llaves_eliminar.php?id=<?php echo (int)$l["id"]; ?>"
                                           class="btn-eliminar"
                                           onclick="return confirm('¿Eliminar esta llave y todos sus cruces?');">
                                           Eliminar
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
