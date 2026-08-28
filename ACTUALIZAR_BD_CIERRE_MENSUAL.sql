-- Actualización: módulo Inventario mensual / Cierre mensual
CREATE TABLE IF NOT EXISTS `cierres_mensuales` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `almacen` varchar(150) NOT NULL DEFAULT 'REGIONAL LA PAZ', `periodo` date NOT NULL,
 `fecha_desde` date NOT NULL, `fecha_hasta` date NOT NULL, `usuario_id` bigint unsigned NULL,
 `estado` varchar(20) NOT NULL DEFAULT 'CERRADO', `total_items` int unsigned NOT NULL DEFAULT 0,
 `importe_saldo_anterior` decimal(16,2) NOT NULL DEFAULT 0, `importe_ingresos_transferencia` decimal(16,2) NOT NULL DEFAULT 0,
 `importe_ingresos_compra_local` decimal(16,2) NOT NULL DEFAULT 0, `importe_total_ingresos` decimal(16,2) NOT NULL DEFAULT 0,
 `importe_egresos` decimal(16,2) NOT NULL DEFAULT 0, `importe_saldo_mes` decimal(16,2) NOT NULL DEFAULT 0,
 `observacion` text NULL, `cerrado_en` timestamp NULL, `created_at` timestamp NULL, `updated_at` timestamp NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `cierres_mensuales_periodo_unique` (`periodo`), KEY `cierres_mensuales_usuario_id_foreign` (`usuario_id`),
 CONSTRAINT `cierres_mensuales_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `cierre_mensual_detalles` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `cierre_mensual_id` bigint unsigned NOT NULL, `medicamento_id` bigint unsigned NULL,
 `partida_codigo` varchar(20) NULL, `codigo` varchar(100) NULL, `descripcion` varchar(500) NOT NULL, `forma_farmaceutica` varchar(255) NULL, `grupo_producto` varchar(255) NULL,
 `saldo_anterior_cantidad` decimal(16,3) NOT NULL DEFAULT 0, `saldo_anterior_precio` decimal(16,6) NOT NULL DEFAULT 0, `saldo_anterior_importe` decimal(16,2) NOT NULL DEFAULT 0,
 `transferencia_cantidad` decimal(16,3) NOT NULL DEFAULT 0, `transferencia_precio` decimal(16,6) NOT NULL DEFAULT 0, `transferencia_importe` decimal(16,2) NOT NULL DEFAULT 0,
 `compra_local_cantidad` decimal(16,3) NOT NULL DEFAULT 0, `compra_local_precio` decimal(16,6) NOT NULL DEFAULT 0, `compra_local_importe` decimal(16,2) NOT NULL DEFAULT 0,
 `total_ingresos_cantidad` decimal(16,3) NOT NULL DEFAULT 0, `total_ingresos_precio` decimal(16,6) NOT NULL DEFAULT 0, `total_ingresos_importe` decimal(16,2) NOT NULL DEFAULT 0,
 `egreso_cantidad` decimal(16,3) NOT NULL DEFAULT 0, `egreso_importe` decimal(16,2) NOT NULL DEFAULT 0,
 `saldo_mes_cantidad` decimal(16,3) NOT NULL DEFAULT 0, `saldo_mes_precio` decimal(16,6) NOT NULL DEFAULT 0, `saldo_mes_importe` decimal(16,2) NOT NULL DEFAULT 0,
 `created_at` timestamp NULL, `updated_at` timestamp NULL, PRIMARY KEY (`id`),
 KEY `cmd_cierre_codigo_idx` (`cierre_mensual_id`,`codigo`), KEY `cmd_medicamento_idx` (`medicamento_id`),
 CONSTRAINT `cmd_cierre_fk` FOREIGN KEY (`cierre_mensual_id`) REFERENCES `cierres_mensuales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
