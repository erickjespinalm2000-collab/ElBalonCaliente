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
    $mensaje_error = "Debes elegir un deporte y una fecha.";
} elseif ($error === "sin_nombre") {
    $mensaje_error = "En un deporte individual debes escribir el nombre del evento.";
} elseif ($error === "sin_equipos") {
    $mensaje_error = "En un deporte por equipos debes elegir el equipo local y el visitante.";
} elseif ($error === "mismo_equipo") {
    $mensaje_error = "Un equipo no puede jugar contra sí mismo.";
} elseif ($error === "equipo_invalido") {
    $mensaje_error = "Los equipos elegidos no pertenecen a ese deporte.";
} elseif ($error === "marcador") {
    $mensaje_error = "Los marcadores deben ser números enteros de 0 en adelante.";
} elseif ($error === "liga_invalida") {
    $mensaje_error = "La liga elegida no pertenece a ese deporte.";
} elseif ($error === "empate_no_permitido") {
    $mensaje_error = "En este deporte no puede haber empate. Revisa el marcador.";
} elseif ($error === "fecha") {
    $mensaje_error = "La fecha y hora no son válidas.";
}

$mensaje_exito = "";
if (($_GET["exito"] ?? "") === "creado") {
    $mensaje_exito = "Evento registrado con éxito.";
} elseif (($_GET["exito"] ?? "") === "actualizado") {
    $mensaje_exito = "Evento actualizado con éxito.";
} elseif (($_GET["exito"] ?? "") === "eliminado") {
    $mensaje_exito = "Evento eliminado.";
}

require_once "../Php/paginacion.php";
require_once "../Php/permisos.php";

