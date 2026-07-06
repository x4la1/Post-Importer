<?php

namespace App\Service;

interface ImportStateStorageInterface
{
    public function getLastPageToImport(): int;

    public function setLastPageToImport(int $page): void;
}
