<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Business\Service;

/**
 * Merkt sich PDF-Objekte, die innerhalb einer noch offenen Transaktion hochgeladen wurden.
 * Scheitert die Transaktion (auch erst beim Commit), löscht der Aufrufer sie über
 * BusinessDocumentStorage::discardPendingUploads wieder.
 */
class PendingPdfUploads
{
    /** @var list<string> */
    private array $objectKeys = [];

    public function record(string $objectKey): void
    {
        $this->objectKeys[] = $objectKey;
    }

    /** @return list<string> */
    public function getObjectKeys(): array
    {
        return $this->objectKeys;
    }
}
