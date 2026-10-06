<?php
/**
 * actualizar_stock_simple.php
 * FASE 3 - Carga de stock real al 01/10/2026
 *
 * Lee SOLO columnas B (codigo), T (cantidad saldo), U (precio unitario saldo)
 * del XLSX y crea los lotes de apertura + cierre_mensual_detalles.
 *
 * Uso: C:\xampp\php\php.exe actualizar_stock_simple.php
 */

set_time_limit(300);
ini_set('memory_limit', '256M');

// ── Configuracion ─────────────────────────────────────────────────────────
$DB_HOST = '127.0.0.1';
$DB_PORT = '3306';
$DB_NAME = 'inventario_cajasalud';
$DB_USER = 'root';
$DB_PASS = '';

$XLSX = __DIR__ . '/Inventario con items y codigos liname actualizados al 01 de octubre de 2026.xlsx';

// Codigos S/C del XLSX → codigo institucional controlado
$MAP_SC = [
    'Acido Ascorbico (Vitamina C)' => 'SC-A001',
    'Loratadina'                   => 'SC-L001',
];

// ── Conexion PDO ──────────────────────────────────────────────────────────
echo "\n=== FASE 3: ACTUALIZAR STOCK REAL AL 01/10/2026 ===\n\n";

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "[OK] Conexion a MySQL: $DB_NAME\n";
} catch (PDOException $e) {
    die("[ERROR] Conexion fallida: " . $e->getMessage() . "\n");
}

// ── Verificar XLSX ────────────────────────────────────────────────────────
if (!file_exists($XLSX)) {
    die("[ERROR] No se encontro el XLSX:\n  $XLSX\n");
}
echo "[OK] XLSX encontrado: " . number_format(filesize($XLSX) / 1024, 1) . " KB\n\n";

// ── Leer XLSX via ZipArchive + SimpleXML ─────────────────────────────────
echo "[1/5] Leyendo XLSX (ZipArchive + SimpleXML)...\n";

$zip = new ZipArchive();
if ($zip->open($XLSX) !== true) {
    die("[ERROR] No se pudo abrir el XLSX como ZIP.\n");
}

$ssXml = $zip->getFromName('xl/sharedStrings.xml');
$wsXml = $zip->getFromName('xl/worksheets/sheet1.xml');
$zip->close();

// Parsear sharedStrings
$ss      = simplexml_load_string($ssXml);
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

// Parsear worksheet — solo columnas B, C, T, U
$ws    = simplexml_load_string($wsXml);
$items = [];   // solo items con stock > 0

foreach ($ws->sheetData->row as $xmlRow) {
    $rowNum = (int)$xmlRow['r'];
    if ($rowNum < 15) continue;

    $cells = [];
    foreach ($xmlRow->c as $cell) {
        $ref  = (string)$cell['r'];
        $type = (string)$cell['t'];
        $val  = (string)$cell->v;
        if ($type === 's') { $val = $strings[(int)$val] ?? ''; }
        $col = preg_replace('/[0-9]/', '', $ref);
        $cells[$col] = $val;
    }

    $codigo      = trim($cells['B'] ?? '');
    $descripcion = trim($cells['C'] ?? '');
    $cantidad    = (float)($cells['T'] ?? 0);
    $precio      = (float)($cells['U'] ?? 0);
    $partida     = trim($cells['A'] ?? '');

    if ($codigo === '' && $descripcion === '') continue;
    if (strtoupper($codigo) === 'TOTAL')        continue;

    // Resolver S/C
    if (strtoupper($codigo) === 'S/C') {
        $codigo = $MAP_SC[$descripcion] ?? ('SC-X' . $rowNum);
    }
    if ($codigo === '') continue;

    // Solo items con stock real
    if ($cantidad <= 0) continue;

    $items[] = [
        'fila'       => $rowNum,
        'codigo'     => $codigo,
        'descripcion'=> $descripcion,
        'partida'    => $partida,
        'cantidad'   => $cantidad,
        'precio'     => $precio,
        'importe'    => round($cantidad * $precio, 2),
    ];
}

echo "    -> Items con stock > 0 leidos: " . count($items) . "\n\n";

if (empty($items)) {
    die("[ERROR] No se encontraron items con stock > 0 en el XLSX.\n");
}

// ── Cargar mapa de medicamentos existentes ────────────────────────────────
echo "[2/5] Cargando mapa de medicamentos del catalogo...\n";

$medStmt = $pdo->query("SELECT id, codigo FROM medicamentos");
$medMap  = [];
foreach ($medStmt->fetchAll() as $m) {
    $medMap[$m['codigo']] = (int)$m['id'];
}
echo "    -> Medicamentos en catalogo: " . count($medMap) . "\n\n";

