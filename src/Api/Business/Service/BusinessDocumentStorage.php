<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Business\Service;

use LukaLtaApi\Exception\ApiException;
use LukaLtaApi\Repository\S3Repository;
use LukaLtaApi\Value\Business\BusinessSettings;
use Ramsey\Uuid\Uuid;
use Throwable;

/** Ablage von Beleg-PDFs und Logo im privaten Objektspeicher, gemeinsam für Angebote und Rechnungen */
class BusinessDocumentStorage
{
    private const string PDF_CONTENT_TYPE = 'application/pdf';

    public function __construct(
        private readonly S3Repository $s3Repository,
    ) {
    }

    /** Das Logo ist rein optisch: fehlt es im Speicher oder ist dieser nicht erreichbar, ohne Logo rendern */
    public function loadLogoBytes(BusinessSettings $settings): ?string
    {
        $logoObjectKey = $settings->getLogoObjectKey();
        if ($logoObjectKey === null) {
            return null;
        }

        try {
            return $this->s3Repository->getObject($logoObjectKey)['body'] ?? null;
        } catch (ApiException) {
            return null;
        }
    }

    /**
     * Neuer Objekt-Key pro Festschreibe-Versuch, z.B. "business/invoices/<id>/<uuid>.pdf". Ein verspätetes
     * Aufräumen nach einem gescheiterten Versuch kann so nie das PDF eines anderen Versuchs löschen.
     * Gelesen wird immer über den in der Datenbank gespeicherten Key.
     *
     * @param string $folder z.B. "business/invoices/" (mit abschließendem Slash)
     */
    public function createPdfObjectKey(string $folder, string $documentId): string
    {
        return $folder . $documentId . '/' . Uuid::uuid4()->toString() . '.pdf';
    }

    public function storePdf(string $objectKey, string $pdf): void
    {
        $this->s3Repository->putPrivateObject($objectKey, $pdf, self::PDF_CONTENT_TYPE);
    }

    /** @return ?string PDF-Bytes, null wenn kein Key gesetzt ist oder das Objekt fehlt */
    public function loadPdf(?string $objectKey): ?string
    {
        if ($objectKey === null) {
            return null;
        }

        return $this->s3Repository->getObject($objectKey)['body'] ?? null;
    }

    public function deleteUploadedPdf(string $objectKey): void
    {
        try {
            $this->s3Repository->deleteObject($objectKey);
        } catch (Throwable) {
            // Der ursprüngliche Fehler hat Vorrang. Ein verwaistes Objekt bleibt dann liegen: es gehört zu keinem
            // festgeschriebenen Beleg (jeder Versuch hat einen eigenen Key) und wird nie ausgeliefert.
            return;
        }
    }

    public function discardPendingUploads(PendingPdfUploads $uploads): void
    {
        foreach ($uploads->getObjectKeys() as $objectKey) {
            $this->deleteUploadedPdf($objectKey);
        }
    }
}
