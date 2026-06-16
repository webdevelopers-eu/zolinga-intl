<?php

declare(strict_types=1);

namespace Zolinga\Intl;

use Zolinga\System\Events\{ListenerInterface, CliRequestResponseEvent};
use Zolinga\System\Types\OriginEnum;

/**
 * CLI handler that runs the full autotranslate pipeline:
 * extract → autotranslate → compile.
 *
 * All request parameters are forwarded to each sub-event.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-05-12
 */
class AutotranslateCli implements ListenerInterface
{
    /**
     * Run the full autotranslate pipeline: extract, autotranslate, compile.
     *
     * @param CliRequestResponseEvent $event
     * @return void
     */
    public function run(CliRequestResponseEvent $event): void
    {
        global $api;

        $steps = ['gettext:extract', 'gettext:autotranslate', 'gettext:reload', 'gettext:compile'];

        foreach ($steps as $eventName) {
            $api->log->info('i18n', "▶️  Running $eventName...");

            $subEvent = new CliRequestResponseEvent($eventName, OriginEnum::CLI, $event->request);
            $subEvent->dispatch();

            if ($subEvent->status !== $subEvent::STATUS_OK) {
                $event->setStatus($subEvent->status, "Step $eventName failed: " . $subEvent->message);
                return;
            }
        }

        $event->setStatus($event::STATUS_OK, 'Autotranslate pipeline completed');
    }
}