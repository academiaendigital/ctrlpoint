<?php
/**
 * Reglas de precio de CTRL+:
 *  - Venta USD = costo × (1 + margen/100), redondeado HACIA ARRIBA al múltiplo de `redondeo_usd` (1 = dólar entero).
 *  - Margen: el del producto si tiene; si no, el global (`margen_ganancia`, 30 % por defecto).
 *  - Bs = USD (USDT) × tasa BCV × (1 + diferencial_bs/100).
 *  - EUR = USD × (tasa USD/Bs ÷ tasa EUR/Bs) × (1 + diferencial_eur/100).
 */
declare(strict_types=1);

function ajustes(): array
{
    static $a = null;
    if ($a === null) {
        $a = [];
        foreach (q('SELECT clave, valor FROM ajustes')->fetchAll() as $r) $a[$r['clave']] = $r['valor'];
    }
    return $a;
}

function ajuste(string $clave, string $porDefecto = '0'): string
{
    return ajustes()[$clave] ?? $porDefecto;
}

function calcular_precio(float $costo, float $margen, float $redondeo): float
{
    $redondeo = $redondeo > 0 ? $redondeo : 1.0;
    $bruto = round($costo * (1 + $margen / 100), 2);
    // round(…, 6) evita que 91.0000000001 suba a 92 por error de coma flotante
    return round(ceil(round($bruto / $redondeo, 6)) * $redondeo, 2);
}

function margen_de(?string $margenProducto): float
{
    return $margenProducto === null || $margenProducto === '' ? (float) ajuste('margen_ganancia', '30') : (float) $margenProducto;
}

function precio_venta(float|string $costo, ?string $margenProducto): float
{
    return calcular_precio((float) $costo, margen_de($margenProducto), (float) ajuste('redondeo_usd', '1'));
}

/** Tasa BCV vigente: la del día o la última publicada antes (fines de semana y feriados). */
function tasa_vigente(): ?array
{
    static $t = false;
    if ($t === false) $t = q('SELECT fecha, usd_bs, eur_bs FROM tasas WHERE fecha <= CURDATE() ORDER BY fecha DESC LIMIT 1')->fetch() ?: null;
    return $t;
}

function a_bs(float $usd, float $tasa): float
{
    return round($usd * $tasa * (1 + (float) ajuste('diferencial_bs') / 100), 2);
}

function a_eur(float $usd, array $tasa): float
{
    if ((float) $tasa['eur_bs'] <= 0) return 0.0;
    return round($usd * ((float) $tasa['usd_bs'] / (float) $tasa['eur_bs']) * (1 + (float) ajuste('diferencial_eur') / 100), 2);
}
