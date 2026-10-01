<?php

namespace Omnibus\Chronopost;

use Omnibus\Model\Address;
use Omnibus\Model\TrackingStatus;

/** Chronopost's XML for ours. Products: 01 Chrono 13, 02 Chrono 10, 16 Chrono 18, 86 Chrono Relais 13, 17 Chrono Classic (international), 44 Chrono Express. */
final class Mapping
{
    public const PRODUCTS = ['01' => 'Chrono 13', '02' => 'Chrono 10', '16' => 'Chrono 18', '86' => 'Chrono Relais 13', '5X' => 'Chrono Relais Europe', '17' => 'Chrono Classic', '44' => 'Chrono Express', '2O' => 'Chrono 13 Relais'];

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
