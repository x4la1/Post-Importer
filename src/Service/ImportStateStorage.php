<?php

namespace App\Service;

use App\Service\ImportStateStorageInterface;

class ImportStateStorage implements ImportStateStorageInterface
{
    private const string FILE_PATH = __DIR__ . '/../../var/import_state.json';

    public function getLastPageToImport(): int
    {
        if (!file_exists(self::FILE_PATH)) {
            return 1;
        }

        $data = json_decode(file_get_contents(self::FILE_PATH), true);

        return $data['lastPage'] ?? 1;
    }

    public function setLastPageToImport(int $page): void
    {
        $data = [
            'lastPage' => $page,
        ];

        file_put_contents(self::FILE_PATH, json_encode($data, JSON_PRETTY_PRINT));
    }
}
