<?php

declare(strict_types=1);

namespace Zolinga\Intl\Models;
use DOMDocument;
use DOMXPath;
use Zolinga\Intl\Types\GettextModeEnum;

class GettextDocument extends DOMDocument implements \Stringable
{
    public readonly DOMXPath $xpath;
    public readonly string $filePath;

    /**
     * The regular expression to match the gettext tag: {domain}:{keyword}#{hash}
     * 
     * @var string
     */
    protected const TAG_RE = '/^(?:(?<domain>[a-zA-Z0-9.-]+):)?(?<keyword>[a-zA-Z0-9-]+|\.)(?:#(?<hash>[a-z0-9]{6}))?$/';

    /**
     * List of translatable elements indexed by hash.
     * 
     * Key is in format {domain}:{keyword}#{hash}, use parseTranslatableKey to generate it.
     * 
     * @var array<string, GettextNodeInterface|GettextElement|GettextAttribute>
     */
    public private(set) array $translatables = [];

    /**
     * The name of the HTML attribute to translate.
     * 
     * @var string
     */
    protected const MARKUP_NAME = 'gettext';    

    /**
     * The value of <meta name="gettext" content="{VALUE}">, can be 'translate', 'cherry-pick' or 'replace'.
     * Setting it to null will remove the meta tag if it exists.
     *
     * @var string|null value of the gettext mode or null if not set
     */
    public ?GettextModeEnum $gettextMode {
        get => GettextModeEnum::tryFrom($this->xpath->evaluate('string(//meta[@name="gettext"]/@content)') ?: null);            
        set => $this->setGettextMode($value);
    }

    public function __construct(string $file)
    { 
        global $api;

        parent::__construct();
        $this->registerNodeClass('DOMElement', GettextElement::class);
        $this->registerNodeClass('DOMAttr', GettextAttribute::class);

        $api->log->info('i18n', "🔰 Loading HTML file $file for gettext operations");

        // fix html5/svg errors - libxml_use_internal_errors
        $errorsVal = libxml_use_internal_errors();
        libxml_use_internal_errors(true);

        $content = file_get_contents($file) or throw new \RuntimeException("Cannot read file: $file");

        // If it contains <meta ... content="text/html; charset=UTF-8"> or <meta ... charset="UTF-8">, we don't need to add it 
        if (!preg_match('/<meta[^>]+charset=/i', $content)) {
            if (stripos($content, '</head')) {
                $content = str_replace('</head', '<meta charset="UTF-8" /></head', $content);
            }
        }

        $this->loadHTML($content, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOXMLDECL);
        libxml_use_internal_errors($errorsVal);

        $this->xpath = new DOMXPath($this);
        $this->filePath = $api->fs->toZolingaUri($file) ?: $file; 

        $this->addCharsetMetaIfMissing();
        $this->reindex();
    }

    private function setGettextMode(?GettextModeEnum $mode): void
    {
        $meta = $this->xpath->query('//meta[@name="gettext"]')->item(0);
        if ($meta && $mode === null) {
            $meta->parentNode?->removeChild($meta);
            return;
        }
        if (!$meta) {
            $head = $this->getElementsByTagName('head')->item(0);
            if (!$head) {
                throw new \RuntimeException("Cannot find <head> element to set gettext mode");
            }
            $meta = $this->createElement('meta');
            $meta->setAttribute('name', 'gettext');
            $head->appendChild($meta);
        }
        /** @var \DOMElement $meta */
        $meta->setAttribute('content', $mode->value);
    }

    private function addCharsetMetaIfMissing(): void
    {
        $head = $this->getElementsByTagName('head')->item(0);
        if ($head && !$this->xpath->evaluate('count(//meta[@charset="UTF-8"])', $head)) {
            $meta = $this->createElement('meta');
            $meta->setAttribute('charset', 'UTF-8');
            $head->appendChild($meta);
        }
    }

    /**
     * Reindex all translatable elements in the document.
     *
     * Scans the document for elements with the gettext attribute and rebuilds
     * the {@see $translatables} array, indexing each translatable node by its
     * composite key in the format `{domain}:{keyword}#{hash}`.
     *
     * For each gettext attribute tag, if the keyword is '.', the translatable
     * node is the element itself (element content translation). Otherwise, the
     * node is the corresponding attribute node on the element.
     *
     * If a tag has no hash (e.g. in a translated document where the original
     * hash is preserved), the hash is taken from the node's existing
     * {@see GettextNodeInterface::gettextHash} property.
     *
     * @return void
     * @throws \RuntimeException If the XPath query for translatable elements fails.
     */
    public function reindex(): void
    {
        global $api;

        $results = $this->xpath->query("//@" . self::MARKUP_NAME)
            or throw new \RuntimeException("Cannot query the document to extract translatable elements: " . self::MARKUP_NAME);

        $this->translatables = [];
        $addedHashes = false;

        foreach ($results as $gettextAttrNode) {
            /** @var \DOMAttr $gettextAttrNode */
            $element = $gettextAttrNode->ownerElement;
            foreach (self::parseGettextAttr($gettextAttrNode) as ['domain' => $domain, 'keyword' => $keyword, 'hash' => $hash]) {
                $node = $keyword === '.' ? $element : $element->getAttributeNode($keyword);
                /** @var GettextNodeInterface $node */
                $addedHashes = $node->ensureGettextHash() || $addedHashes;
                $this->translatables["$domain:$keyword#$node->gettextHash"] = $node;
            }
        }

        if ($addedHashes) {
            $api->log->info('i18n', "Added missing gettext-hash attributes to $this");
            $this->save($this->filePath);
        }
    }

