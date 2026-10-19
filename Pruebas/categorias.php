<?php
require_once __DIR__ . "/../Php/categorias_reglas.php";

$sub15 = ["nombre" => "Sub-15", "edad_maxima" => 15];
$libre = ["nombre" => "Libre",  "edad_maxima" => 99];

$casos = [
    // [edad, categoria, esperado, descripcion]
    [12,  $sub15, null,        "menor de 12 en Sub-15 -> SE PERMITE (jugar hacia arriba)"],
    [15,  $sub15, null,        "justo 15 en Sub-15 -> SE PERMITE (el limite entra)"],
    [16,  $sub15, "muy_mayor", "16 en Sub-15 -> SE RECHAZA"],
    [28,  $sub15, "muy_mayor", "28 en Sub-15 -> SE RECHAZA"],
    [28,  $libre, null,        "28 en Libre (99) -> SE PERMITE"],
    [null,$sub15, "sin_fecha", "sin fecha de nacimiento + categoria -> SE RECHAZA"],
    [null,null,   null,        "sin fecha y sin categoria -> SE PERMITE"],
    [40,  null,   null,        "cualquier edad sin categoria -> SE PERMITE"],
];

$fallos = 0;
foreach ($casos as [$edad, $cat, $esperado, $texto]) {
    $obtenido = revisar_edad($edad, $cat);
    $ok = $obtenido === $esperado;
    if (!$ok) { $fallos++; }
    printf("  %s  %s%s\n",
        $ok ? "OK  " : "FALLA",
        $texto,
        $ok ? "" : "   (esperaba " . var_export($esperado, true) . ", dio " . var_export($obtenido, true) . ")"
    );
}

echo "\n--- calculo de edad ---\n";
$hoy = new DateTime("today");
foreach ([15, 16, 17] as $anios) {
    $cumple = (clone $hoy)->modify("-{$anios} years")->format("Y-m-d");
    printf("  nacido %s  ->  %d anios\n", $cumple, edad_actual($cumple));
}
// El dia antes de cumplir todavia no cuenta el año
$casi = (clone $hoy)->modify("-16 years")->modify("+1 day")->format("Y-m-d");
printf("  nacido %s (cumple 16 manana) -> %d anios\n", $casi, edad_actual($casi));

echo "\n" . ($fallos === 0 ? "TODOS LOS CASOS PASARON" : "$fallos CASOS FALLARON") . "\n";
