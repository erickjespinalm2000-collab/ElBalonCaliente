<?php

// Copia este archivo como config.php y ajusta los valores de tu entorno.
define("ENTORNO", "local");

if (ENTORNO === "produccion") {
    define("BD_HOST", "localhost");
    define("BD_NOMBRE", "cambiar_por_el_nombre_real");
    define("BD_USUARIO", "cambiar_por_el_usuario_real");
    define("BD_CLAVE", "cambiar_por_la_clave_real");
} else {
    define("BD_HOST", "localhost");
    define("BD_NOMBRE", "el_balon_caliente");
    define("BD_USUARIO", "root");
    define("BD_CLAVE", "");
}
