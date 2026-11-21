# Kunden, Angebote und Rechnungen — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or
> superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Kunden, Angebote (`AN-JAHR-0001`) und Rechnungen (`RE-JAHR-0001`) inkl. Anzahlung, Storno, Import und
PDF-Download im Admin-Dashboard.

**Architecture:** Neue Domains in der Slim-API (`Customer`, `BusinessSettings`, `Offer`, `Invoice`), Daten in
MySQL, PDFs per mPDF beim Festschreiben erzeugt und privat in MinIO abgelegt. Dashboard (`luka-lta-backend`) bekommt
Seiten unter „Business“ nach dem üblichen `api/<domain>` + `feature/<domain>`-Muster.

**Tech Stack:** PHP 8.4, Slim 4, PHP-DI, PDO/MySQL, AWS SDK (MinIO), `mpdf/mpdf`; React, TypeScript, React Query, Zod,
Axios.

**Spec:** `docs/superpowers/specs/2026-11-20-invoicing-design.md`. Das Spec ist maßgeblich für alle fachlichen
Regeln; dieser Plan legt Dateien, Namen, Schnittstellen und Reihenfolge fest.

## Global Constraints

- **Keine Test-Suite** (Lukas Entscheidung). Jede Aufgabe verifiziert per `just lint-path <pfad>` (muss ohne Fehler
  durchlaufen), per `curl` gegen die Dev-Umgebung (`just dev`) und im Frontend per `bun run build` + `bun run lint`.
- Beträge sind immer **int Cent**, nie float, im Code als `Money`. Mengen sind `Quantity` (Hundertstel als int).
- Kein `SELECT *`, keine Logik in Actions, jede Exception erbt `ApiException`, `declare(strict_types=1)` überall.
- Kein `else`/`elseif`, Guard-Clauses, max. 120 Zeichen pro Zeile, Trailing-Kommata, Kommentare deutsch.
- Jede Action bekommt den `// Usage: curl …`-Kommentar mit Platzhaltern (`<raw-jwt>`, `<origin>`).
- Alle neuen Routen in Gruppen mit `->add(AuthMiddleware::class)`.
- MinIO-Objekte für Business **nie** mit `ACL => public-read`.
- PDF-Pflichthinweis exakt: `Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.`
- Nummernformat exakt: `RE-2026-0001` / `AN-2026-0001` (Präfix, Jahr, Zähler mind. 4-stellig zero-padded).
- Bestehende Migrationen nicht ändern; nur neue Dateien.
- Frontend: 2 Spaces, Semikolons, `@/`-Imports, kein `any`, Antworten mit Zod parsen, Payload in `response.data.data`,
  Fehlertexte über `getApiErrorMessage()`, Datum über `src/lib/dateTimeUtils.ts`, UI-Texte deutsch.
- Keine echten Firmen-/Bank-/Kundendaten in Code, Seeds oder Commits; Beispiele mit Platzhaltern.

## Review Focus

1. **Gleichzeitiges Festschreiben** zweier Rechnungen → zwei verschiedene, lückenlose Nummern
   (Sequenz mit `SELECT … FOR UPDATE` in derselben Transaktion wie das Setzen der Nummer). Verifikation in Task 7 Step 4.
2. **PDF-Fehler beim Festschreiben** → keine Nummer verbraucht, Rechnung bleibt `draft`. Reihenfolge: Nummer in
   Transaktion holen, PDF rendern, hochladen, erst dann commit; bei Fehler Rollback + MinIO-Objekt löschen.
   Verifikation in Task 7 Step 5.
3. **Nachträgliche Änderung von Kunden-/Firmendaten** → altes PDF und Rechnungsdetail unverändert (Snapshots).
   Verifikation in Task 7 Step 6.
4. **Rundung bei krummen Mengen** (`1,5 × 33,33 €` = `49,995` → `50,00 €`, kaufmännisch). Verifikation in Task 2 Step 3.
5. **Import mit Nummer aus falschem Jahr oder Duplikat** → `400` bzw. `409`, Zähler unverändert. Verifikation in
   Task 10 Step 3.

---

## Datenmodell (gilt für alle Aufgaben)

Neue Migrationsdateien in `data/mysql/` (alle `utf8mb4_0900_ai_ci`, InnoDB, IDs `char(36)` UUID):

