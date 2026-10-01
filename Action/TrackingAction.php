<?php

namespace Omnibus\Chronopost\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Chronopost\Api;
use Omnibus\Chronopost\Mapping;
use Omnibus\Model\Tracking as TrackingModel;
use Omnibus\Model\TrackingEvent;
use Omnibus\Model\TrackingStatus;
use Omnibus\Request\Request;
use Omnibus\Request\Tracking;

/** TrackingServiceWS's trackSkybillV2: the events, oldest first. */
final class TrackingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Tracking;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Tracking);
        $data = $this->api->call(Api::TRACKING, 'trackSkybillV2', '<language>'.(str_starts_with($request->locale, 'fr') ? 'fr_FR' : 'en_GB').'</language><skybillNumber>'.Api::e($request->trackingNumber).'</skybillNumber>');
        $events = [];
        foreach ($data->listEventInfoComp->events ?? $data->listEvents->events ?? [] as $e) {
            $events[] = new TrackingEvent(new \DateTimeImmutable((string) $e->eventDate), Mapping::status((string) $e->code, (string) $e->eventLabel), (string) $e->eventLabel, trim((string) $e->zipCode.' '.(string) $e->officeLabel) ?: null, (string) $e->code ?: null);
        }
        usort($events, static fn (TrackingEvent $a, TrackingEvent $b) => $a->at <=> $b->at);
        $request->setResult(new TrackingModel('chronopost', $request->trackingNumber, $events ? $events[array_key_last($events)]->status : TrackingStatus::UNKNOWN, $events));
    }
}
