<?php

function es_admin(): bool
{
    return ($_SESSION["rol"] ?? "") === "admin";
}

function exigir_admin(string $volver_a = "menu.php", bool $desde_formularios = false): void
{
    if (es_admin()) {
        return;
    }

    $destino = $desde_formularios ? $volver_a : "../Formularios/" . $volver_a;
    $separador = str_contains($destino, "?") ? "&" : "?";

    header("Location: " . $destino . $separador . "error=sin_permiso");
    exit;
}

function puede_eliminar(): bool
{
    return es_admin();
}

function mensaje_sin_permiso(): string
{
    return "No tienes permiso para esa acción. Solo un administrador puede hacerlo.";
}
