<?php

declare(strict_types=1);

namespace Zolinga\Intl;

use Zolinga\System\Events\ServiceInterface;
use Zolinga\Intl\Gettext\GettextDomain;
use Zolinga\Intl\Types\FileTypes;

/**
 * I18n service — provides gettext domain discovery.
 *
 * Available as $api->i18n.
 *
 * Usage:
 *   $domains = $api->i18n->getGettextDomains();
 *   foreach ($domains as $domain) { ... }
 */
class I18nService implements ServiceInterface
{
    private ?array $gettextDomains = null;

    /**
     * Get all gettext domains (modules + hard-coded 'default').
     *
     * @return array<string, GettextDomain> keyed by domain name
     */
    public function getGettextDomains(): array
    {
        global $api;

        if ($this->gettextDomains !== null) {
            return $this->gettextDomains;
        }

        $domains = [];

        // Module domains
        foreach ($api->manifest->moduleNames as $moduleName) {
            $localePath = $api->fs->toPath("module://$moduleName") . '/locale';
            // if (is_dir($localePath)) {
            $domains[$moduleName] = new GettextDomain(
                name: $moduleName,
                serverOutput: $localePath,
                clientJsonOutput: $api->fs->toPath("dist://$moduleName") . '/locale',
                folders: [$api->fs->toPath("module://$moduleName")],
                fileTypes: FileTypes::ALL,
            );
            // }
        }

        // Hard-coded 'default' domain — always present, directory is ensured by install/private/
        $defaultServerOutput = $api->fs->toPath('private://zolinga-intl/default/locale/');
        if (!is_dir($defaultServerOutput)) {
            mkdir($defaultServerOutput, 0777, true) or throw new \RuntimeException("Cannot create default locale directory: $defaultServerOutput");
        }
        $domains['default'] = new GettextDomain(
            name: 'default',
            serverOutput: $defaultServerOutput,
            clientJsonOutput: $api->fs->toPath('public://data/zolinga-intl/default/locale/'),
            folders: [$api->fs->toPath('private://'), $api->fs->toPath('public://')],
            fileTypes: FileTypes::ALL,
        );

        $this->gettextDomains = $domains;
        return $this->gettextDomains;
    }
}