| Datei / Tabelle | Spalten |
|---|---|
| `business_customers.sql` | `customer_id` PK, `company_name` varchar(150) NULL, `contact_name` varchar(150) NULL, `street` varchar(150), `postal_code` varchar(20), `city` varchar(100), `country` char(2) DEFAULT 'DE', `email` varchar(255) NULL, `vat_id` varchar(30) NULL, `notes` text NULL, `archived_at` datetime NULL, `created_at`, `updated_at` |
| `business_settings.sql` | `settings_id` tinyint PK (immer 1), `company_name`, `owner_name`, `street`, `postal_code`, `city`, `email`, `phone` NULL, `tax_number`, `bank_name`, `iban`, `bic` NULL, `logo_object_key` NULL, `default_payment_term_days` smallint DEFAULT 14, `footer_text` text NULL, `updated_at`. Keine Seed-Zeile; fehlende Zeile = nicht konfiguriert. |
| `business_number_sequences.sql` | `prefix` enum('RE','AN'), `year` smallint, `last_value` int; PK (`prefix`,`year`) |
| `business_offers.sql` | `offer_id` PK, `offer_number` varchar(20) NULL UNIQUE, `customer_id`, `status` enum('draft','sent','accepted','rejected'), `offer_date` date, `valid_until` date, `intro_text` text NULL, `closing_text` text NULL, `total_cents` int, `customer_snapshot` json NULL, `issuer_snapshot` json NULL, `pdf_object_key` varchar(255) NULL, `created_at`, `updated_at` |
| `business_offer_items.sql` | `item_id` PK, `offer_id`, `position` smallint, `description` text, `quantity_hundredths` int, `unit` varchar(30), `unit_price_cents` int, `line_total_cents` int |
| `business_invoices.sql` | `invoice_id` PK, `invoice_number` varchar(20) NULL UNIQUE, `kind` enum('standard','deposit','final','cancellation'), `status` enum('draft','open','paid','cancelled','issued'), `customer_id`, `offer_id` NULL, `deposit_invoice_id` NULL, `cancels_invoice_id` NULL, `invoice_date` date, `service_date` date NULL, `service_period_start` date NULL, `service_period_end` date NULL, `payment_term_days` smallint, `due_date` date NULL, `intro_text` text NULL, `closing_text` text NULL, `subtotal_cents` int, `deposit_deduction_cents` int DEFAULT 0, `total_cents` int, `paid_at` date NULL, `is_imported` tinyint(1) DEFAULT 0, `customer_snapshot` json NULL, `issuer_snapshot` json NULL, `pdf_object_key` varchar(255) NULL, `created_at`, `updated_at` |
| `business_invoice_items.sql` | wie `business_offer_items`, aber `invoice_id` |
| `zzz_business_foreign_keys.sql` | FKs: offers/invoices → customers (`RESTRICT`), items → offer/invoice (`CASCADE`), invoices.offer_id/deposit_invoice_id/cancels_invoice_id → offers/invoices (`RESTRICT`) |

`total_cents` = `subtotal_cents − deposit_deduction_cents`. Storno: alle Beträge negativ.

## Dateistruktur

```
luka-lta-api/
  src/Value/Business/          Money, Quantity, DocumentNumber, DocumentPrefix, LineItem, LineItems,
                               CustomerId, Customer, Customers, BusinessSettings, OfferId, Offer, Offers,
                               OfferStatus, InvoiceId, Invoice, Invoices, InvoiceKind, InvoiceStatus,
                               PostalAddressSnapshot
  src/Repository/              BusinessCustomerRepository, BusinessSettingsRepository,
                               BusinessNumberSequenceRepository, BusinessOfferRepository,
                               BusinessInvoiceRepository
  src/Api/Customer/            Action/*, Service/CustomerService
  src/Api/BusinessSettings/    Action/*, Service/BusinessSettingsService
  src/Api/Business/Pdf/        BusinessPdfRenderer, templates/document.php
  src/Api/Offer/               Action/*, Service/OfferService
  src/Api/Invoice/             Action/*, Service/InvoiceService, Service/InvoiceFinalizer,
                               Service/InvoiceImportService
  src/Exception/               Business*Exception
luka-lta-backend/
  src/api/{customers,business-settings,offers,invoices}/{endpoints,hooks}.ts
  src/feature/{customers,business-settings,offers,invoices}/{index.tsx,components/,schema/}
  src/feature/business-documents/   gemeinsamer LineItemsEditor, MoneyText, StatusBadge
  src/pages/Dashboard/{Customers,Offers,Invoices,BusinessSettings}Page.tsx
```

---

### Task 1: Grundlage — Dependency, Docker, Migrationen

**Files:**
- Modify: `composer.json`, `composer.lock`, `docker/php/Dockerfile:11`
- Create: alle 9 Migrationsdateien aus dem Datenmodell

**Interfaces:**
- Produces: Tabellen exakt wie im Datenmodell; Paket `mpdf/mpdf`.

- [ ] **Step 1:** `docker/php/Dockerfile`: `gd` mit Alpine-Abhängigkeiten installieren
  (`apk add --no-cache libpng-dev libjpeg-turbo-dev freetype-dev` + `docker-php-ext-configure gd --with-freetype
  --with-jpeg` + `docker-php-ext-install gd`). mPDF verlangt `ext-gd` und `ext-mbstring` (mbstring ist im Basis-Image).
- [ ] **Step 2:** `just build development`, dann im Container `composer require mpdf/mpdf`
  (`docker compose -f docker-compose.development.yml run --rm php-fpm-api composer require mpdf/mpdf`).
  Erwartet: Installation ohne Plattform-Fehler.
- [ ] **Step 3:** Migrationsdateien schreiben, je mit kurzem deutschem Kopfkommentar wie `projects.sql`.
- [ ] **Step 4:** Verifizieren auf frischem Volume: `just stop`, `docker volume rm` des MySQL-Volumes, `just dev`,
  dann `SHOW TABLES LIKE 'business_%'` → 8 Tabellen, `SHOW CREATE TABLE business_invoice_items` zeigt den FK.
  Achtung: Laut Plan-Doc der Topologie laufen die Altmigrationen auf leerem Volume evtl. nicht durch (`04_`); in dem
  Fall die `business_*`-Dateien plus `zzz_business_foreign_keys.sql` manuell per `mysql < datei` einspielen und das
  im Commit erwähnen.
- [ ] **Step 5: Commit** `feat(business): add mpdf, gd and business schema`

### Task 2: Value Objects (Geld, Menge, Nummer, Positionen)

**Files:**
- Create: `src/Value/Business/{Money,Quantity,DocumentPrefix,DocumentNumber,LineItem,LineItems}.php`

