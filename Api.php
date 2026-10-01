<?php

namespace Omnibus\Chronopost;

use Omnibus\Exception\CarrierException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Chronopost's web services (SOAP 1.1, ws.chronopost.fr): Quickcost for
 * prices, ShippingServiceWS for labels, TrackingServiceWS for events,
 * PointRelaisServiceWS for Pickup points. The account number and password
 * travel inside each request's body.
 */
final class Api
{
    public const BASE = 'https://ws.chronopost.fr';
    public const QUICKCOST = ['/quickcost-cxf/QuickcostServiceWS', 'http://cxf.quickcost.soap.chronopost.fr/'];
    public const SHIPPING = ['/shipping-cxf/ShippingServiceWS', 'http://cxf.shipping.soap.chronopost.fr/'];
    public const TRACKING = ['/tracking-cxf/TrackingServiceWS', 'http://cxf.tracking.soap.chronopost.fr/'];
    public const RELAIS = ['/recherchebt-ws-cxf/PointRelaisServiceWS', 'http://cxf.rechercherbt.soap.chronopost.fr/'];

    public function __construct(
        private readonly HttpClientInterface $http,
        public readonly string $accountNumber,
        public readonly string $password,
        public readonly ?string $subAccount = null,
        private readonly int $timeout = 20,
    ) {
    }

    /**
     * One SOAP call: the operation and its parameters as inner XML; back as the <return> element.
     *
     * @param array{string, string} $service
     */
    public function call(array $service, string $operation, string $params): \SimpleXMLElement
    {
        [$path, $ns] = $service;
        $envelope = '<?xml version="1.0" encoding="utf-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:cxf="'.$ns.'"><soapenv:Header/><soapenv:Body><cxf:'.$operation.'>'.$params.'</cxf:'.$operation.'></soapenv:Body></soapenv:Envelope>';
        try {
            $response = $this->http->request('POST', self::BASE.$path, [
                'headers' => ['Content-Type' => 'text/xml; charset=utf-8', 'SOAPAction' => '""'],
                'body' => $envelope,
                'timeout' => $this->timeout,
            ]);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (HttpExceptionInterface $e) {
            throw new CarrierException('chronopost', 'Chronopost request failed: '.$e->getMessage(), null, $e);
        }
        $xml = @simplexml_load_string(preg_replace(['/<(\/?)[\w-]+:/', '/\sxmlns(:\w+)?="[^"]*"/'], ['<$1', ''], $content));
        if (false === $xml) {
            throw new CarrierException('chronopost', sprintf('Chronopost answered HTTP %d with a body that is not SOAP.', $status));
        }
        if (isset($xml->Body->Fault)) {
            throw new CarrierException('chronopost', (string) ($xml->Body->Fault->faultstring ?? 'SOAP fault'));
        }
        $return = $xml->Body->children()[0]->return ?? null;
        if ($status >= 400 || null === $return) {
            throw new CarrierException('chronopost', sprintf('HTTP %d', $status));
        }
        if (isset($return->errorCode) && '0' !== (string) $return->errorCode) {
            throw new CarrierException('chronopost', (string) ($return->errorMessage ?: 'Chronopost error '.$return->errorCode), (string) $return->errorCode);
        }

        return $return;
    }

    public static function e(string $s): string
    {
        return htmlspecialchars($s, \ENT_XML1 | \ENT_QUOTES, 'UTF-8');
    }

    /** Chronopost's 3-digit type code for a sender/recipient: pro or private. */
    public static function civility(\Omnibus\Model\Address $a): string
    {
        return $a->company ? 'E' : 'M';
    }
}
