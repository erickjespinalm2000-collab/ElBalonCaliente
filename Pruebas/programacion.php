<?php
require_once __DIR__ . "/../Php/programacion_logica.php";

$fallos = 0;
function comprobar(string $texto, bool $ok) {
    global $fallos;
    if (!$ok) { $fallos++; }
    printf("  %s  %s\n", $ok ? "OK   " : "FALLA", $texto);
}

echo "--- generar cruces ---\n";
$c4 = generar_cruces([1, 2, 3, 4]);
comprobar("4 equipos todos contra todos = 6 partidos", count($c4) === 6);
$c4v = generar_cruces([1, 2, 3, 4], true);
comprobar("4 equipos ida y vuelta = 12 partidos", count($c4v) === 12);
comprobar("nadie juega contra si mismo",
    count(array_filter($c4, fn($p) => $p[0] === $p[1])) === 0);

$parejas = array_map(fn($p) => implode("-", $p), $c4);
comprobar("no hay cruces repetidos", count($parejas) === count(array_unique($parejas)));

echo "\n--- franjas ---\n";
$f = generar_franjas("2026-11-01", "2026-11-03", [14, 16], [1, 2]);
comprobar("3 dias x 2 horas x 2 sedes = 12 franjas", count($f) === 12);
comprobar("fechas bien formadas", $f[0]["fecha"] === "2026-11-01 14:00:00");
comprobar("rango invertido da vacio", count(generar_franjas("2026-11-05", "2026-11-01", [14], [1])) === 0);

echo "\n--- solapamiento ---\n";
comprobar("14:00 y 15:00 con 120 min SI se pisan", se_pisan("2026-11-01 14:00:00", "2026-11-01 15:00:00", 120));
comprobar("14:00 y 16:00 con 120 min NO se pisan", !se_pisan("2026-11-01 14:00:00", "2026-11-01 16:00:00", 120));
comprobar("14:00 y 15:00 con 60 min NO se pisan", !se_pisan("2026-11-01 14:00:00", "2026-11-01 15:00:00", 60));
comprobar("dias distintos no se pisan", !se_pisan("2026-11-01 14:00:00", "2026-11-02 14:00:00", 120));

echo "\n--- reparto completo ---\n";
$equipos = [10, 11, 12, 13];
$cruces = generar_cruces($equipos);
$franjas = generar_franjas("2026-11-01", "2026-11-10", [14, 16, 18], [1, 2]);
$r = repartir($cruces, $franjas, [100, 101], 120, [], 12345);

comprobar("los 6 partidos encontraron lugar", count($r["programados"]) === 6 && count($r["sin_lugar"]) === 0);

// Nadie puede estar en dos sitios a la vez
$choques_sede = 0; $choques_arbitro = 0; $choques_equipo = 0;
foreach ($r["programados"] as $i => $a) {
    foreach ($r["programados"] as $j => $b) {
        if ($i >= $j) { continue; }
        if (!se_pisan($a["fecha"], $b["fecha"], 120)) { continue; }
        if ($a["localidad_id"] === $b["localidad_id"]) { $choques_sede++; }
        if ($a["arbitro_id"] === $b["arbitro_id"]) { $choques_arbitro++; }
        $ea = [$a["equipo_local_id"], $a["equipo_visitante_id"]];
        $eb = [$b["equipo_local_id"], $b["equipo_visitante_id"]];
        if (count(array_intersect($ea, $eb)) > 0) { $choques_equipo++; }
    }
}
comprobar("ninguna sede doble reservada ($choques_sede)", $choques_sede === 0);
comprobar("ningun arbitro en dos partidos a la vez ($choques_arbitro)", $choques_arbitro === 0);
comprobar("ningun equipo juega dos veces a la vez ($choques_equipo)", $choques_equipo === 0);

echo "\n--- respeta lo que ya existe en la base ---\n";
$ocupado = [
    "localidades" => [1 => ["2026-11-01 14:00:00"]],
    "arbitros"    => [100 => ["2026-11-01 16:00:00"]],
    "equipos"     => [10 => ["2026-11-01 18:00:00"]],
];
// Las 14, 16 y 18 estan bloqueadas cada una por un motivo distinto.
// Las 20:00 es la unica salida posible, y ahi tiene que caer.
$r2 = repartir(generar_cruces([10, 11]), generar_franjas("2026-11-01", "2026-11-01", [14, 16, 18, 20], [1]), [100], 120, $ocupado, 7);
$p = $r2["programados"][0] ?? null;
comprobar("coloco el unico partido", $p !== null);
if ($p) {
    comprobar("no uso la sede 1 a las 14:00 (ya ocupada)", $p["fecha"] !== "2026-11-01 14:00:00");
    comprobar("no uso al arbitro 100 a las 16:00 (ya ocupado)", $p["fecha"] !== "2026-11-01 16:00:00");
    comprobar("no puso al equipo 10 a las 18:00 (ya juega)", $p["fecha"] !== "2026-11-01 18:00:00");
    comprobar("eligio la unica franja libre, las 20:00", $p["fecha"] === "2026-11-01 20:00:00");
}

// Y si las tres unicas franjas estan bloqueadas, no debe inventarse nada
$r2b = repartir(generar_cruces([10, 11]), generar_franjas("2026-11-01", "2026-11-01", [14, 16, 18], [1]), [100], 120, $ocupado, 7);
comprobar("con todo bloqueado no programa nada y lo reporta",
    count($r2b["programados"]) === 0 && count($r2b["sin_lugar"]) === 1);

echo "\n--- cuando no cabe, lo dice ---\n";
// 6 partidos pero solo 1 franja disponible
$r3 = repartir(generar_cruces([1,2,3,4]), generar_franjas("2026-11-01", "2026-11-01", [14], [1]), [100], 120, [], 3);
comprobar("coloco 1 y reporto 5 sin lugar",
    count($r3["programados"]) === 1 && count($r3["sin_lugar"]) === 5);

echo "\n--- sin arbitros no programa nada ---\n";
$r4 = repartir(generar_cruces([1,2]), generar_franjas("2026-11-01", "2026-11-05", [14], [1]), [], 120, [], 1);
comprobar("sin arbitros todo queda sin lugar", count($r4["programados"]) === 0 && count($r4["sin_lugar"]) === 1);

echo "\n" . ($fallos === 0 ? "TODOS LOS CASOS PASARON" : "$fallos CASOS FALLARON") . "\n";
