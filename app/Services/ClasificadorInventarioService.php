<?php

namespace App\Services;

use Illuminate\Support\Collection;
use RuntimeException;

class ClasificadorInventarioService
{
    private const CATALOGO_RELATIVO = 'js/data/catalogo_clasificacion_institucional.json';

    private static ?array $fuente = null;

    private array $catalogo;
    private array $grupos;
    private array $subgrupos;

    public function __construct()
    {
        $fuente = $this->cargarFuenteUnica();

        $this->catalogo = $fuente['por_codigo'] ?? [];
        $this->grupos = $fuente['grupos'] ?? [];
        $this->subgrupos = $fuente['subgrupos_laboratorio'] ?? [];
    }

    /**
     * Clasifica un producto usando primero el catálogo institucional que
     * también consume CierreMensual.vue.
     *
     * Mantiene exactamente el fallback de la vista previa: cuando no existe
     * clasificación por catálogo ni por grupo_producto, el ítem queda en
     * "OTROS MATERIALES Y SUMINISTROS", pero se marca como no clasificado
     * para que pueda ser detectado y reportado.
     */
    public function clasificar(?string $codigo, ?string $grupoProducto = null): ?array
    {
        $codigoNormalizado = $this->normalizarCodigo($codigo);

        if ($codigoNormalizado !== '' && isset($this->catalogo[$codigoNormalizado])) {
            return $this->catalogo[$codigoNormalizado] + ['clasificado' => true];
        }

        $grupoOrigen = $this->normalizar($grupoProducto);

        if ($grupoOrigen !== '') {
            foreach ($this->grupos as $grupo) {
                if ($this->normalizar($grupo) === $grupoOrigen) {
                    return ['grupo' => $grupo, 'subgrupo' => null, 'clasificado' => true];
                }
            }

            foreach ($this->subgrupos as $subgrupo) {
                if ($this->normalizar($subgrupo) === $grupoOrigen) {
                    return [
                        'grupo' => 'MATERIAL DE LABORATORIO Y REACTIVOS',
                        'subgrupo' => $subgrupo,
                        'clasificado' => true,
                    ];
                }
            }
        }

        return [
            'grupo' => 'OTROS MATERIALES Y SUMINISTROS',
            'subgrupo' => null,
            'clasificado' => false,
        ];
    }

    public function grupos(): array
    {
        return $this->grupos;
    }

    public function subgruposLaboratorio(): array
    {
        return $this->subgrupos;
    }

    public function normalizarCodigo(?string $codigo): string
    {
        $codigo = strtoupper(trim((string) $codigo));
        return preg_replace('/\.0$/', '', $codigo);
    }

    private function normalizar(?string $texto): string
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
            if ($convertido !== false) {
                $texto = $convertido;
            }
        }

        return strtoupper(preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Devuelve la clasificación para una colección sin modificar sus datos.
     */
    public function clasificaciones(Collection $detalles): array
    {
        $resultado = [];

        foreach ($detalles as $detalle) {
            $resultado[$detalle->getKey()] = $this->clasificar(
                $detalle->codigo ?? null,
                $detalle->grupo_producto ?? null
            );
        }

        return $resultado;
    }

    private function cargarFuenteUnica(): array
    {
        if (self::$fuente !== null) {
            return self::$fuente;
        }

        $ruta = resource_path(self::CATALOGO_RELATIVO);
        $contenido = @file_get_contents($ruta);

        if ($contenido === false) {
            throw new RuntimeException(
                'No se pudo leer el catálogo institucional de clasificación: ' . $ruta
            );
        }

        $fuente = json_decode($contenido, true);

        if (!is_array($fuente) || !isset($fuente['grupos'], $fuente['subgrupos_laboratorio'], $fuente['por_codigo'])) {
            throw new RuntimeException(
                'El catálogo institucional de clasificación no tiene una estructura válida.'
            );
        }

        self::$fuente = $fuente;

        return self::$fuente;
    }
}
