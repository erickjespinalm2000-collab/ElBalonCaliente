<?php
session_start();
require_once "../Php/conexion.php";

if (!isset($_SESSION["usuario_id"], $_SESSION["nombre_usuario"], $_SESSION["rol"])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}

$deportes = $conexion->query("SELECT id, nombre, icono FROM deportes ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$deporte_id = $_GET["deporte_id"] ?? "";
$liga_id = $_GET["liga_id"] ?? "";

if ($deporte_id === "" || !ctype_digit((string)$deporte_id)) {
    $mas_usado = $conexion->query("SELECT deporte_id FROM eventos GROUP BY deporte_id ORDER BY COUNT(*) DESC LIMIT 1")->fetchColumn();
    $deporte_id = $mas_usado !== false ? (string)$mas_usado : (count($deportes) ? (string)$deportes[0]["id"] : "");
}

$ligas = [];
if ($deporte_id !== "") {
    $stmt = $conexion->prepare("SELECT id, nombre, temporada FROM ligas WHERE deporte_id = :d ORDER BY nombre ASC");
    $stmt->execute(["d" => $deporte_id]);
    $ligas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$liga_valida = false;
foreach ($ligas as $l) {
    if ((string)$l["id"] === (string)$liga_id) {
        $liga_valida = true;
    }
}
if (!$liga_valida) {
    $liga_id = "";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Dashboard</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/dashboard.css?v=9">
</head>
<body>

    <div class="dashboard-pagina">

        <header class="cabecera">
            <div>
                <a href="menu.php" class="volver">&larr; Volver al menú</a>
                <h1>Dashboard de estadísticas</h1>
                <p class="subtitulo">Rendimiento histórico por deporte y liga</p>
            </div>
            <img src="../Informacion/Logo.png" alt="El Balón Caliente" class="logo-dashboard" width="90">
        </header>

        <?php if (count($deportes) === 0): ?>
            <p class="vacio">
                Todavía no hay deportes registrados.
                <a href="deportes.php">Agrega uno primero</a>.
            </p>
        <?php else: ?>

            <form method="GET" action="dashboard.php" class="barra-filtros">
                <div class="campo">
                    <label for="deporte_id">Deporte</label>
                    <select id="deporte_id" name="deporte_id"
                            onchange="document.getElementById('liga_id').value=''; this.form.submit();">
                        <?php foreach ($deportes as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>"
                                <?php echo ((string)$d['id'] === (string)$deporte_id) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars(trim(($d['icono'] ?? "") . " " . $d['nombre'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="liga_id">Liga</label>
                    <select id="liga_id" name="liga_id" onchange="this.form.submit()">
                        <option value="">Todas las ligas y eventos</option>
                        <?php foreach ($ligas as $l): ?>
                            <option value="<?php echo (int)$l['id']; ?>"
                                <?php echo ((string)$l['id'] === (string)$liga_id) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($l['nombre'] . ($l['temporada'] ? " (" . $l['temporada'] . ")" : "")); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="actualizacion">
                    <label class="opcion-checkbox">
                        <input type="checkbox" id="auto" checked>
                        Actualizar cada 15 s
                    </label>
                    <button type="button" id="btn-actualizar" class="btn-actualizar">Actualizar ahora</button>
                    <span id="estado" class="estado">Cargando...</span>
                </div>
            </form>

            <div id="aviso-chart" class="mensaje-error" hidden>
                No se pudo cargar la librería de gráficos. Comprueba que exista el archivo
                <strong>Js/chart.umd.min.js</strong> en la carpeta del proyecto.
            </div>

            <section class="indicadores">
                <div class="indicador"><span class="numero" id="kpi-eventos">–</span><span class="texto">Eventos</span></div>
                <div class="indicador"><span class="numero" id="kpi-equipos">–</span><span class="texto">Equipos</span></div>
                <div class="indicador"><span class="numero" id="kpi-jugadores">–</span><span class="texto">Jugadores / atletas</span></div>
                <div class="indicador"><span class="numero" id="kpi-registros">–</span><span class="texto">Datos estadísticos</span></div>
            </section>

            <section class="rejilla-graficos">

                <article class="tarjeta-grafico ancho" id="tarjeta-lideres">
                    <div class="encabezado-grafico">
                        <h3>Mejores jugadores</h3>
                        <select id="selector-estadistica" aria-label="Estadística"></select>
                    </div>
                    <div class="caja-grafico"><canvas id="grafico-lideres"></canvas></div>
                    <p class="sin-datos" hidden>Todavía no hay estadísticas registradas para este filtro.</p>
                </article>

                <article class="tarjeta-grafico solo-equipos" id="tarjeta-equipos">
                    <div class="encabezado-grafico"><h3>Equipos: a favor y en contra</h3></div>
                    <div class="caja-grafico"><canvas id="grafico-equipos"></canvas></div>
                    <p class="sin-datos" hidden>Todavía no hay partidos con marcador.</p>
                </article>

                <article class="tarjeta-grafico solo-equipos" id="tarjeta-puntos">
                    <div class="encabezado-grafico"><h3>Puntos por equipo</h3></div>
                    <div class="caja-grafico"><canvas id="grafico-puntos"></canvas></div>
                    <p class="sin-datos" hidden>Todavía no hay partidos con marcador.</p>
                </article>

                <article class="tarjeta-grafico solo-equipos" id="tarjeta-resultados">
                    <div class="encabezado-grafico"><h3>Resultados de los partidos</h3></div>
                    <div class="caja-grafico"><canvas id="grafico-resultados"></canvas></div>
                    <p class="sin-datos" hidden>Todavía no hay partidos con marcador.</p>
                </article>

                <article class="tarjeta-grafico solo-equipos" id="tarjeta-tendencia">
                    <div class="encabezado-grafico"><h3>Anotación total por fecha</h3></div>
                    <div class="caja-grafico"><canvas id="grafico-tendencia"></canvas></div>
                    <p class="sin-datos" hidden>Todavía no hay partidos con marcador.</p>
                </article>

                <article class="tarjeta-grafico ancho" id="tarjeta-disciplinas">
                    <div class="encabezado-grafico"><h3>Actividad por disciplina (todos los deportes)</h3></div>
                    <div class="caja-grafico"><canvas id="grafico-disciplinas"></canvas></div>
                    <p class="sin-datos" hidden>Todavía no hay deportes con eventos.</p>
                </article>

            </section>

        <?php endif; ?>

    </div>

    <?php if (count($deportes) > 0): ?>
    <!-- Chart.js 4.4.1 guardado dentro del proyecto: los gráficos funcionan
         sin conexión a internet. Si algún día quieres actualizarlo, reemplaza
         el archivo Js/chart.umd.min.js por la versión nueva. -->
    <script src="../Js/chart.umd.min.js"></script>
    <script>
        const FILTROS = {
            deporte_id: <?php echo json_encode((string)$deporte_id); ?>,
            liga_id: <?php echo json_encode((string)$liga_id); ?>
        };
        const URL_DATOS = "../Php/dashboard_datos.php";
        const INTERVALO_MS = 15000;

        const COLOR = {
            naranja: "#ff6b35", amarillo: "#ffd166", rojo: "#e63946",
            azul: "#4cc9f0", verde: "#52b788", gris: "#8fa3bf"
        };

        const elEstado = document.getElementById("estado");

        function mostrarEstado(texto, esError) {
            elEstado.textContent = texto;
            elEstado.classList.toggle("error", !!esError);
        }

        if (typeof Chart === "undefined") {
            document.getElementById("aviso-chart").hidden = false;
            mostrarEstado("Sin gráficos", true);
        } else {
            iniciar();
        }

        function iniciar() {
            Chart.defaults.color = "#c9d6e8";
            Chart.defaults.borderColor = "#263349";
            Chart.defaults.font.family = "'Segoe UI', Arial, sans-serif";

            const base = { maintainAspectRatio: false, responsive: true };

            const graficos = {
                lideres: new Chart(document.getElementById("grafico-lideres"), {
                    type: "bar",
                    data: { labels: [], datasets: [{ label: "Total", data: [], backgroundColor: COLOR.naranja, borderRadius: 6 }] },
                    options: { ...base, indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
                }),
                equipos: new Chart(document.getElementById("grafico-equipos"), {
                    type: "bar",
                    data: { labels: [], datasets: [
                        { label: "A favor", data: [], backgroundColor: COLOR.naranja, borderRadius: 6 },
                        { label: "En contra", data: [], backgroundColor: COLOR.azul, borderRadius: 6 }
                    ] },
                    options: { ...base, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
                }),
                puntos: new Chart(document.getElementById("grafico-puntos"), {
                    type: "bar",
                    data: { labels: [], datasets: [{ label: "Puntos", data: [], backgroundColor: COLOR.amarillo, borderRadius: 6 }] },
                    options: { ...base, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
                }),
                resultados: new Chart(document.getElementById("grafico-resultados"), {
                    type: "doughnut",
                    data: {
                        labels: ["Victorias locales", "Empates", "Victorias visitantes"],
                        datasets: [{ data: [0, 0, 0], backgroundColor: [COLOR.naranja, COLOR.gris, COLOR.azul], borderColor: "#1b263b", borderWidth: 3 }]
                    },
                    options: { ...base, cutout: "62%", plugins: { legend: { position: "bottom" } } }
                }),
                tendencia: new Chart(document.getElementById("grafico-tendencia"), {
                    type: "line",
                    data: { labels: [], datasets: [{
                        label: "Goles / puntos", data: [], borderColor: COLOR.naranja,
                        backgroundColor: "rgba(255,107,53,0.18)", fill: true, tension: 0.3, pointRadius: 4, pointBackgroundColor: COLOR.amarillo
                    }] },
                    options: { ...base, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
                }),
                disciplinas: new Chart(document.getElementById("grafico-disciplinas"), {
                    type: "bar",
                    data: { labels: [], datasets: [{ label: "Eventos", data: [], backgroundColor: COLOR.verde, borderRadius: 6 }] },
                    options: { ...base, indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
                })
            };

            let lideres = [];
            const selector = document.getElementById("selector-estadistica");

            // Muestra u oculta el aviso de "sin datos" de una tarjeta
            function vacio(idTarjeta, esVacio) {
                const tarjeta = document.getElementById(idTarjeta);
                tarjeta.querySelector(".caja-grafico").hidden = esVacio;
                tarjeta.querySelector(".sin-datos").hidden = !esVacio;
            }

            function pintarLideres() {
                const elegido = lideres.find(t => String(t.id) === String(selector.value));
                if (!elegido || elegido.filas.length === 0) {
                    vacio("tarjeta-lideres", true);
                    return;
                }
                vacio("tarjeta-lideres", false);
                const g = graficos.lideres;
                g.data.labels = elegido.filas.map(f => f.nombre);
                g.data.datasets[0].data = elegido.filas.map(f => f.total);
                g.data.datasets[0].label = elegido.nombre
                    + (elegido.unidad ? " (" + elegido.unidad + ")" : "")
                    + (elegido.menor_es_mejor ? " · mejor marca, menor es mejor" : "");
                g.update();
            }

            selector.addEventListener("change", pintarLideres);

            function aplicar(d) {
                document.getElementById("kpi-eventos").textContent = d.kpis.eventos;
                document.getElementById("kpi-equipos").textContent = d.kpis.equipos;
                document.getElementById("kpi-jugadores").textContent = d.kpis.jugadores;
                document.getElementById("kpi-registros").textContent = d.kpis.registros;

                document.querySelectorAll(".solo-equipos").forEach(el => { el.hidden = !d.es_equipos; });

                lideres = d.lideres;
                const anterior = selector.value;
                selector.innerHTML = "";
                lideres.forEach(t => {
                    const op = document.createElement("option");
                    op.value = t.id;
                    op.textContent = t.nombre + (t.unidad ? " (" + t.unidad + ")" : "");
                    selector.appendChild(op);
                });
                selector.hidden = lideres.length === 0;
                if (lideres.some(t => String(t.id) === String(anterior))) {
                    selector.value = anterior;
                } else {
                    const conDatos = lideres.find(t => t.filas.length > 0);
                    if (conDatos) { selector.value = conDatos.id; }
                }
                if (lideres.length === 0) { vacio("tarjeta-lideres", true); } else { pintarLideres(); }

                if (d.es_equipos) {
                    const sinEquipos = d.equipos.length === 0;
                    vacio("tarjeta-equipos", sinEquipos);
                    vacio("tarjeta-puntos", sinEquipos);
                    graficos.equipos.data.labels = d.equipos.map(e => e.nombre);
                    graficos.equipos.data.datasets[0].data = d.equipos.map(e => e.gf);
                    graficos.equipos.data.datasets[1].data = d.equipos.map(e => e.gc);
                    graficos.equipos.update();
                    graficos.puntos.data.labels = d.equipos.map(e => e.nombre);
                    graficos.puntos.data.datasets[0].data = d.equipos.map(e => e.pts);
                    graficos.puntos.update();

                    const r = d.resultados;
                    vacio("tarjeta-resultados", r.local + r.empate + r.visitante === 0);
                    graficos.resultados.data.datasets[0].data = [r.local, r.empate, r.visitante];
                    graficos.resultados.update();

                    vacio("tarjeta-tendencia", d.tendencia.length === 0);
                    graficos.tendencia.data.labels = d.tendencia.map(t => t.dia);
                    graficos.tendencia.data.datasets[0].data = d.tendencia.map(t => t.total);
                    graficos.tendencia.update();
                }

                vacio("tarjeta-disciplinas", d.disciplinas.every(x => x.eventos === 0));
                graficos.disciplinas.data.labels = d.disciplinas.map(x => (x.icono ? x.icono + " " : "") + x.nombre);
                graficos.disciplinas.data.datasets[0].data = d.disciplinas.map(x => x.eventos);
                graficos.disciplinas.update();
            }

            async function cargar() {
                try {
                    const respuesta = await fetch(URL_DATOS + "?" + new URLSearchParams(FILTROS).toString(), { cache: "no-store" });
                    if (respuesta.status === 401) { location.href = "../index.php"; return; }
                    if (!respuesta.ok) { throw new Error("error " + respuesta.status); }
                    const datos = await respuesta.json();
                    aplicar(datos);
                    mostrarEstado("Actualizado a las " + datos.generado, false);
                } catch (e) {
                    mostrarEstado("No se pudo actualizar (" + e.message + ")", true);
                }
            }

            const casilla = document.getElementById("auto");
            let temporizador = setInterval(cargar, INTERVALO_MS);
            casilla.addEventListener("change", () => {
                clearInterval(temporizador);
                if (casilla.checked) { temporizador = setInterval(cargar, INTERVALO_MS); }
            });
            document.getElementById("btn-actualizar").addEventListener("click", cargar);

            cargar();
        }
    </script>
    <?php endif; ?>

</body>
</html>