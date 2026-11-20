<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Business\Service;

use LukaLtaApi\Exception\ApiException;
use LukaLtaApi\Repository\S3Repository;
use LukaLtaApi\Value\Business\BusinessSettings;
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
            // Der ursprüngliche Fehler hat Vorrang. Ein verwaistes Objekt ist harmlos: es gehört zu einem
            // Entwurf und wird beim nächsten erfolgreichen Festschreiben unter demselben Key überschrieben.
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
