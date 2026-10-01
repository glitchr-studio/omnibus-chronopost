# omnibus/chronopost

Chronopost for [glitchr/omnibus](https://github.com/glitchr-studio/omnibus): Quickcost prices,
skybills with their labels (ShippingServiceWS), tracking and cancellation (TrackingServiceWS),
Pickup relays (PointRelaisServiceWS) - the SOAP web services on ws.chronopost.fr.

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
17 Chrono Classic, 44 Chrono Express); a shipment with a `pickupPoint` defaults to 86. Shipment
options: `label_format` (PDF, ZPL), `products` (the codes Quickcost keeps).

Credentials: a Chronopost contract gives the account number and the web services password. There
is no sandbox: Chronopost's published test account is 19869502 / 255562
(`ChronopostGatewayFactory::TEST_ACCOUNT`), which issues test skybills.

Built from Chronopost's published web services documentation and tested on recorded answers; not
yet run against the test account.

License: LGPL-3.0-or-later.
