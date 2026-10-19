<?php
/**
 * Página pública del sistema.
 *
 * Existe por una razón concreta: la pantalla de inicio es un formulario
 * de acceso y un buscador no tiene nada que leer ahí. Esta página sí
 * describe qué es el sistema, y es la que Google puede indexar.
 */

$titulo = "El Balón Caliente — Sistema de Estadísticas Deportivas";
$descripcion = "Sistema web para registrar y analizar estadísticas deportivas de "
    . "cualquier disciplina: tabla de posiciones, rankings por jugador, categorías "
    . "por edad, programación de eventos, llaves de eliminación y análisis comercial.";

// La dirección real del sitio, para las etiquetas que la necesitan completa.
$esquema = !empty($_SERVER["HTTPS"]) ? "https" : "http";
$base = $esquema . "://" . ($_SERVER["HTTP_HOST"] ?? "localhost");
$aqui = $base . strtok($_SERVER["REQUEST_URI"] ?? "/acerca.php", "?");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($titulo); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($descripcion); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($aqui); ?>">

    <!-- Cómo se ve el enlace al compartirlo en WhatsApp, Facebook o X -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($titulo); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($descripcion); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($aqui); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($base); ?>/Informacion/Logo.png">
    <meta property="og:locale" content="es_HN">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" type="image/png" href="Informacion/Logo.png">
    <link rel="stylesheet" href="Css/acerca.css?v=1">

    <!-- Datos estructurados: le dicen a Google qué tipo de cosa es esto -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": "El Balón Caliente",
      "applicationCategory": "SportsApplication",
      "operatingSystem": "Navegador web",
      "inLanguage": "es",
      "description": <?php echo json_encode($descripcion, JSON_UNESCAPED_UNICODE); ?>,
      "url": <?php echo json_encode($aqui, JSON_UNESCAPED_UNICODE); ?>
    }
    </script>
</head>
<body>

    <header class="cabecera">
        <img src="Informacion/Logo.png" alt="El Balón Caliente" class="marca" width="220">
        <a href="index.php" class="btn-entrar">Entrar al sistema</a>
    </header>

    <main class="pagina">

        <h1>El Balón Caliente</h1>
        <p class="entrada">
            Sistema web para registrar y analizar estadísticas deportivas de
            cualquier disciplina. Convierte lo que pasa en la cancha en información
            que sirve al cuerpo técnico, a la dirigencia y al área comercial.
        </p>

        <h2>Qué resuelve</h2>
        <p>
            Las ligas menores y los torneos escolares llevan sus estadísticas en
            cuadernos y hojas de cálculo sueltas. Al terminar la temporada nadie
            puede responder con datos quién fue el goleador, qué equipo fue más
            regular o cuánto se recaudó. El Balón Caliente centraliza esa captura
            y la conserva.
        </p>

        <h2>Qué incluye</h2>
        <div class="rejilla">
            <article class="ficha">
                <h3>Multidisciplina real</h3>
                <p>
                    Cada deporte define qué se mide, cuánto vale una victoria y si
                    el valor más bajo es el mejor. La natación y el fútbol conviven
                    sin forzar uno dentro del otro.
                </p>
            </article>
            <article class="ficha">
                <h3>Categorías por edad</h3>
                <p>
                    Divisiones Sub-15, Sub-17 o las que defina la liga. El sistema
                    impide inscribir a un jugador que pase de la edad de su categoría.
                </p>
            </article>
            <article class="ficha">
                <h3>Tabla de posiciones y rankings</h3>
                <p>
                    Posiciones calculadas con la regla de puntuación de cada
                    disciplina, y líderes por cada estadística registrada.
                </p>
            </article>
            <article class="ficha">
                <h3>Programación automática</h3>
                <p>
                    Genera el calendario de una liga al azar, verificando que la
                    sede esté libre, el árbitro esté libre y ningún equipo juegue
                    dos veces a la vez.
                </p>
            </article>
            <article class="ficha">
                <h3>Llaves de eliminación</h3>
                <p>
                    Organigrama de cruces para torneos de eliminación directa, con
                    pases directos resueltos y avance automático del ganador.
                </p>
            </article>
            <article class="ficha">
                <h3>Análisis comercial</h3>
                <p>
                    Valoración de jugadores como activos y proyección de ingresos
                    por boletería, calculada sector por sector según el precio de
                    cada zona del estadio.
                </p>
            </article>
        </div>

        <h2>Cómo funciona</h2>
        <ol class="pasos">
            <li>El administrador configura las disciplinas, categorías, equipos, sedes y árbitros.</li>
            <li>El sistema genera el calendario de eventos.</li>
            <li>Después de cada partido se captura el marcador y las estadísticas de cada jugador.</li>
            <li>Las posiciones, los rankings y las proyecciones se recalculan solos.</li>
        </ol>

        <h2>Sobre el proyecto</h2>
        <p>
            Desarrollado como proyecto final del Bachillerato Técnico Profesional en
            Informática. Está construido con PHP, MariaDB y Chart.js, todo software
            libre, de modo que una liga sin presupuesto pueda usarlo sin pagar
            licencias ni mensualidades.
        </p>

        <p class="llamado">
            <a href="index.php" class="btn-entrar grande">Entrar al sistema</a>
        </p>

    </main>

    <footer class="pie">
        El Balón Caliente · Centro de estadísticas deportivas · Honduras
    </footer>

</body>
</html>
