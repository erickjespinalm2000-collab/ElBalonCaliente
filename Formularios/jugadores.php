<?php
session_start();
require_once "../Php/conexion.php";

if (!isset($_SESSION["usuario_id"], $_SESSION["nombre_usuario"], $_SESSION["rol"])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}

$error = $_GET["error"] ?? "";
$mensaje_error = "";
if ($error === "vacio") {
    $mensaje_error = "Debes elegir un deporte y escribir el nombre.";
} elseif ($error === "sin_equipo") {
    $mensaje_error = "Este deporte se juega en equipos: debes elegir un equipo.";
} elseif ($error === "equipo_invalido") {
    $mensaje_error = "El equipo elegido no pertenece a ese deporte.";
} elseif ($error === "foto_no_png") {
    $mensaje_error = "La foto debe ser un archivo de imagen .png.";
} elseif ($error === "foto_fallo") {
    $mensaje_error = "Hubo un problema al subir la imagen, intenta de nuevo.";
} elseif ($error === "muy_mayor") {
    $mensaje_error = "Ese jugador pasa de la edad de la categoría "
        . ($_GET["cat"] ?? "") . ", que admite hasta " . (int)($_GET["edad"] ?? 0) . " años. "
        . "Puedes inscribirlo en un equipo de categoría mayor.";
} elseif ($error === "sin_fecha") {
    $mensaje_error = "Ese equipo es de la categoría " . ($_GET["cat"] ?? "")
        . ", así que la fecha de nacimiento es obligatoria para comprobar la edad.";
}

$mensaje_exito = "";
if (($_GET["exito"] ?? "") === "creado") {
    $mensaje_exito = "Jugador/atleta agregado con éxito.";
} elseif (($_GET["exito"] ?? "") === "actualizado") {
    $mensaje_exito = "Jugador/atleta actualizado con éxito.";
} elseif (($_GET["exito"] ?? "") === "eliminado") {
    $mensaje_exito = "Jugador/atleta eliminado.";
}

require_once "../Php/paginacion.php";
require_once "../Php/categorias_reglas.php";
require_once "../Php/permisos.php";