**Interfaces:**
- Produces:
  - `Money::fromCents(int): self`, `Money::fromEuroString(string): self` (akzeptiert `"12,50"` und `"12.50"`),
    `asCents(): int`, `add(Money): Money`, `subtract(Money): Money`, `negate(): Money`,
    `multiplyByQuantity(Quantity): Money` (kaufmännisch gerundet: `intdiv(cents * hundredths + 50, 100)` mit
    Vorzeichenbehandlung), `format(): string` → `"1.234,50 €"`, `isNegative(): bool`, `JsonSerializable` → int.
  - `Quantity::fromHundredths(int)`, `Quantity::fromDecimalString(string)` (max. 2 Nachkommastellen, > 0),
    `asHundredths(): int`, `format(): string` (`"1,5"`, `"2"`).
  - `enum DocumentPrefix: string { case Invoice = 'RE'; case Offer = 'AN'; }`
  - `DocumentNumber::from(DocumentPrefix, int $year, int $sequence)`, `DocumentNumber::parse(string, DocumentPrefix)`
    (Regex `^(RE|AN)-(\d{4})-(\d{4,})$`, Präfix muss passen, sonst `ApiValidationException` 400), `getYear(): int`,
    `getSequence(): int`, `asString(): string`, `Stringable`.
  - `LineItem::create(int $position, string $description, Quantity, string $unit, Money $unitPrice)`;
    `getLineTotal(): Money` = `unitPrice->multiplyByQuantity(quantity)`; `fromDatabase(array)`, `toArray(): array`
    (Felder `position, description, quantity (string), unit, unitPriceCents, lineTotalCents`), `negated(): LineItem`.
    Beschreibung nicht leer, max. 2000 Zeichen; Einheit max. 30.
  - `LineItems` (Collection, `Countable`, `IteratorAggregate`): `from(LineItem ...)`,
    `fromRequestArray(array): self` (Positionen 1..n in Eingabereihenfolge, mind. 1 Eintrag), `getTotal(): Money`,
    `negated(): self`, `toArray(): array`.

- [ ] **Step 1:** Klassen nach obigen Signaturen schreiben (Muster: `src/Value/Project/ProjectId.php`).
- [ ] **Step 2:** `just lint-path src/Value/Business` → keine Fehler.
- [ ] **Step 3: Rundung prüfen** per Einzeiler im Container:
  `php -r 'require "vendor/autoload.php"; use LukaLtaApi\Value\Business\{Money,Quantity};
  echo Money::fromCents(3333)->multiplyByQuantity(Quantity::fromDecimalString("1.5"))->asCents();'` → `5000`;
  mit `Money::fromCents(-3333)` → `-5000`; `Money::fromCents(123450)->format()` → `1.234,50 €`;
  `DocumentNumber::from(DocumentPrefix::Invoice, 2026, 7)->asString()` → `RE-2026-0007`.
- [ ] **Step 4: Commit** `feat(business): add money, quantity and document number values`

### Task 3: Kunden (API)

**Files:**
- Create: `src/Value/Business/{CustomerId,Customer,Customers}.php`, `src/Repository/BusinessCustomerRepository.php`,
  `src/Api/Customer/Service/CustomerService.php`,
  `src/Api/Customer/Action/{ListCustomersAction,GetCustomerAction,CreateCustomerAction,UpdateCustomerAction,ArchiveCustomerAction}.php`,
  `src/Exception/BusinessCustomerNotFoundException.php` (404)
- Modify: `src/Slim/RouteMiddlewareCollector.php` (neue Gruppe `/customers` mit `AuthMiddleware`)

**Interfaces:**
- Produces:
  - `Customer::fromRequest(array, ?CustomerId)` (Firma **oder** Name Pflicht → sonst 400), `fromDatabase(array)`,
    `toArray()` (camelCase: `customerId, companyName, contactName, street, postalCode, city, country, email, vatId,
    notes, archivedAt, displayName`), `getDisplayName(): string` (Firma, sonst Name),
    `toAddressLines(): list<string>` (für PDF/Snapshot).
  - `BusinessCustomerRepository::loadCustomer(CustomerId): ?Customer`, `loadCustomers(bool $includeArchived): Customers`,
    `create(Customer)`, `update(Customer)`, `archive(CustomerId)`.
  - Routen: `GET /customers?includeArchived=1`, `POST /customers`, `GET /customers/{customerId}`,
    `PATCH /customers/{customerId}`, `POST /customers/{customerId}/archive`.
  - JSON: `{ customers: [...] }` bzw. `{ customer: {...} }`.

- [ ] **Step 1:** Value Objects + Repository (explizite Feldliste wie `ProjectAssetRepository`).
- [ ] **Step 2:** Service + Actions + Routen.
- [ ] **Step 3:** `just lint-path src/Api/Customer src/Value/Business src/Repository/BusinessCustomerRepository.php`.
- [ ] **Step 4:** curl: anlegen ohne Firma und Name → 400; mit `contactName` → 201 + Objekt; Liste enthält ihn;
  archivieren → nicht mehr in Liste, mit `includeArchived=1` wieder da; ohne `Authorization` → 401.
- [ ] **Step 5: Commit** `feat(business): customers api`

### Task 4: Firmendaten (API)

**Files:**
- Create: `src/Value/Business/BusinessSettings.php`, `src/Repository/BusinessSettingsRepository.php`,
  `src/Api/BusinessSettings/Service/BusinessSettingsService.php`,
  `src/Api/BusinessSettings/Action/{GetBusinessSettingsAction,UpdateBusinessSettingsAction,UploadBusinessLogoAction}.php`,
  `src/Exception/BusinessSettingsIncompleteException.php` (409)
- Modify: `src/Repository/S3Repository.php` (neue Methode `putPrivateObject(string $objectKey, string $body,
  string $contentType): void`, ohne ACL), Router (Gruppe `/business/settings`)

