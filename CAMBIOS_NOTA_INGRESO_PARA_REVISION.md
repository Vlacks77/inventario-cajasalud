# Cambios implementados: notas de ingreso y Kardex

Fecha de implementación: 12 de agosto de 2026  
Proyecto: Sistema de Inventario - Caja de Salud de Caminos

## Objetivo del cambio

El sistema dejó de registrar ingresos de inventario uno por uno. Ahora registra una **nota de ingreso**: una cabecera vinculada a una procedencia/proveedor y una lista de uno o más ítems recibidos.

Esto permite registrar, por ejemplo, diez medicamentos o insumos de una empresa en una sola operación, manteniendo lote, vencimiento y valores por cada ítem.

## Datos del encabezado de la nota

Cada nota guarda:

- Almacén (por defecto: `REGIONAL LA PAZ`).
- Procedencia o proveedor.
- Teléfono del proveedor (opcional).
- Fecha de ingreso.
- Número de nota correlativo generado por el sistema, con formato `N.º 000001`.
- Número de remisión (opcional).
- Número de factura (opcional).
- Tipo de ingreso: compra local, transferencia, donación, devolución u otro.
- Recibido por (se carga inicialmente con el usuario conectado, pero puede editarse).
- Autorizado por (por defecto: `CAJA NACIONAL DE CAMINOS`).
- Observaciones (opcional).

No se solicita en el formulario de ingreso:

- NIT del proveedor.
- Contacto del proveedor.
- Dirección del proveedor.
- Unidad de presentación del producto.

## Datos por ítem de la nota

La tabla permite agregar o quitar filas antes de guardar. Cada fila registra:

- Código de partida presupuestaria, por ejemplo `34200`.
- Nombre de la partida, por ejemplo `Medicamentos`.
- Código LINAME o código institucional del producto, por ejemplo `J0501`.
- Descripción/nombre del producto y concentración.
- Forma farmacéutica.
- Número de lote.
- Fecha de vencimiento.
- Cantidad recibida.
- Precio unitario en bolivianos.
- Importe total calculado: cantidad × precio unitario.

La pantalla calcula el total de todos los ítems de la nota, tanto como moneda como en formato literal (por ejemplo: `dos mil ciento treinta con 00/100 bolivianos`).

## Cambios en la base de datos

Se ejecutó la migración `2026_08_12_000000_create_partidas_e_ingresos_tables.php`.

### Tabla nueva: `partidas_presupuestarias`

Catálogo de familias de productos:

- `codigo` único.
- `nombre`.
- `estado`.

### Tabla nueva: `ingresos`

Representa la cabecera de cada nota de ingreso. Incluye proveedor, almacén, fecha, número de nota, remisión, factura, tipo, observación, recibido por y autorizado por.

### Cambios en `medicamentos`

- `partida_presupuestaria_id`: relación con la partida presupuestaria.
- `tipo_producto`: permite que la entidad represente medicamentos, prótesis, telas, insumos u otros productos.

El nombre técnico de la tabla se mantuvo como `medicamentos` para no romper los módulos existentes, aunque ahora se usa como catálogo general de productos.

### Cambios en `lotes`

- `ingreso_id`: vincula cada lote con su nota de ingreso.
- `precio_unitario`.
- `importe_total`.

## Funcionamiento del guardado

El endpoint `POST /api/ingresos` recibe una cabecera y una matriz de `items`.

Todo se registra en una transacción de base de datos: si una fila presenta un error, no se guarda ni la nota ni sus ítems. Para cada ítem el sistema crea o actualiza el producto según su código, crea/busca la partida presupuestaria y registra el lote con su cantidad, precio e importe.

El número correlativo se asigna después de crear la cabecera, utilizando el identificador de la tabla `ingresos`.

## Kardex / consultas de ingresos

Se añadió el endpoint `GET /api/kardex` y una vista de consulta.

Filtros disponibles:

- Producto, concentración o código LINAME.
- Procedencia/proveedor.
- Fecha desde.
- Fecha hasta.

Por cada movimiento se muestra:

- Fecha de ingreso.
- Número de nota y número de remisión.
- Partida presupuestaria.
- Código LINAME.
- Producto y forma farmacéutica.
- Procedencia.
- Lote.
- Vencimiento.
- Cantidad inicial recibida.
- Precio unitario e importe total.

## Archivos modificados o añadidos

### Backend

- `app/Http/Controllers/Api/IngresoController.php`
- `app/Http/Controllers/Api/InventarioController.php`
- `app/Http/Requests/StoreIngresoRequest.php`
- `app/Models/Ingreso.php` (nuevo)
- `app/Models/PartidaPresupuestaria.php` (nuevo)
- `app/Models/Lote.php`
- `app/Models/Medicamento.php`
- `database/migrations/2026_08_12_000000_create_partidas_e_ingresos_tables.php` (nueva)
- `routes/api.php`

### Frontend

- `resources/js/components/RegistrarIngreso.vue` (nuevo)
- `resources/js/components/Inventario.vue`
- `resources/js/App.vue`

## Verificaciones realizadas

- Sintaxis PHP verificada para controladores, modelos, request y migración.
- Migración ejecutada correctamente sobre la base de datos local.
- Compilación de Vue/Vite ejecutada correctamente con `npm run build`.
- Pruebas existentes de Laravel ejecutadas correctamente: 2 pruebas aprobadas.

## Puntos que conviene revisar o implementar después

1. **Catálogo institucional inicial:** importar desde el Excel todas las partidas y productos existentes para reducir el registro manual y habilitar autocompletado.
2. **PDF/reimpresión:** crear un módulo de notas de ingreso con búsqueda por fecha/número y exportación o impresión PDF.
3. **Numeración por gestión o almacén:** decidir si el correlativo debe ser global (actual), anual o independiente por almacén.
4. **Varios almacenes:** actualmente el almacén se escribe en la nota. Se puede convertir en un catálogo relacionado si se administrarán varias regionales.
5. **Validaciones institucionales:** confirmar si se deben permitir fechas de vencimiento pasadas para correcciones históricas y si la factura debe ser obligatoria en compras locales.
6. **Salida de otros insumos:** revisar que el módulo de salidas use terminología general de “producto” y no solamente “medicamento”.

