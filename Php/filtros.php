<?php

/**
 * Barra de filtros compartida.
 *
 * Varias pantallas listan datos guardados y todas necesitan lo mismo:
 * filtrar primero por disciplina y después por equipo (o por liga, en el
 * caso de los eventos). Tenerlo aquí evita repetir el mismo formulario y
 * el mismo JavaScript en cada archivo, y asegura que todas se comporten
 * igual.
 *
 * El segundo desplegable se filtra solo al cambiar el primero, sin
 * recargar la página.
 */


/** Las disciplinas, para el primer desplegable. */
function filtros_deportes(PDO $conexion): array
{
    return $conexion->query(
        "SELECT id, nombre, icono FROM deportes ORDER BY nombre ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}


/** Los equipos con su disciplina, para el segundo desplegable. */
function filtros_equipos(PDO $conexion): array
{
    return $conexion->query(
        "SELECT equipos.id, equipos.nombre, equipos.deporte_id
         FROM equipos
         ORDER BY equipos.nombre ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}


/** Las ligas con su disciplina, para el segundo desplegable de eventos. */
function filtros_ligas(PDO $conexion): array
{
    return $conexion->query(
        "SELECT ligas.id, ligas.nombre, ligas.deporte_id, ligas.temporada
         FROM ligas
         ORDER BY ligas.nombre ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Lee los dos filtros de la URL y los deja validados.
 *
 * $nombre_segundo es "equipo" o "liga". Devuelve
 * ["deporte" => "" | "3", "segundo" => "" | "sin" | "7"].
 */
function filtros_leer(string $nombre_segundo = "equipo"): array
{
    $deporte = trim((string)($_GET["deporte"] ?? ""));
    if ($deporte !== "" && !ctype_digit($deporte)) {
        $deporte = "";
    }

    $segundo = trim((string)($_GET[$nombre_segundo] ?? ""));
    if ($segundo !== "" && $segundo !== "sin" && !ctype_digit($segundo)) {
        $segundo = "";
    }

    return ["deporte" => $deporte, "segundo" => $segundo];
}


/**
 * Los filtros tal como hay que pasarlos a navegacion_paginas() para que
 * no se pierdan al cambiar de página.
 */
function filtros_url(array $f, string $nombre_segundo = "equipo"): array
{
    $url = [];
    if ($f["deporte"] !== "") {
        $url["deporte"] = $f["deporte"];
    }
    if ($f["segundo"] !== "") {
        $url[$nombre_segundo] = $f["segundo"];
    }
    return $url;
}


/**
 * Lee el filtro simple de disciplina, para las pantallas donde el segundo
 * nivel no aplica (ligas, tipos de estadística, árbitros, localidades...).
 */
function filtro_deporte_leer(): string
{
    $d = trim((string)($_GET["deporte"] ?? ""));
    return ctype_digit($d) ? $d : "";
}


/**
 * Dibuja una barra con un solo desplegable de disciplina.
 *
 * $nota aparece junto al conteo, para explicar casos como "incluidas las
 * multiusos" donde el filtro no excluye del todo.
 */
function filtro_deporte_pintar(string $accion, array $deportes, string $elegido, int $total, string $cosa, string $nota = ""): void
{
    ?>
    <form method="GET" action="<?php echo htmlspecialchars($accion); ?>" class="barra-filtros">
        <div class="filtro">
            <label for="filtro-deporte">Disciplina</label>
            <select id="filtro-deporte" name="deporte" onchange="this.form.submit();">
                <option value="">Todas</option>
                <?php foreach ($deportes as $d): ?>
                    <option value="<?php echo (int)$d["id"]; ?>"
                        <?php echo $elegido === (string)$d["id"] ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars(trim(($d["icono"] ?? "") . " " . $d["nombre"])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($elegido !== ""): ?>
            <a href="<?php echo htmlspecialchars($accion); ?>" class="btn-limpiar-filtro">Quitar filtro</a>
        <?php endif; ?>
        <noscript><button type="submit" class="btn-secundario">Aplicar</button></noscript>
    </form>

    <p class="conteo-filtro">
        <?php echo $total . " " . htmlspecialchars($cosa); ?>
        <?php echo ($elegido !== "" && $nota !== "") ? htmlspecialchars($nota) : ""; ?>
    </p>
    <?php
}


/**
 * Dibuja la barra de filtros.
 *
 *   $accion          página a la que se envía (ej. "jugadores.php")
 *   $deportes        lista de filtros_deportes()
 *   $segundos        lista de filtros_equipos() o filtros_ligas()
 *   $f               lo que devolvió filtros_leer()
 *   $nombre_segundo  "equipo" o "liga"
 *   $rotulo_sin      texto de la opción "sin asignar", o null para no mostrarla
 */
function filtros_pintar(
    string $accion,
    array $deportes,
    array $segundos,
    array $f,
    string $nombre_segundo = "equipo",
    ?string $rotulo_sin = null
): void {
    $titulo = $nombre_segundo === "liga" ? "Liga" : "Equipo";
    $hay_filtro = $f["deporte"] !== "" || $f["segundo"] !== "";
    ?>
    <form method="GET" action="<?php echo htmlspecialchars($accion); ?>" class="barra-filtros">

        <div class="filtro">
            <label for="filtro-deporte">Disciplina</label>
            <select id="filtro-deporte" name="deporte" onchange="this.form.submit();">
                <option value="">Todas</option>
                <?php foreach ($deportes as $d): ?>
                    <option value="<?php echo (int)$d["id"]; ?>"
                        <?php echo $f["deporte"] === (string)$d["id"] ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars(trim(($d["icono"] ?? "") . " " . $d["nombre"])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filtro">
            <label for="filtro-segundo"><?php echo $titulo; ?></label>
            <select id="filtro-segundo" name="<?php echo htmlspecialchars($nombre_segundo); ?>"
                    onchange="this.form.submit();"
                    data-elegido="<?php echo htmlspecialchars($f["segundo"]); ?>">
                <option value="">Todos</option>
            </select>
        </div>

        <?php if ($hay_filtro): ?>
            <a href="<?php echo htmlspecialchars($accion); ?>" class="btn-limpiar-filtro">Quitar filtros</a>
        <?php endif; ?>

        <noscript><button type="submit" class="btn-secundario">Aplicar</button></noscript>
    </form>

    <script>
        (function () {
            var lista = <?php echo json_encode(array_values($segundos), JSON_UNESCAPED_UNICODE); ?>;
            var rotuloSin = <?php echo json_encode($rotulo_sin, JSON_UNESCAPED_UNICODE); ?>;
            var selDeporte = document.getElementById("filtro-deporte");
            var selSegundo = document.getElementById("filtro-segundo");
            if (!selDeporte || !selSegundo) { return; }

            var elegido = selSegundo.getAttribute("data-elegido");

            function llenar() {
                var deporte = selDeporte.value;
                selSegundo.innerHTML = '<option value="">Todos</option>';

                if (rotuloSin) {
                    var sin = document.createElement("option");
                    sin.value = "sin";
                    sin.textContent = rotuloSin;
                    if (elegido === "sin") { sin.selected = true; }
                    selSegundo.appendChild(sin);
                }

                lista.filter(function (x) {
                    return deporte === "" || String(x.deporte_id) === String(deporte);
                }).forEach(function (x) {
                    var o = document.createElement("option");
                    o.value = x.id;
                    o.textContent = x.nombre + (x.temporada ? " (" + x.temporada + ")" : "");
                    if (String(x.id) === String(elegido)) { o.selected = true; }
                    selSegundo.appendChild(o);
                });
            }

            // Al cambiar de disciplina, el segundo filtro se reinicia: un
            // equipo de otra disciplina no tendria sentido ahi.
            selDeporte.addEventListener("change", function () { elegido = ""; });
            llenar();
        })();
    </script>
    <?php
}
