<?php
// Inspeccionar filas S/C y los 12 códigos nuevos de la auditoría
$xlsxPath = "Inventario con items y codigos liname actualizados al 01 de octubre de 2026.xlsx";
$zip = new ZipArchive();
$zip->open($xlsxPath);
$sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
$worksheetXml     = $zip->getFromName('xl/worksheets/sheet1.xml');
$zip->close();

$ss = simplexml_load_string($sharedStringsXml);
$strings = [];
foreach ($ss->si as $si) {
    if (isset($si->t)) {
        $strings[] = (string)$si->t;
    } else {
        $txt = '';
        foreach ($si->r as $r) { $txt .= (string)$r->t; }
        $strings[] = $txt;
    }
}

$ws = simplexml_load_string($worksheetXml);
$allRows = [];
foreach ($ws->sheetData->row as $row) {
    $rowNum = (int)$row['r'];
    $rowData = [];
    foreach ($row->c as $cell) {
        $ref  = (string)$cell['r'];
        $type = (string)$cell['t'];
        $val  = (string)$cell->v;
        if ($type === 's') { $val = $strings[(int)$val] ?? ""; }
        $col = preg_replace('/[0-9]/', '', $ref);
        $rowData[$col] = $val;
    }
    $allRows[$rowNum] = $rowData;
}

// Códigos nuevos a buscar
$newCodes = ['A0304','J0541','Z34004','Z35020','Z38001','Z42270','DAC093','DTO047','DTO049','DTO051','DTO053','Z45038'];

echo "=== Filas S/C ===\n";
foreach ([792, 793] as $rn) {
    $r = $allRows[$rn];
    echo "Fila $rn:\n";
    foreach ($r as $col => $val) {
        if ($val !== '') echo "  [$col]: $val\n";
    }
}

echo "\n=== Códigos nuevos de auditoría ===\n";
foreach ($allRows as $rn => $r) {
    $b = trim($r['B'] ?? '');
    if (in_array($b, $newCodes)) {
        echo "Fila $rn:\n";
        foreach ($r as $col => $val) {
            if ($val !== '') echo "  [$col]: $val\n";
        }
    }
}

echo "\n=== Totales SALDO MES (col T) y VALOR SALDO MES (col V) ===\n";
$totalCantSaldo = 0;
$totalValSaldo  = 0;
$totalCantEgr   = 0;
$totalValEgr    = 0;
$totalCantIngTransf = 0;
$totalValIngTransf  = 0;
$totalCantIngCompra = 0;
$totalValIngCompra  = 0;
$totalCantSaldoAnterior = 0;
$totalValSaldoAnterior  = 0;
$nItems = 0;

foreach ($allRows as $rn => $r) {
    if ($rn < 15) continue;
    $b = trim($r['B'] ?? '');
    if ($b === '' || strtoupper($b) === 'TOTAL' || !isset($r['C'])) continue;
    $c = trim($r['C'] ?? '');
    if ($c === '') continue;
    $nItems++;
    $totalCantSaldoAnterior += (float)($r['F'] ?? 0);
    $totalValSaldoAnterior  += (float)($r['H'] ?? 0);
    $totalCantIngTransf     += (float)($r['I'] ?? 0);
    $totalValIngTransf      += (float)($r['K'] ?? 0);
    $totalCantIngCompra     += (float)($r['L'] ?? 0);
    $totalValIngCompra      += (float)($r['N'] ?? 0);
    $totalCantEgr           += (float)($r['R'] ?? 0);
    $totalValEgr            += (float)($r['S'] ?? 0);
    $totalCantSaldo         += (float)($r['T'] ?? 0);
    $totalValSaldo          += (float)($r['V'] ?? 0);
}

echo "Items procesados: $nItems\n";
echo "Saldo Anterior Cantidad: $totalCantSaldoAnterior\n";
echo "Saldo Anterior Importe:  " . round($totalValSaldoAnterior, 2) . " Bs\n";
echo "Ingresos Transf Cantidad: $totalCantIngTransf\n";
echo "Ingresos Transf Importe:  " . round($totalValIngTransf, 2) . " Bs\n";
echo "Ingresos Compra Cantidad: $totalCantIngCompra\n";
echo "Ingresos Compra Importe:  " . round($totalValIngCompra, 2) . " Bs\n";
echo "Egresos Cantidad:         $totalCantEgr\n";
echo "Egresos Importe:          " . round($totalValEgr, 2) . " Bs\n";
echo "SALDO MES Cantidad:       $totalCantSaldo\n";
echo "SALDO MES Importe:        " . round($totalValSaldo, 2) . " Bs\n";