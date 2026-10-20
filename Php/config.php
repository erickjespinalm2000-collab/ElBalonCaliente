<?php
define("ENTORNO", getenv("ENTORNO") ?: "local");

define("BD_HOST", getenv("DB_HOST") ?: "localhost");
define("BD_NOMBRE", getenv("DB_NAME") ?: "el_balon_caliente");
define("BD_USUARIO", getenv("DB_USER") ?: "root");
define("BD_CLAVE", getenv("DB_PASS") ?: "");
define("BD_PUERTO", getenv("DB_PORT") ?: "3306");
?>