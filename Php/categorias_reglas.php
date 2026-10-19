<?php

/**
 * Reglas de las categorías por edad.
 *
 * Vive aparte porque la misma validación se necesita en tres sitios:
 * al inscribir un jugador, al moverlo de equipo y al cambiarle la
 * categoría a un equipo que ya tiene plantilla. Tenerla duplicada era
 * la forma segura de que las tres versiones terminaran distintas.
 *
 * La regla es: un jugador puede jugar en su categoría o en una mayor,
 * nunca en una menor. Es decir, solo se rechaza al que PASA de la edad
 * máxima. Un niño de 12 puede jugar en Sub-15; uno de 16 no.
 */

/**
 * Edad cumplida hoy. Devuelve null si no hay fecha de nacimiento.
 */
function edad_actual(?string $fecha_nacimiento): ?int
{
    if ($fecha_nacimiento === null || trim($fecha_nacimiento) === "") {
        return null;
    }

    $nacimiento = date_create($fecha_nacimiento);
    if ($nacimiento === false) {
        return null;
    }

    return (int)$nacimiento->diff(new DateTime("today"))->y;
}

/**
 * La categoría de un equipo, o null si el equipo no tiene ninguna
 * (o si no se recibió equipo, como en los deportes individuales).
 *
 * Devuelve ["nombre" => ..., "edad_maxima" => int].
 */
function categoria_del_equipo(PDO $conexion, $equipo_id): ?array
{
    if ($equipo_id === null || $equipo_id === "" || !ctype_digit((string)$equipo_id)) {
        return null;
    }

    $stmt = $conexion->prepare(
        "SELECT categorias.nombre, categorias.edad_maxima
         FROM equipos
         JOIN categorias ON categorias.id = equipos.categoria_id
         WHERE equipos.id = :id"
    );
    $stmt->execute(["id" => $equipo_id]);

    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fila) {
        return null;
    }

    return ["nombre" => $fila["nombre"], "edad_maxima" => (int)$fila["edad_maxima"]];
}

/**
 * Comprueba si un jugador cabe en una categoría.
 *
 * Devuelve null si todo está bien, o un código de error si no:
 *   "sin_fecha"  el equipo exige categoría y el jugador no tiene fecha
 *   "muy_mayor"  el jugador pasa de la edad máxima
 */
function revisar_edad(?int $edad, ?array $categoria): ?string
{
    // Sin categoría no hay restricción de ningún tipo.
    if ($categoria === null) {
        return null;
    }

    // Si el equipo tiene categoría, la fecha de nacimiento deja de ser
    // opcional: sin ella no hay forma de comprobar nada.
    if ($edad === null) {
        return "sin_fecha";
    }

    if ($edad > $categoria["edad_maxima"]) {
        return "muy_mayor";
    }

    return null;
}
