<?php

/**
 * Lógica de las llaves de eliminación directa.
 *
 * Igual que la del sorteo, vive aparte de la pantalla para poderla
 * probar sola. Lo que resuelve es el armado del cuadro: cuántas rondas
 * hacen falta, quién se enfrenta a quién en la primera, y a qué casilla
 * de la ronda siguiente pasa cada ganador.
 */


/**
 * El cuadro siempre es una potencia de 2: 2, 4, 8, 16, 32...
 * Con 6 equipos el cuadro es de 8 y sobran 2 huecos, que se reparten
 * como pases directos.
 */
function tamano_llave(int $equipos): int
{
    if ($equipos < 2) {
        return 0;
    }

    $tamano = 2;
    while ($tamano < $equipos) {
        $tamano *= 2;
    }

    return $tamano;
}


/**
 * Cuántas rondas tiene un cuadro de ese tamaño.
 * 8 equipos -> 3 rondas (cuartos, semifinal, final).
 */
function total_rondas(int $tamano): int
{
    if ($tamano < 2) {
        return 0;
    }

    return (int)round(log($tamano, 2));
}


/**
 * Nombre de la ronda, contando desde el final hacia atrás, que es como
 * se nombran de verdad: la última es la Final, la anterior Semifinal.
 */
function nombre_ronda(int $ronda, int $rondas): string
{
    $faltan = $rondas - $ronda;

    if ($faltan === 0) { return "Final"; }
    if ($faltan === 1) { return "Semifinal"; }
    if ($faltan === 2) { return "Cuartos de final"; }
    if ($faltan === 3) { return "Octavos de final"; }

    return "Ronda " . $ronda;
}


/**
 * Arma la primera ronda.
 *
 * Los equipos se emparejan el primero con el último, el segundo con el
 * penúltimo, y así: es el cruce clásico, el que hace que los dos
 * mejores sembrados no se encuentren hasta la final.
 *
 * Los huecos sobrantes quedan en null y significan pase directo.
 * Devuelve una lista de pares [a, b].
 */
function emparejar_primera_ronda(array $equipos): array
{
    $tamano = tamano_llave(count($equipos));
    if ($tamano === 0) {
        return [];
    }

    // Rellenar con huecos hasta completar la potencia de 2
    $cuadro = $equipos;
    while (count($cuadro) < $tamano) {
        $cuadro[] = null;
    }

    $partidos = [];
    for ($i = 0; $i < $tamano / 2; $i++) {
        $partidos[] = [$cuadro[$i], $cuadro[$tamano - 1 - $i]];
    }

    return $partidos;
}


/**
 * A qué posición de la ronda siguiente pasa el ganador de un partido.
 * Los partidos 0 y 1 alimentan el 0; el 2 y el 3 alimentan el 1.
 */
function posicion_siguiente(int $posicion): int
{
    return intdiv($posicion, 2);
}


/**
 * ¿El ganador entra como equipo A o como equipo B en la ronda siguiente?
 * Los de posición par entran como A, los impares como B.
 */
function lado_siguiente(int $posicion): string
{
    return $posicion % 2 === 0 ? "a" : "b";
}


/**
 * Resuelve los pases directos de la primera ronda.
 *
 * Si un partido tiene un solo equipo, ese equipo gana sin jugar y hay
 * que subirlo ya a la ronda siguiente. Devuelve la lista de partidos
 * con su ganador rellenado donde corresponda.
 */
function resolver_pases_directos(array $partidos): array
{
    foreach ($partidos as $i => $p) {
        $a = $p[0] ?? null;
        $b = $p[1] ?? null;

        if ($a !== null && $b === null) {
            $partidos[$i]["ganador"] = $a;
        } elseif ($a === null && $b !== null) {
            $partidos[$i]["ganador"] = $b;
        } else {
            $partidos[$i]["ganador"] = null;
        }
    }

    return $partidos;
}