**Interfaces:**
- Produces:
  - `BusinessSettingsRepository::loadSettings(): ?BusinessSettings`, `save(BusinessSettings)` (Upsert auf id 1).
  - `BusinessSettings::isComplete(): bool` (company_name, street, postal_code, city, tax_number, iban gesetzt),
    `toSnapshot(): array`, `toArray()`; IBAN beim Parsen ohne Leerzeichen, Großbuchstaben.
  - `BusinessSettingsService::loadCompleteOrFail(): BusinessSettings` → wirft `BusinessSettingsIncompleteException`
    („Business settings are incomplete“).
  - `S3Repository::putPrivateObject(...)`.
  - Routen: `GET /business/settings` (liefert `{ settings: null }` falls leer), `PUT /business/settings`,
    `POST /business/settings/logo` (multipart `file`, png/jpeg, max. 2 MiB, Key `business/logo.<ext>`).

- [ ] **Step 1–2:** implementieren, Router ergänzen.
- [ ] **Step 3:** `just lint-path src/Api/BusinessSettings src/Repository/BusinessSettingsRepository.php`.
- [ ] **Step 4:** curl: `GET` → `settings: null`; `PUT` mit Platzhalterdaten → 200; Logo hochladen → 200; im MinIO-
  Container prüfen, dass das Objekt **keine** öffentliche Lese-ACL hat (`mc stat` / anonymer GET → 403).
- [ ] **Step 5: Commit** `feat(business): business settings and private logo upload`

### Task 5: PDF-Renderer

**Files:**
- Create: `src/Api/Business/Pdf/BusinessPdfRenderer.php`, `src/Api/Business/Pdf/templates/document.php`,
  `src/Value/Business/PdfDocument.php`

**Interfaces:**
- Produces:
  - `PdfDocument` (reines Datenobjekt für die Vorlage): `title` (`Angebot`|`Rechnung`|`Anzahlungsrechnung`|
    `Schlussrechnung`|`Stornorechnung`), `number ?string`, `date`, `metaRows: list<array{label,value}>` (z. B.
    Leistungsdatum, gültig bis, fällig am, „Storno zu RE-…“, „Anzahlung zu Angebot AN-…“), `issuer array`,
    `recipientLines list<string>`, `introText ?string`, `items LineItems`, `subtotal Money`,
    `depositDeduction ?{label: string, amount: Money}`, `total Money`, `closingText ?string`, `isDraft bool`,
    `logoBytes ?string`.
  - `BusinessPdfRenderer::render(PdfDocument): string` (PDF-Bytes). mPDF mit `tempDir` unter `/tmp/mpdf`,
    Format A4, DejaVu Sans. Wasserzeichen „ENTWURF“ wenn `isDraft`. Fußzeile: Firmenname, Anschrift,
    Steuernummer, Bank, IBAN, BIC. Der § 19-Hinweis steht bei **allen** Dokumentarten (auch Angeboten) direkt
    unter der Summe, wörtlich: „Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.“
  - Template: einfaches PHP mit `htmlspecialchars` auf **jeder** Ausgabe; Layout DIN 5008 (Absenderzeile klein
    über Anschriftfeld, Infoblock rechts, Positionstabelle `Pos | Beschreibung | Menge | Einheit | Einzelpreis |
    Gesamt`, Summenblock: Zwischensumme, ggf. „abzgl. Anzahlung RE-… vom …“, **Gesamtbetrag** bzw. **Restbetrag**).

- [ ] **Step 1:** Renderer + Template.
- [ ] **Step 2:** `just lint-path src/Api/Business`.
- [ ] **Step 3:** Probe-PDF per Einzeiler im Container mit Platzhalterdaten (3 Positionen, eine mehrzeilige
  Beschreibung mit `<script>` und Umlauten, Anzahlungsabzug) nach `var/sample.pdf` schreiben, öffnen: Umlaute und
  `€` korrekt, `<script>` als Text sichtbar, Wasserzeichen bei `isDraft`. `var/sample.pdf` nicht committen.
- [ ] **Step 4: Commit** `feat(business): pdf renderer with mpdf`

### Task 6: Nummernkreise + Angebote (API)

**Files:**
- Create: `src/Repository/BusinessNumberSequenceRepository.php`, `src/Value/Business/{OfferId,Offer,Offers,
  OfferStatus,PostalAddressSnapshot}.php`, `src/Repository/BusinessOfferRepository.php`,
  `src/Api/Offer/Service/OfferService.php`, `src/Api/Offer/Action/{ListOffersAction,GetOfferAction,
  CreateOfferAction,UpdateOfferAction,DeleteOfferAction,SendOfferAction,SetOfferStatusAction,DuplicateOfferAction,
  GetOfferPdfAction}.php`, `src/Exception/{BusinessOfferNotFoundException,BusinessDocumentStateException}.php`
  (404 / 409, letztere mit statischen Factories `notDraft()`, `notAccepted()`, …)
- Modify: Router (Gruppe `/offers`)

