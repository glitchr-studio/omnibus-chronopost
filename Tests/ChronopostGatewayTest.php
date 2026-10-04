<?php

namespace Omnibus\Chronopost\Tests;

use Omnibus\Chronopost\ChronopostGatewayFactory;
use Omnibus\Exception\CarrierException;
use Omnibus\Model\TrackingStatus;
use Omnibus\Tests\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ChronopostGatewayTest extends TestCase
{
    private array $calls = [];

    private function gateway(): \Omnibus\GatewayInterface
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertStringStartsWith('https://ws.chronopost.fr/', $url);
            $body = (string) $options['body'];
            $this->calls[] = [$url, $body];
            $wrap = static fn (string $op, string $inner) => new MockResponse('<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ns1:'.$op.'Response xmlns:ns1="http://cxf.soap.chronopost.fr/"><return>'.$inner.'</return></ns1:'.$op.'Response></soap:Body></soap:Envelope>');

            return match (true) {
                str_contains($body, 'calculateProducts') => $wrap('calculateProducts', '<errorCode>0</errorCode><productList><productCode>01</productCode><productLabel>Chrono 13</productLabel><amount>12.50</amount><amountTTC>15.00</amountTTC><deliveryMode>D</deliveryMode></productList><productList><productCode>86</productCode><productLabel>Chrono Relais 13</productLabel><amount>7.90</amount><amountTTC>9.48</amountTTC><deliveryMode>R</deliveryMode></productList>'),
                str_contains($body, 'shippingMultiParcelV4') => $wrap('shippingMultiParcelV4', '<errorCode>0</errorCode><resultMultiParcelValue><skybillNumber>XY123456789FR</skybillNumber><pdfEtiquette>'.base64_encode('%PDF-1.4 chrono').'</pdfEtiquette></resultMultiParcelValue>'),
                str_contains($body, 'trackSkybillV2') => $wrap('trackSkybillV2', '<errorCode>0</errorCode><listEventInfoComp><events><code>D</code><eventDate>2026-10-02T11:30:00+02:00</eventDate><eventLabel>Colis livré</eventLabel><officeLabel>PARIS 09</officeLabel><zipCode>75009</zipCode></events><events><code>PC</code><eventDate>2026-10-01T17:00:00+02:00</eventDate><eventLabel>Colis pris en charge</eventLabel><officeLabel>PARIS 02</officeLabel><zipCode>75002</zipCode></events></listEventInfoComp>'),
                str_contains($body, 'recherchePointChronopostInter') => $wrap('recherchePointChronopostInter', '<errorCode>0</errorCode><listePointRelais><identifiant>P12345</identifiant><nom>TABAC LE BRUXELLES</nom><adresse1>19 RUE DE BRUXELLES</adresse1><adresse2></adresse2><codePostal>75009</codePostal><localite>PARIS</localite><codePays>FR</codePays><coordGeolocalisationLatitude>48.8820</coordGeolocalisationLatitude><coordGeolocalisationLongitude>2.3320</coordGeolocalisationLongitude><distanceEnMetre>120</distanceEnMetre><listeHoraireOuverture><jour>1</jour><horairesAsString>08:00-12:30 14:00-19:30</horairesAsString></listeHoraireOuverture><listeHoraireOuverture><jour>7</jour><horairesAsString>00:00-00:00</horairesAsString></listeHoraireOuverture></listePointRelais>'),
                str_contains($body, 'cancelSkybill') => $wrap('cancelSkybill', '<errorCode>0</errorCode><errorMessage></errorMessage>'),
                default => $wrap('calculateProducts', '<errorCode>3</errorCode><errorMessage>Unknown operation</errorMessage>'),
            };
        });

        return (new ChronopostGatewayFactory($http))->create(['account_number' => ChronopostGatewayFactory::TEST_ACCOUNT, 'password' => ChronopostGatewayFactory::TEST_PASSWORD]);
    }

    public function testQuickcostPricesTheProductsCheapestFirstWithTheRelayFlag(): void
    {
        $rates = $this->gateway()->rate(Fixtures::shipment());
        self::assertSame(['86', '01'], array_map(fn ($r) => $r->service, $rates));
        self::assertSame(948, $rates[0]->amount, 'TTC');
        self::assertTrue($rates[0]->toPickupPoint);
        self::assertFalse($rates[1]->toPickupPoint);
        self::assertStringContainsString('quickcost-cxf', $this->calls[0][0]);
        self::assertStringContainsString('<accountNumber>19869502</accountNumber>', $this->calls[0][1]);
        self::assertStringContainsString('<weight>0.80</weight>', $this->calls[0][1]);
    }

    public function testASkybillIsIssuedWithItsPdf(): void
    {
        $label = $this->gateway()->ship(Fixtures::shipment());
        self::assertSame('XY123456789FR', $label->trackingNumber);
        self::assertSame('%PDF-1.4 chrono', $label->content);
        self::assertStringContainsString('<productCode>01</productCode>', $this->calls[0][1]);
        self::assertStringContainsString('<recipientName>Émile Zola</recipientName>', $this->calls[0][1]);
        self::assertStringContainsString('<shipperName>LTO</shipperName><shipperName2>La Touche Originale</shipperName2>', $this->calls[0][1]);
        self::assertStringContainsString('<mode>PDF</mode>', $this->calls[0][1]);
    }

    private function chilled(array $options, ?string $service = null, ?string $pickupPoint = null): \Omnibus\Model\Shipment
    {
        $s = Fixtures::shipment();

        return new \Omnibus\Model\Shipment($s->sender, $s->recipient, $s->parcels, $service, $pickupPoint, 'NT-0042', $options, new \DateTimeImmutable('2026-10-06 10:00'));
    }

    public function testAChronofreshParcelTravelsWithItsUseByDate(): void
    {
        $label = $this->gateway()->ship($this->chilled(['product' => 'fresh', 'use_by' => new \DateTimeImmutable('2026-10-11')]));

        self::assertSame('XY123456789FR', $label->trackingNumber);
        self::assertStringContainsString('<productCode>2R</productCode>', $this->calls[0][1]);
        self::assertStringContainsString('<service>0</service>', $this->calls[0][1]);
        self::assertStringContainsString('<scheduledValue><expirationDate>2026-10-11</expirationDate><sellByDate>2026-10-11</sellByDate></scheduledValue>', $this->calls[0][1]);
        self::assertStringContainsString('<shipDate>2026-10-06T10:00:00</shipDate>', $this->calls[0][1]);
    }

    public function testTheRangesAndTheirRelays(): void
    {
        $gateway = $this->gateway();
        $gateway->ship($this->chilled(['product' => 'fresh', 'use_by' => '2026-10-12', 'sell_by' => '2026-10-10', 'saturday' => true], null, 'P12345'));
        self::assertStringContainsString('<productCode>6S</productCode>', $this->calls[0][1]);
        self::assertStringContainsString('<expirationDate>2026-10-12</expirationDate><sellByDate>2026-10-10</sellByDate>', $this->calls[0][1]);
        self::assertStringContainsString('<service>6</service>', $this->calls[0][1]);

        $gateway->ship($this->chilled(['product' => 'freeze', 'use_by' => '2027-01-01']));
        self::assertStringContainsString('<productCode>2S</productCode>', $this->calls[1][1]);
        $gateway->ship($this->chilled(['product' => 'ambient']));
        self::assertStringContainsString('<productCode>5M</productCode>', $this->calls[2][1]);
        self::assertStringNotContainsString('scheduledValue', $this->calls[2][1], 'dry food carries no use-by date unless given one');
        $gateway->ship($this->chilled(['product' => 'fresh', 'use_by' => '2026-10-12'], '01'));
        self::assertStringContainsString('<productCode>01</productCode>', $this->calls[3][1], 'a service named wins over the range');
    }

    /** @dataProvider refusedChilledParcels */
    public function testAChilledParcelRefusedBeforeAnyCall(array $options, ?string $pickupPoint, string $message): void
    {
        try {
            $this->gateway()->ship($this->chilled($options, null, $pickupPoint));
            self::fail('shipped');
        } catch (CarrierException $e) {
            self::assertStringContainsString($message, $e->getMessage());
            self::assertSame([], $this->calls, 'Chronopost was not asked');
        }
    }

    public static function refusedChilledParcels(): iterable
    {
        yield 'no use-by date' => [['product' => 'fresh'], null, 'needs its use-by date'];
        yield 'a use-by date that does not outlast the shipping day' => [['product' => 'fresh', 'use_by' => '2026-10-06'], null, 'does not outlast'];
        yield 'frozen to a relay' => [['product' => 'freeze', 'use_by' => '2027-01-01'], 'P12345', 'does not go to a relay'];
        yield 'an unknown range' => [['product' => 'tepid'], null, 'Unknown Chronopost product'];
    }

    public function testTrackingRelaysAndCancel(): void
    {
        $gateway = $this->gateway();
        $tracking = $gateway->track('XY123456789FR');
        self::assertSame(TrackingStatus::DELIVERED, $tracking->status);
        self::assertSame(TrackingStatus::PENDING, $tracking->events[0]->status);
        self::assertSame('75009 PARIS 09', $tracking->latest()->location);

        $points = $gateway->pickupPoints(Fixtures::customer());
        self::assertSame('P12345', $points[0]->id);
        self::assertSame(120, $points[0]->distance);
        self::assertSame([['08:00', '12:30'], ['14:00', '19:30']], $points[0]->openingHours[1]);
        self::assertArrayNotHasKey(7, $points[0]->openingHours, 'closed on Sunday');

        self::assertTrue($gateway->cancel('XY123456789FR'));
    }

    public function testAnErrorCodeIsRaised(): void
    {
        $http = new MockHttpClient(new MockResponse('<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ns1:calculateProductsResponse xmlns:ns1="x"><return><errorCode>2</errorCode><errorMessage>Compte inconnu</errorMessage></return></ns1:calculateProductsResponse></soap:Body></soap:Envelope>'));
        try {
            (new ChronopostGatewayFactory($http))->create(['account_number' => '1', 'password' => '2'])->rate(Fixtures::shipment());
            self::fail('raised');
        } catch (CarrierException $e) {
            self::assertSame('2', $e->carrierCode);
            self::assertStringContainsString('Compte inconnu', $e->getMessage());
        }
    }
}
