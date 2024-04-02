<?php

declare(strict_types=1);

namespace Zolinga\Intl;

use Zolinga\System\Events\{ListenerInterface, RequestResponseEvent};
use Zolinga\Intl\Gettext\{Extractor, Compiler, JavascriptCompiler, JavascriptExtractor};
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
     * The log buffer.
     *
     * @var array<string>
     */
    static private array $log = [];

    /**
     * Extract gettext strings from the source files.
     * 
     * The request parameter 'module' can be used to specify the module to process.
     * Otherwise all modules are processed.
     *
     * @param RequestResponseEvent $event
     * @return void
     */
    public function extract(RequestResponseEvent $event): void
    {
        self::log("▶️  Extracting gettext strings from module folders...");

        foreach ($this->getTargetModules($event->request['module'] ?? null, '/locale') as $path) {
            $extractor = new Extractor($path);
            $extractor->extract();
        }

        self::log("▶️  Extracting gettext strings from \"install/dist\" folders...");

        // Generate POT/PO files for JS Gettext
        foreach ($this->getTargetModules($event->request['module'] ?? null, '/install/dist/locale') as $path) {
            $extractor = new JavascriptExtractor($path);
            $extractor->extract();
        }

        $event->response['log'] = array_merge($event->response['log'] ?? [], self::getLog());
        self::clearLog();

        $event->setStatus($event::STATUS_OK, 'Extracted gettext strings');
    }

    /**
     * Compile gettext strings to the mo files.
     * 
     * The request parameter 'module' can be used to specify the module to process.
     * Otherwise all modules are processed.
     *
     * @param RequestResponseEvent $event
     * @return void
     */
    public function compile(RequestResponseEvent $event): void
    {
        foreach ($this->getTargetModules($event->request['module'] ?? null, '/locale') as $path) {
            $compiler = new Compiler($path);
            $compiler->compile();
        }

        // Generate POT/PO files for JS Gettext
        foreach ($this->getTargetModules($event->request['module'] ?? null, '/install/dist/locale') as $path) {
            $extractor = new JavascriptCompiler($path);
            self::log("📜 Compiling gettext strings from $path/install/dist for locales: " . implode(', ', $extractor->locales));
            $extractor->compile();
        }

        $event->response['log'] = array_merge($event->response['log'] ?? [], self::getLog());
        self::clearLog();

        $event->setStatus($event::STATUS_OK, 'Compiled gettext strings');
    }

    /**
     * Get the target modules paths to process.
     *
     * The request parameter 'module' can be used to specify the module to process.
     * Otherwise all modules are processed.
     *
     * @param string|null $moduleName if user specivied module name on the command line.
     * @return array<string> of module paths to process
     */
    private function getTargetModules(?string $moduleName, string $checkPath = '/locale'): array
    {
        global $api;

        if ($moduleName) {
            $list = [$api->fs->toPath("module://$moduleName")];
        } else {
            $list = $api->manifest->moduleRealPaths;
        }

        $list = array_filter($list, function ($path) use ($checkPath) {
            if (is_dir($path . $checkPath)) {
                return true;
            } else {
                self::log("Skipped. No locale directory found: $path/locale");
                return false;
            }
        });

        return $list;
    }

    /**
     * Get the log of the last operation.
     *
     * @return array<string>
     */
    static public function getLog(): array
    {
        return self::$log;
    }

    /**
     * Clear the log of the last operation.
     *
     * @return void
     */
    static public function clearLog(): void
    {
        self::$log = [];
    }

    /**
     * Log the message.
     *
     * @param string $message
     * @return void
     */
    static public function log(string $message): void
    {
        self::$log[] = str_replace(ROOT_DIR, '.', $message);
    }
}