**Interfaces:**
- Produces:
  - `BusinessNumberSequenceRepository::allocateNext(DocumentPrefix, int $year): DocumentNumber` — **muss innerhalb
    einer laufenden Transaktion** aufgerufen werden: `INSERT … ON DUPLICATE KEY UPDATE last_value = last_value`,
    dann `SELECT last_value … FOR UPDATE`, `+1`, `UPDATE`. `raiseTo(DocumentPrefix, int $year, int $value): void`
    (nur anheben, nie senken). Nutzt `TransactionTrait` nicht selbst; der Aufrufer öffnet die Transaktion.
  - `BusinessOfferRepository`: `loadOffer(OfferId): ?Offer` (inkl. Items), `loadOffers(?OfferStatus, ?CustomerId,
    ?int $year): Offers`, `create(Offer)`, `update(Offer)` (ersetzt Items), `delete(OfferId)`, `markSent(OfferId,
    DocumentNumber, array $customerSnapshot, array $issuerSnapshot, string $pdfObjectKey)`, `setStatus(OfferId,
    OfferStatus)`, `executeInTransaction(callable)` (via `TransactionTrait`), `getPdo()` nicht nötig — der
    Sequenz-Repository teilt dieselbe `PDO`-Instanz aus dem Container.
  - `Offer::toArray()` camelCase: `offerId, offerNumber, status, customerId, customerName, offerDate, validUntil,
    introText, closingText, items, totalCents, hasPdf, depositInvoiceId, finalInvoiceId, createdAt`.
    (`depositInvoiceId`/`finalInvoiceId` per Subquery auf nicht stornierte Rechnungen — ab Task 9 befüllt, vorher null.)
  - `OfferService::send(OfferId)`: Settings `loadCompleteOrFail`, nur `draft`; Transaktion: Nummer
    (`AN`, Jahr aus `offer_date`), Snapshots, PDF rendern, `putPrivateObject("business/offers/<id>.pdf")`,
    `markSent`; bei Exception nach Upload Objekt löschen.
  - Routen: `GET /offers`, `POST /offers`, `GET /offers/{offerId}`, `PATCH /offers/{offerId}` (nur draft),
    `DELETE /offers/{offerId}` (nur draft), `POST /offers/{offerId}/send`, `POST /offers/{offerId}/status`
    (`{status: accepted|rejected}`, nur aus `sent`), `POST /offers/{offerId}/duplicate` (neuer Entwurf, heutiges Datum),
    `GET /offers/{offerId}/pdf` (sent: gespeichertes PDF; draft: Vorschau mit Wasserzeichen, nicht gespeichert;
    `Content-Type: application/pdf`, `Content-Disposition: attachment; filename="AN-2026-0001.pdf"`,
    `Cache-Control: private, no-store`). `valid_until` Default `offer_date + 30 Tage`.

- [ ] **Step 1:** Sequenz-Repository.
- [ ] **Step 2:** Offer-Values, Repository, Service, Actions, Router.
- [ ] **Step 3:** `just lint-path src/Api/Offer src/Repository/BusinessOfferRepository.php
  src/Repository/BusinessNumberSequenceRepository.php`.
- [ ] **Step 4:** curl-Durchlauf: Entwurf anlegen → PDF-Vorschau hat Wasserzeichen → senden → `AN-2026-0001`, PDF ohne
  Wasserzeichen → PATCH → 409 → Status `accepted` → duplizieren ergibt Entwurf ohne Nummer → zweiten senden →
  `AN-2026-0002`. Senden ohne vollständige Firmendaten → 409.
- [ ] **Step 5: Commit** `feat(business): offers with numbering and pdf`

### Task 7: Rechnungen — Entwurf und Festschreiben (API)

**Files:**
- Create: `src/Value/Business/{InvoiceId,Invoice,Invoices,InvoiceKind,InvoiceStatus}.php`,
  `src/Repository/BusinessInvoiceRepository.php`, `src/Api/Invoice/Service/{InvoiceService,InvoiceFinalizer}.php`,
  `src/Api/Invoice/Action/{ListInvoicesAction,GetInvoiceAction,CreateInvoiceAction,UpdateInvoiceAction,
  DeleteInvoiceAction,FinalizeInvoiceAction,GetInvoicePdfAction}.php`,
  `src/Exception/BusinessInvoiceNotFoundException.php` (404)
- Modify: Router (Gruppe `/invoices`)

**Interfaces:**
- Consumes: `allocateNext`, `BusinessPdfRenderer::render`, `putPrivateObject`, `loadCompleteOrFail`,
  `BusinessDocumentStateException`.
- Produces:
  - `Invoice::toArray()` camelCase: `invoiceId, invoiceNumber, kind, status, customerId, customerName, offerId,
    offerNumber, depositInvoiceId, depositInvoiceNumber, cancelsInvoiceId, cancelsInvoiceNumber, invoiceDate,
    serviceDate, servicePeriodStart, servicePeriodEnd, paymentTermDays, dueDate, introText, closingText, items,
    subtotalCents, depositDeductionCents, totalCents, paidAt, isImported, isOverdue, isLocked, lockReason, hasPdf,
    createdAt`. `isOverdue` = `status === open && dueDate < heute`. `isLocked`/`lockReason` ab Task 9, vorher false/null.
  - `BusinessInvoiceRepository`: `loadInvoice(InvoiceId): ?Invoice`, `loadInvoices(?InvoiceStatus, ?CustomerId,
    ?int $year, bool $onlyOverdue): Invoices`, `create`, `update`, `delete`, `markFinalized(InvoiceId,
    DocumentNumber, string $dueDate, array $customerSnapshot, array $issuerSnapshot, string $pdfObjectKey)`,
    `executeInTransaction`.
  - `InvoiceFinalizer::finalize(Invoice): Invoice` — gemeinsame Logik für alle Rechnungsarten (auch Storno in
    Task 8): Settings vollständig, Leistungsdatum **oder** Zeitraum gesetzt (sonst 400; Storno erbt vom Original),
    Transaktion mit `allocateNext(Invoice, Jahr aus invoice_date)`, `due_date = invoice_date + payment_term_days`,
    Snapshots, PDF rendern, `putPrivateObject("business/invoices/<id>.pdf")`, Status `open` (Storno: `issued`).
  - Routen: `GET /invoices?status=&customerId=&year=&overdue=1`, `POST /invoices` (kind `standard`, optional
    `offerId`), `GET/PATCH/DELETE /invoices/{invoiceId}` (PATCH/DELETE nur draft), `POST /invoices/{invoiceId}/finalize`,
    `GET /invoices/{invoiceId}/pdf` (Header wie bei Angeboten, Dateiname = Nummer bzw. `Entwurf.pdf`).
  - `payment_term_days` Default aus Settings.

