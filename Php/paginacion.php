<?php

function paginar(PDO $conexion, string $sql_conteo, array $params = [], int $por_pagina = 20): array
{
    $por_pagina = max(5, min(200, $por_pagina));

    $stmt = $conexion->prepare($sql_conteo);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $paginas = max(1, (int)ceil($total / $por_pagina));

    $pedida = $_GET["pagina"] ?? "1";
    $pagina = ctype_digit((string)$pedida) ? (int)$pedida : 1;
    $pagina = max(1, min($paginas, $pagina));

    return [
        "pagina"     => $pagina,
        "paginas"    => $paginas,
        "por_pagina" => $por_pagina,
        "offset"     => ($pagina - 1) * $por_pagina,
        "total"      => $total,
        "desde"      => $total === 0 ? 0 : ($pagina - 1) * $por_pagina + 1,
        "hasta"      => min($total, $pagina * $por_pagina),
    ];
}

function paginar_total(int $total, int $por_pagina = 20): array
{
    $por_pagina = max(5, min(200, $por_pagina));
    $total = max(0, $total);
    $paginas = max(1, (int)ceil($total / $por_pagina));

    $pedida = $_GET["pagina"] ?? "1";
    $pagina = ctype_digit((string)$pedida) ? (int)$pedida : 1;
    $pagina = max(1, min($paginas, $pagina));

    return [
        "pagina"     => $pagina,
        "paginas"    => $paginas,
        "por_pagina" => $por_pagina,
        "offset"     => ($pagina - 1) * $por_pagina,
        "total"      => $total,
        "desde"      => $total === 0 ? 0 : ($pagina - 1) * $por_pagina + 1,
        "hasta"      => min($total, $pagina * $por_pagina),
    ];
}

function navegacion_paginas(array $pag, string $base, array $conservar = []): string
{
    if ($pag["total"] === 0) {
        return "";
    }

    $enlace = function (int $n) use ($base, $conservar): string {
        $query = array_merge($conservar, ["pagina" => $n]);
        return htmlspecialchars($base . "?" . http_build_query($query));
    };

    $html = '<div class="paginacion">';
    $html .= '<span class="paginacion-info">Mostrando '
           . $pag["desde"] . '&ndash;' . $pag["hasta"]
           . ' de <strong>' . number_format($pag["total"], 0, ".", ",") . '</strong></span>';

    if ($pag["paginas"] > 1) {
        $html .= '<span class="paginacion-botones">';

        if ($pag["pagina"] > 1) {
            $html .= '<a href="' . $enlace($pag["pagina"] - 1) . '" class="pag-btn">&larr; Anterior</a>';
        } else {
            $html .= '<span class="pag-btn pag-btn-inactivo">&larr; Anterior</span>';
        }

        $desde = max(1, $pag["pagina"] - 2);
        $hasta = min($pag["paginas"], $pag["pagina"] + 2);

        if ($desde > 1) {
            $html .= '<a href="' . $enlace(1) . '" class="pag-btn">1</a>';
            if ($desde > 2) {
                $html .= '<span class="pag-puntos">&hellip;</span>';
            }
        }

        for ($n = $desde; $n <= $hasta; $n++) {
            if ($n === $pag["pagina"]) {
                $html .= '<span class="pag-btn pag-btn-actual">' . $n . '</span>';
            } else {
                $html .= '<a href="' . $enlace($n) . '" class="pag-btn">' . $n . '</a>';
            }
        }

        if ($hasta < $pag["paginas"]) {
            if ($hasta < $pag["paginas"] - 1) {
                $html .= '<span class="pag-puntos">&hellip;</span>';
            }
            $html .= '<a href="' . $enlace($pag["paginas"]) . '" class="pag-btn">' . $pag["paginas"] . '</a>';
        }

        if ($pag["pagina"] < $pag["paginas"]) {
            $html .= '<a href="' . $enlace($pag["pagina"] + 1) . '" class="pag-btn">Siguiente &rarr;</a>';
        } else {
            $html .= '<span class="pag-btn pag-btn-inactivo">Siguiente &rarr;</span>';
        }

        $html .= '</span>';
    }

    $html .= '</div>';
    return $html;
}
