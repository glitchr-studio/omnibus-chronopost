<?php

namespace Omnibus\Chronopost\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Chronopost\Api;
use Omnibus\Model\Address;
use Omnibus\Model\PickupPoint;
use Omnibus\Request\Pickup;
use Omnibus\Request\Request;

/** PointRelaisServiceWS's recherchePointChronopostInter: Pickup relays near an address. */
final class PickupAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Pickup;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Pickup);
        $near = $request->near;
        $parcel = $request->parcel;
        $data = $this->api->call(Api::RELAIS, 'recherchePointChronopostInter',
            '<accountNumber>'.Api::e($this->api->accountNumber).'</accountNumber><password>'.Api::e($this->api->password).'</password>'
            .'<address>'.Api::e($near->line(0)).'</address><zipCode>'.Api::e($near->postcode).'</zipCode><city>'.Api::e($near->city).'</city><countryCode>'.strtoupper($near->country).'</countryCode>'
            .'<type>P</type><productCode>86</productCode><service>L</service><weight>'.($parcel ? max(1, $parcel->weight) : 1000).'</weight><shippingDate>'.date('d/m/Y').'</shippingDate><maxPointChronopost>'.min(50, max(1, $request->limit)).'</maxPointChronopost><maxDistanceSearch>20</maxDistanceSearch><holidayTolerant>1</holidayTolerant>');
        $points = [];
        foreach ($data->listePointRelais ?? [] as $r) {
            $hours = [];
            foreach ($r->listeHoraireOuverture ?? [] as $h) {
                $day = (int) $h->jour;
                foreach (explode(' ', trim((string) $h->horairesAsString)) as $slot) {
                    if (preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $slot, $m) && '00:00' !== $m[2]) {
                        $hours[$day][] = [$m[1], $m[2]];
                    }
                }
            }
            $points[] = new PickupPoint('chronopost', (string) $r->identifiant, (string) $r->nom,
                new Address((string) $r->nom, array_values(array_filter([(string) $r->adresse1, (string) $r->adresse2])), (string) $r->codePostal, (string) $r->localite, (string) ($r->codePays ?: $near->country)),
                isset($r->coordGeolocalisationLatitude) ? (float) $r->coordGeolocalisationLatitude : null, isset($r->coordGeolocalisationLongitude) ? (float) $r->coordGeolocalisationLongitude : null,
                $hours, isset($r->distanceEnMetre) ? (int) $r->distanceEnMetre : null);
        }
        $request->setResult(\array_slice($points, 0, $request->limit));
    }
}