$deportes = $conexion->query("SELECT id, nombre, requiere_equipo FROM deportes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipos = $conexion->query("SELECT id, nombre, deporte_id FROM equipos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// ---- Filtros: primero disciplina, después equipo ----
require_once "../Php/filtros.php";

$lista_deportes = filtros_deportes($conexion);
$lista_equipos = filtros_equipos($conexion);
$f = filtros_leer("equipo");

$condiciones = [];
$parametros = [];

if ($f["deporte"] !== "") {
    $condiciones[] = "jugadores.deporte_id = :deporte";
    $parametros["deporte"] = (int)$f["deporte"];
}

if ($f["segundo"] === "sin") {
    $condiciones[] = "jugadores.equipo_id IS NULL";
} elseif ($f["segundo"] !== "") {
    $condiciones[] = "jugadores.equipo_id = :equipo";
    $parametros["equipo"] = (int)$f["segundo"];
}

$donde = count($condiciones) > 0 ? " WHERE " . implode(" AND ", $condiciones) : "";

$pag = paginar($conexion, "SELECT COUNT(*) FROM jugadores" . $donde, $parametros, 20);

$sql = "SELECT jugadores.id, jugadores.nombre, jugadores.posicion, jugadores.foto,
               jugadores.fecha_nacimiento,
               deportes.nombre AS deporte, equipos.nombre AS equipo,
               categorias.nombre AS categoria, categorias.edad_maxima
        FROM jugadores
        JOIN deportes ON deportes.id = jugadores.deporte_id
        LEFT JOIN equipos ON equipos.id = jugadores.equipo_id
        LEFT JOIN categorias ON categorias.id = equipos.categoria_id
        {$donde}
        ORDER BY deportes.nombre ASC, jugadores.nombre ASC
        LIMIT {$pag['por_pagina']} OFFSET {$pag['offset']}";
$stmt = $conexion->prepare($sql);
$stmt->execute($parametros);
$jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Para que la paginación conserve los filtros al cambiar de página
$filtros_url = filtros_url($f, "equipo");

$editando = null;
$editar_id = $_GET["editar"] ?? "";
if ($editar_id !== "" && ctype_digit((string)$editar_id)) {
    $stmt = $conexion->prepare("SELECT id, nombre, equipo_id, deporte_id, posicion, foto, fecha_nacimiento FROM jugadores WHERE id = :id");
    $stmt->execute(["id" => $editar_id]);
    $editando = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Jugadores</title>
    <link rel="stylesheet" href="../Css/jugadores.css?v=9">
    <script src="../Js/imagenes_en_linea.js?v=3" defer></script>
</head>
<body>

    <div class="jugadores-contenedor">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Jugadores / Atletas</h1>
        <p class="subtitulo">Registra a los jugadores de equipos y a los atletas individuales</p>

        <?php if ($mensaje_error !== ""): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <?php if ($mensaje_exito !== ""): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <?php if (count($deportes) === 0): ?>
            <p class="vacio">
                Todavía no hay deportes registrados.
                <a href="deportes.php">Agrega uno primero</a>.
            </p>
        <?php else: ?>
            <form action="../Php/<?php echo $editando ? "jugadores_actualizar" : "jugadores_guardar"; ?>.php" method="POST" class="form-jugador" enctype="multipart/form-data">
                <?php if ($editando): ?>
                    <p class="aviso-edicion">Editando: <strong><?php echo htmlspecialchars($editando["nombre"]); ?></strong></p>
                    <input type="hidden" name="id" value="<?php echo (int)$editando["id"]; ?>">
                <?php endif; ?>

                <div class="campo">
                    <label for="deporte_id">Deporte</label>
                    <select id="deporte_id" name="deporte_id" required onchange="actualizarEquipos()" <?php echo $editando ? "disabled" : ""; ?>>
                        <option value="">-- Selecciona un deporte --</option>
                        <?php foreach ($deportes as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>" <?php echo ($editando && (int)$editando["deporte_id"] === (int)$d['id']) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($d['nombre']); ?>
                                (<?php echo $d['requiere_equipo'] ? "por equipos" : "individual"; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo" id="campo-equipo" style="display:none;">
                    <label for="equipo_id">Equipo</label>
                    <select id="equipo_id" name="equipo_id">
                        <option value="">-- Selecciona un equipo --</option>
                    </select>
                </div>

                <div class="campo">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" placeholder="Ej. Carlos Pérez" required value="<?php echo htmlspecialchars($editando["nombre"] ?? ""); ?>">
                </div>

                <div class="campo-doble">
                    <div class="campo">
                        <label for="posicion">Posición / especialidad (opcional)</label>
                        <input type="text" id="posicion" name="posicion" placeholder="Ej. Delantero, 100m libres" value="<?php echo htmlspecialchars($editando["posicion"] ?? ""); ?>">
                    </div>

                    <div class="campo">
                        <label for="fecha_nacimiento">Fecha de nacimiento (opcional)</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo htmlspecialchars($editando["fecha_nacimiento"] ?? ""); ?>">
                    </div>
                </div>

                <div class="campo">
                    <label>Foto (opcional)</label>
                    <?php if ($editando && !empty($editando["foto"])): ?>
                        <?php $foto_actual = str_starts_with($editando["foto"], "http") ? $editando["foto"] : "../" . $editando["foto"]; ?>
                        <p class="logo-actual">Foto actual: <img src="<?php echo htmlspecialchars($foto_actual); ?>" alt="" class="foto-mini"></p>
                    <?php endif; ?>

                    <div class="opciones-foto">
                        <?php if ($editando): ?>
                            <label class="opcion-radio"><input type="radio" name="tipo_foto" value="mantener" checked onchange="mostrarCampoFoto('mantener')"> Mantener la actual</label>
                            <label class="opcion-radio"><input type="radio" name="tipo_foto" value="quitar" onchange="mostrarCampoFoto('quitar')"> Quitar foto</label>
                        <?php endif; ?>
                        <label class="opcion-radio">
                            <input type="radio" name="tipo_foto" value="url" <?php echo $editando ? "" : "checked"; ?> onchange="mostrarCampoFoto('url')">
                            Insertar imagen desde internet
                        </label>
                        <label class="opcion-radio">
                            <input type="radio" name="tipo_foto" value="archivo" onchange="mostrarCampoFoto('archivo')">
                            Subir imagen .png
                        </label>
                    </div>

                    <div id="campo-foto-url" <?php echo $editando ? 'style="display:none;"' : ""; ?>>
                        <input type="hidden" name="foto_url" id="foto_url" value="<?php echo htmlspecialchars(($editando && str_starts_with((string)$editando["foto"], "http")) ? $editando["foto"] : ""); ?>">
                        <div class="imagen-linea" data-imagen-linea data-campo="foto_url" data-consulta="#nombre" data-sufijo="" data-incluir-jpg="1"></div>
                    </div>

                    <div id="campo-foto-archivo" style="display:none;">
                        <input type="file" name="foto_archivo" accept=".png,image/png">
                    </div>
                </div>

                <button type="submit" class="btn-agregar"><?php echo $editando ? "Guardar cambios" : "Agregar"; ?></button>
                <?php if ($editando): ?><a href="jugadores.php" class="btn-cancelar">Cancelar</a><?php endif; ?>
            </form>

            <script>
                const deportes = <?php echo json_encode($deportes); ?>;
                const equipos = <?php echo json_encode($equipos); ?>;

                function actualizarEquipos() {
                    const deporteId = document.getElementById('deporte_id').value;
                    const campoEquipo = document.getElementById('campo-equipo');
                    const selectEquipo = document.getElementById('equipo_id');

                    selectEquipo.innerHTML = '<option value="">-- Selecciona un equipo --</option>';

                    const deporte = deportes.find(d => String(d.id) === deporteId);

                    if (!deporte || Number(deporte.requiere_equipo) === 0) {
                        campoEquipo.style.display = 'none';
                        selectEquipo.required = false;
                        return;
                    }

                    equipos
                        .filter(e => String(e.deporte_id) === deporteId)
                        .forEach(e => {
                            const opt = document.createElement('option');
                            opt.value = e.id;
                            opt.textContent = e.nombre;
                            selectEquipo.appendChild(opt);
                        });

                    campoEquipo.style.display = 'block';
                    selectEquipo.required = true;
                }

                <?php if ($editando): ?>
                document.getElementById('deporte_id').value = <?php echo json_encode((string)$editando["deporte_id"]); ?>;
                actualizarEquipos();
                document.getElementById('equipo_id').value = <?php echo json_encode((string)($editando["equipo_id"] ?? "")); ?>;
                <?php endif; ?>

                function mostrarCampoFoto(tipo) {
                    document.getElementById('campo-foto-url').style.display = (tipo === 'url') ? 'block' : 'none';
                    document.getElementById('campo-foto-archivo').style.display = (tipo === 'archivo') ? 'block' : 'none';
                }
            </script>
        <?php endif; ?>

        <h2>Registrados</h2>

        <?php filtros_pintar("jugadores.php", $lista_deportes, $lista_equipos, $f, "equipo", "Sin equipo — atletas individuales"); ?>

        <p class="conteo-filtro"><?php echo (int)$pag["total"]; ?> jugador(es) con estos filtros</p>

        <?php if (count($jugadores) === 0): ?>
            <p class="vacio">
                <?php if ($f["deporte"] !== "" || $f["segundo"] !== ""): ?>
                    No hay jugadores que cumplan esos filtros.
                    <a href="jugadores.php">Ver todos</a>.
                <?php else: ?>
                    Todavía no has agregado a nadie.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <table class="tabla-jugadores">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Nombre</th>
                        <th>Deporte</th>
                        <th>Equipo</th>
                        <th>Categoría</th>
                        <th>Edad</th>
                        <th>Posición</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jugadores as $j): ?>
                        <tr>
                            <td>
                                <?php if (!empty($j["foto"])): ?>
                                    <?php
                                        $src = str_starts_with($j["foto"], "http")
                                            ? $j["foto"]
                                            : "../" . $j["foto"];
                                    ?>
                                    <img src="<?php echo htmlspecialchars($src); ?>" alt="" class="foto-mini">
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($j["nombre"]); ?></td>
                            <td><?php echo htmlspecialchars($j["deporte"]); ?></td>
                            <td><?php echo htmlspecialchars($j["equipo"] ?? "Individual"); ?></td>
                            <td>
                                <?php if ($j["categoria"] !== null): ?>
                                    <span class="etiqueta-categoria" title="Hasta <?php echo (int)$j['edad_maxima']; ?> años">
                                        <?php echo htmlspecialchars($j["categoria"]); ?>
                                    </span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $edad = edad_actual($j["fecha_nacimiento"]);
                                    echo $edad !== null ? $edad . " años" : "—";
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($j["posicion"] ?? "—"); ?></td>
                            <td>
                                <a href="jugadores.php?editar=<?php echo (int)$j['id']; ?>" class="btn-editar">Editar</a>
                                <?php if (puede_eliminar()): ?>
                                    <a href="../Php/jugadores_eliminar.php?id=<?php echo (int)$j['id']; ?>"
                                       class="btn-eliminar"
                                       onclick="return confirm('Esto también borrará sus estadísticas. ¿Continuar?');">
                                       Eliminar
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php echo navegacion_paginas($pag, "jugadores.php", $filtros_url); ?>
        <?php endif; ?>

    </div>

</body>
</html>