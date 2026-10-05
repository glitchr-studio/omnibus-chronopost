<?php

namespace Omnibus\Chronopost;

use Omnibus\Chronopost\Action\CancelAction;
use Omnibus\Chronopost\Action\PickupAction;
use Omnibus\Chronopost\Action\RatingAction;
use Omnibus\Chronopost\Action\ShippingAction;
use Omnibus\Chronopost\Action\TrackingAction;
use Omnibus\Config;
use Omnibus\GatewayFactory;
use Symfony\Component\HttpClient\HttpClient;

/**
 *   options:
 *     account_number: '%env(CHRONOPOST_ACCOUNT)%'   # the 8-digit contract number
 *     password: '%env(CHRONOPOST_PASSWORD)%'        # its web services password
 *     sub_account: null                             # optional, 3 digits
 *     rates: [...]                                  # optional: configured prices instead of Quickcost
 *
 * Chronopost has no sandbox: the test account 19869502 / 255562 is the published one.
 */
final class ChronopostGatewayFactory extends GatewayFactory
{
    /**
     * Chronopost's test account, published in its web services' documentation,
     * the same for everyone: test identifiers, not a secret.
     */
    public const TEST_ACCOUNT = '19869502';
    public const TEST_PASSWORD = '255562';

    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibus.factory_name' => 'chronopost',
            'omnibus.factory_title' => 'Chronopost',
            'omnibus.required_options' => ['account_number', 'password'],
            'sub_account' => null,
            'omnibus.api' => function (Config $c) {
                $http = $this->http ?? HttpClient::create();

                return new Api($http, (string) $c['account_number'], (string) $c['password'], $c['sub_account'] ?: null);
            },
            'omnibus.action.rating' => static fn (Config $c) => $c->get('rates') ? null : new RatingAction(),
            'omnibus.action.shipping' => new ShippingAction(),
            'omnibus.action.tracking' => new TrackingAction(),
            'omnibus.action.pickup' => new PickupAction(),
            'omnibus.action.cancel' => new CancelAction(),
        ]);
    }
}
