<?php
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once "conexion.php";

$candidatos = [];
foreach (explode(",", (string)($_GET["u"] ?? "")) as $u) {
    $u = trim($u);
    if ($u !== "" && preg_match('/^[A-Za-z0-9_.]{1,50}$/', $u)) {
        $candidatos[] = $u;
    }
}

$candidatos = array_slice(array_values(array_unique($candidatos)), 0, 50);

if (count($candidatos) === 0) {
    echo json_encode(["ocupados" => []]);
    exit;
}

$marcas = implode(",", array_fill(0, count($candidatos), "?"));
$stmt = $conexion->prepare("SELECT nombre_usuario FROM usuarios WHERE nombre_usuario IN ($marcas)");
$stmt->execute($candidatos);

echo json_encode(["ocupados" => array_map("strtolower", $stmt->fetchAll(PDO::FETCH_COLUMN))]);