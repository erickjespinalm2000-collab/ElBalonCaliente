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

// Aviso cuando alguien llega aquí rebotado por falta de permisos
$mensaje_error = ($_GET["error"] ?? "") === "sin_permiso" ? mensaje_sin_permiso() : "";

$resumen = $conexion->query(
    "SELECT (SELECT COUNT(*) FROM deportes)             AS deportes,
            (SELECT COUNT(*) FROM equipos)              AS equipos,
            (SELECT COUNT(*) FROM jugadores)            AS jugadores,
            (SELECT COUNT(*) FROM eventos)              AS eventos,
            (SELECT COUNT(*) FROM estadisticas_jugador) AS datos"
)->fetch(PDO::FETCH_ASSOC);

$ultimo = $conexion->query(
    "SELECT eventos.nombre, eventos.fecha,
            eventos.marcador_local, eventos.marcador_visitante,
            deportes.nombre AS deporte, deportes.icono,
            local.nombre AS local, visita.nombre AS visita
     FROM eventos
     JOIN deportes ON deportes.id = eventos.deporte_id
     LEFT JOIN equipos AS local  ON local.id  = eventos.equipo_local_id
     LEFT JOIN equipos AS visita ON visita.id = eventos.equipo_visitante_id
     ORDER BY eventos.fecha DESC, eventos.id DESC
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

function icono(string $nombre): string
{
    $formas = [
        "dashboard" => '<line x1="3" y1="20.5" x2="21" y2="20.5"/>
                        <rect x="5" y="12" width="3.6" height="8.5" rx="1"/>
                        <rect x="10.2" y="6.5" width="3.6" height="14" rx="1"/>
                        <rect x="15.4" y="15" width="3.6" height="5.5" rx="1"/>',
        "trofeo"    => '<path d="M8 21h8M12 17.5V21M6.5 4h11v4.5a5.5 5.5 0 0 1-11 0V4z"/>
                        <path d="M6.5 6H4.5a2 2 0 0 0 0 4h2M17.5 6h2a2 2 0 0 1 0 4h-2"/>',
        "maletin"   => '<rect x="2.5" y="7.5" width="19" height="13" rx="2"/>
                        <path d="M8.5 7.5V5.5a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2"/>
                        <path d="M2.5 13h19"/>',
        "calendario" => '<rect x="3.5" y="5.5" width="17" height="15" rx="2"/>
                         <path d="M8 3v4.5M16 3v4.5M3.5 11h17"/>',
        "captura"   => '<rect x="5" y="4.5" width="14" height="16" rx="2"/>
                        <path d="M9 4.5V3.5h6v1"/>
                        <path d="M8.5 11.5l1.6 1.6 2.4-2.6M8.5 16.5h7"/>',
        "actividad" => '<path d="M22 12h-4l-3 8.5L9 3.5l-3 8.5H2"/>',
        "escudo"    => '<path d="M12 21.5s7.5-3.8 7.5-9.5V5L12 2.5 4.5 5v7c0 5.7 7.5 9.5 7.5 9.5z"/>',
        "medalla"   => '<circle cx="12" cy="8.5" r="5.5"/>
                        <path d="M8.3 13.6 7 21.5l5-2.9 5 2.9-1.3-7.9"/>',
        "jugador"   => '<circle cx="12" cy="8" r="3.8"/>
                        <path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>',
        "lista"     => '<path d="M8.5 6.5h12M8.5 12h12M8.5 17.5h12"/>
                        <circle cx="4.5" cy="6.5" r="1.6"/>
                        <circle cx="4.5" cy="12" r="1.6"/>
                        <circle cx="4.5" cy="17.5" r="1.6"/>',
        "salir"     => '<path d="M9.5 21H5.5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <path d="M16 16.5l4.5-4.5L16 7.5M20.5 12H9.5"/>',
        "lupa"      => '<circle cx="10.5" cy="10.5" r="6.5"/>
                        <path d="M15.5 15.5 21 21"/>',
        "sede"      => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/>
                        <circle cx="12" cy="10" r="2.6"/>',
        "silbato"   => '<path d="M3.5 12a5 5 0 0 1 5-5h12v4.5a5.5 5.5 0 0 1-5.5 5.5h-6.5a5 5 0 0 1-5-5z"/>
                        <circle cx="8.5" cy="12" r="1.8"/>',
        "sorteo"    => '<rect x="3" y="3" width="8" height="8" rx="2"/>
                        <rect x="13" y="13" width="8" height="8" rx="2"/>
                        <path d="M11 7h4a2 2 0 0 1 2 2v4"/>',
        "llave"     => '<path d="M3 5h6M3 12h6M3 19h6"/>
                        <path d="M9 5v7h5M9 19v-7"/>
                        <path d="M14 12h3M17 8.5v7"/>',
    ];

    $forma = $formas[$nombre] ?? "";
    return '<svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">' . $forma . '</svg>';
}

$secciones = [
    [
        "titulo" => "Consulta",
        "nota" => "Para buscar un equipo o jugador, usa la barra de arriba",
        "items" => [
            ["llaves.php", "llave", "Llaves de eliminación", "El cuadro de cruces de cada torneo"],
        ],
    ],
    [
        "titulo" => "Análisis",
        "nota" => "Lo que el sistema calcula a partir de los datos capturados",
        "items" => [
            ["dashboard.php", "dashboard", "Dashboard", "Gráficos interactivos que se actualizan solos"],
            ["rankings.php", "trofeo", "Rankings", "Tabla de posiciones y líderes por estadística"],
            ["comercial.php", "maletin", "Análisis comercial", "Jugadores como activos y proyección de ingresos"],
        ],
    ],
    [
        "titulo" => "Registro de datos",
        "nota" => "El día a día: cargar lo que pasó en cada competencia",
        "items" => [
            ["eventos.php", "calendario", "Eventos", "Partidos y competencias, con su marcador"],
            ["estadisticas.php", "captura", "Capturar estadísticas", "Valores de cada jugador en cada evento"],
            ["jugadores.php", "jugador", "Jugadores y atletas", "Plantillas y competidores individuales"],
        ],
    ],
];

// El catálogo solo lo administra el administrador, así que a un usuario normal
// ni siquiera se le muestran estos accesos.
if (es_admin()) {
    $secciones[] = [
        "titulo" => "Organización",
        "nota" => "Dónde y con quién se juega · solo administrador",
        "items" => [
            ["localidades.php", "sede", "Localidades", "Sedes y sus sectores con precio propio"],
            ["arbitros.php", "silbato", "Árbitros", "Quiénes pueden dirigir los eventos"],
            ["programacion.php", "sorteo", "Programar eventos", "Sorteo automático de fechas, sedes y árbitros"],
        ],
    ];

    $secciones[] = [
        "titulo" => "Catálogo",
        "nota" => "La base sobre la que se registra todo lo demás · solo administrador",
        "items" => [
            ["deportes.php", "actividad", "Deportes", "Disciplinas, categorías por edad y puntuación"],
            ["equipos.php", "escudo", "Equipos", "Clubes de cada disciplina, con su logo"],
            ["ligas.php", "medalla", "Ligas", "Torneos y temporadas"],
            ["tipos_estadisticas.php", "lista", "Tipos de estadística", "Qué se mide en cada disciplina"],
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Balón Caliente - Inicio</title>
    <link rel="icon" type="image/png" href="../Informacion/Logo.png">
    <link rel="stylesheet" href="../Css/menu.css?v=9">
</head>
<body>

    <div class="app">

        <header class="barra">
            <a href="menu.php" class="barra-marca">
                <img src="../Informacion/Logo.png" alt="El Balón Caliente" width="150">
            </a>

            <form class="barra-busqueda" method="GET" action="buscador.php" role="search">
                <svg class="barra-lupa" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/>
                </svg>
                <input type="search" name="q" placeholder="Buscar equipo o jugador..."
                       aria-label="Buscar equipo o jugador">
                <button type="submit">Buscar</button>
            </form>

            <div class="barra-derecha">
                <div class="usuario">
                    <span class="usuario-nombre"><?php echo htmlspecialchars($_SESSION["nombre_usuario"]); ?></span>
                    <span class="usuario-rol"><?php echo htmlspecialchars($_SESSION["rol"]); ?></span>
                </div>
                <a href="../Php/logout.php" class="btn-salir">
                    <?php echo icono("salir"); ?>
                    <span>Cerrar sesión</span>
                </a>
            </div>
        </header>

        <?php if ($mensaje_error !== ""): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <section class="portada">
            <p class="saludo">Hola, <?php echo htmlspecialchars($_SESSION["nombre_usuario"]); ?></p>
            <h1>Centro de estadísticas deportivas</h1>

            <div class="indicadores">
                <div class="indicador">
                    <span class="indicador-valor"><?php echo number_format($resumen["deportes"], 0, ".", ","); ?></span>
                    <span class="indicador-rotulo">Disciplinas</span>
                </div>
                <div class="indicador">
                    <span class="indicador-valor"><?php echo number_format($resumen["equipos"], 0, ".", ","); ?></span>
                    <span class="indicador-rotulo">Equipos</span>
                </div>
                <div class="indicador">
                    <span class="indicador-valor"><?php echo number_format($resumen["jugadores"], 0, ".", ","); ?></span>
                    <span class="indicador-rotulo">Jugadores</span>
                </div>
                <div class="indicador">
                    <span class="indicador-valor"><?php echo number_format($resumen["eventos"], 0, ".", ","); ?></span>
                    <span class="indicador-rotulo">Eventos</span>
                </div>
                <div class="indicador indicador-destacado">
                    <span class="indicador-valor"><?php echo number_format($resumen["datos"], 0, ".", ","); ?></span>
                    <span class="indicador-rotulo">Datos registrados</span>
                </div>
            </div>

            <?php if ($ultimo): ?>
                <p class="ultimo-evento">
                    <span class="etiqueta">Último evento</span>
                    <?php if (!empty($ultimo["icono"])): ?>
                        <span class="disciplina-icono"><?php echo htmlspecialchars($ultimo["icono"]); ?></span>
                    <?php endif; ?>
                    <strong>
                        <?php if (!empty($ultimo["local"]) && !empty($ultimo["visita"])): ?>
                            <?php echo htmlspecialchars($ultimo["local"]); ?>
                            <?php if ($ultimo["marcador_local"] !== null): ?>
                                <span class="marcador"><?php echo (int)$ultimo["marcador_local"]; ?> - <?php echo (int)$ultimo["marcador_visitante"]; ?></span>
                            <?php else: ?>
                                vs
                            <?php endif; ?>
                            <?php echo htmlspecialchars($ultimo["visita"]); ?>
                        <?php else: ?>
                            <?php echo htmlspecialchars($ultimo["nombre"] ?? "Evento sin nombre"); ?>
                        <?php endif; ?>
                    </strong>
                    <span class="meta">
                        <?php echo htmlspecialchars($ultimo["deporte"]); ?>
                        &middot; <?php echo date("d/m/Y", strtotime($ultimo["fecha"])); ?>
                    </span>
                </p>
            <?php else: ?>
                <p class="ultimo-evento sin-datos">
                    Todavía no hay eventos registrados.
                    <a href="eventos.php">Registra el primero</a>.
                </p>
            <?php endif; ?>
        </section>

        <?php foreach ($secciones as $seccion): ?>
            <section class="grupo">
                <div class="grupo-encabezado">
                    <h2><?php echo htmlspecialchars($seccion["titulo"]); ?></h2>
                    <p><?php echo htmlspecialchars($seccion["nota"]); ?></p>
                </div>
                <nav class="tarjetas">
                    <?php foreach ($seccion["items"] as [$destino, $ico, $titulo, $descripcion]): ?>
                        <a href="<?php echo htmlspecialchars($destino); ?>" class="tarjeta">
                            <span class="tarjeta-icono"><?php echo icono($ico); ?></span>
                            <span class="tarjeta-texto">
                                <span class="tarjeta-titulo"><?php echo htmlspecialchars($titulo); ?></span>
                                <span class="tarjeta-descripcion"><?php echo htmlspecialchars($descripcion); ?></span>
                            </span>
                            <span class="tarjeta-flecha" aria-hidden="true">&rarr;</span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </section>
        <?php endforeach; ?>

        <footer class="pie">
            El Balón Caliente &middot; Centro de estadísticas deportivas
        </footer>

    </div>

</body>
</html>
