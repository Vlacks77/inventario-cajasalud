<?php
// Inspeccionar estructura del XLSX usando ZipArchive (sin PhpSpreadsheet)
// Un .xlsx es un ZIP con XML interno
$xlsxPath = "Inventario con items y codigos liname actualizados al 01 de octubre de 2026.xlsx";

if (!file_exists($xlsxPath)) {
    die("ERROR: No se encuentra el archivo XLSX\n");
}

$zip = new ZipArchive();
if ($zip->open($xlsxPath) !== true) {
    die("ERROR: No se pudo abrir el XLSX como ZIP\n");
}

// Listar archivos dentro del ZIP
echo "=== Archivos internos del XLSX ===\n";
for ($i = 0; $i < min($zip->numFiles, 30); $i++) {
    echo "  " . $zip->getNameIndex($i) . "\n";
}

// Leer la hoja de trabajo
$sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
$worksheetXml     = $zip->getFromName('xl/worksheets/sheet1.xml');
$workbookXml      = $zip->getFromName('xl/workbook.xml');
$zip->close();

// Parsear workbook para ver las hojas
echo "\n=== Hojas disponibles ===\n";
$wb = simplexml_load_string($workbookXml);
foreach ($wb->sheets->sheet as $sheet) {
    echo "  Hoja: " . (string)$sheet['name'] . "\n";
}

// Parsear sharedStrings
$ss = simplexml_load_string($sharedStringsXml);
$strings = [];
foreach ($ss->si as $si) {
    if (isset($si->t)) {
        $strings[] = (string)$si->t;
    } else {
        // rich text
        $txt = '';
        foreach ($si->r as $r) {
            $txt .= (string)$r->t;
        }
        $strings[] = $txt;
    }
}

// Parsear worksheet - primeras 10 filas para ver estructura
echo "\n=== Primeras 10 filas del worksheet ===\n";
$ws = simplexml_load_string($worksheetXml);
$rowCount = 0;
foreach ($ws->sheetData->row as $row) {
    $rowCount++;
    if ($rowCount > 10) break;
    $rowNum = (string)$row['r'];
    echo "Fila $rowNum: ";
    $cells = [];
    foreach ($row->c as $cell) {
        $ref = (string)$cell['r'];
        $type = (string)$cell['t'];
        $val = (string)$cell->v;
        if ($type === 's') {
            $val = $strings[(int)$val] ?? "?";
        }
        $cells[$ref] = $val;
    }
    // Mostrar solo las primeras 20 celdas
    $count = 0;
    foreach ($cells as $ref => $val) {
        echo "[$ref]=" . substr($val, 0, 30) . " | ";
        if (++$count >= 20) { echo "..."; break; }
    }
    echo "\n";
}

echo "\n=== Total de filas en worksheet ===\n";
// Contar filas totales
$totalRows = 0;
foreach ($ws->sheetData->row as $row) {
    $totalRows++;
}
echo "Total filas: $totalRows\n";

// Ver dimension si existe
if (isset($ws->dimension)) {
    echo "Dimension declarada: " . (string)$ws->dimension['ref'] . "\n";
}