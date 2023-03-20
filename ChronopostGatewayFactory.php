<?php

namespace Omnibus\Chronopost;

use Omnibus\Core\GatewayFactory;

class ChronopostGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'omnibus.factory_name' => 'chronopost',
            'omnibus.factory_title' => 'Chronopost'
        ]);
    }
}