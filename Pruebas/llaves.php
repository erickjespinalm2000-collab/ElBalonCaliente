<?php
require_once __DIR__ . "/../Php/llaves_logica.php";
$fallos = 0;
function comprobar($t, $ok) { global $fallos; if (!$ok) { $fallos++; } printf("  %s  %s\n", $ok ? "OK   " : "FALLA", $t); }

echo "--- tamano del cuadro ---\n";
comprobar("2 equipos -> cuadro de 2", tamano_llave(2) === 2);
comprobar("4 equipos -> cuadro de 4", tamano_llave(4) === 4);
comprobar("5 equipos -> cuadro de 8", tamano_llave(5) === 8);
comprobar("8 equipos -> cuadro de 8", tamano_llave(8) === 8);
comprobar("13 equipos -> cuadro de 16", tamano_llave(13) === 16);
comprobar("1 equipo no forma llave", tamano_llave(1) === 0);

echo "\n--- rondas ---\n";
comprobar("cuadro de 2 -> 1 ronda", total_rondas(2) === 1);
comprobar("cuadro de 8 -> 3 rondas", total_rondas(8) === 3);
comprobar("cuadro de 16 -> 4 rondas", total_rondas(16) === 4);

echo "\n--- nombres ---\n";
comprobar("ultima ronda es Final", nombre_ronda(3, 3) === "Final");
comprobar("penultima es Semifinal", nombre_ronda(2, 3) === "Semifinal");
comprobar("primera de 8 es Cuartos", nombre_ronda(1, 3) === "Cuartos de final");
comprobar("primera de 16 es Octavos", nombre_ronda(1, 4) === "Octavos de final");

echo "\n--- emparejamiento ---\n";
$p8 = emparejar_primera_ronda([1,2,3,4,5,6,7,8]);
comprobar("8 equipos -> 4 partidos", count($p8) === 4);
comprobar("el 1 juega contra el 8", $p8[0] === [1, 8]);
comprobar("el 2 juega contra el 7", $p8[1] === [2, 7]);
comprobar("el 4 juega contra el 5", $p8[3] === [4, 5]);

$todos = [];
foreach ($p8 as $par) { $todos = array_merge($todos, $par); }
sort($todos);
comprobar("los 8 equipos aparecen una sola vez", $todos === [1,2,3,4,5,6,7,8]);

echo "\n--- pases directos ---\n";
$p5 = emparejar_primera_ronda([1,2,3,4,5]);
comprobar("5 equipos -> cuadro de 8, 4 partidos", count($p5) === 4);
$huecos = 0;
foreach ($p5 as $par) { foreach ($par as $x) { if ($x === null) { $huecos++; } } }
comprobar("hay 3 huecos (8 - 5)", $huecos === 3);

$res = resolver_pases_directos($p5);
$con_ganador = count(array_filter($res, fn($x) => $x["ganador"] !== null));
comprobar("3 equipos pasan sin jugar", $con_ganador === 3);
comprobar("el unico partido real no tiene ganador aun",
    count(array_filter($res, fn($x) => $x["ganador"] === null)) === 1);

echo "\n--- avance al siguiente ---\n";
comprobar("partidos 0 y 1 van a la posicion 0",
    posicion_siguiente(0) === 0 && posicion_siguiente(1) === 0);
comprobar("partidos 2 y 3 van a la posicion 1",
    posicion_siguiente(2) === 1 && posicion_siguiente(3) === 1);
comprobar("el par entra como A, el impar como B",
    lado_siguiente(0) === "a" && lado_siguiente(1) === "b" && lado_siguiente(2) === "a");

echo "\n" . ($fallos === 0 ? "TODOS LOS CASOS PASARON" : "$fallos CASOS FALLARON") . "\n";