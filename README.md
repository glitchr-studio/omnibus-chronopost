# omnibus/chronopost

Chronopost for [glitchr/omnibus](https://github.com/glitchr-studio/omnibus): Quickcost prices,
skybills with their labels (ShippingServiceWS), tracking and cancellation (TrackingServiceWS),
Pickup relays (PointRelaisServiceWS) - the SOAP web services on ws.chronopost.fr.

```php
$gateway = (new ChronopostGatewayFactory($http))->create($options);   // $http: the application's HTTP client - none given, the factory makes its own; the options below
```

No framework needed: the package requires `glitchr/omnibus` and `symfony/http-client`. In a
Symfony application, the same through the bundle's configuration:

```yaml
omnibus:
    gateways:
        chronopost:
            factory: chronopost
            options:
                account_number: '%env(CHRONOPOST_ACCOUNT)%'   # 8 digits
                password: '%env(CHRONOPOST_PASSWORD)%'
                sub_account: null
                rates: [...]        # optional: configured prices instead of Quickcost
```

The service is the product code (01 Chrono 13, 02 Chrono 10, 16 Chrono 18, 86 Chrono Relais 13,
17 Chrono Classic, 44 Chrono Express, and the Chronofresh ranges below); a shipment with a `pickupPoint` defaults to 86. Shipment
options: `label_format` (PDF, ZPL), `products` (the codes Quickcost keeps), `product`, `use_by`,
`sell_by`, `saturday` (Chronofresh).

## Chronofresh

Food under controlled temperature (Chronopost Food). A shipment asks for a range with its
`product` option, and gives the parcel's use-by date:

| `product` | At home | To a relay | |
|---|---|---|---|
| `fresh` | 2R Chrono Fresh 13 | 6S Chrono Fresh Relais 13 | 0 to 4 °C |
| `freeze` | 2S Chrono Freeze 13 | - | -18 °C |
| `ambient` | 5M Chrono Ambient 13 | 5Q Chrono Ambient Relais 13 | dry food |

```php
$gateway->ship(new Shipment($sender, $recipient, [$parcel], options: [
    'product' => 'fresh',
    'use_by' => $useBy,        // a date or "2026-10-11": the shortest of what the parcel holds
    'sell_by' => null,         // when it differs from use_by
    'saturday' => false,       // true: delivered on a Saturday (service 6)
], shippingDate: $monday));
```

A `service` named on the shipment wins over the range. The use-by date goes as
`scheduledValue` (`expirationDate`, `sellByDate`). A fresh or frozen parcel without one, or whose
date does not outlast the shipping day, is refused here before Chronopost is asked: it would not
be delivered. Quickcost prices the range with the shipment option `products: ['2R']`, when the
contract has it; `rates` otherwise.

Chronofresh needs its own contract (a food business, an approved insulated packaging, parcels
handed over from Monday to the day the contract allows). **Not verified against a real
contract**: the codes and fields are those of the public integrations of Chronopost Food; the
order of `scheduledValue` in the request and the Saturday service in particular are to be
confirmed on a first real skybill.

Credentials: a Chronopost contract gives the account number and the web services password. There
is no sandbox: Chronopost's published test account is 19869502 / 255562
(`ChronopostGatewayFactory::TEST_ACCOUNT`), which issues test skybills.

Built from Chronopost's published web services documentation and tested on recorded answers; not
yet run against the test account.

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
