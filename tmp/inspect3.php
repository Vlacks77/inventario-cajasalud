<?php
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
        if ($type === 's') {
            $val = $strings[(int)$val] ?? "";
        }
        $col = preg_replace('/[0-9]/', '', $ref);
        $rowData[$col] = $val;
    }
    $allRows[$rowNum] = $rowData;
}

// Ver últimas 30 filas
echo "=== Últimas 30 filas (para ver totales y estructura final) ===\n";
$total = count($allRows);
$keys = array_keys($allRows);
$last30 = array_slice($keys, -30);
foreach ($last30 as $rn) {
    $r = $allRows[$rn];
    $colA = $r['A'] ?? '';
    $colB = $r['B'] ?? '';
    $colC = $r['C'] ?? '';
    $colF = $r['F'] ?? '';
    $colT = $r['T'] ?? '';
    $colV = $r['V'] ?? '';
    echo "Fila $rn: A=[$colA] B=[$colB] C=[" . substr($colC,0,30) . "] F=[$colF] T=[$colT] V=[$colV]\n";
}

// Contar filas de datos reales (con código en columna B distinto de vacío/TOTAL/subtítulo)
echo "\n=== Análisis de filas de datos ===\n";
$filasDatos = 0;
$filasSubtotal = 0;
$codigosUnicos = [];
$codigosSC = [];
foreach ($allRows as $rn => $r) {
    if ($rn < 15) continue;
    $b = trim($r['B'] ?? '');
    $c = trim($r['C'] ?? '');
    $a = trim($r['A'] ?? '');
    if ($b === '' && $c === '') continue; // fila completamente vacía
    if ($b === '' && $c !== '') { $filasSubtotal++; continue; } // subtítulo de grupo
    if (is_numeric($b) || $b === 'TOTAL') { $filasSubtotal++; continue; }
    if ($b !== '') {
        $filasDatos++;
        if (strtoupper($b) === 'S/C' || trim($b) === '') {
            $codigosSC[] = "Fila $rn: B=[$b] C=[$c]";
        } else {
            $codigosUnicos[$b] = $c;
        }
    }
}
echo "Filas de datos reales: $filasDatos\n";
echo "Filas de subtotales/encabezados: $filasSubtotal\n";
echo "Códigos únicos con valor real: " . count($codigosUnicos) . "\n";
echo "\nCódigos S/C encontrados:\n";
foreach ($codigosSC as $sc) echo "  $sc\n";