- [ ] **Step 1–2:** Values, Repository, Finalizer, Service, Actions, Router.
- [ ] **Step 3:** `just lint-path src/Api/Invoice src/Repository/BusinessInvoiceRepository.php`.
- [ ] **Step 4: Nebenläufigkeit** (Review Focus 1): zwei Entwürfe anlegen, beide Finalize-Requests parallel
  (`curl … & curl … & wait`) → zwei verschiedene Nummern `RE-2026-000n` und `…n+1`, `business_number_sequences`
  steht auf n+1.
- [ ] **Step 5: PDF-Fehler** (Review Focus 2): MinIO-Container stoppen, Finalize → 500, Rechnung weiter `draft`,
  Sequenz unverändert; MinIO starten, Finalize → nächste lückenlose Nummer.
- [ ] **Step 6: Snapshot** (Review Focus 3): festgeschriebene Rechnung, dann Kundenadresse und Firmenname ändern →
  `GET /invoices/{id}` zeigt alten Kundennamen, PDF unverändert (gleiche Bytes/Hash).
- [ ] **Step 7:** PATCH/DELETE auf festgeschriebene Rechnung → 409. Fälligkeit in der Vergangenheit → `isOverdue: true`.
- [ ] **Step 8: Commit** `feat(business): invoice drafts and finalizing`

### Task 8: Zahlung und Storno (API)

**Files:**
- Create: `src/Api/Invoice/Action/{MarkInvoicePaidAction,UnmarkInvoicePaidAction,CancelInvoiceAction}.php`
- Modify: `InvoiceService`, `BusinessInvoiceRepository` (`setPaid(InvoiceId, ?string $paidAt)`,
  `setStatus`), Router

**Interfaces:**
- Produces:
  - `POST /invoices/{id}/payment` `{paidAt: "YYYY-MM-DD"}` (Default heute; nur `open`; nicht für Storno) → `paid`.
  - `DELETE /invoices/{id}/payment` → `open`; **409**, wenn es eine `deposit`-Rechnung ist, auf die eine
    festgeschriebene, nicht stornierte `final`-Rechnung verweist.
  - `POST /invoices/{id}/cancel` → erzeugt `cancellation`-Rechnung (`cancels_invoice_id`, Kunde, heutiges Datum,
    Leistungsdaten vom Original, Positionen = `items->negated()`, `subtotal/deposit_deduction/total` jeweils negiert;
    importierte Originale: eine Position „Storno zu Rechnung <Nummer>“, Menge 1, Einheit `pausch.`, Preis
    −Gesamtbetrag), finalisiert sie via `InvoiceFinalizer` (Status `issued`, Meta-Zeile „Storno zu RE-…“) und setzt
    das Original auf `cancelled` — alles in **einer** Transaktion. `paid_at` des Originals bleibt erhalten.
    Erlaubt nur aus `open`/`paid`, nicht für `cancellation`; für `deposit` 409, solange eine festgeschriebene,
    nicht stornierte Schlussrechnung darauf verweist. Antwort: `{ invoice: <Storno> }`, Status 201.

- [ ] **Step 1–2:** implementieren.
- [ ] **Step 3:** `just lint-path src/Api/Invoice`.
- [ ] **Step 4:** curl: bezahlt markieren → `paid`; zurücknehmen → `open`; stornieren → neue Nummer, Summe negativ,
  Original `cancelled`; Storno nochmals stornieren → 409; Storno als bezahlt markieren → 409.
- [ ] **Step 5: Commit** `feat(business): invoice payments and cancellation`

### Task 9: Anzahlung und Schlussrechnung (API)

**Files:**
- Create: `src/Api/Offer/Action/CreateInvoiceFromOfferAction.php`
- Modify: `InvoiceService`, `InvoiceFinalizer` (Sperre), `BusinessInvoiceRepository`
  (`loadActiveDepositForOffer(OfferId): ?Invoice` = kind `deposit`, alle Status außer `cancelled`, also inkl.
  Entwürfe), `Invoice::toArray()` (`isLocked`, `lockReason`), `Offer::toArray()`
  (`depositInvoiceId`, `finalInvoiceId`), Router

**Interfaces:**
- Produces:
  - `POST /offers/{offerId}/invoices` Body `{kind: "standard"|"deposit"|"final", amountCents?: int, percent?: number}`;
    nur für `accepted` Angebote (sonst 409).
    - `standard`: Entwurf mit Angebotspositionen, `offer_id` gesetzt.
    - `deposit`: genau eins von `amountCents`/`percent` (sonst 400); Prozent → `round(total * percent / 100)`;
      0 < Betrag < Angebotssumme; 409 wenn schon eine nicht stornierte Anzahlung existiert. Eine Position
      „Anzahlung zu Angebot AN-…“, Menge 1, Einheit `pausch.`.
    - `final`: Angebotspositionen; `deposit_invoice_id` = aktive Anzahlung (falls vorhanden); 409 falls schon eine
      nicht stornierte Schlussrechnung zum Angebot existiert.
  - Abzug wird **beim Finalisieren** neu berechnet: `deposit_deduction_cents` = Gesamt der verknüpften Anzahlung,
    0 wenn diese `cancelled` ist; `total = subtotal − deduction`. PDF-Label: „abzgl. Anzahlung RE-2026-0004 vom
    12.11.2026“, Summe beschriftet als „Restbetrag“.
  - **Sperre**: `final` mit Anzahlung in Status ≠ `paid` und ≠ `cancelled` → Finalize 409
    `BusinessDocumentStateException::depositUnpaid('RE-2026-0004')` mit Meldung
    `Deposit invoice RE-2026-0004 is not paid`; Anzahlung noch Entwurf → Meldung `Deposit invoice is not finalized`.
    `toArray()`: `isLocked: true`, `lockReason` = dieselbe Meldung.

