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
$rowCount = 0;
foreach ($ws->sheetData->row as $row) {
    $rowCount++;
    if ($rowCount < 9)  continue; // Saltar filas vacías iniciales
    if ($rowCount > 25) break;
    $rowNum = (string)$row['r'];
    echo "=== Fila $rowNum ===\n";
    foreach ($row->c as $cell) {
        $ref  = (string)$cell['r'];
        $type = (string)$cell['t'];
        $val  = (string)$cell->v;
        if ($type === 's') {
            $val = $strings[(int)$val] ?? "?";
        }
        // Solo columnas A-AO
        $col = preg_replace('/[0-9]/', '', $ref);
        echo "  [$ref] ($col): " . substr($val, 0, 60) . "\n";
    }
}