<?php

namespace Omnibus\Chronopost;

use Omnibus\Model\Address;
use Omnibus\Model\TrackingStatus;

/** Chronopost's XML for ours. Products: 01 Chrono 13, 02 Chrono 10, 16 Chrono 18, 86 Chrono Relais 13, 17 Chrono Classic (international), 44 Chrono Express. */
final class Mapping
{
    public const PRODUCTS = [
        '01' => 'Chrono 13', '02' => 'Chrono 10', '16' => 'Chrono 18', '86' => 'Chrono Relais 13', '5X' => 'Chrono Relais Europe', '17' => 'Chrono Classic', '44' => 'Chrono Express', '2O' => 'Chrono 13 Relais',
        // Chronofresh (Chronopost Food): under controlled temperature.
        '2R' => 'Chrono Fresh 13', '6S' => 'Chrono Fresh Relais 13', '2S' => 'Chrono Freeze 13', '5M' => 'Chrono Ambient 13', '5Q' => 'Chrono Ambient Relais 13',
    ];

    /** The Chronofresh ranges a shipment asks for with its `product` option, at home and (where there is one) to a relay. */
    public const RANGES = [
        'fresh' => ['2R', '6S'],      // 0 to 4 °C
        'freeze' => ['2S', null],     // -18 °C: no relay
        'ambient' => ['5M', '5Q'],    // dry food
    ];

    /** The products that travel cold: Chronopost delivers them only with a use-by date, not yet passed. */
    public const CHILLED = ['2R', '6S', '2S'];

    /**
     * The product code of a shipment: the service it names, else its
     * `product` range (fresh, freeze, ambient) at home or to a relay, else
     * Chrono 13 (Chrono Relais 13 to a pickup point).
     */
    public static function product(?string $service, ?string $range, bool $toPickupPoint): string
    {
        if (null !== $service && '' !== $service) {
            return $service;
        }
        if (null !== $range && '' !== $range) {
            $codes = self::RANGES[strtolower($range)] ?? throw new \InvalidArgumentException(sprintf('Unknown Chronopost product "%s": fresh, freeze or ambient.', $range));

            return ($toPickupPoint ? $codes[1] : null) ?? ($toPickupPoint ? throw new \InvalidArgumentException(sprintf('Chronopost\'s "%s" product does not go to a relay.', $range)) : $codes[0]);
        }

        return $toPickupPoint ? '86' : '01';
    }

    /** A use-by date as Chronopost reads it (yyyy-mm-dd), from a date or a string. */
    public static function day(\DateTimeInterface|string|null $date): ?string
    {
        if (null === $date || '' === $date) {
            return null;
        }

        return ($date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable($date))->format('Y-m-d');
    }

    public static function valueXml(string $prefix, Address $a, bool $pro = false): string
    {
        return "<{$prefix}Civility>".($a->company ? 'E' : 'M')."</{$prefix}Civility>"
            ."<{$prefix}Name>".Api::e(mb_substr($a->company ?? $a->name, 0, 100))."</{$prefix}Name>"
            ."<{$prefix}Name2>".Api::e(mb_substr($a->company ? $a->name : '', 0, 100))."</{$prefix}Name2>"
            ."<{$prefix}Adress1>".Api::e(mb_substr($a->line(0), 0, 38))."</{$prefix}Adress1>"
            ."<{$prefix}Adress2>".Api::e(mb_substr($a->line(1), 0, 38))."</{$prefix}Adress2>"
            ."<{$prefix}ZipCode>".Api::e($a->postcode)."</{$prefix}ZipCode>"
            ."<{$prefix}City>".Api::e(mb_substr($a->city, 0, 50))."</{$prefix}City>"
            ."<{$prefix}Country>".strtoupper($a->country)."</{$prefix}Country>"
            ."<{$prefix}ContactName>".Api::e(mb_substr($a->name, 0, 100))."</{$prefix}ContactName>"
            ."<{$prefix}Email>".Api::e((string) $a->email)."</{$prefix}Email>"
            ."<{$prefix}Phone>".Api::e((string) $a->phone)."</{$prefix}Phone>"
            ."<{$prefix}Mobile>".Api::e((string) $a->phone)."</{$prefix}Mobile>"
            ."<{$prefix}PreAlert>0</{$prefix}PreAlert>";
    }

    public static function status(?string $code, ?string $label = null): TrackingStatus
    {
        $l = strtolower((string) $label);

        return match (true) {
            \in_array($code, ['D', 'DI', 'DL', 'DP', 'DR'], true) || str_contains($l, 'livré') || str_contains($l, 'delivered') => TrackingStatus::DELIVERED,
            \in_array($code, ['SD', 'DE'], true) || str_contains($l, 'en cours de livraison') || str_contains($l, 'out for delivery') => TrackingStatus::OUT_FOR_DELIVERY,
            \in_array($code, ['RD', 'RB', 'MA'], true) || str_contains($l, 'disponible') || str_contains($l, 'available') => TrackingStatus::AVAILABLE_FOR_PICKUP,
            \in_array($code, ['RE', 'RT', 'RTS'], true) || str_contains($l, 'retour') || str_contains($l, 'return') => TrackingStatus::RETURNED,
            \in_array($code, ['AE', 'AR', 'IN', 'AN', 'EXC'], true) || str_contains($l, 'anomalie') || str_contains($l, 'incident') || str_contains($l, 'absent') => TrackingStatus::EXCEPTION,
            \in_array($code, ['PC', 'PCH', 'TA'], true) || str_contains($l, 'préparation') || str_contains($l, 'annoncé') || str_contains($l, 'pris en charge') => TrackingStatus::PENDING,
            null !== $code && '' !== $code => TrackingStatus::IN_TRANSIT,
            default => TrackingStatus::UNKNOWN,
        };
    }
}
