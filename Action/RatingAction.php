<?php

namespace Omnibus\Chronopost\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Chronopost\Api;
use Omnibus\Chronopost\Mapping;
use Omnibus\Model\Rate;
use Omnibus\Request\Rating;
use Omnibus\Request\Request;

/** Quickcost's calculateProducts: every product for the lane, with the contract's prices (option products: the codes to keep). */
final class RatingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Rating;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Rating);
        $s = $request->shipment;
        $data = $this->api->call(Api::QUICKCOST, 'calculateProducts',
            '<accountNumber>'.Api::e($this->api->accountNumber).'</accountNumber><password>'.Api::e($this->api->password).'</password>'
            .'<depCountryCode>'.strtoupper($s->sender->country).'</depCountryCode><depZipCode>'.Api::e($s->sender->postcode).'</depZipCode>'
            .'<arrCountryCode>'.strtoupper($s->recipient->country).'</arrCountryCode><arrZipCode>'.Api::e($s->recipient->postcode).'</arrZipCode><arrCity>'.Api::e($s->recipient->city).'</arrCity>'
            .'<type>'.($s->pickupPoint ? 'R' : 'D').'</type><weight>'.number_format(max(0.1, $s->weight() / 1000), 2, '.', '').'</weight>');
        $keep = $s->option('products');
        $rates = [];
        foreach ($data->productList ?? [] as $product) {
            $code = (string) $product->productCode;
            if ($keep && !\in_array($code, (array) $keep, true)) {
                continue;
            }
            $amount = (string) ($product->amountTTC ?: $product->amount);
            if ('' === $amount) {
                continue;
            }
            $rates[] = new Rate('chronopost', $code, (string) ($product->productLabel ?: Mapping::PRODUCTS[$code] ?? 'Chronopost '.$code), (int) round(((float) $amount) * 100), 'EUR', null, 'R' === (string) $product->deliveryMode || \in_array($code, ['86', '5X', '2O'], true));
        }
        usort($rates, static fn (Rate $a, Rate $b) => $a->amount <=> $b->amount);
        $request->setResult($rates);
    }
}
