<?php

namespace Omnibus\Chronopost\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Chronopost\Api;
use Omnibus\Chronopost\Mapping;
use Omnibus\Exception\CarrierException;
use Omnibus\Model\Label;
use Omnibus\Request\Request;
use Omnibus\Request\Shipping;

/** ShippingServiceWS's shippingMultiParcelV4: the label (PDF; "ZPL" with option label_format) for a product (service: 01 Chrono 13 by default; a pickupPoint makes it a Relais product). */
final class ShippingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Shipping;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Shipping);
        $s = $request->shipment;
        $product = $s->service ?? ($s->pickupPoint ? '86' : '01');
        $format = 'ZPL' === strtoupper((string) $s->option('label_format', 'PDF')) ? 'ZPL' : 'PDF';
        $date = ($s->shippingDate ?? new \DateTimeImmutable())->format('Y-m-d\TH:i:s');
        $parcels = '';
        foreach ($s->parcels as $i => $p) {
            $parcels .= '<skybillValue><bulkNumber>'.($i + 1).'</bulkNumber><codCurrency>EUR</codCurrency><codValue>0</codValue><customsCurrency>'.Api::e($p->currency).'</customsCurrency><customsValue>'.number_format(($p->value ?? 0) / 100, 2, '.', '').'</customsValue><evtCode>DC</evtCode><insuredCurrency>EUR</insuredCurrency><insuredValue>0</insuredValue><objectType>MAR</objectType><productCode>'.Api::e($product).'</productCode><service>0</service><shipDate>'.$date.'</shipDate><shipHour>'.date('H').'</shipHour><weight>'.number_format(max(0.1, $p->weight / 1000), 2, '.', '').'</weight><weightUnit>KGM</weightUnit>'.($p->length && $p->width && $p->height ? '<length>'.$p->length.'</length><width>'.$p->width.'</width><height>'.$p->height.'</height>' : '').'<skybillRank>'.($i + 1).'</skybillRank></skybillValue>';
        }
        $data = $this->api->call(Api::SHIPPING, 'shippingMultiParcelV4',
            '<headerValue><accountNumber>'.Api::e($this->api->accountNumber).'</accountNumber><idEmit>CHRFR</idEmit><identWebPro></identWebPro><subAccount>'.Api::e((string) $this->api->subAccount).'</subAccount></headerValue>'
            .'<shipperValue>'.Mapping::valueXml('shipper', $s->sender).'</shipperValue>'
            .'<customerValue>'.Mapping::valueXml('customer', $s->sender).'<printAsSender>N</printAsSender></customerValue>'
            .'<recipientValue>'.Mapping::valueXml('recipient', $s->recipient).'<recipientType>'.($s->recipient->company ? '1' : '2').'</recipientType></recipientValue>'
            .'<refValue><recipientRef>'.Api::e(mb_substr((string) $s->reference, 0, 35)).'</recipientRef><shipperRef>'.Api::e(mb_substr((string) $s->reference, 0, 35)).'</shipperRef><customerSkybillNumber>'.Api::e(mb_substr((string) $s->reference, 0, 35)).'</customerSkybillNumber></refValue>'
            .$parcels
            .'<skybillParamsValue><mode>'.$format.'</mode><withReservation>0</withReservation></skybillParamsValue>'
            .'<password>'.Api::e($this->api->password).'</password><modeRetour>2</modeRetour><numberOfParcel>'.\count($s->parcels).'</numberOfParcel><version>2.0</version><multiParcel>'.(\count($s->parcels) > 1 ? 'Y' : 'N').'</multiParcel>'
            .($s->pickupPoint ? '<recipientLocalMetroValue/><scheduledValue/><esdValue><retrievalDateTime>'.$date.'</retrievalDateTime><closingDateTime>'.$date.'</closingDateTime><specificInstructions/><height>1</height><width>1</width><length>1</length><shipperCarriesCode>N</shipperCarriesCode><shipperBuildingFloor/><shipperServiceDirection/><ltAImprimerParChronopost>N</ltAImprimerParChronopost><nombreDePassageMaximum>1</nombreDePassageMaximum></esdValue>' : ''));
        $results = $data->resultMultiParcelValue ?? [];
        $first = $results[0] ?? $results;
        $number = (string) ($first->skybillNumber ?? '');
        if ('' === $number) {
            throw new CarrierException('chronopost', 'Chronopost issued no skybill.');
        }
        $encoded = (string) ($first->pdfEtiquette ?? $first->skybill ?? '');
        $request->setResult(new Label('chronopost', $number, '' !== $encoded ? base64_decode($encoded) : null, 'ZPL' === $format ? Label::ZPL : Label::PDF, null, 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT='.rawurlencode($number)));
    }
}