$deportes = $conexion->query("SELECT id, nombre, requiere_equipo FROM deportes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipos = $conexion->query("SELECT id, nombre, deporte_id FROM equipos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$ligas = $conexion->query("SELECT id, nombre, temporada, deporte_id FROM ligas ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// ---- Filtros: primero disciplina, después liga ----
require_once "../Php/filtros.php";

$lista_deportes = filtros_deportes($conexion);
$lista_ligas = filtros_ligas($conexion);
$f = filtros_leer("liga");

$condiciones = [];
$parametros = [];

if ($f["deporte"] !== "") {
    $condiciones[] = "eventos.deporte_id = :deporte";
    $parametros["deporte"] = (int)$f["deporte"];
}

if ($f["segundo"] === "sin") {
    $condiciones[] = "eventos.liga_id IS NULL";
} elseif ($f["segundo"] !== "") {
    $condiciones[] = "eventos.liga_id = :liga";
    $parametros["liga"] = (int)$f["segundo"];
}

$donde = count($condiciones) > 0 ? " WHERE " . implode(" AND ", $condiciones) : "";

$sql = "SELECT eventos.id, eventos.fecha, eventos.nombre, eventos.marcador_local, eventos.marcador_visitante,
               deportes.nombre AS deporte, ligas.nombre AS liga,
               local.nombre AS local, visita.nombre AS visitante,
               localidades.nombre AS sede, arbitros.nombre AS arbitro
        FROM eventos
        JOIN deportes ON deportes.id = eventos.deporte_id
        LEFT JOIN ligas ON ligas.id = eventos.liga_id
        LEFT JOIN equipos AS local ON local.id = eventos.equipo_local_id
        LEFT JOIN equipos AS visita ON visita.id = eventos.equipo_visitante_id
        LEFT JOIN localidades ON localidades.id = eventos.localidad_id
        LEFT JOIN arbitros ON arbitros.id = eventos.arbitro_id
        {$donde}
        ORDER BY eventos.fecha DESC, eventos.id DESC
        LIMIT {LIMITE} OFFSET {DESPLAZAMIENTO}";

// El historial de eventos es la lista que más crece, así que se pagina.
$pag = paginar($conexion, "SELECT COUNT(*) FROM eventos" . $donde, $parametros, 20);
$filtros_url = filtros_url($f, "liga");
$sql = str_replace(
    ["{LIMITE}", "{DESPLAZAMIENTO}"],
    [(string)$pag["por_pagina"], (string)$pag["offset"]],
    $sql
);
$stmt = $conexion->prepare($sql);
$stmt->execute($parametros);
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$equipos_bloqueados = false;
// Modo edición: se llega con ?editar=ID
$editando = null;
$editar_id = $_GET["editar"] ?? "";
if ($editar_id !== "" && ctype_digit((string)$editar_id)) {
    $stmt = $conexion->prepare("SELECT id, deporte_id, liga_id, equipo_local_id, equipo_visitante_id, nombre, fecha, marcador_local, marcador_visitante FROM eventos WHERE id = :id");
    $stmt->execute(["id" => $editar_id]);
    $editando = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($editando) {
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM estadisticas_jugador WHERE evento_id = :id");
        $stmt->execute(["id" => $editando["id"]]);
        $equipos_bloqueados = (int)$stmt->fetchColumn() > 0;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Eventos</title>
    <link rel="stylesheet" href="../Css/eventos.css?v=9">
</head>
<body>

    <div class="eventos-contenedor">

        <a href="menu.php" class="volver">&larr; Volver al menú</a>

        <h1>Eventos</h1>
        <p class="subtitulo">Registra partidos entre equipos y competencias individuales</p>

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
            <form action="../Php/<?php echo $editando ? "eventos_actualizar" : "eventos_guardar"; ?>.php" method="POST" class="form-evento">
                <?php if ($editando): ?>
                    <p class="aviso-edicion">Editando el evento del <strong><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($editando["fecha"]))); ?></strong><?php if ($equipos_bloqueados): ?> · los equipos no se pueden cambiar porque ya tiene estadísticas capturadas<?php endif; ?></p>
                    <input type="hidden" name="id" value="<?php echo (int)$editando["id"]; ?>">
                <?php endif; ?>

                <div class="campo">
                    <label for="deporte_id">Deporte</label>
                    <select id="deporte_id" name="deporte_id" required onchange="actualizarFormulario()" <?php echo $editando ? "disabled" : ""; ?>>
                        <option value="">-- Selecciona un deporte --</option>
                        <?php foreach ($deportes as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>">
                                <?php echo htmlspecialchars($d['nombre']); ?>
                                (<?php echo $d['requiere_equipo'] ? "por equipos" : "individual"; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo" id="campo-liga" style="display:none;">
                    <label for="liga_id">Liga (opcional)</label>
                    <select id="liga_id" name="liga_id">
                        <option value="">-- Sin liga (amistoso u otro) --</option>
                    </select>
                </div>

                <div class="campo">
                    <label for="fecha">Fecha y hora</label>
                    <input type="datetime-local" id="fecha" name="fecha" required>
                </div>

                <div id="bloque-equipos" style="display:none;">
                    <div class="campo-doble">
                        <div class="campo">
                            <label for="equipo_local_id">Equipo local</label>
                            <select id="equipo_local_id" name="equipo_local_id"></select>
                        </div>
                        <div class="campo">
                            <label for="equipo_visitante_id">Equipo visitante</label>
                            <select id="equipo_visitante_id" name="equipo_visitante_id"></select>
                        </div>
                    </div>

                    <div class="campo-doble">
                        <div class="campo">
                            <label for="marcador_local">Marcador local</label>
                            <input type="number" id="marcador_local" name="marcador_local" min="0" step="1" value="0">
                        </div>
                        <div class="campo">
                            <label for="marcador_visitante">Marcador visitante</label>
                            <input type="number" id="marcador_visitante" name="marcador_visitante" min="0" step="1" value="0">
                        </div>
                    </div>
                </div>

                <div class="campo" id="campo-nombre" style="display:none;">
                    <label for="nombre" id="etiqueta-nombre">Nombre del evento</label>
                    <input type="text" id="nombre" name="nombre" placeholder="">
                </div>

                <button type="submit" class="btn-agregar"><?php echo $editando ? "Guardar cambios" : "Registrar evento"; ?></button>
                <?php if ($editando): ?><a href="eventos.php" class="btn-cancelar">Cancelar</a><?php endif; ?>
            </form>

            <script>
                const deportes = <?php echo json_encode($deportes); ?>;
                const equipos = <?php echo json_encode($equipos); ?>;
                const ligas = <?php echo json_encode($ligas); ?>;

                function llenar(select, lista, textoInicial, formato) {
                    select.innerHTML = '';
                    const inicial = document.createElement('option');
                    inicial.value = '';
                    inicial.textContent = textoInicial;
                    select.appendChild(inicial);
                    lista.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.id;
                        opt.textContent = formato(item);
                        select.appendChild(opt);
                    });
                }

                <?php if ($editando): ?>
                // Modo edición: se rellena el formulario con los datos del evento
                (function () {
                    const ev = <?php echo json_encode([
                        "deporte_id" => (string)$editando["deporte_id"],
                        "liga_id" => (string)($editando["liga_id"] ?? ""),
                        "local" => (string)($editando["equipo_local_id"] ?? ""),
                        "visita" => (string)($editando["equipo_visitante_id"] ?? ""),
                        "nombre" => $editando["nombre"] ?? "",
                        "fecha" => date("Y-m-d\TH:i", strtotime($editando["fecha"])),
                        "ml" => $editando["marcador_local"],
                        "mv" => $editando["marcador_visitante"],
                        "bloqueados" => $equipos_bloqueados,
                    ]); ?>;
                    document.getElementById('deporte_id').value = ev.deporte_id;
                    actualizarFormulario();
                    document.getElementById('liga_id').value = ev.liga_id;
                    document.getElementById('fecha').value = ev.fecha;
                    document.getElementById('nombre').value = ev.nombre;
                    document.getElementById('equipo_local_id').value = ev.local;
                    document.getElementById('equipo_visitante_id').value = ev.visita;
                    if (ev.ml !== null) { document.getElementById('marcador_local').value = ev.ml; }
                    if (ev.mv !== null) { document.getElementById('marcador_visitante').value = ev.mv; }
                    if (ev.bloqueados) {
                        document.getElementById('equipo_local_id').disabled = true;
                        document.getElementById('equipo_visitante_id').disabled = true;
                    }
                })();
                <?php endif; ?>

                function actualizarFormulario() {
                    const deporteId = document.getElementById('deporte_id').value;
                    const deporte = deportes.find(d => String(d.id) === deporteId);

                    const campoLiga = document.getElementById('campo-liga');
                    const bloqueEquipos = document.getElementById('bloque-equipos');
                    const campoNombre = document.getElementById('campo-nombre');
                    const inputNombre = document.getElementById('nombre');
                    const local = document.getElementById('equipo_local_id');
                    const visita = document.getElementById('equipo_visitante_id');

                    if (!deporte) {
                        campoLiga.style.display = 'none';
                        bloqueEquipos.style.display = 'none';
                        campoNombre.style.display = 'none';
                        local.required = visita.required = inputNombre.required = false;
                        return;
                    }

                    llenar(
                        document.getElementById('liga_id'),
                        ligas.filter(l => String(l.deporte_id) === deporteId),
                        '-- Sin liga (amistoso u otro) --',
                        l => l.nombre + (l.temporada ? ' (' + l.temporada + ')' : '')
                    );
                    campoLiga.style.display = 'block';
                    campoNombre.style.display = 'block';

                    if (Number(deporte.requiere_equipo) === 1) {
                        const delDeporte = equipos.filter(e => String(e.deporte_id) === deporteId);
                        llenar(local, delDeporte, '-- Equipo local --', e => e.nombre);
                        llenar(visita, delDeporte, '-- Equipo visitante --', e => e.nombre);
                        bloqueEquipos.style.display = 'block';
                        local.required = visita.required = true;
                        inputNombre.required = false;
                        document.getElementById('etiqueta-nombre').textContent = 'Nombre del evento (opcional, ej. Jornada 5)';
                        inputNombre.placeholder = 'Ej. Jornada 5';
                    } else {
                        bloqueEquipos.style.display = 'none';
                        local.required = visita.required = false;
                        inputNombre.required = true;
                        document.getElementById('etiqueta-nombre').textContent = 'Nombre de la competencia';
                        inputNombre.placeholder = 'Ej. Final 100m libres';
                    }
                }
            </script>
        <?php endif; ?>

        <h2>Eventos registrados</h2>

        <?php filtros_pintar("eventos.php", $lista_deportes, $lista_ligas, $f, "liga", "Sin liga — amistosos"); ?>

        <p class="conteo-filtro"><?php echo (int)$pag["total"]; ?> evento(s) con estos filtros</p>

        <?php if (count($eventos) === 0): ?>
            <p class="vacio">
                <?php if ($f["deporte"] !== "" || $f["segundo"] !== ""): ?>
                    No hay eventos que cumplan esos filtros.
                    <a href="eventos.php">Ver todos</a>.
                <?php else: ?>
                    Todavía no has registrado ningún evento.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <table class="tabla-eventos">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Deporte</th>
                        <th>Liga</th>
                        <th>Evento</th>
                        <th>Sede</th>
                        <th>Árbitro</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eventos as $e): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($e["fecha"]))); ?></td>
                            <td><?php echo htmlspecialchars($e["deporte"]); ?></td>
                            <td><?php echo htmlspecialchars($e["liga"] ?? "—"); ?></td>
                            <td>
                                <?php if ($e["local"] !== null): ?>
                                    <?php echo htmlspecialchars($e["local"]); ?>
                                    <strong><?php echo (int)$e["marcador_local"]; ?> - <?php echo (int)$e["marcador_visitante"]; ?></strong>
                                    <?php echo htmlspecialchars($e["visitante"]); ?>
                                    <?php if (!empty($e["nombre"])): ?>
                                        <span class="detalle">(<?php echo htmlspecialchars($e["nombre"]); ?>)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($e["nombre"] ?? "—"); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($e["sede"] ?? "—"); ?></td>
                            <td><?php echo htmlspecialchars($e["arbitro"] ?? "—"); ?></td>
                            <td>
                                <a href="eventos.php?editar=<?php echo (int)$e['id']; ?>" class="btn-editar">Editar</a>
                                <?php if (puede_eliminar()): ?>
                                    <a href="../Php/eventos_eliminar.php?id=<?php echo (int)$e['id']; ?>"
                                       class="btn-eliminar"
                                       onclick="return confirm('Esto también borrará las estadísticas registradas en este evento. ¿Continuar?');">
                                       Eliminar
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php echo navegacion_paginas($pag, "eventos.php", $filtros_url); ?>
        <?php endif; ?>

    </div>

</body>
</html>