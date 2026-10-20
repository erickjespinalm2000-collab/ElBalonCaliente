# El Balón Caliente

Aplicación web en PHP para registrar y consultar estadísticas deportivas, gestionar equipos, jugadores, ligas y eventos.

## Requisitos

- XAMPP (Apache, PHP y MySQL/MariaDB).
- Extensiones de PHP `pdo_mysql` y `mbstring`.

## Instalación local

1. Clona este repositorio dentro de `htdocs`.
2. Copia `Php/config.example.php` como `Php/config.php` y configura el acceso a la base de datos.
3. Crea la base de datos indicada en `Php/config.php` y prepara las tablas que requiere la aplicación. Este repositorio no incluye un volcado SQL.
4. Inicia Apache y MySQL desde el panel de XAMPP.
5. Abre `http://localhost/ElBalonCaliente/`.

No publiques `Php/config.php`: contiene la configuración específica de cada instalación y está excluido de Git.

## Archivos no incluidos

Por privacidad, el repositorio excluye el informe del proyecto, los diagramas editables y los archivos de diseño de `Informacion`. También excluye los logos de equipos cargados por usuarios en `Logos/Equipos`.
"# ElBalonCaliente" 
