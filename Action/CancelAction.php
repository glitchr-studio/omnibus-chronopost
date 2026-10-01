<?php

namespace Omnibus\Chronopost\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Chronopost\Api;
use Omnibus\Request\Cancel;
use Omnibus\Request\Request;

/** TrackingServiceWS's cancelSkybill: a skybill not yet picked up, cancelled. */
final class CancelAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Cancel;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Cancel);
        $data = $this->api->call(Api::TRACKING, 'cancelSkybill', '<accountNumber>'.Api::e($this->api->accountNumber).'</accountNumber><password>'.Api::e($this->api->password).'</password><language>fr_FR</language><skybillNumber>'.Api::e($request->trackingNumber).'</skybillNumber>');
        $request->setResult('0' === (string) ($data->errorCode ?? '0'));
    }
}
