<?php

declare(strict_types=1);

namespace Zolinga\Intl;

use Zolinga\System\Events\{ListenerInterface, RequestResponseEvent};
use Zolinga\Intl\Gettext\{Extractor, Compiler, JavascriptCompiler, Autotranslate};
use const Zolinga\System\ROOT_DIR;

/**
 * CLI interface for the gettext service.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-16
 */
class GettextCli implements ListenerInterface
{

    /**
     * Get filtered gettext domains from CLI event parameters.
     *
     * Honors --domains (comma-separated list) and --all (process all domains).
     * Without either, all domains are processed.
     *
     * @param RequestResponseEvent $event
     * @return array<string, GettextDomain> Keyed by domain name.
     */
    private function getFilteredDomains(RequestResponseEvent $event): array
    {
        global $api;

        $domains = $api->i18n->getGettextDomains();
        $filter = $event->request['domains'] ?? null;
        $all = (bool) ($event->request['all'] ?? false);
        $filterList = $filter ? array_map('trim', explode(',', $filter)) : [];

        if ($filterList && !$all) {
            $domains = array_filter($domains, fn($o) => in_array($o->name, $filterList, true));
        }

        return $domains;
    }

    /**
     * Extract gettext strings from the source files.
     *
    * The request parameter 'domains' can be used to specify one or more gettext
    * domains to process (comma-separated). Otherwise all domains are processed.
     *
     * @param RequestResponseEvent $event
     * @return void
     */
    public function extract(RequestResponseEvent $event): void
    {
        global $api;

        $api->log->info('i18n', "▶️  Extracting gettext strings...");

        $domains = $this->getFilteredDomains($event);

        if (empty($domains)) {
            $api->log->warning('i18n', "No gettext domains found to extract. Check --domains parameter.");
            $event->setStatus($event::STATUS_NOT_FOUND, 'No gettext domains to extract');
            return;
        }

        foreach ($domains as $domain) {
            $extractor = new Extractor($domain);
            $extractor->extract();
        }

        $event->setStatus($event::STATUS_OK, 'Extracted gettext strings');
    }

    /**
     * Compile gettext strings to the mo files.
     *
    * The request parameter 'domains' can be used to specify one or more gettext
    * domains to process (comma-separated). Otherwise all domains are processed.
     *
     * @param RequestResponseEvent $event
     * @return void
     */
    public function compile(RequestResponseEvent $event): void
    {
        global $api;

        $domains = $this->getFilteredDomains($event);

        if (empty($domains)) {
            $api->log->warning('i18n', "No gettext domains found to compile. Check --domains parameter.");
            $event->setStatus($event::STATUS_NOT_FOUND, 'No gettext domains to compile');
            return;
        }

        foreach ($domains as $domain) {
            // Server-side: .po → .mo + HTML translation
            $compiler = new Compiler($domain);
            $compiler->compile();

            // Client-side: .po + client.pot → .json
            $jsCompiler = new JavascriptCompiler($domain);
            $jsCompiler->compile();
        }

        $event->setStatus($event::STATUS_OK, 'Compiled gettext strings');
    }

    /**
     * Autotranslate untranslated gettext entries using AI.
     *
     * The request parameter 'domains' can be used to specify one or more gettext
     * domains to process (comma-separated). Otherwise all domains are processed.
     *
     * @param RequestResponseEvent $event
     * @return void
     */
    public function autotranslate(RequestResponseEvent $event): void
    {
        global $api;

        $api->log->info('i18n', "▶️  Autotranslating gettext strings...");

        $domains = $this->getFilteredDomains($event);

        if (empty($domains)) {
            $api->log->warning('i18n', "No gettext domains found to autotranslate. Check --domains parameter.");
            $event->setStatus($event::STATUS_NOT_FOUND, 'No gettext domains to autotranslate');
            return;
        }

        foreach ($domains as $domain) {
            /** @var \Zolinga\Intl\Gettext\GettextDomain $domain */
            $autotranslate = new Autotranslate($domain);
            $autotranslate->autotranslate();
        }

        $event->setStatus($event::STATUS_OK, 'Autotranslated gettext strings');
    }
}
