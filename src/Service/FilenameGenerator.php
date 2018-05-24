<?php

declare(strict_types=1);

namespace FriendsOfSylius\SyliusImportExportPlugin\Service;

class FilenameGenerator
{
    // @todo: interface

    public function generateFilename(string $resource, string $format)
    {
        return sprintf('%s-%s.%s', $resource, date('Y-m-d'), $format);
    }
}
