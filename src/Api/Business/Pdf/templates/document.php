<?php

/**
 * Vorlage für Angebote und Rechnungen. Reine Darstellung.
 *
 * @var \LukaLtaApi\Value\Business\PdfDocument $document
 * @var \Closure(?string): string $escape
 * @var \Closure(?string): string $multiline
 * @var string $logoVar
 */

$issuer = $document->getIssuer();
$addressLines = is_array($issuer['addressLines'] ?? null) ? $issuer['addressLines'] : [];
$senderLine = implode(' · ', array_filter(
    array_map(static fn (mixed $part): string => is_string($part) ? $part : '', [
        $issuer['companyName'] ?? null,
        ...$addressLines,
    ]),
    static fn (string $part): bool => $part !== '',
));
$deposit = $document->getDepositDeduction();
$totalLabel = $deposit === null ? 'Gesamtbetrag' : 'Restbetrag';
$number = $document->getNumber();
$infoRows = [];
if ($number !== null) {
    $infoRows[] = ['label' => 'Nummer', 'value' => $number];
}
$infoRows[] = ['label' => 'Datum', 'value' => $document->getDate()->format('d.m.Y')];
$infoRows = [...$infoRows, ...$document->getMetaRows()];
?>
<style>
    body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #111111; }
    .sender { font-size: 7pt; color: #555555; border-bottom: 0.2mm solid #555555; padding-bottom: 0.5mm; }
    h1 { font-size: 16pt; margin: 0 0 4mm 0; }
    table.items { border-collapse: collapse; width: 100%; }
    table.items th {
        background-color: #eeeeee; border-bottom: 0.4mm solid #333333; padding: 1.5mm; text-align: left;
    }
    table.items td { border-bottom: 0.2mm solid #cccccc; padding: 1.5mm; vertical-align: top; }
    .right { text-align: right; }
    table.totals { border-collapse: collapse; width: 100%; page-break-inside: avoid; }
    table.totals td { padding: 1.2mm 1.5mm; }
    .grand td { border-top: 0.4mm solid #333333; font-weight: bold; font-size: 10.5pt; }
</style>

<?php if ($document->getLogoBytes() !== null) : ?>
<div style="text-align: right;">
    <img src="var:<?= $escape($logoVar) ?>" style="max-height: 18mm; max-width: 60mm;">
</div>
<?php endif; ?>

<table width="100%" style="margin-top: 8mm;">
    <tr>
        <td width="95mm" valign="top">
            <div class="sender"><?= $escape($senderLine) ?></div>
            <div style="margin-top: 3mm; font-size: 10pt; line-height: 1.4;">
                <?= implode('<br>', array_map($escape, $document->getRecipientLines())) ?>
            </div>
        </td>
        <td valign="top" style="padding-left: 10mm;">
            <table width="100%" style="font-size: 9pt;">
                <?php foreach ($infoRows as $row) : ?>
                <tr>
                    <td style="color: #555555; padding: 0.5mm 0;"><?= $escape($row['label']) ?></td>
                    <td class="right" style="padding: 0.5mm 0;"><?= $escape($row['value']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </td>
    </tr>
</table>

<h1 style="margin-top: 14mm;">
    <?= $escape($document->getTitle()) ?><?= $number === null ? '' : ' ' . $escape($number) ?>
</h1>

<?php if ($document->getIntroText() !== null) : ?>
<p><?= $multiline($document->getIntroText()) ?></p>
<?php endif; ?>

<table class="items" repeat_header="1">
    <thead>
        <tr>
            <th width="7%">Pos</th>
            <th>Beschreibung</th>
            <th class="right" width="10%">Menge</th>
            <th width="11%">Einheit</th>
            <th class="right" width="15%">Einzelpreis</th>
            <th class="right" width="15%">Gesamt</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($document->getItems() as $item) : ?>
            <?php $data = $item->toArray(); ?>
        <tr style="page-break-inside: avoid;">
            <td><?= $escape((string)$item->getPosition()) ?></td>
            <td><?= $multiline(is_string($data['description']) ? $data['description'] : '') ?></td>
            <td class="right"><?= $escape($item->getQuantity()->format()) ?></td>
            <td><?= $escape(is_string($data['unit']) ? $data['unit'] : '') ?></td>
            <td class="right"><?= $escape($item->getUnitPrice()->format()) ?></td>
            <td class="right"><?= $escape($item->getLineTotal()->format()) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<table class="totals" style="margin-top: 4mm;">
    <tr>
        <td width="38%"></td>
        <td>Zwischensumme</td>
        <td class="right"><?= $escape($document->getSubtotal()->format()) ?></td>
    </tr>
    <?php if ($deposit !== null) : ?>
    <tr>
        <td></td>
        <td><?= $escape($deposit['label']) ?></td>
        <td class="right"><?= $escape($deposit['amount']->negate()->format()) ?></td>
    </tr>
    <?php endif; ?>
    <tr class="grand">
        <td></td>
        <td><?= $escape($totalLabel) ?></td>
        <td class="right"><?= $escape($document->getTotal()->format()) ?></td>
    </tr>
    <tr>
        <td colspan="3" class="right" style="font-size: 8pt; color: #444444;">
            Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.
        </td>
    </tr>
</table>

<?php if ($document->getClosingText() !== null) : ?>
<p style="margin-top: 8mm;"><?= $multiline($document->getClosingText()) ?></p>
<?php endif; ?>