- [ ] **Step 1–2:** implementieren.
- [ ] **Step 3:** `just lint-path src/Api/Offer src/Api/Invoice`.
- [ ] **Step 4:** curl-Szenario: Angebot 1.200 € angenommen → Anzahlung 400 € (`amountCents: 40000`) festschreiben →
  Schlussrechnung anlegen → `isLocked: true`, Finalize 409 → Anzahlung bezahlt → Finalize → Restbetrag 800 €,
  PDF zeigt Abzug. Zweite Anzahlung → 409. Prozent `33.333` auf 1.200 € → 40000 Cent. Anzahlung zurück auf unbezahlt
  → 409. Variante: Anzahlung stornieren, dann Schlussrechnung → kein Abzug, 1.200 €.
- [ ] **Step 5: Commit** `feat(business): deposit and final invoices`

### Task 10: Import einer Bestandsrechnung (API)

**Files:**
- Create: `src/Api/Invoice/Service/InvoiceImportService.php`, `src/Api/Invoice/Action/ImportInvoiceAction.php`
- Modify: Router (`POST /invoices/import` **vor** `/invoices/{invoiceId}`-Routen registrieren)

**Interfaces:**
- Produces: `POST /invoices/import` multipart: `file` (Pflicht, MIME per `finfo` = `application/pdf`, max. 10 MiB),
  `customerId`, `invoiceNumber`, `invoiceDate`, `dueDate`, `totalCents`, `status` (`open`|`paid`), `paidAt` (Pflicht
  bei `paid`), optional `serviceDate`. Ablauf in Transaktion: `DocumentNumber::parse(…, Invoice)`; Jahr ≠ Jahr von
  `invoiceDate` → 400; Nummer existiert → 409 (`BusinessDocumentStateException::numberTaken`); Insert mit
  `kind standard`, `is_imported 1`, ohne Positionen, `subtotal = total = totalCents`, `customer_snapshot` aus
  aktuellem Kunden, `issuer_snapshot` null; PDF nach `business/invoices/<id>.pdf`; `raiseTo(RE, Jahr, Sequenz)`.
  Antwort 201 `{ invoice }`.

- [ ] **Step 1:** implementieren.
- [ ] **Step 2:** `just lint-path src/Api/Invoice`.
- [ ] **Step 3** (Review Focus 5): Import `RE-2026-0003` → 201, Sequenz 2026 steht auf 3, nächste Festschreibung
  ergibt `RE-2026-0004`. Gleiche Nummer erneut → 409; `RE-2025-0009` mit Datum 2026 → 400; beides: Sequenz
  unverändert. Nicht-PDF hochladen → 400. Import mit niedrigerer Nummer `RE-2026-0001` (frei) → 201, Sequenz bleibt 4.
- [ ] **Step 4: Commit** `feat(business): import existing invoices`

### Task 11: Dashboard — Firmendaten und Kunden

**Files (luka-lta-backend):**
- Create: `src/api/business-settings/{endpoints,hooks}.ts`, `src/api/customers/{endpoints,hooks}.ts`,
  `src/feature/business-settings/{index.tsx,schema/businessSettings.ts}`,
  `src/feature/customers/{index.tsx,schema/customer.ts,components/CustomerFormDialog.tsx,components/CustomerTable.tsx}`,
  `src/pages/Dashboard/{CustomersPage,BusinessSettingsPage}.tsx`
- Modify: `src/AppRouter.tsx` (Routen `business/customers`, `business/offers`, `business/invoices`,
  `business/settings`), `src/components/layout/data/sidebar-data.ts` („Business“ bekommt Unterpunkte Übersicht,
  Kunden, Angebote, Rechnungen, Einstellungen — Muster bestehender Untermenüs übernehmen)

**Interfaces:**
- Produces: Hooks `useCustomers(includeArchived)`, `useCustomer(id)`, `useCreateCustomer`, `useUpdateCustomer`,
  `useArchiveCustomer`, `useBusinessSettings`, `useSaveBusinessSettings`, `useUploadBusinessLogo` (über `apiForm`);
  Query-Keys `["customers", …]`, `["business-settings"]`. Zod-Typ `Customer` mit Feldern aus Task 3.

- [ ] **Step 1:** Endpoints/Hooks/Schemas.
- [ ] **Step 2:** Seiten: Kundentabelle mit Suche + Archiv-Umschalter, Formulardialog; Einstellungsformular mit
  Hinweisbanner „Ohne vollständige Firmendaten können keine Dokumente festgeschrieben werden“ solange unvollständig.
- [ ] **Step 3:** `bun run build && bun run lint` → ohne Fehler.
- [ ] **Step 4:** Im Browser gegen lokale API: Firmendaten speichern, Logo hochladen, Kunde anlegen/bearbeiten/archivieren.
- [ ] **Step 5: Commit** (luka-lta-backend) `feat(business): customers and business settings pages`

### Task 12: Dashboard — Angebote