    /**
     * Extract the value from <meta name="gettext" content="{VALUE}">.
     * 
     * Fast way to check the translation mode without parsing the whole file.
     *
     * @param string $file
     * @param string|null $default
     * @return string|null
     */
    static public function getGettextMode(string $file, ?string $default = null): string|null
    {
        $meta = get_meta_tags($file);
        return $meta['gettext'] ?? $default;
    }

    /**
     * Parse @gettext attribute and return array of [domain, keyword, hash].
     *
     * Example with domain and hash:
     *   parseGettextAttr($node)
     *   // Returns: ['title' => ['domain' => 'app', 'keyword' => 'title', 'hash' => 'abc123']]
     *
     * Example with default domain and no hash:
     *   parseGettextAttr($node)
     *   // Returns: ['title' => ['domain' => 'default', 'keyword' => 'title', 'hash' => null]]
     *
     * Example with element content marker:
     *   parseGettextAttr($node)
     *   // Returns: ['.' => ['domain' => 'default', 'keyword' => '.', 'hash' => null]]
     *
     * Example with multiple tags:
     *   parseGettextAttr($node)
     *   // Returns: [
     *   //   'title' => ['domain' => 'app', 'keyword' => 'title', 'hash' => 'abc123'],
     *   //   'description' => ['domain' => 'app', 'keyword' => 'description', 'hash' => 'def456']
     *   // ]
     *
     * @param GettextAttribute $attr The node to parse the gettext attribute from
     * @return array<string, array{domain: ?string, keyword: string, hash: ?string}> of tag => [domain, keyword, hash]
     */
    static public function parseGettextAttr(GettextAttribute $attr): array
    {
        $tags = $attr->value;
        $list = [];

        try {
            foreach (preg_split('/\s+/', $tags) ?: [] as $tag) {
                $i = self::parseTranslatableKey($tag);
                $list[$i['keyword']] = $i;
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error parsing gettext attribute on node " . $attr->getNodePath() .": " . $e->getMessage() . " " . $attr->ownerDocument->saveXML($attr), 2692, $e);
        }

        return $list;
    }

    
    /**
     * Update the hash portion of a specific domain:keyword tag within a gettext attribute string.
     *
     * Given a whitespace-separated string of gettext tags, this method finds the tag
     * matching the given $domain and $keyword and replaces its hash with $newHash.
     * All other tags in the string remain unchanged.
     *
     * Example:
     *   $tags = 'app:title#abc123 app:description#def456';
     *   updateGettextAttrHash($tags, 'app', 'title', 'fff999')
     *   // Returns: 'app:title#fff999 app:description#def456'
     *
     * Example with default domain:
     *   $tags = 'name#aaa111 mydomain:label#bbb222';
     *   updateGettextAttrHash($tags, 'default', 'name', 'ccc333')
     *   // Returns: 'default:name#ccc333 mydomain:label#bbb222'
     *
     * Example with no existing hash:
     *   $tags = 'app:title';
     *   updateGettextAttrHash($tags, 'app', 'title', '123abc')
     *   // Returns: 'app:title#123abc'
     *
     * @param GettextAttribute $attr The node containing the gettext attribute
     * @param string $keyword The keyword of the tag to update (e.g. 'title', 'description', or '.' for element content)
     * @param string $newHash The new 6-character hash to set (e.g. 'abc123')
     * @return string The updated whitespace-separated tags string
     */
    static public function updateGettextAttrHash(GettextAttribute $attr, string $keyword, string $newHash): string
    {
        $list = self::parseGettextAttr($attr);

        if (!isset($list[$keyword])) {
            throw new \InvalidArgumentException("Cannot find tag with keyword '$keyword' in gettext attribute of node: " . $attr->ownerDocument->saveXML($attr));
        }

        $list[$keyword]['hash'] = $newHash;
        $tags = array_map(fn ($i) => "{$i['domain']}:{$i['keyword']}" . ($i['hash'] ? "#{$i['hash']}" : ''), $list);

        $str = implode(' ', $tags);
        $attr->value = $str;

        return $str;
    }

    /**
     * Calculate the short hash from the string.
     *
     * @param string $strid
     * @return string
     */
    public static function calculateHash(string $strid): string
    {
        return substr(sha1($strid), 0, 6);
    }

    /**
     * Parse the translatable key in format {domain}:{keyword}#{hash}.
     *
     * @param string $key
     * @return array{domain: string, keyword: string, hash: ?string}
     */
    public static function parseTranslatableKey(string $key): array
    {
        global $api;
        if (!preg_match(self::TAG_RE, $key, $matches)) {
            $api->log->error('i18n', "Invalid translatable key: $key"); // print it because it throws SegFault when throwing from getters
            throw new \InvalidArgumentException("Invalid translatable key: $key");
        }
        return [
            'domain' => $matches['domain'] ?: 'default',
            'keyword' => $matches['keyword'],
            'hash' => $matches['hash'] ?? null
        ];
    }

    public function __toString(): string
    {
        return "🔰GettextDocument[$this->filePath]";
    }
}