# Implementación: Inventario mensual y cierre histórico

## Qué incorpora
- Nueva pestaña **Inventario mensual**.
- Vista preliminar del mes antes de confirmarlo.
- Saldo anterior, transferencias entre regionales, compras locales, total de ingresos, egresos y saldo del mes.
- Snapshot congelado por producto para preservar trazabilidad.
- Resumen por grupos de productos.
- Consulta posterior de cada cierre y exportación PDF/Excel.

## Regla importante
Un mes cerrado no se recalcula automáticamente: sus detalles quedan guardados. Esto evita que ingresos o salidas posteriores modifiquen un informe histórico ya emitido.

## Saldo anterior
Si existe un cierre del mes inmediatamente anterior, se usa su saldo final. Si no existe, el sistema reconstruye el saldo con los movimientos históricos disponibles antes del inicio del periodo.

## Ingresos
- `tipo_ingreso = transferencia`: Transferencias entre regionales.
- Los demás tipos actuales (`compra_local`, `donacion`, `devolucion`, `otro`) se incluyen provisionalmente en la columna Compras locales para que ningún ingreso quede fuera del saldo. Esta clasificación puede refinarse posteriormente si la doctora requiere columnas separadas.

## Instalación
Preferida: `php artisan migrate`.
Alternativa: importar `ACTUALIZAR_BD_CIERRE_MENSUAL.sql` en phpMyAdmin.