**Files:**
- Create: `src/feature/business-documents/{LineItemsEditor.tsx,MoneyText.tsx,DocumentStatusBadge.tsx,
  downloadPdf.ts,money.ts}`, `src/api/offers/{endpoints,hooks}.ts`,
  `src/feature/offers/{index.tsx,schema/offer.ts,components/OfferTable.tsx,components/OfferEditor.tsx,
  components/OfferDetail.tsx}`, `src/pages/Dashboard/OffersPage.tsx`

**Interfaces:**
- Produces:
  - `money.ts`: `formatCents(cents: number): string` (`Intl.NumberFormat('de-DE', {style:'currency',currency:'EUR'})`),
    `parseEuroInput(value: string): number | null` (→ Cent).
  - `LineItemsEditor` Props `{ value: LineItemInput[]; onChange(next: LineItemInput[]): void; disabled?: boolean }`,
    `LineItemInput = { description: string; quantity: string; unit: string; unitPriceCents: number }`, Live-Summe
    mit derselben Rundung wie die API (`Math.round` auf Hundertstel-Produkt; Anzeige nur, API ist maßgeblich).
  - `downloadPdf(path: string, fileName: string): Promise<void>` — `api.get(path, {responseType: 'blob'})`,
    Object-URL, `<a download>`, URL wieder freigeben.
  - `DocumentStatusBadge` für Angebots- und Rechnungsstatus inkl. „überfällig“.
- [ ] **Step 1:** Gemeinsame Bausteine.
- [ ] **Step 2:** Angebotsliste (Filter Status/Kunde/Jahr), Editor (nur Entwurf), Detail mit Aktionen: Vorschau-PDF,
  „Versenden (festschreiben)“ mit Bestätigungsdialog, Angenommen/Abgelehnt, Duplizieren, PDF herunterladen.
  Platz für „Anzahlungsrechnung / Schlussrechnung / Rechnung“-Buttons (Task 13 verdrahtet sie).
- [ ] **Step 3:** `bun run build && bun run lint`.
- [ ] **Step 4:** Browser: Angebot anlegen, Vorschau, versenden, PDF laden, annehmen.
- [ ] **Step 5: Commit** `feat(business): offers pages`

### Task 13: Dashboard — Rechnungen, Anzahlung, Storno, Import

**Files:**
- Create: `src/api/invoices/{endpoints,hooks}.ts`, `src/feature/invoices/{index.tsx,schema/invoice.ts,
  components/InvoiceTable.tsx,components/InvoiceEditor.tsx,components/InvoiceDetail.tsx,
  components/MarkPaidDialog.tsx,components/CancelInvoiceDialog.tsx,components/ImportInvoiceDialog.tsx,
  components/CreateFromOfferDialog.tsx}`, `src/pages/Dashboard/InvoicesPage.tsx`
- Modify: `src/feature/offers/components/OfferDetail.tsx` (Buttons verdrahten)

**Interfaces:**
- Consumes: Bausteine aus Task 12; API aus Tasks 7–10.
- Produces: Hooks `useInvoices(filter)`, `useInvoice(id)`, `useCreateInvoice`, `useUpdateInvoice`,
  `useDeleteInvoice`, `useFinalizeInvoice`, `useMarkInvoicePaid`, `useUnmarkInvoicePaid`, `useCancelInvoice`,
  `useImportInvoice` (über `apiForm`), `useCreateInvoiceFromOffer`; alle Mutationen invalidieren `["invoices"]`
  **und** `["offers"]`.
- [ ] **Step 1:** Endpoints/Hooks/Schema.
- [ ] **Step 2:** Liste mit Filtern (Status inkl. „überfällig“, Kunde, Jahr) und Summe „offen“; Detail mit Aktionen
  je Status; gesperrte Schlussrechnung zeigt `lockReason` deutsch übersetzt („Gesperrt: Anzahlung RE-… noch offen“)
  und deaktiviert „Festschreiben“; Bestätigungsdialoge für Festschreiben und Storno nennen die Endgültigkeit.
  `CreateFromOfferDialog`: Art wählen; bei Anzahlung Umschalter € / % mit Live-Betrag.
  `ImportInvoiceDialog`: Felder aus Task 10, Kunde wählen oder neu anlegen.
- [ ] **Step 3:** `bun run build && bun run lint`.
- [ ] **Step 4: End-to-end im Browser** (lokal): Bestandsrechnung importieren → nächste Rechnung schließt nummerisch
  an; komplettes Anzahlungs-Szenario aus Task 9 Step 4 über die Oberfläche; Storno einer Schlussrechnung zeigt
  −Restbetrag; überfällige Rechnung erscheint im Filter.
- [ ] **Step 5: Commit** `feat(business): invoices pages`

### Task 14: Abschluss

- [ ] **Step 1:** `CLAUDE.md` (API) ergänzen: Business-Domain in Verzeichnis-Map, MinIO-Prefix `business/` privat,
  Hinweis „Festgeschriebene Rechnungen sind unveränderlich“. `luka-lta-backend/CLAUDE.md`: neue Domains.
- [ ] **Step 2:** `just lint-path src/Api/Customer src/Api/BusinessSettings src/Api/Business src/Api/Offer
  src/Api/Invoice src/Value/Business` und `bun run build && bun run lint` → sauber.
- [ ] **Step 3:** Produktions-Hinweis im PR: Migrationen `business_*.sql` + `zzz_business_foreign_keys.sql` manuell
  einspielen, Image wegen `gd` neu bauen, danach Firmendaten im Dashboard pflegen.
- [ ] **Step 4: Commit** `docs: document business domain`
