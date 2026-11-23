<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Invoice\Service;

use DateTimeImmutable;
use LukaLtaApi\Value\Business\DocumentNumber;
use LukaLtaApi\Value\Business\Invoice;
use LukaLtaApi\Value\Business\Money;
use LukaLtaApi\Value\Business\PdfDocument;
use LukaLtaApi\Value\Business\PostalAddressSnapshot;

/** Baut aus einer Rechnung (jeder Art) das Datenobjekt für die PDF-Vorlage */
class InvoicePdfDocumentFactory
{
    private const string DISPLAY_DATE_FORMAT = 'd.m.Y';

    /**
     * @param ?DocumentNumber $invoiceNumber null = Entwurfs-Vorschau mit Wasserzeichen
     * @param array<string, mixed> $issuerSnapshot
     */
    public function create(
        Invoice $invoice,
        ?DocumentNumber $invoiceNumber,
        DateTimeImmutable $dueDate,
        PostalAddressSnapshot $recipient,
        array $issuerSnapshot,
        ?string $logoBytes,
    ): PdfDocument {
        return PdfDocument::create(
            $invoice->getKind()->getPdfTitle(),
            $invoiceNumber?->asString(),
            $invoice->getInvoiceDate(),
            $this->buildMetaRows($invoice, $dueDate),
            $issuerSnapshot,
            $recipient->getAddressLines(),
            $invoice->getIntroText(),
            $invoice->getItems(),
            $invoice->getSubtotal(),
            $this->buildDepositDeduction($invoice),
            $invoice->getTotal(),
            $invoice->getClosingText(),
            $invoiceNumber === null,
            $logoBytes,
        );
    }

    /** @return list<array{label: string, value: string}> */
    private function buildMetaRows(Invoice $invoice, DateTimeImmutable $dueDate): array
    {
        $rows = [];
        $serviceDate = $invoice->getServiceDate();
        $periodStart = $invoice->getServicePeriodStart();
        $periodEnd = $invoice->getServicePeriodEnd();
        if ($serviceDate !== null) {
            $rows[] = ['label' => 'Leistungsdatum', 'value' => $this->formatDate($serviceDate)];
        }
        if ($periodStart !== null && $periodEnd !== null) {
            $rows[] = [
                'label' => 'Leistungszeitraum',
                'value' => $this->formatDate($periodStart) . ' – ' . $this->formatDate($periodEnd),
            ];
        }
        if (!$invoice->getKind()->isCancellation()) {
            $rows[] = ['label' => 'Fällig am', 'value' => $this->formatDate($dueDate)];
        }
        $cancelsInvoiceNumber = $invoice->getCancelsInvoiceNumber();
        if ($cancelsInvoiceNumber !== null) {
            $rows[] = ['label' => 'Storno zu', 'value' => $cancelsInvoiceNumber];
        }
        $offerNumber = $invoice->getOfferNumber();
        if ($offerNumber !== null) {
            $rows[] = ['label' => 'Angebot', 'value' => $offerNumber];
        }

        return $rows;
    }

    /** @return ?array{label: string, amount: Money} */
    private function buildDepositDeduction(Invoice $invoice): ?array
    {
        $deduction = $invoice->getDepositDeduction();
        if ($deduction->asCents() === 0) {
            return null;
        }

        // Die Vorlage druckt den Abzug negiert. Beim Storno einer Schlussrechnung ist der gespeicherte Abzug
        // negativ und erscheint daher positiv: Zwischensumme −X, Abzug +D, Gesamtbetrag −(X − D)
        $label = $invoice->getKind()->isCancellation() ? 'Storno Abzug Anzahlung' : 'abzgl. Anzahlung';
        $depositNumber = $invoice->getDepositInvoiceNumber();
        $depositDate = $invoice->getDepositInvoiceDate();
        if ($depositNumber !== null && $depositDate !== null) {
            $label .= sprintf(' %s vom %s', $depositNumber, $this->formatDate($depositDate));
        }

        return ['label' => $label, 'amount' => $deduction];
    }

    private function formatDate(DateTimeImmutable $date): string
    {
        return $date->format(self::DISPLAY_DATE_FORMAT);
    }
}
