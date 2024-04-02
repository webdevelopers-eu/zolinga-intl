<?php

namespace Zolinga\Intl\Gettext;

use Zolinga\Intl\GettextCli;

/**
 * Extract translate-able strings from the source files and create language po files.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-16
 */
class JavascriptExtractor extends Extractor
{
    public function __construct(string $modulePath)
    {
        parent::__construct($modulePath . "/install/dist");
    }

    public function extract(): void
    {
        GettextCli::log("📜 Extracting gettext strings from $this->modulePath for locales: " . implode(', ', $this->locales) . " (no PO files)");

        $this->generateMessagesPotFile();
        
        file_put_contents("$this->modulePath/locale/README.txt", <<<EOT
            This directory contains the language files for the JavaScript Gettext translations.
            
            DO NOT EDIT THESE FILES MANUALLY IN THIS {MODULE}/install/dist/locale DIRECTORY.
            
            They are generated automatically by the Gettext service. All translations that need to be updated 
            are located in module's "{MODULE}/locale" directory.
            
            EOT);
    }
}
