<?php

declare(strict_types=1);

namespace Zolinga\Intl\Models;

interface GettextNodeInterface
{
    /**
     * The gettext attribute node associated with this translatable node, if any.
     * It is the @gettext attribute of the element itself or one of its ancestors, depending on the context.
     *
     * @var GettextAttribute
     */
    public ?GettextAttribute $gettextAttribute { get; }

    /**
     * The gettext domain this translatable node belongs to, determined by the nearest ancestor with a @gettext attribute.
     *
     * @var string
     */
    public string $gettextDomain { get; }

    /**
     * The unique identifier representing the translatable node and its contents.
     * 
     * @var string
     */
    public string $gettextHash { get; }

    /** 
     * Gettext translatable string representing the contents to be inserted into .pot files
     * 
     */
    public string $gettextString { get; }

    /**
     * The context for the gettext string, used to disambiguate identical strings in different contexts.
     *
     * @var string
     */
    public string $gettextContext { get; }

    /**
     * All ordered descendant elements of this translatable node.
     *
     * @var array<GettextElement>
     */
    public array $descendantElements { get; }


    public function translate(string $translation, ?array $elements = null): void;
}