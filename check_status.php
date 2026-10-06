<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=inventario_cajasalud;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$lotes = $pdo->query("SELECT count(*) as total, sum(cantidad_actual) as stock_total, sum(cantidad_actual * precio_unitario) as valor_total FROM lotes")->fetch(PDO::FETCH_ASSOC);
$ingresos = $pdo->query("SELECT count(*) as total FROM ingresos")->fetch(PDO::FETCH_ASSOC);
$cierres = $pdo->query("SELECT count(*) as total FROM cierres_mensuales")->fetch(PDO::FETCH_ASSOC);
$cierreDet = $pdo->query("SELECT count(*) as total FROM cierre_mensual_detalles")->fetch(PDO::FETCH_ASSOC);
$meds = $pdo->query("SELECT count(*) as total FROM medicamentos")->fetch(PDO::FETCH_ASSOC);

echo "MEDICAMENTOS: " . $meds['total'] . "\n";
echo "INGRESOS: " . $ingresos['total'] . "\n";
echo "LOTES: " . $lotes['total'] . "\n";
echo "CIERRES MENSUALES: " . $cierres['total'] . "\n";
echo "CIERRE MENSUAL DETALLES: " . $cierreDet['total'] . "\n";
echo "STOCK TOTAL: " . number_format($lotes['stock_total'], 2) . "\n";
echo "VALOR TOTAL: Bs " . number_format($lotes['valor_total'], 2) . "\n";

$ing = $pdo->query("SELECT * FROM ingresos LIMIT 1")->fetch(PDO::FETCH_ASSOC);
print_r($ing);