// ── Verificar / Crear cierre mensual ─────────────────────────────────────
echo "[3/5] Verificando cierre mensual Septiembre 2026...\n";

$cStmt = $pdo->prepare("SELECT id FROM cierres_mensuales WHERE periodo = '2026-09-01' LIMIT 1");
$cStmt->execute();
$cierre = $cStmt->fetch();

if ($cierre) {
    $cierreId = (int)$cierre['id'];
    echo "    -> Cierre ya existe (id=$cierreId). Usando existente.\n\n";
} else {
    // Calcular totales para la cabecera
    $totalCant    = array_sum(array_column($items, 'cantidad'));
    $totalImporte = array_sum(array_column($items, 'importe'));

    $ins = $pdo->prepare("
        INSERT INTO cierres_mensuales
            (almacen, periodo, fecha_desde, fecha_hasta, estado, total_items,
             importe_saldo_anterior, importe_ingresos_transferencia, importe_ingresos_compra_local,
             importe_total_ingresos, importe_egresos, importe_saldo_mes,
             observacion, cerrado_en, created_at, updated_at)
        VALUES
            ('REGIONAL LA PAZ','2026-09-01','2026-09-01','2026-09-30','CERRADO', :nitems,
             0, 0, 0, 0, 0, :importe,
             'Carga inicial stock real al 01/10/2026 desde XLSX SEPT-2026.', NOW(), NOW(), NOW())
    ");
    $ins->execute([':nitems' => count($items), ':importe' => round($totalImporte, 2)]);
    $cierreId = (int)$pdo->lastInsertId();
    echo "    -> Cierre mensual CREADO (id=$cierreId). Importe saldo: Bs " . number_format($totalImporte, 2) . "\n\n";
}

// ── Proveedor y Nota de Apertura ─────────────────────────────────────────
echo "[4/5] Preparando proveedor e ingreso de apertura...\n";

// Proveedor
$pStmt = $pdo->prepare("SELECT id FROM proveedores WHERE nombre = 'APERTURA INVENTARIO 2026' LIMIT 1");
$pStmt->execute();
$prov = $pStmt->fetch();
if ($prov) {
    $provId = (int)$prov['id'];
    echo "    -> Proveedor ya existe (id=$provId)\n";
} else {
    $ins = $pdo->prepare("INSERT INTO proveedores (nombre, nit, contacto, estado, created_at, updated_at) VALUES ('APERTURA INVENTARIO 2026','N/A','Sistema',1,NOW(),NOW())");
    $ins->execute();
    $provId = (int)$pdo->lastInsertId();
    echo "    -> Proveedor CREADO (id=$provId)\n";
}

// Ingreso de apertura
$nota   = 'APT-20261001-001';
$iStmt  = $pdo->prepare("SELECT id FROM ingresos WHERE numero_nota = :nota LIMIT 1");
$iStmt->execute([':nota' => $nota]);
$ingreso = $iStmt->fetch();
if ($ingreso) {
    $ingresoId = (int)$ingreso['id'];
    echo "    -> Ingreso apertura ya existe (id=$ingresoId)\n\n";
} else {
    $ins = $pdo->prepare("
        INSERT INTO ingresos
            (proveedor_id, almacen, fecha_ingreso, numero_nota, numero_remision, numero_factura,
             tipo_ingreso, observacion, recibido_por, autorizado_por, created_at, updated_at)
        VALUES
            (:prov, 'REGIONAL LA PAZ', '2026-10-01', :nota, 'APERTURA-INVENTARIO-01OCT2026', 'N/A',
             'apertura', 'Lote apertura stock real SEPT-2026 al 01/10/2026.', 'Sistema - Carga inicial',
             'CAJA DE SALUD DE CAMINOS', NOW(), NOW())
    ");
    $ins->execute([':prov' => $provId, ':nota' => $nota]);
    $ingresoId = (int)$pdo->lastInsertId();
    echo "    -> Ingreso apertura CREADO (id=$ingresoId)\n\n";
}

// ── Insertar lotes + cierre_mensual_detalles ──────────────────────────────
echo "[5/5] Insertando lotes y cierre_mensual_detalles...\n";

$pdo->beginTransaction();

$stmtLote = $pdo->prepare("
    INSERT INTO lotes
        (ingreso_id, medicamento_id, proveedor_id, codigo_lote, fecha_vencimiento,
         cantidad_inicial, cantidad_actual, precio_unitario, importe_total, created_at, updated_at)
    VALUES
        (:ingreso, :med, :prov, :lote, '2030-12-31',
         :cantI, :cantA, :pu, :imp, NOW(), NOW())
");

$stmtDet = $pdo->prepare("
    INSERT INTO cierre_mensual_detalles
        (cierre_mensual_id, medicamento_id, partida_codigo, codigo, descripcion,
         saldo_mes_cantidad, saldo_mes_precio, saldo_mes_importe,
         saldo_anterior_cantidad, saldo_anterior_precio, saldo_anterior_importe,
         transferencia_cantidad, transferencia_precio, transferencia_importe,
         compra_local_cantidad, compra_local_precio, compra_local_importe,
         total_ingresos_cantidad, total_ingresos_precio, total_ingresos_importe,
         egreso_cantidad, egreso_importe,
         created_at, updated_at)
    VALUES
        (:cierre, :med, :partida, :codigo, :desc,
         :cant, :pu, :imp,
         0, 0, 0,
         0, 0, 0,
         0, 0, 0,
         0, 0, 0,
         0, 0,
         NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        saldo_mes_cantidad = VALUES(saldo_mes_cantidad),
        saldo_mes_precio   = VALUES(saldo_mes_precio),
        saldo_mes_importe  = VALUES(saldo_mes_importe),
        updated_at         = NOW()
");

$lotesCreados     = 0;
$detallesCreados  = 0;
$sinCatalogo      = [];
$totalImporteLotes = 0.0;

try {
    foreach ($items as $item) {
        $medId = $medMap[$item['codigo']] ?? null;

        if ($medId === null) {
            // Medicamento no encontrado en catalogo — registrar para informe
            $sinCatalogo[] = "[Fila {$item['fila']}] {$item['codigo']} | {$item['descripcion']}";
            continue;
        }

        $cantI  = (int)round($item['cantidad']);
        $pu     = round($item['precio'], 6);
        $imp    = round($item['importe'], 2);

        // Lote de apertura
        $stmtLote->execute([
            ':ingreso' => $ingresoId,
            ':med'     => $medId,
            ':prov'    => $provId,
            ':lote'    => 'APT-20261001-' . $item['codigo'],
            ':cantI'   => $cantI,
            ':cantA'   => $cantI,
            ':pu'      => $pu,
            ':imp'     => $imp,
        ]);
        $lotesCreados++;
        $totalImporteLotes += $imp;

        // Detalle del cierre mensual
        $stmtDet->execute([
            ':cierre'  => $cierreId,
            ':med'     => $medId,
            ':partida' => $item['partida'],
            ':codigo'  => $item['codigo'],
            ':desc'    => $item['descripcion'],
            ':cant'    => $item['cantidad'],
            ':pu'      => $pu,
            ':imp'     => $imp,
        ]);
        $detallesCreados++;
    }

    $pdo->commit();

} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\n[ERROR CRITICO] ROLLBACK ejecutado.\n";
    echo "Detalle: " . $e->getMessage() . "\n";
    echo "En: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// ── Informe final ─────────────────────────────────────────────────────────
echo "\n";
echo "================================================================\n";
echo "  INFORME FINAL - FASE 3 COMPLETADA\n";
echo "================================================================\n";
echo "  Archivo XLSX procesado:         " . basename($XLSX) . "\n";
echo "  Items con stock > 0 en XLSX:    " . count($items) . "\n";
echo "  Lotes de apertura creados:      $lotesCreados\n";
echo "  Registros en cierre_mensual_detalles: $detallesCreados\n";
echo "  Valor total stock cargado:      Bs " . number_format($totalImporteLotes, 2) . "\n";
echo "  cierres_mensuales.id:           $cierreId\n";
echo "  ingresos.id (apertura):         $ingresoId\n";

// Verificar conteos finales en BD
$cntLotes  = $pdo->query("SELECT COUNT(*) FROM lotes WHERE ingreso_id = $ingresoId")->fetchColumn();
$cntDet    = $pdo->query("SELECT COUNT(*) FROM cierre_mensual_detalles WHERE cierre_mensual_id = $cierreId")->fetchColumn();
$cntMeds   = $pdo->query("SELECT COUNT(*) FROM medicamentos")->fetchColumn();

echo "\n  VERIFICACION EN BASE DE DATOS:\n";
echo "  lotes (apertura):               $cntLotes\n";
echo "  cierre_mensual_detalles:        $cntDet\n";
echo "  medicamentos (total catalogo):  $cntMeds\n";

if (!empty($sinCatalogo)) {
    echo "\n  ADVERTENCIA - " . count($sinCatalogo) . " codigos no encontrados en catalogo:\n";
    foreach (array_slice($sinCatalogo, 0, 20) as $sc) {
        echo "    $sc\n";
    }
    if (count($sinCatalogo) > 20) {
        echo "    ... y " . (count($sinCatalogo) - 20) . " mas.\n";
    }
}

echo "\n  El sistema esta listo para registrar movimientos desde el 01/10/2026.\n";
echo "================================================================\n\n";
