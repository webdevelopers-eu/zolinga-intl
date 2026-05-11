<?php

declare(strict_types=1);

namespace Zolinga\Intl\Events;

use ArrayAccess;
use ArrayObject;
use Zolinga\Intl\Types\GettextTemplateEnum;
use Zolinga\System\Events\RequestResponseEvent;
use Zolinga\System\Types\OriginEnum;

/**
 * Translation event for async translation via AI.
 *
 * Request keys:
 * - 'string' (string, required): The text to translate.
 * - 'fromLang' (string, required): Source language tag (e.g. "en_US").
 * - 'toLang' (string, required): Target language tag (e.g. "cs_CZ").
 * - 'context' (string|null): Optional context for the translator. Default: null.
 * - 'ai' (string): AI backend name. Default: "translator".
 * - 'priority' (float): Processing priority between 0 and 1 (exclusive). Higher = processed first. Default: 0.5.
 *
 * Response keys:
 * - 'data' (string): The translated text — set by the system after processing.
 * - Any custom keys you add to response[] are preserved and available in your callback.
 *   Use this to attach identifiers (e.g. record IDs, field names) needed to process
 *   the translated string when your callback fires.
 *
 * Usage:
 * ```php
 * $event = new TranslateEvent(
 *     'my-unique-translation-id', // required — duplicate UUIDs are silently ignored
 *     "my:translation:done",
 *     OriginEnum::INTERNAL,
 *     [
 *         'string' => 'Hello world',
 *         'fromLang' => 'en_US',
 *         'toLang' => 'cs_CZ',
 *         'context' => 'This is a greeting on a website.',
 *     ],
 *     [
 *         'recordId' => 42,       // custom metadata preserved for your callback
 *         'field' => 'title',
 *     ],
 * );
 * $api->translator->translateAsync($event);
 * ```
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-04-30
 */
class TranslateEvent extends RequestResponseEvent
{
    public ?string $uuid {
        set {
            if (empty($value)) {
                throw new \Exception("UUID cannot be empty.");
            }
            $this->uuid = $value;
        }
    }

    private const REQUEST_DEFAULTS = [
        'ai' => 'translator',
        'fromLang' => 'en_US',
        'context' => null,
        'priority' => 0.5,
        'template' => GettextTemplateEnum::DEFAULT,
    ];

    private const REQUEST_REQUIRED = [
        'string',
        'fromLang',
        'toLang',
    ];

    /**
     * TranslateEvent constructor.
     *
     * @param string $uuid Unique identifier for deduplication. Duplicate UUIDs are silently ignored.
     * @param string $type The event type for the callback.
     * @param OriginEnum $origin The origin of the event.
     * @param ArrayAccess|array $request The request data. Required keys: string, fromLang, toLang.
     * @param ArrayAccess|array $response The response data. Pre-fill with identifiers needed by your callback.
     */
    public function __construct(
        string $uuid,
        string $type,
        OriginEnum $origin = OriginEnum::INTERNAL,
        ArrayAccess|array $request = new ArrayObject,
        ArrayAccess|array $response = new ArrayObject,
    ) {
        $this->uuid = $uuid;
        $request = array_merge(self::REQUEST_DEFAULTS, (array) $request);
        $request = $this->validateRequest($request);
        parent::__construct($type, $origin, $request, $response);
    }

    private function validateRequest(array $request): array
    {
        if (!is_float($request['priority'] ?? null) || $request['priority'] < 0 || $request['priority'] >= 1) {
            throw new \InvalidArgumentException("Parameter 'priority' must be a float between 0 (inclusive) and 1 (exclusive).");
        }

        foreach (self::REQUEST_REQUIRED as $key) {
            if (empty($request[$key] ?? null)) {
                throw new \InvalidArgumentException(
                    "Missing required parameter '$key' in TranslateEvent request."
                );
            }
        }

        $request['template'] = GettextTemplateEnum::from($request['template']);
        
        return $request;
    }

    public static function fromArray(array $data): static
    {
        $event = new static(
            $data['uuid'],
            $data['type'],
            OriginEnum::tryFrom($data['origin']),
            new ArrayObject($data['request']),
            new ArrayObject($data['response']),
        );
        if ($data['status']) {
            $event->setStatus($data['status'], $data['message']);
        }
        return $event;
    }
}
