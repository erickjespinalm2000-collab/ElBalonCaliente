<?php

/**
 * Lógica del sorteo de eventos.
 *
 * Está separada de la pantalla y de la base de datos a propósito: así se
 * puede probar con datos inventados, sin tocar la base ni levantar el
 * servidor. El archivo Pruebas/programacion.php hace exactamente eso.
 *
 * El problema que resuelve: dados unos equipos, unos días, unas horas,
 * unas sedes y unos árbitros, repartir los partidos al azar sin que se
 * pisen. Tres cosas no se pueden repetir en la misma franja horaria:
 * la sede, el árbitro y cada uno de los dos equipos.
 */


/**
 * Todos contra todos. Si $ida_vuelta es true cada pareja juega dos veces,
 * una en cada sede.
 *
 * Devuelve una lista de pares [id_local, id_visitante].
 */
function generar_cruces(array $equipos, bool $ida_vuelta = false): array
{
    $cruces = [];
    $total = count($equipos);

    for ($i = 0; $i < $total; $i++) {
        for ($j = $i + 1; $j < $total; $j++) {
            $cruces[] = [$equipos[$i], $equipos[$j]];
            if ($ida_vuelta) {
                $cruces[] = [$equipos[$j], $equipos[$i]];
            }
        }
    }

    return $cruces;
}


/**
 * Construye todas las franjas posibles: cada combinación de día, hora y
 * sede. Las fechas van en formato "Y-m-d H:i:s".
 *
 * $horas son enteros de 0 a 23.
 */
function generar_franjas(string $desde, string $hasta, array $horas, array $localidades): array
{
    $franjas = [];

    $inicio = date_create($desde);
    $fin = date_create($hasta);
    if ($inicio === false || $fin === false || $inicio > $fin) {
        return [];
    }

    sort($horas);

    $dia = clone $inicio;
    while ($dia <= $fin) {
        foreach ($horas as $hora) {
            foreach ($localidades as $localidad) {
                $franjas[] = [
                    "fecha" => $dia->format("Y-m-d") . sprintf(" %02d:00:00", $hora),
                    "localidad_id" => $localidad,
                ];
            }
        }
        $dia->modify("+1 day");
    }

    return $franjas;
}


/**
 * ¿Chocan dos eventos que empiezan en $a y $b y duran $minutos?
 *
 * Se comparan como intervalos, no como horas iguales: un partido de 120
 * minutos a las 14:00 y otro a las 15:00 se pisan aunque la hora de
 * inicio sea distinta.
 */
function se_pisan(string $a, string $b, int $minutos): bool
{
    $ia = strtotime($a);
    $ib = strtotime($b);
    $largo = $minutos * 60;

    return $ia < $ib + $largo && $ib < $ia + $largo;
}


/**
 * Reparte los cruces entre las franjas disponibles.
 *
 * $ocupado es lo que YA existe en la base y hay que respetar:
 *   ["localidades" => [id => [fechas...]],
 *    "arbitros"    => [id => [fechas...]],
 *    "equipos"     => [id => [fechas...]]]
 *
 * Devuelve ["programados" => [...], "sin_lugar" => [...]].
 * Lo que no cupo se informa en lugar de perderse en silencio.
 */
function repartir(array $cruces, array $franjas, array $arbitros, int $minutos, array $ocupado = [], ?int $semilla = null): array
{
    $ocupado_localidad = $ocupado["localidades"] ?? [];
    $ocupado_arbitro = $ocupado["arbitros"] ?? [];
    $ocupado_equipo = $ocupado["equipos"] ?? [];

    // El azar es lo que pide el requisito. Con semilla fija el resultado
    // se puede repetir, que es lo que permite probarlo.
    if ($semilla !== null) {
        mt_srand($semilla);
    }

    $cruces = mezclar($cruces);
    $franjas = mezclar($franjas);

    $programados = [];
    $sin_lugar = [];

    foreach ($cruces as $cruce) {
        [$local, $visitante] = $cruce;
        $colocado = false;

        foreach ($franjas as $indice => $franja) {
            $fecha = $franja["fecha"];
            $sede = $franja["localidad_id"];

            // La sede no puede tener otro evento a esa hora
            if (choca($ocupado_localidad[$sede] ?? [], $fecha, $minutos)) {
                continue;
            }

            // Ninguno de los dos equipos puede estar jugando a esa hora
            if (choca($ocupado_equipo[$local] ?? [], $fecha, $minutos)
                || choca($ocupado_equipo[$visitante] ?? [], $fecha, $minutos)) {
                continue;
            }

            // Hace falta un árbitro libre. Si no hay, esta franja no sirve.
            $libre = null;
            foreach ($arbitros as $arbitro) {
                if (!choca($ocupado_arbitro[$arbitro] ?? [], $fecha, $minutos)) {
                    $libre = $arbitro;
                    break;
                }
            }
            if ($libre === null) {
                continue;
            }

            $programados[] = [
                "equipo_local_id" => $local,
                "equipo_visitante_id" => $visitante,
                "fecha" => $fecha,
                "localidad_id" => $sede,
                "arbitro_id" => $libre,
            ];

            $ocupado_localidad[$sede][] = $fecha;
            $ocupado_arbitro[$libre][] = $fecha;
            $ocupado_equipo[$local][] = $fecha;
            $ocupado_equipo[$visitante][] = $fecha;

            // Esa franja de esa sede ya se gastó
            unset($franjas[$indice]);
            $colocado = true;
            break;
        }

        if (!$colocado) {
            $sin_lugar[] = $cruce;
        }
    }

    return ["programados" => $programados, "sin_lugar" => $sin_lugar];
}


/**
 * ¿La fecha propuesta choca con alguna de las ya ocupadas?
 */
function choca(array $ocupadas, string $fecha, int $minutos): bool
{
    foreach ($ocupadas as $otra) {
        if (se_pisan($fecha, $otra, $minutos)) {
            return true;
        }
    }
    return false;
}


/**
 * Baraja conservando los índices, porque repartir() los usa para ir
 * descartando franjas ya usadas.
 */
function mezclar(array $lista): array
{
    $claves = array_keys($lista);
    shuffle($claves);

    $mezclada = [];
    foreach ($claves as $k) {
        $mezclada[$k] = $lista[$k];
    }

    return $mezclada;
}
