# Projects Backend Implementation Plan (Teil 1 von 3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Die `luka-lta-api` wird zur zentralen Quelle der Wahrheit für Portfolio-Projekte — CRUD, wiederverwendbare Tags, MinIO-Assets, Sortierung, Import der 6 Altprojekte.

**Architecture:** Action → Service → Repository, exakt nach dem bestehenden Blog-Domain-Muster. Projekte in `projects`, Assets in `project_assets` (MinIO-Keys, Bytes über PHP-Proxy ausgeliefert wie beim Avatar), Tags als wiederverwendbares Dictionary `project_tags` + Join `project_tag_assignments` (1:1 gespiegelt von `blog_tags`/`blog_post_tags`).

**Tech Stack:** PHP 8.4, Slim 4, PHP-DI, PDO/MySQL, aws/aws-sdk-php (MinIO), ramsey/uuid, symfony/console.

**Spec:** `docs/plans/2026-10-03-centralized-project-management.md`

## Global Constraints

- `declare(strict_types=1);` in jeder neuen PHP-Datei, direkt über dem Namespace.
- PSR-12, max. Zeilenlänge 120 Zeichen. Verifikation ausschließlich über `just lint` (phpmd + phpcs).
- **Kein** `else`/`elseif` — Guard Clauses / Early Return.
- **Kein** `SELECT *` — alle Felder explizit auflisten, bei JOINs Tabellen-Alias verwenden.
- Value Objects: privater Konstruktor + statische Named Constructors (`from…`, `create`, `fromDatabase`).
- Kommentare auf Deutsch (internes Projekt), nur wo das *Warum* nicht offensichtlich ist.
- Keine Magic Numbers — benannte Konstanten mit Doc-Comment.
- Exception-Messages kurz, ohne abschließendes Satzzeichen-Fehlverhalten (bestehender Stil: mit Punkt, z. B. `'Tag not found.'`) — bestehenden Stil übernehmen.
- **Keine Test-Infrastruktur** (bewusste Entscheidung): Verifikation pro Task = Lint + echte HTTP-Calls per `curl` gegen den Dev-Stack + Prüfung der DB-Zeilen.
- Es gibt **keinen** Test-Runner. Niemals `phpunit`, `composer test` o. ä. aufrufen.
- Trailing-Kommata wo möglich.

## Verifizierte Umgebungs-Fakten (alle im Dev-Stack geprüft, nicht raten)

Diese Werte sind gemessen, nicht angenommen. Weicht etwas ab, erst prüfen, nicht umbauen.

| Fakt | Wert |
|---|---|
| Compose-Service PHP | `php-fpm-api` (**nicht** `php-fpm`) |
| Laufender Container | `php-fpm-luka-lta` |
| Datenbank | `luka_lta_api` (ein früherer Entwurf dieses Plans nannte `luka_lta` — falsch) |
| MySQL-Container | `mysql-luka-lta`, root-Passwort steht als `MYSQL_ROOT_PASSWORD` im Container |
| API Base-URL | `http://localhost/api/v1` |
| Auth-Route | `POST /auth/login` mit `{"email","password"}` |
| MinIO-Bucket | `avatars` (Env `AWS_BUCKET`) — Projekt-Keys liegen als Prefix `projects/…` in **diesem** Bucket |

### Lint

`just lint` ist **nicht** benutzbar: phpmd läuft unter PHP 8.4 nicht (pdepend/Symfony-DI-Inkompatibilität, Fatal Error) und bricht die Recipe ab, bevor phpcs läuft. Das Gate für neuen Code ist deshalb phpcs, begrenzt auf die Pfade des Tasks:

```bash
just lint-path src/Value/Project src/Api/Project      # Beispiel: eigene Pfade des Tasks einsetzen
```

Erwartet: `FOUND 0 ERRORS` und keine Warnings für die eigenen Dateien. `src` im Ganzen (`just lint-cs`) enthält Altbestand mit Line-Length-Warnings in fremden Dateien — die sind **nicht** Aufgabe dieses Plans und werden nicht angefasst.

Die `@SuppressWarnings(PHPMD.…)`-Annotationen im geplanten Code bleiben drin: sie dokumentieren die Absicht und greifen, sobald phpmd wieder läuft.

### Auth für geschützte Routen (wichtig — zwei Stolperfallen)

`AuthMiddleware` verlangt **beides**: einen `Authorization`-Header **und** einen nicht-leeren `Origin`-Header. Und es übergibt den kompletten Header-Wert an `Token::validate()` — ein `Bearer `-Prefix lässt die Validierung fehlschlagen. Der Header enthält also den **rohen JWT ohne `Bearer`**.

Token ohne Passwort erzeugen (das Dev-Passwort ist unbekannt und wird **nicht** geändert):

```bash
TOKEN=$(docker compose -f docker-compose.development.yml run --rm -T php-fpm-api php -r \
  'require "/app/vendor/autoload.php";
   echo ReallySimpleJWT\Token::create("1", getenv("JWT_SECRET"), time()+86400, "backend.luka-lta.dev");' \
  2>/dev/null | tr -d "\r\n")

AUTH=(-H "Authorization: $TOKEN" -H "Origin: http://localhost:5173")
```

Alle curl-Aufrufe auf geschützte Routen in diesem Plan verwenden `"${AUTH[@]}"`. Gegenprobe, dass das Setup steht:

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/api/v1/api-keys/ "${AUTH[@]}"   # 200
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/api/v1/api-keys/                 # 401
```

### MySQL-Zugriff für Verifikation

```bash
docker compose -f docker-compose.development.yml exec -T mysql \
  sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" luka_lta_api' -e "SELECT ..."
```

## Review Focus

Diese Eingabeklassen sind von der Spec impliziert, werden aber von keinem Task-Verifikationsschritt automatisch abgedeckt. Jede Zeile hat unten einen expliziten curl-Schritt im besitzenden Task:

1. **Doppelter Slug** — zweites Projekt mit gleichem Slug muss 409 liefern, nicht 500 aus der Unique-Constraint. (Task 6)
2. **Unsichtbares Projekt über öffentliche Route** — `is_visible = 0` darf über `GET /projects` und `GET /projects/{slug}` nicht auffindbar sein, auch nicht mit gefälschtem `Authorization`-Header. (Task 6)
3. **Asset-Replace** — zweiter Logo-Upload darf nicht zwei Zeilen und zwei MinIO-Objekte hinterlassen. (Task 7)
4. **Projekt-Löschung mit Assets** — MinIO-Objekte müssen verschwinden, Tags im Dictionary müssen bleiben. (Task 6 + Task 8)
5. **Falscher MIME-Type / zu große Datei** — muss 400 liefern, nicht 500. (Task 8)

---

## Hinweis zur Abweichung vom Blog-Precedent (bewusst)

`GetAllBlogsAction` bestimmt den Auth-Status mit `$isAuthenticated = !empty($request->getHeaderLine('Authorization'))`. Das ist eine **Auth-Bypass-Schwäche**: ein beliebiger Garbage-Header reicht, um unveröffentlichte Blog-Posts zu lesen. Dieses Muster wird für Projects **nicht** übernommen. Stattdessen:

- Öffentliche Routen (`GET /projects`, `GET /projects/{slug}`) filtern **immer** hart auf `is_visible = 1`, ohne jede Header-Auswertung.
- Das Dashboard nutzt separate, echt durch `AuthMiddleware` geschützte Routen (`GET /projects/manage`, `GET /projects/manage/{projectId}`), die alle Projekte liefern.

Die bestehende Blog-Schwäche wird in diesem Plan **nicht** mitgefixt (anderes Subsystem, nicht im Auftrag) — sie ist dem Auftraggeber separat gemeldet.

---

## File Structure

**Neu angelegt:**

| Datei | Verantwortung |
|---|---|
| `data/mysql/projects.sql` | Tabelle `projects` |
| `data/mysql/project_assets.sql` | Tabelle `project_assets` |
| `data/mysql/project_tags.sql` | Tag-Dictionary |
| `data/mysql/project_tag_assignments.sql` | Join-Tabelle |
| `src/Value/Project/ProjectId.php` | UUID-Wrapper |
| `src/Value/Project/ProjectSlug.php` | Slug-Validierung + Slugify |
| `src/Value/Project/ProjectName.php` | Name-Validierung |
| `src/Value/Project/ProjectStatus.php` | Backed Enum der Status-Werte |
| `src/Value/Project/Project.php` | Projekt-Entity |
| `src/Value/Project/Projects.php` | Collection |
| `src/Value/Project/Tag/ProjectTag.php` | Tag-Entity |
| `src/Value/Project/Tag/ProjectTagId.php` | Tag-ID |
| `src/Value/Project/Tag/ProjectTagName.php` | Tag-Name |
| `src/Value/Project/Tag/ProjectTagSlug.php` | Tag-Slug |
| `src/Value/Project/Tag/ProjectTags.php` | Tag-Collection |
| `src/Value/Project/Asset/ProjectAsset.php` | Asset-Entity |
| `src/Value/Project/Asset/ProjectAssets.php` | Asset-Collection |
| `src/Value/Project/Asset/ProjectAssetType.php` | Backed Enum logo/cover/screenshot |
| `src/Exception/ProjectNotFoundException.php` | 404 |
| `src/Exception/ProjectSlugAlreadyExistsException.php` | 409 |
| `src/Exception/ProjectTagNotFoundException.php` | 404 |
| `src/Exception/ProjectAssetNotFoundException.php` | 404 |
| `src/Exception/ProjectAssetUploadException.php` | 400/500 |
| `src/Repository/ProjectTagRepository.php` | Tag-Dictionary + Assignments |
| `src/Repository/ProjectRepository.php` | Projekt-CRUD + Sortierung |
| `src/Repository/ProjectAssetRepository.php` | Asset-Zeilen |
| `src/Api/Project/Service/ProjectTagService.php` | Tag-Logik (idempotente Anlage) |
| `src/Api/Project/Service/ProjectService.php` | Projekt-Logik |
| `src/Api/Project/Service/ProjectAssetService.php` | Upload-Validierung, Key-Building, Lifecycle |
| `src/Api/Project/Action/*.php` | 12 Actions (s. Tasks) |
| `src/Command/Project/ImportLegacyProjectsCommand.php` | Einmal-Import der 6 Altprojekte |

**Modifiziert:**

| Datei | Änderung |
|---|---|
| `data/mysql/zz_foreign_keys.sql` | 3 neue FK-Constraints |
| `src/Repository/S3Repository.php` | `uploadProjectAsset`, `deleteObject`, `getObject` |
| `src/Slim/RouteMiddlewareCollector.php` | Imports + Projects-Routen |
| `bin/app.php` | Import-Command registrieren |

---

### Task 1: Datenbank-Schema

**Files:**
- Create: `data/mysql/projects.sql`
- Create: `data/mysql/project_assets.sql`
- Create: `data/mysql/project_tags.sql`
- Create: `data/mysql/project_tag_assignments.sql`
- Modify: `data/mysql/zz_foreign_keys.sql` (ans Ende anfügen)

**Interfaces:**
- Consumes: nichts
- Produces: Tabellen `projects`, `project_assets`, `project_tags`, `project_tag_assignments` — Spaltennamen werden von allen folgenden Tasks exakt so verwendet.

- [ ] **Step 1: `data/mysql/projects.sql` anlegen**

```sql
-- Portfolio-Projekte. Zentrale Quelle der Wahrheit; das oeffentliche Frontend
-- hardcodet keine Projekte mehr. `status` ist beschreibend (Badge), `is_visible`
-- ist der harte Schalter fuer die oeffentliche Sichtbarkeit — bewusst getrennt,
-- damit ein Projekt z.B. als 'archived' markiert aber weiter sichtbar sein kann.
CREATE TABLE `projects`
(
    `project_id`        char(36)                                                            NOT NULL,
    `name`              varchar(100)                                                        NOT NULL,
    `slug`              varchar(100)                                                        NOT NULL,
    `short_description` varchar(255)                                                            NULL DEFAULT NULL,
    `description`       text                                                                    NULL,
    `status`            enum ('development', 'beta', 'active', 'paused', 'archived')         NOT NULL DEFAULT 'development',
    `is_visible`        tinyint(1)                                                          NOT NULL DEFAULT 1,
    `category`          varchar(50)                                                             NULL DEFAULT NULL,
    `tech_stack`        json                                                                    NULL DEFAULT NULL,
    `website_url`       varchar(1024)                                                           NULL DEFAULT NULL,
    `live_label`        varchar(100)                                                            NULL DEFAULT NULL,
    `repository_url`    varchar(1024)                                                           NULL DEFAULT NULL,
    `repository_owner`  varchar(100)                                                            NULL DEFAULT NULL,
    `repository_name`   varchar(100)                                                            NULL DEFAULT NULL,
    `demo_url`          varchar(1024)                                                           NULL DEFAULT NULL,
    `documentation_url` varchar(1024)                                                           NULL DEFAULT NULL,
    `role`              varchar(100)                                                            NULL DEFAULT NULL,
    `project_year`      smallint                                                                NULL DEFAULT NULL,
    `is_client_project` tinyint(1)                                                          NOT NULL DEFAULT 0,
    `metadata`          json                                                                    NULL DEFAULT NULL,
    `sort_order`        int                                                                 NOT NULL DEFAULT 0,
    `created_at`        datetime                                                            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        datetime                                                            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`project_id`),
    UNIQUE KEY `uq_project_slug` (`slug`),
    KEY `project_sort` (`sort_order`),
    KEY `project_visibility` (`is_visible`, `status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
```

- [ ] **Step 2: `data/mysql/project_assets.sql` anlegen**

```sql
-- Projekt-Bilder. Die DB haelt nur die MinIO-Referenz, nie die Bytes.
-- logo/cover sind pro Projekt Singleton (applikationsseitig erzwungen, da eine
-- gemeinsame Unique-Constraint die mehrfachen screenshot-Zeilen brechen wuerde).
CREATE TABLE `project_assets`
(
    `asset_id`   char(36)                                   NOT NULL,
    `project_id` char(36)                                   NOT NULL,
    `type`       enum ('logo', 'cover', 'screenshot')       NOT NULL,
    `object_key` varchar(255)                               NOT NULL,
    `alt_text`   varchar(150)                                   NULL DEFAULT NULL,
    `sort_order` int                                        NOT NULL DEFAULT 0,
    `created_at` datetime                                   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`asset_id`),
    KEY `project_asset_lookup` (`project_id`, `type`, `sort_order`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
```

- [ ] **Step 3: `data/mysql/project_tags.sql` anlegen**

```sql
-- Wiederverwendbares Tag-Dictionary fuer Projekte. Struktur gespiegelt von
-- `blog_tags`; eigene Tabelle statt Mitnutzung, damit Blog und Portfolio
-- entkoppelt bleiben. Collation utf8mb4_0900_ai_ci ist case-insensitive, die
-- Unique-Keys deduplizieren damit "Analytics" und "analytics" automatisch.
CREATE TABLE `project_tags`
(
    `tag_id`     int         NOT NULL AUTO_INCREMENT,
    `name`       varchar(50) NOT NULL,
    `slug`       varchar(50) NOT NULL,
    `created_at` datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tag_id`),
    UNIQUE KEY `uq_project_tags_name` (`name`),
    UNIQUE KEY `uq_project_tags_slug` (`slug`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
```

- [ ] **Step 4: `data/mysql/project_tag_assignments.sql` anlegen**

```sql
-- Zuordnung Projekt <-> Tag. Beim Loeschen eines Projekts verschwindet nur die
-- Zuordnung, der Tag bleibt im Dictionary erhalten — genau das macht ihn
-- wiederverwendbar.
CREATE TABLE `project_tag_assignments`
(
    `assignment_id` int      NOT NULL AUTO_INCREMENT,
    `project_id`    char(36) NOT NULL,
    `tag_id`        int      NOT NULL,
    PRIMARY KEY (`assignment_id`),
    UNIQUE KEY `uq_project_tag_assignment` (`project_id`, `tag_id`),
    KEY `project_tag_reverse` (`tag_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;
```

- [ ] **Step 5: FKs in `data/mysql/zz_foreign_keys.sql` ergänzen**

Ans **Ende** der Datei anfügen (bestehende Zeilen nicht anfassen):

```sql

ALTER TABLE `project_assets`
    ADD CONSTRAINT `fk_project_asset_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE;

ALTER TABLE `project_tag_assignments`
    ADD CONSTRAINT `fk_project_tag_assignment_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE;

ALTER TABLE `project_tag_assignments`
    ADD CONSTRAINT `fk_project_tag_assignment_tag` FOREIGN KEY (`tag_id`) REFERENCES `project_tags` (`tag_id`) ON DELETE CASCADE;
```

- [ ] **Step 6: Schema gegen laufende Dev-DB anwenden**

Die SQL-Dateien laufen nur beim **ersten** Container-Init. Für die bestehende Dev-DB einmalig manuell einspielen:

```bash
cd /Users/lliebenthal/projects/luka-lta-api
for f in projects project_tags project_tag_assignments project_assets; do
  docker compose -f docker-compose.development.yml exec -T mysql \
    sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" luka_lta_api' < "data/mysql/$f.sql"
done
docker compose -f docker-compose.development.yml exec -T mysql \
  sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" luka_lta_api' <<'SQL'
ALTER TABLE `project_assets`
    ADD CONSTRAINT `fk_project_asset_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE;
ALTER TABLE `project_tag_assignments`
    ADD CONSTRAINT `fk_project_tag_assignment_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE;
ALTER TABLE `project_tag_assignments`
    ADD CONSTRAINT `fk_project_tag_assignment_tag` FOREIGN KEY (`tag_id`) REFERENCES `project_tags` (`tag_id`) ON DELETE CASCADE;
SQL
```

Erwartet: keine Fehlerausgabe. Falls der DB-Name nicht `luka_lta_api` ist, aus `docker-compose.development.yml` den Wert von `MYSQL_DATABASE` lesen und verwenden.

- [ ] **Step 7: Schema verifizieren**

```bash
docker compose -f docker-compose.development.yml exec -T mysql \
  sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" luka_lta_api' \
  -e "SHOW TABLES LIKE 'project%'; SHOW CREATE TABLE project_tag_assignments;"
```

Erwartet: 4 Tabellen (`projects`, `project_assets`, `project_tags`, `project_tag_assignments`), und `project_tag_assignments` zeigt beide Foreign Keys.

- [ ] **Step 8: Commit**

```bash
git add data/mysql/projects.sql data/mysql/project_assets.sql data/mysql/project_tags.sql \
        data/mysql/project_tag_assignments.sql data/mysql/zz_foreign_keys.sql
git commit -m "$(cat <<'EOF'
feat: add project, asset and tag schema for centralized project management

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Tag-Value-Objects

**Files:**
- Create: `src/Value/Project/Tag/ProjectTagId.php`
- Create: `src/Value/Project/Tag/ProjectTagName.php`
- Create: `src/Value/Project/Tag/ProjectTagSlug.php`
- Create: `src/Value/Project/Tag/ProjectTag.php`
- Create: `src/Value/Project/Tag/ProjectTags.php`

**Interfaces:**
- Consumes: Tabelle `project_tags` aus Task 1.
- Produces: `ProjectTagId::fromInt(int): self`, `::fromString(string): self`, `->asInt(): int`, `->asString(): string`; `ProjectTagName::fromString(string): self`; `ProjectTagSlug::fromName(string): self`, `::fromString(string): self`; `ProjectTag::create(string $name): self`, `::fromDatabase(array $row): self`, `->getTagId(): ?ProjectTagId`, `->getName(): ProjectTagName`, `->getSlug(): ProjectTagSlug`, `->getCreatedAt(): DateTimeImmutable`, `->toArray(): array`; `ProjectTags::from(ProjectTag ...): self`, `::empty(): self`, `->toArray(): array`, `->count(): int`.

- [ ] **Step 1: `ProjectTagId.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Tag;

use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Value\IdentifierInterface;

class ProjectTagId implements IdentifierInterface
{
    private function __construct(
        private readonly int $value,
    ) {
        if ($value <= 0) {
            throw new ApiValidationException('Project tag ID must be greater than zero.', 400);
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public static function fromString(string $value): self
    {
        return new self((int) $value);
    }

    public function asInt(): int
    {
        return $this->value;
    }

    public function asString(): string
    {
        return (string) $this->value;
    }
}
```

- [ ] **Step 2: `ProjectTagName.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Tag;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

class ProjectTagName
{
    /** Maximale Laenge entspricht der Spaltenbreite von project_tags.name */
    private const int MAX_LENGTH = 50;

    private function __construct(
        private readonly string $value,
    ) {
        if (trim($value) === '') {
            throw new ApiInvalidArgumentException('Project tag name cannot be empty.', 400);
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new ApiInvalidArgumentException('Project tag name must not exceed 50 characters.', 400);
        }
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 3: `ProjectTagSlug.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Tag;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

class ProjectTagSlug
{
    private function __construct(
        private readonly string $value,
    ) {
        if ($value === '') {
            throw new ApiInvalidArgumentException('Project tag slug cannot be empty.', 400);
        }
    }

    public static function fromName(string $name): self
    {
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));

        return new self(trim($slug, '-'));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 4: `ProjectTag.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Tag;

use DateTimeImmutable;

class ProjectTag
{
    private function __construct(
        private readonly ?ProjectTagId     $tagId,
        private readonly ProjectTagName    $name,
        private readonly ProjectTagSlug    $slug,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(string $name): self
    {
        return new self(
            null,
            ProjectTagName::fromString($name),
            ProjectTagSlug::fromName($name),
            new DateTimeImmutable(),
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            ProjectTagId::fromInt((int) $row['tag_id']),
            ProjectTagName::fromString($row['name']),
            ProjectTagSlug::fromString($row['slug']),
            new DateTimeImmutable($row['created_at']),
        );
    }

    public function getTagId(): ?ProjectTagId
    {
        return $this->tagId;
    }

    public function getName(): ProjectTagName
    {
        return $this->name;
    }

    public function getSlug(): ProjectTagSlug
    {
        return $this->slug;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'tagId' => $this->tagId?->asInt(),
            'name'  => (string) $this->name,
            'slug'  => (string) $this->slug,
        ];
    }
}
```

- [ ] **Step 5: `ProjectTags.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Tag;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;

class ProjectTags implements IteratorAggregate, JsonSerializable, Countable
{
    private readonly array $tags;

    private function __construct(ProjectTag ...$tags)
    {
        $this->tags = $tags;
    }

    public static function from(ProjectTag ...$tags): self
    {
        return new self(...$tags);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function getIterator(): Generator
    {
        yield from $this->tags;
    }

    public function count(): int
    {
        return count($this->tags);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        return array_map(static fn(ProjectTag $tag) => $tag->toArray(), $this->tags);
    }
}
```

- [ ] **Step 6: Lint**

Run: `just lint`
Erwartet: keine Findings zu den neuen Dateien.

- [ ] **Step 7: Commit**

```bash
git add src/Value/Project/Tag/
git commit -m "$(cat <<'EOF'
feat: add project tag value objects

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Projekt- und Asset-Value-Objects + Exceptions

**Files:**
- Create: `src/Value/Project/ProjectId.php`
- Create: `src/Value/Project/ProjectName.php`
- Create: `src/Value/Project/ProjectSlug.php`
- Create: `src/Value/Project/ProjectStatus.php`
- Create: `src/Value/Project/Asset/ProjectAssetType.php`
- Create: `src/Value/Project/Asset/ProjectAsset.php`
- Create: `src/Value/Project/Asset/ProjectAssets.php`
- Create: `src/Value/Project/Project.php`
- Create: `src/Value/Project/Projects.php`
- Create: `src/Exception/ProjectNotFoundException.php`
- Create: `src/Exception/ProjectSlugAlreadyExistsException.php`
- Create: `src/Exception/ProjectTagNotFoundException.php`
- Create: `src/Exception/ProjectAssetNotFoundException.php`
- Create: `src/Exception/ProjectAssetUploadException.php`

**Interfaces:**
- Consumes: `ProjectTags` aus Task 2.
- Produces: `ProjectId::generate(): self`, `::fromString(string): self`, `->asString(): string`; `ProjectSlug::fromString(string): self`, `::fromName(string): self`; `ProjectStatus` (Backed Enum, `::fromString(string): self`, `->value`); `ProjectAssetType` (Backed Enum: `LOGO`, `COVER`, `SCREENSHOT`, `::fromString(string): self`); `ProjectAsset::create(ProjectId, ProjectAssetType, string $objectKey, ?string $altText, int $sortOrder): self`, `::fromDatabase(array): self`, `->getAssetId(): string`, `->getObjectKey(): string`, `->getType(): ProjectAssetType`, `->toArray(string $baseUrl): array`; `ProjectAssets::from(ProjectAsset ...): self`, `::empty(): self`, `->ofType(ProjectAssetType): ?ProjectAsset`, `->allOfType(ProjectAssetType): array`, `->toArray(string $baseUrl): array`; `Project::create(array $data): self`, `::fromDatabase(array): self`, `->getProjectId(): ProjectId`, `->getSlug(): ProjectSlug`, `->setTags(ProjectTags): void`, `->setAssets(ProjectAssets): void`, `->applyChanges(array $data): void`, `->toArray(string $assetBaseUrl): array`, `->toDatabaseRow(): array`; `Projects::from(Project ...): self`, `->toArray(string): array`.

- [ ] **Step 1: `ProjectId.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project;

use LukaLtaApi\Exception\ApiValidationException;
use Ramsey\Uuid\Uuid;

class ProjectId
{
    private function __construct(
        private readonly string $value,
    ) {
        if (!Uuid::isValid($value)) {
            throw new ApiValidationException('Project ID must be a valid UUID.', 400);
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function asString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 2: `ProjectName.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

class ProjectName
{
    /** Entspricht der Spaltenbreite von projects.name */
    private const int MAX_LENGTH = 100;

    private function __construct(
        private readonly string $value,
    ) {
        if (trim($value) === '') {
            throw new ApiInvalidArgumentException('Project name cannot be empty.', 400);
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new ApiInvalidArgumentException('Project name must not exceed 100 characters.', 400);
        }
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 3: `ProjectSlug.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

class ProjectSlug
{
    /** Entspricht der Spaltenbreite von projects.slug */
    private const int MAX_LENGTH = 100;

    private const string PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private function __construct(
        private readonly string $value,
    ) {
        if (trim($value) === '') {
            throw new ApiInvalidArgumentException('Project slug cannot be empty.', 400);
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new ApiInvalidArgumentException('Project slug must not exceed 100 characters.', 400);
        }

        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new ApiInvalidArgumentException(
                'Project slug may only contain lowercase letters, digits and single hyphens.',
                400,
            );
        }
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public static function fromName(string $name): self
    {
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));

        return new self(trim($slug, '-'));
    }

    public function asString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 4: `ProjectStatus.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

enum ProjectStatus: string
{
    case DEVELOPMENT = 'development';
    case BETA = 'beta';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case ARCHIVED = 'archived';

    public static function fromString(string $value): self
    {
        $status = self::tryFrom($value);

        if ($status === null) {
            throw new ApiInvalidArgumentException(
                sprintf('Invalid project status: %s', $value),
                400,
            );
        }

        return $status;
    }
}
```

- [ ] **Step 5: `ProjectAssetType.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Asset;

use LukaLtaApi\Exception\ApiInvalidArgumentException;

enum ProjectAssetType: string
{
    case LOGO = 'logo';
    case COVER = 'cover';
    case SCREENSHOT = 'screenshot';

    public static function fromString(string $value): self
    {
        $type = self::tryFrom($value);

        if ($type === null) {
            throw new ApiInvalidArgumentException(
                sprintf('Invalid project asset type: %s', $value),
                400,
            );
        }

        return $type;
    }

    /** logo und cover existieren pro Projekt genau einmal und werden beim Upload ersetzt */
    public function isSingleton(): bool
    {
        return $this !== self::SCREENSHOT;
    }
}
```

- [ ] **Step 6: `ProjectAsset.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Asset;

use DateTimeImmutable;
use LukaLtaApi\Value\Project\ProjectId;
use Ramsey\Uuid\Uuid;

class ProjectAsset
{
    private function __construct(
        private readonly string            $assetId,
        private readonly ProjectId         $projectId,
        private readonly ProjectAssetType  $type,
        private readonly string            $objectKey,
        private readonly ?string           $altText,
        private readonly int               $sortOrder,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        ProjectId        $projectId,
        ProjectAssetType $type,
        string           $objectKey,
        ?string          $altText = null,
        int              $sortOrder = 0,
    ): self {
        return new self(
            Uuid::uuid4()->toString(),
            $projectId,
            $type,
            $objectKey,
            $altText,
            $sortOrder,
            new DateTimeImmutable(),
        );
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            $row['asset_id'],
            ProjectId::fromString($row['project_id']),
            ProjectAssetType::fromString($row['type']),
            $row['object_key'],
            $row['alt_text'],
            (int) $row['sort_order'],
            new DateTimeImmutable($row['created_at']),
        );
    }

    public function getAssetId(): string
    {
        return $this->assetId;
    }

    public function getProjectId(): ProjectId
    {
        return $this->projectId;
    }

    public function getType(): ProjectAssetType
    {
        return $this->type;
    }

    public function getObjectKey(): string
    {
        return $this->objectKey;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Die oeffentliche URL zeigt auf die eigene Proxy-Route, nicht auf MinIO —
     * Endpoint und Credentials des Object Storage bleiben damit serverseitig.
     */
    public function toArray(string $assetBaseUrl): array
    {
        return [
            'id'        => $this->assetId,
            'type'      => $this->type->value,
            'url'       => sprintf('%s/%s/assets/%s', $assetBaseUrl, $this->projectId->asString(), $this->assetId),
            'alt'       => $this->altText,
            'sortOrder' => $this->sortOrder,
        ];
    }

    public function toDatabaseRow(): array
    {
        return [
            'asset_id'   => $this->assetId,
            'project_id' => $this->projectId->asString(),
            'type'       => $this->type->value,
            'object_key' => $this->objectKey,
            'alt_text'   => $this->altText,
            'sort_order' => $this->sortOrder,
        ];
    }
}
```

- [ ] **Step 7: `ProjectAssets.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project\Asset;

use Countable;
use Generator;
use IteratorAggregate;

class ProjectAssets implements IteratorAggregate, Countable
{
    private readonly array $assets;

    private function __construct(ProjectAsset ...$assets)
    {
        $this->assets = $assets;
    }

    public static function from(ProjectAsset ...$assets): self
    {
        return new self(...$assets);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function getIterator(): Generator
    {
        yield from $this->assets;
    }

    public function count(): int
    {
        return count($this->assets);
    }

    public function ofType(ProjectAssetType $type): ?ProjectAsset
    {
        foreach ($this->assets as $asset) {
            if ($asset->getType() === $type) {
                return $asset;
            }
        }

        return null;
    }

    /** @return ProjectAsset[] */
    public function allOfType(ProjectAssetType $type): array
    {
        return array_values(
            array_filter($this->assets, static fn(ProjectAsset $asset) => $asset->getType() === $type)
        );
    }

    public function toArray(string $assetBaseUrl): array
    {
        return array_map(static fn(ProjectAsset $asset) => $asset->toArray($assetBaseUrl), $this->assets);
    }
}
```

- [ ] **Step 8: `Project.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project;

use DateTimeImmutable;
use LukaLtaApi\Exception\ApiInvalidArgumentException;
use LukaLtaApi\Value\Project\Asset\ProjectAssets;
use LukaLtaApi\Value\Project\Asset\ProjectAssetType;
use LukaLtaApi\Value\Project\Tag\ProjectTags;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Project
{
    /** Maximale Anzahl Eintraege in tech_stack */
    private const int MAX_TECH_STACK_ITEMS = 20;

    /** Maximale Laenge eines einzelnen tech_stack-Eintrags */
    private const int MAX_TECH_STACK_ITEM_LENGTH = 50;

    /** Nullable URL-Felder, die beim Schreiben identisch validiert werden */
    private const array URL_FIELDS = [
        'websiteUrl'       => 'websiteUrl',
        'repositoryUrl'    => 'repositoryUrl',
        'demoUrl'          => 'demoUrl',
        'documentationUrl' => 'documentationUrl',
    ];

    private ProjectTags $tags;

    private ProjectAssets $assets;

    private function __construct(
        private readonly ProjectId     $projectId,
        private ProjectName            $name,
        private ProjectSlug            $slug,
        private ?string                $shortDescription,
        private ?string                $description,
        private ProjectStatus          $status,
        private bool                   $isVisible,
        private ?string                $category,
        private array                  $techStack,
        private ?string                $websiteUrl,
        private ?string                $liveLabel,
        private ?string                $repositoryUrl,
        private ?string                $repositoryOwner,
        private ?string                $repositoryName,
        private ?string                $demoUrl,
        private ?string                $documentationUrl,
        private ?string                $role,
        private ?int                   $projectYear,
        private bool                   $isClientProject,
        private ?array                 $metadata,
        private int                    $sortOrder,
        private readonly ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable     $updatedAt,
    ) {
        $this->tags   = ProjectTags::empty();
        $this->assets = ProjectAssets::empty();
    }

    public static function create(array $data): self
    {
        $name = ProjectName::fromString((string) $data['name']);
        $slug = isset($data['slug']) && trim((string) $data['slug']) !== ''
            ? ProjectSlug::fromString((string) $data['slug'])
            : ProjectSlug::fromName((string) $data['name']);

        $project = new self(
            ProjectId::generate(),
            $name,
            $slug,
            null,
            null,
            ProjectStatus::DEVELOPMENT,
            true,
            null,
            [],
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            false,
            null,
            0,
            new DateTimeImmutable(),
            null,
        );

        $project->applyChanges($data);

        return $project;
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            ProjectId::fromString($row['project_id']),
            ProjectName::fromString($row['name']),
            ProjectSlug::fromString($row['slug']),
            $row['short_description'],
            $row['description'],
            ProjectStatus::fromString($row['status']),
            (bool) $row['is_visible'],
            $row['category'],
            $row['tech_stack'] !== null ? (array) json_decode($row['tech_stack'], true, 512, JSON_THROW_ON_ERROR) : [],
            $row['website_url'],
            $row['live_label'],
            $row['repository_url'],
            $row['repository_owner'],
            $row['repository_name'],
            $row['demo_url'],
            $row['documentation_url'],
            $row['role'],
            $row['project_year'] !== null ? (int) $row['project_year'] : null,
            (bool) $row['is_client_project'],
            $row['metadata'] !== null ? (array) json_decode($row['metadata'], true, 512, JSON_THROW_ON_ERROR) : null,
            (int) $row['sort_order'],
            new DateTimeImmutable($row['created_at']),
            $row['updated_at'] !== null ? new DateTimeImmutable($row['updated_at']) : null,
        );
    }

    /** Partielles Update: nur uebergebene Keys werden angewendet (PATCH-Semantik). */
    public function applyChanges(array $data): void
    {
        if (isset($data['name'])) {
            $this->name = ProjectName::fromString((string) $data['name']);
        }

        if (isset($data['slug'])) {
            $this->slug = ProjectSlug::fromString((string) $data['slug']);
        }

        if (array_key_exists('shortDescription', $data)) {
            $this->shortDescription = $this->asNullableString($data['shortDescription']);
        }

        if (array_key_exists('description', $data)) {
            $this->description = $this->asNullableString($data['description']);
        }

        if (isset($data['status'])) {
            $this->status = ProjectStatus::fromString((string) $data['status']);
        }

        if (isset($data['isVisible'])) {
            $this->isVisible = (bool) $data['isVisible'];
        }

        if (array_key_exists('category', $data)) {
            $this->category = $this->asNullableString($data['category']);
        }

        if (array_key_exists('techStack', $data)) {
            $this->techStack = $this->parseTechStack($data['techStack']);
        }

        if (array_key_exists('liveLabel', $data)) {
            $this->liveLabel = $this->asNullableString($data['liveLabel']);
        }

        if (array_key_exists('repositoryOwner', $data)) {
            $this->repositoryOwner = $this->asNullableString($data['repositoryOwner']);
        }

        if (array_key_exists('repositoryName', $data)) {
            $this->repositoryName = $this->asNullableString($data['repositoryName']);
        }

        if (array_key_exists('role', $data)) {
            $this->role = $this->asNullableString($data['role']);
        }

        if (array_key_exists('projectYear', $data)) {
            $this->projectYear = $data['projectYear'] !== null ? (int) $data['projectYear'] : null;
        }

        if (isset($data['isClientProject'])) {
            $this->isClientProject = (bool) $data['isClientProject'];
        }

        if (array_key_exists('metadata', $data)) {
            $this->metadata = $data['metadata'] !== null ? (array) $data['metadata'] : null;
        }

        if (isset($data['sortOrder'])) {
            $this->sortOrder = (int) $data['sortOrder'];
        }

        $this->applyUrlChanges($data);
        $this->updatedAt = new DateTimeImmutable();
    }

    private function applyUrlChanges(array $data): void
    {
        foreach (self::URL_FIELDS as $key => $property) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $this->{$property} = $this->parseUrl($data[$key], $key);
        }
    }

    private function parseUrl(mixed $value, string $fieldName): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $url = trim((string) $value);

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ApiInvalidArgumentException(sprintf('Field %s must be a valid URL.', $fieldName), 400);
        }

        return $url;
    }

    private function parseTechStack(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            throw new ApiInvalidArgumentException('Field techStack must be an array of strings.', 400);
        }

        if (count($value) > self::MAX_TECH_STACK_ITEMS) {
            throw new ApiInvalidArgumentException('Field techStack must not exceed 20 entries.', 400);
        }

        $entries = [];
        foreach ($value as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                throw new ApiInvalidArgumentException('Field techStack must be an array of strings.', 400);
            }

            if (mb_strlen($entry) > self::MAX_TECH_STACK_ITEM_LENGTH) {
                throw new ApiInvalidArgumentException('Each techStack entry must not exceed 50 characters.', 400);
            }

            $entries[] = trim($entry);
        }

        return $entries;
    }

    private function asNullableString(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }

    public function getProjectId(): ProjectId
    {
        return $this->projectId;
    }

    public function getSlug(): ProjectSlug
    {
        return $this->slug;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setTags(ProjectTags $tags): void
    {
        $this->tags = $tags;
    }

    public function setAssets(ProjectAssets $assets): void
    {
        $this->assets = $assets;
    }

    public function getAssets(): ProjectAssets
    {
        return $this->assets;
    }

    public function toDatabaseRow(): array
    {
        return [
            'project_id'        => $this->projectId->asString(),
            'name'              => (string) $this->name,
            'slug'              => $this->slug->asString(),
            'short_description' => $this->shortDescription,
            'description'       => $this->description,
            'status'            => $this->status->value,
            'is_visible'        => $this->isVisible ? 1 : 0,
            'category'          => $this->category,
            'tech_stack'        => json_encode($this->techStack, JSON_THROW_ON_ERROR),
            'website_url'       => $this->websiteUrl,
            'live_label'        => $this->liveLabel,
            'repository_url'    => $this->repositoryUrl,
            'repository_owner'  => $this->repositoryOwner,
            'repository_name'   => $this->repositoryName,
            'demo_url'          => $this->demoUrl,
            'documentation_url' => $this->documentationUrl,
            'role'              => $this->role,
            'project_year'      => $this->projectYear,
            'is_client_project' => $this->isClientProject ? 1 : 0,
            'metadata'          => $this->metadata !== null
                ? json_encode($this->metadata, JSON_THROW_ON_ERROR)
                : null,
            'sort_order'        => $this->sortOrder,
        ];
    }

    public function toArray(string $assetBaseUrl): array
    {
        $logo  = $this->assets->ofType(ProjectAssetType::LOGO);
        $cover = $this->assets->ofType(ProjectAssetType::COVER);

        return [
            'id'               => $this->projectId->asString(),
            'name'             => (string) $this->name,
            'slug'             => $this->slug->asString(),
            'shortDescription' => $this->shortDescription,
            'description'      => $this->description,
            'status'           => $this->status->value,
            'isVisible'        => $this->isVisible,
            'category'         => $this->category,
            'tags'             => $this->tags->toArray(),
            'techStack'        => $this->techStack,
            'websiteUrl'       => $this->websiteUrl,
            'liveLabel'        => $this->liveLabel,
            'repositoryUrl'    => $this->repositoryUrl,
            'repositoryOwner'  => $this->repositoryOwner,
            'repositoryName'   => $this->repositoryName,
            'demoUrl'          => $this->demoUrl,
            'documentationUrl' => $this->documentationUrl,
            'role'             => $this->role,
            'year'             => $this->projectYear,
            'isClientProject'  => $this->isClientProject,
            'sortOrder'        => $this->sortOrder,
            'logo'             => $logo?->toArray($assetBaseUrl),
            'cover'            => $cover?->toArray($assetBaseUrl),
            'screenshots'      => array_map(
                static fn($asset) => $asset->toArray($assetBaseUrl),
                $this->assets->allOfType(ProjectAssetType::SCREENSHOT),
            ),
            'createdAt'        => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt'        => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
```

- [ ] **Step 9: `Projects.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project;

use Countable;
use Generator;
use IteratorAggregate;

class Projects implements IteratorAggregate, Countable
{
    private readonly array $projects;

    private function __construct(Project ...$projects)
    {
        $this->projects = $projects;
    }

    public static function from(Project ...$projects): self
    {
        return new self(...$projects);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function getIterator(): Generator
    {
        yield from $this->projects;
    }

    public function count(): int
    {
        return count($this->projects);
    }

    public function toArray(string $assetBaseUrl): array
    {
        return array_map(static fn(Project $project) => $project->toArray($assetBaseUrl), $this->projects);
    }
}
```

- [ ] **Step 10: Exceptions anlegen**

`src/Exception/ProjectNotFoundException.php`:

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class ProjectNotFoundException extends ApiException
{
    public function __construct(string $message = 'Project not found.', ?Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
```

`src/Exception/ProjectSlugAlreadyExistsException.php`:

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class ProjectSlugAlreadyExistsException extends ApiException
{
    public function __construct(string $message = 'Project slug already exists.', ?Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }
}
```

`src/Exception/ProjectTagNotFoundException.php`:

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class ProjectTagNotFoundException extends ApiException
{
    public function __construct(string $message = 'Project tag not found.', ?Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
```

`src/Exception/ProjectAssetNotFoundException.php`:

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class ProjectAssetNotFoundException extends ApiException
{
    public function __construct(string $message = 'Project asset not found.', ?Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
```

`src/Exception/ProjectAssetUploadException.php`:

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Exception;

use Throwable;

class ProjectAssetUploadException extends ApiException
{
    public function __construct(string $message, int $code = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

- [ ] **Step 11: Lint**

Run: `just lint`
Erwartet: keine Findings. Falls phpmd bei `Project.php` trotz der `@SuppressWarnings`-Annotationen Findings meldet, die genaue Regel ergänzen (nicht die Klasse zerschneiden — das Projekt-Entity hat naturgemäß viele Felder).

- [ ] **Step 12: Commit**

```bash
git add src/Value/Project/ src/Exception/Project*.php
git commit -m "$(cat <<'EOF'
feat: add project value objects, asset types and exceptions

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Tag-Repository, -Service, -Actions und Routen

**Files:**
- Create: `src/Repository/ProjectTagRepository.php`
- Create: `src/Api/Project/Service/ProjectTagService.php`
- Create: `src/Api/Project/Action/GetProjectTagsAction.php`
- Create: `src/Api/Project/Action/CreateProjectTagAction.php`
- Modify: `src/Slim/RouteMiddlewareCollector.php`

**Interfaces:**
- Consumes: `ProjectTag`, `ProjectTags`, `ProjectTagId`, `ProjectTagSlug` (Task 2), `ProjectTagNotFoundException` (Task 3), Tabellen aus Task 1.
- Produces: `ProjectTagRepository::getAll(): ProjectTags`, `->getById(ProjectTagId): ?ProjectTag`, `->getBySlug(ProjectTagSlug): ?ProjectTag`, `->create(ProjectTag): ProjectTag`, `->getTagsForProject(ProjectId): ProjectTags`, `->attachTags(ProjectId, array $tagIds): void`, `->detachTags(ProjectId): void`, `->assertTagsExist(array $tagIds): void`; `ProjectTagService::getTags(): ApiResult`, `->createTag(string $name): ApiResult`.

- [ ] **Step 1: `ProjectTagRepository.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Exception\ProjectTagNotFoundException;
use LukaLtaApi\Value\Project\ProjectId;
use LukaLtaApi\Value\Project\Tag\ProjectTag;
use LukaLtaApi\Value\Project\Tag\ProjectTagId;
use LukaLtaApi\Value\Project\Tag\ProjectTagSlug;
use LukaLtaApi\Value\Project\Tag\ProjectTags;
use PDO;
use PDOException;

class ProjectTagRepository
{
    private const string TAG_SELECT = 'SELECT t.tag_id, t.name, t.slug, t.created_at FROM project_tags t';

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getAll(): ProjectTags
    {
        $sql = self::TAG_SELECT . ' ORDER BY t.name ASC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $tags = [];
            foreach ($stmt as $row) {
                $tags[] = ProjectTag::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project tags.', previous: $exception);
        }

        return ProjectTags::from(...$tags);
    }

    public function getById(ProjectTagId $tagId): ?ProjectTag
    {
        $sql = self::TAG_SELECT . ' WHERE t.tag_id = :tag_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['tag_id' => $tagId->asInt()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project tag.', previous: $exception);
        }

        return $row !== false ? ProjectTag::fromDatabase($row) : null;
    }

    public function getBySlug(ProjectTagSlug $slug): ?ProjectTag
    {
        $sql = self::TAG_SELECT . ' WHERE t.slug = :slug';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['slug' => (string) $slug]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project tag by slug.', previous: $exception);
        }

        return $row !== false ? ProjectTag::fromDatabase($row) : null;
    }

    public function create(ProjectTag $tag): ProjectTag
    {
        $sql = <<<SQL
            INSERT INTO project_tags (name, slug)
            VALUES (:name, :slug)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'name' => (string) $tag->getName(),
                'slug' => (string) $tag->getSlug(),
            ]);
            $id = (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create project tag.', previous: $exception);
        }

        return ProjectTag::fromDatabase([
            'tag_id'     => $id,
            'name'       => (string) $tag->getName(),
            'slug'       => (string) $tag->getSlug(),
            'created_at' => $tag->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function getTagsForProject(ProjectId $projectId): ProjectTags
    {
        $sql = <<<SQL
            SELECT t.tag_id, t.name, t.slug, t.created_at
            FROM project_tags t
            INNER JOIN project_tag_assignments pta ON t.tag_id = pta.tag_id
            WHERE pta.project_id = :project_id
            ORDER BY t.name ASC
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['project_id' => $projectId->asString()]);

            $tags = [];
            foreach ($stmt as $row) {
                $tags[] = ProjectTag::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch tags for project.', previous: $exception);
        }

        return ProjectTags::from(...$tags);
    }

    public function attachTags(ProjectId $projectId, array $tagIds): void
    {
        if ($tagIds === []) {
            return;
        }

        $sql = 'INSERT IGNORE INTO project_tag_assignments (project_id, tag_id) VALUES (:project_id, :tag_id)';

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($tagIds as $tagId) {
                $stmt->execute([
                    'project_id' => $projectId->asString(),
                    'tag_id'     => (int) $tagId,
                ]);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to attach project tags.', previous: $exception);
        }
    }

    public function detachTags(ProjectId $projectId): void
    {
        $sql = 'DELETE FROM project_tag_assignments WHERE project_id = :project_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['project_id' => $projectId->asString()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to detach project tags.', previous: $exception);
        }
    }

    /** Verhindert, dass eine unbekannte Tag-ID als stilles INSERT IGNORE verschwindet. */
    public function assertTagsExist(array $tagIds): void
    {
        foreach ($tagIds as $tagId) {
            if ($this->getById(ProjectTagId::fromInt((int) $tagId)) !== null) {
                continue;
            }

            throw new ProjectTagNotFoundException(sprintf('Project tag %d not found.', (int) $tagId));
        }
    }
}
```

- [ ] **Step 2: `ProjectTagService.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\ProjectTagRepository;
use LukaLtaApi\Value\Project\Tag\ProjectTag;
use LukaLtaApi\Value\Project\Tag\ProjectTagSlug;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class ProjectTagService
{
    public function __construct(
        private readonly ProjectTagRepository $repository,
    ) {
    }

    public function getTags(): ApiResult
    {
        $tags = $this->repository->getAll();

        return ApiResult::from(
            JsonResult::from('Project tags fetched.', ['tags' => $tags->toArray()])
        );
    }

    /**
     * Idempotent: ein bereits existierender Tag wird zurueckgegeben statt als
     * Konflikt abgewiesen. Das KiboUI-"Create a Tag"-Feld im Dashboard schickt
     * beim Tippen eines bekannten Namens sonst unnoetig einen Fehler.
     */
    public function createTag(string $name): ApiResult
    {
        $tag      = ProjectTag::create($name);
        $existing = $this->repository->getBySlug(ProjectTagSlug::fromName($name));

        if ($existing !== null) {
            return ApiResult::from(
                JsonResult::from('Project tag already exists.', ['tag' => $existing->toArray()])
            );
        }

        $created = $this->repository->create($tag);

        return ApiResult::from(
            JsonResult::from('Project tag created.', ['tag' => $created->toArray()]),
            StatusCodeInterface::STATUS_CREATED
        );
    }
}
```

- [ ] **Step 3: `GetProjectTagsAction.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectTagService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/projects/tags --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
class GetProjectTagsAction extends ApiAction
{
    public function __construct(
        private readonly ProjectTagService $service,
    ) {
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getTags()->getResponse($response);
    }
}
```

- [ ] **Step 4: `CreateProjectTagAction.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectTagService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/projects/tags --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --header 'Content-Type: application/json' --data '{"name":"Analytics"}'
class CreateProjectTagAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator  $requestValidator,
        private readonly ProjectTagService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'name' => ['required' => true, 'location' => 'body'],
        ]);

        $name = (string) $request->getParsedBody()['name'];

        return $this->service->createTag($name)->getResponse($response);
    }
}
```

- [ ] **Step 5: Routen registrieren**

In `src/Slim/RouteMiddlewareCollector.php` die Imports alphabetisch zu den bestehenden `use`-Statements ergänzen:

```php
use LukaLtaApi\Api\Project\Action\CreateProjectTagAction;
use LukaLtaApi\Api\Project\Action\GetProjectTagsAction;
```

Und im geschützten Bereich (innerhalb der Gruppe, in der auch `/api-keys` registriert wird, direkt davor) ergänzen:

```php
            // Projects — Tag-Dictionary, nur Dashboard
            $app->group('/projects/tags', function (RouteCollectorProxy $tags) {
                $tags->get('', GetProjectTagsAction::class);
                $tags->post('', CreateProjectTagAction::class);
            })->add(AuthMiddleware::class);
```

**Wichtig:** Diese Gruppe muss **vor** der in Task 6 ergänzten Route `GET /projects/{slug}` stehen, sonst schluckt der Wildcard-Platzhalter `/projects/tags`. Das Blog-Precedent (`/blog/tags` vor `/blog/{blogId}`) zeigt dieselbe Reihenfolge-Abhängigkeit.

- [ ] **Step 6: Lint**

Run: `just lint`
Erwartet: keine Findings.

- [ ] **Step 7: Verifizieren per curl**

```bash
# Token holen (s. "Dev-Stack Vorbereitung")
curl -s http://localhost/api/v1/projects/tags "${AUTH[@]}" | jq
```
Erwartet: `{"status":200,"message":"Project tags fetched.","data":{"tags":[]}}`

```bash
curl -s -X POST http://localhost/api/v1/projects/tags "${AUTH[@]}" \
  -H 'Content-Type: application/json' -d '{"name":"Analytics"}' | jq
```
Erwartet: Status 201, `data.tag` mit `tagId`, `name: "Analytics"`, `slug: "analytics"`.

```bash
# Idempotenz: gleicher Name, andere Schreibweise
curl -s -X POST http://localhost/api/v1/projects/tags "${AUTH[@]}" \
  -H 'Content-Type: application/json' -d '{"name":"analytics"}' | jq
```
Erwartet: Status 200, Message `"Project tag already exists."`, **dieselbe** `tagId` wie zuvor — keine zweite Zeile.

```bash
# Ohne Token
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/api/v1/projects/tags
```
Erwartet: `401`.

- [ ] **Step 8: Commit**

```bash
git add src/Repository/ProjectTagRepository.php src/Api/Project/ src/Slim/RouteMiddlewareCollector.php
git commit -m "$(cat <<'EOF'
feat: add reusable project tag dictionary with idempotent creation

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Projekt-Repository und Asset-Repository

**Files:**
- Create: `src/Repository/ProjectRepository.php`
- Create: `src/Repository/ProjectAssetRepository.php`

**Interfaces:**
- Consumes: `Project`, `Projects`, `ProjectId`, `ProjectSlug` (Task 3), Tabellen aus Task 1.
- Produces: `ProjectRepository::getAll(bool $onlyVisible): Projects`, `->getById(ProjectId): ?Project`, `->getBySlug(ProjectSlug, bool $onlyVisible): ?Project`, `->create(Project): Project`, `->update(Project): void`, `->delete(ProjectId): void`, `->updateSortOrder(array $pairs): void`, `->getNextSortOrder(): int`; `ProjectAssetRepository::getByProject(ProjectId): ProjectAssets`, `->getById(string $assetId): ?ProjectAsset`, `->create(ProjectAsset): ProjectAsset`, `->delete(string $assetId): void`, `->getNextSortOrder(ProjectId): int`.

- [ ] **Step 1: `ProjectRepository.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Project\Project;
use LukaLtaApi\Value\Project\ProjectId;
use LukaLtaApi\Value\Project\Projects;
use LukaLtaApi\Value\Project\ProjectSlug;
use PDO;
use PDOException;

class ProjectRepository
{
    private const string PROJECT_SELECT = <<<SQL
        SELECT
            p.project_id,
            p.name,
            p.slug,
            p.short_description,
            p.description,
            p.status,
            p.is_visible,
            p.category,
            p.tech_stack,
            p.website_url,
            p.live_label,
            p.repository_url,
            p.repository_owner,
            p.repository_name,
            p.demo_url,
            p.documentation_url,
            p.role,
            p.project_year,
            p.is_client_project,
            p.metadata,
            p.sort_order,
            p.created_at,
            p.updated_at
        FROM projects p
    SQL;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getAll(bool $onlyVisible): Projects
    {
        $sql = self::PROJECT_SELECT;

        if ($onlyVisible) {
            $sql .= ' WHERE p.is_visible = 1';
        }

        $sql .= ' ORDER BY p.sort_order ASC, p.created_at ASC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $projects = [];
            foreach ($stmt as $row) {
                $projects[] = Project::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch projects.', previous: $exception);
        }

        return Projects::from(...$projects);
    }

    public function getById(ProjectId $projectId): ?Project
    {
        $sql = self::PROJECT_SELECT . ' WHERE p.project_id = :project_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['project_id' => $projectId->asString()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project.', previous: $exception);
        }

        return $row !== false ? Project::fromDatabase($row) : null;
    }

    public function getBySlug(ProjectSlug $slug, bool $onlyVisible): ?Project
    {
        $sql = self::PROJECT_SELECT . ' WHERE p.slug = :slug';

        if ($onlyVisible) {
            $sql .= ' AND p.is_visible = 1';
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['slug' => $slug->asString()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project by slug.', previous: $exception);
        }

        return $row !== false ? Project::fromDatabase($row) : null;
    }

    public function create(Project $project): Project
    {
        $sql = <<<SQL
            INSERT INTO projects (
                project_id, name, slug, short_description, description, status, is_visible,
                category, tech_stack, website_url, live_label, repository_url, repository_owner,
                repository_name, demo_url, documentation_url, role, project_year,
                is_client_project, metadata, sort_order
            ) VALUES (
                :project_id, :name, :slug, :short_description, :description, :status, :is_visible,
                :category, :tech_stack, :website_url, :live_label, :repository_url, :repository_owner,
                :repository_name, :demo_url, :documentation_url, :role, :project_year,
                :is_client_project, :metadata, :sort_order
            )
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($project->toDatabaseRow());
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create project.', previous: $exception);
        }

        return $project;
    }

    public function update(Project $project): void
    {
        $sql = <<<SQL
            UPDATE projects SET
                name              = :name,
                slug              = :slug,
                short_description = :short_description,
                description       = :description,
                status            = :status,
                is_visible        = :is_visible,
                category          = :category,
                tech_stack        = :tech_stack,
                website_url       = :website_url,
                live_label        = :live_label,
                repository_url    = :repository_url,
                repository_owner  = :repository_owner,
                repository_name   = :repository_name,
                demo_url          = :demo_url,
                documentation_url = :documentation_url,
                role              = :role,
                project_year      = :project_year,
                is_client_project = :is_client_project,
                metadata          = :metadata,
                sort_order        = :sort_order
            WHERE project_id = :project_id
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($project->toDatabaseRow());
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to update project.', previous: $exception);
        }
    }

    public function delete(ProjectId $projectId): void
    {
        $sql = 'DELETE FROM projects WHERE project_id = :project_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['project_id' => $projectId->asString()]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to delete project.', previous: $exception);
        }
    }

    /** @param array<int, array{projectId: string, sortOrder: int}> $pairs */
    public function updateSortOrder(array $pairs): void
    {
        $sql = 'UPDATE projects SET sort_order = :sort_order WHERE project_id = :project_id';

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            foreach ($pairs as $pair) {
                $stmt->execute([
                    'sort_order' => $pair['sortOrder'],
                    'project_id' => $pair['projectId'],
                ]);
            }
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new ApiDatabaseException('Failed to update project sort order.', previous: $exception);
        }
    }

    public function getNextSortOrder(): int
    {
        $sql = 'SELECT COALESCE(MAX(p.sort_order), -1) + 1 AS next_order FROM projects p';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to determine next sort order.', previous: $exception);
        }

        return (int) $row['next_order'];
    }
}
```

- [ ] **Step 2: `ProjectAssetRepository.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Project\Asset\ProjectAsset;
use LukaLtaApi\Value\Project\Asset\ProjectAssets;
use LukaLtaApi\Value\Project\ProjectId;
use PDO;
use PDOException;

class ProjectAssetRepository
{
    private const string ASSET_SELECT = <<<SQL
        SELECT
            a.asset_id,
            a.project_id,
            a.type,
            a.object_key,
            a.alt_text,
            a.sort_order,
            a.created_at
        FROM project_assets a
    SQL;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getByProject(ProjectId $projectId): ProjectAssets
    {
        $sql = self::ASSET_SELECT . ' WHERE a.project_id = :project_id ORDER BY a.type ASC, a.sort_order ASC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['project_id' => $projectId->asString()]);

            $assets = [];
            foreach ($stmt as $row) {
                $assets[] = ProjectAsset::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project assets.', previous: $exception);
        }

        return ProjectAssets::from(...$assets);
    }

    public function getById(string $assetId): ?ProjectAsset
    {
        $sql = self::ASSET_SELECT . ' WHERE a.asset_id = :asset_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['asset_id' => $assetId]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to fetch project asset.', previous: $exception);
        }

        return $row !== false ? ProjectAsset::fromDatabase($row) : null;
    }

    public function create(ProjectAsset $asset): ProjectAsset
    {
        $sql = <<<SQL
            INSERT INTO project_assets (asset_id, project_id, type, object_key, alt_text, sort_order)
            VALUES (:asset_id, :project_id, :type, :object_key, :alt_text, :sort_order)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($asset->toDatabaseRow());
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to create project asset.', previous: $exception);
        }

        return $asset;
    }

    public function delete(string $assetId): void
    {
        $sql = 'DELETE FROM project_assets WHERE asset_id = :asset_id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['asset_id' => $assetId]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to delete project asset.', previous: $exception);
        }
    }

    public function getNextSortOrder(ProjectId $projectId): int
    {
        $sql = <<<SQL
            SELECT COALESCE(MAX(a.sort_order), -1) + 1 AS next_order
            FROM project_assets a
            WHERE a.project_id = :project_id AND a.type = 'screenshot'
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['project_id' => $projectId->asString()]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException('Failed to determine next asset sort order.', previous: $exception);
        }

        return (int) $row['next_order'];
    }
}
```

- [ ] **Step 3: Lint**

Run: `just lint`
Erwartet: keine Findings.

- [ ] **Step 4: Commit**

```bash
git add src/Repository/ProjectRepository.php src/Repository/ProjectAssetRepository.php
git commit -m "$(cat <<'EOF'
feat: add project and project asset repositories

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Projekt-Service, CRUD-Actions und Routen

**Files:**
- Create: `src/Api/Project/Service/ProjectService.php`
- Create: `src/Api/Project/Action/GetAllProjectsAction.php`
- Create: `src/Api/Project/Action/GetProjectAction.php`
- Create: `src/Api/Project/Action/GetManagedProjectsAction.php`
- Create: `src/Api/Project/Action/GetManagedProjectAction.php`
- Create: `src/Api/Project/Action/CreateProjectAction.php`
- Create: `src/Api/Project/Action/UpdateProjectAction.php`
- Create: `src/Api/Project/Action/DeleteProjectAction.php`
- Create: `src/Api/Project/Action/ReorderProjectsAction.php`
- Modify: `src/Slim/RouteMiddlewareCollector.php`

**Interfaces:**
- Consumes: `ProjectRepository`, `ProjectAssetRepository` (Task 5), `ProjectTagRepository` (Task 4), `Project`/`Projects`/`ProjectId`/`ProjectSlug` (Task 3). **`ProjectAssetService` existiert noch nicht** — die Projekt-Löschung räumt MinIO-Objekte in diesem Task noch nicht auf; das wird in Task 8 nachgezogen (dort ist ein expliziter Schritt dafür).
- Produces: `ProjectService::getAllProjects(bool $onlyVisible): ApiResult`, `->getProjectBySlug(ProjectSlug): ApiResult`, `->getProjectById(ProjectId): ApiResult`, `->createProject(array $data): ApiResult`, `->updateProject(ProjectId, array $data): ApiResult`, `->deleteProject(ProjectId): ApiResult`, `->reorderProjects(array $items): ApiResult`, `->loadProjectOrFail(ProjectId): Project`, `->getAssetBaseUrl(): string`.

- [ ] **Step 1: `ProjectService.php`**

**Verifiziert:** Es gibt **keine** Env-Variable für die öffentliche API-URL. `User::getAvatarUrl()` hat `https://api.luka-lta.dev/api/v1/avatar/` **hart im Code** (`src/Value/User/User.php:79`) — in Dev zeigen Avatar-URLs damit auf Produktion. Dieser Fehler wird für Projekt-Assets **nicht** kopiert, aber auch nicht im Avatar-Code mitgefixt (anderes Subsystem, nicht im Auftrag).

Lösung: `API_BASE_URL` mit Produktions-Default. `EnvironmentRepository::get()` unterstützt einen zweiten Parameter als Default, d. h. Produktion funktioniert ohne Deploy-Änderung weiter und Dev wird korrekt, sobald die Variable gesetzt ist:

```php
    /** Fallback entspricht der Produktions-URL, damit ein fehlendes Env dort nichts bricht. */
    private const string DEFAULT_API_BASE_URL = 'https://api.luka-lta.dev/api/v1';
```

und im Service:

```php
    public function getAssetBaseUrl(): string
    {
        $baseUrl = $this->environmentRepository->get('API_BASE_URL', self::DEFAULT_API_BASE_URL);

        return rtrim((string) $baseUrl, '/') . '/projects';
    }
```

Zusätzlich in `docker-compose.development.yml` beim Service `php-fpm-api` zu den übrigen `environment:`-Einträgen ergänzen:

```yaml
      API_BASE_URL: 'http://localhost/api/v1'
```

Danach `docker compose -f docker-compose.development.yml up -d php-fpm-api`, damit der laufende Container die Variable sieht — ohne Neustart liefert er weiter den Produktions-Default.

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiInvalidArgumentException;
use LukaLtaApi\Exception\ProjectNotFoundException;
use LukaLtaApi\Exception\ProjectSlugAlreadyExistsException;
use LukaLtaApi\Repository\EnvironmentRepository;
use LukaLtaApi\Repository\ProjectAssetRepository;
use LukaLtaApi\Repository\ProjectRepository;
use LukaLtaApi\Repository\ProjectTagRepository;
use LukaLtaApi\Value\Project\Project;
use LukaLtaApi\Value\Project\ProjectId;
use LukaLtaApi\Value\Project\ProjectSlug;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;

class ProjectService
{
    /** Maximale Anzahl Tags pro Projekt */
    private const int MAX_TAGS_PER_PROJECT = 20;

    /** Fallback entspricht der Produktions-URL, damit ein fehlendes Env dort nichts bricht. */
    private const string DEFAULT_API_BASE_URL = 'https://api.luka-lta.dev/api/v1';

    public function __construct(
        private readonly ProjectRepository      $repository,
        private readonly ProjectAssetRepository $assetRepository,
        private readonly ProjectTagRepository   $tagRepository,
        private readonly EnvironmentRepository  $environmentRepository,
    ) {
    }

    public function getAssetBaseUrl(): string
    {
        $baseUrl = $this->environmentRepository->get('API_BASE_URL', self::DEFAULT_API_BASE_URL);

        return rtrim((string) $baseUrl, '/') . '/projects';
    }

    public function getAllProjects(bool $onlyVisible): ApiResult
    {
        $projects = $this->repository->getAll($onlyVisible);

        foreach ($projects as $project) {
            $this->hydrate($project);
        }

        return ApiResult::from(
            JsonResult::from('Projects fetched.', ['projects' => $projects->toArray($this->getAssetBaseUrl())])
        );
    }

    public function getProjectBySlug(ProjectSlug $slug): ApiResult
    {
        $project = $this->repository->getBySlug($slug, true);

        if ($project === null) {
            throw new ProjectNotFoundException();
        }

        $this->hydrate($project);

        return ApiResult::from(
            JsonResult::from('Project fetched.', ['project' => $project->toArray($this->getAssetBaseUrl())])
        );
    }

    public function getProjectById(ProjectId $projectId): ApiResult
    {
        $project = $this->loadProjectOrFail($projectId);
        $this->hydrate($project);

        return ApiResult::from(
            JsonResult::from('Project fetched.', ['project' => $project->toArray($this->getAssetBaseUrl())])
        );
    }

    public function createProject(array $data): ApiResult
    {
        $project = Project::create($data);

        $this->assertSlugIsFree($project->getSlug(), null);

        if (!isset($data['sortOrder'])) {
            $project->applyChanges(['sortOrder' => $this->repository->getNextSortOrder()]);
        }

        $created = $this->repository->create($project);
        $this->syncTags($created->getProjectId(), $data);
        $this->hydrate($created);

        return ApiResult::from(
            JsonResult::from('Project created.', ['project' => $created->toArray($this->getAssetBaseUrl())]),
            StatusCodeInterface::STATUS_CREATED
        );
    }

    public function updateProject(ProjectId $projectId, array $data): ApiResult
    {
        $project = $this->loadProjectOrFail($projectId);
        $project->applyChanges($data);

        $this->assertSlugIsFree($project->getSlug(), $projectId);

        $this->repository->update($project);
        $this->syncTags($projectId, $data);
        $this->hydrate($project);

        return ApiResult::from(
            JsonResult::from('Project updated.', ['project' => $project->toArray($this->getAssetBaseUrl())])
        );
    }

    public function deleteProject(ProjectId $projectId): ApiResult
    {
        $this->loadProjectOrFail($projectId);

        $this->repository->delete($projectId);

        return ApiResult::from(
            JsonResult::from('Project deleted.'),
            StatusCodeInterface::STATUS_NO_CONTENT
        );
    }

    public function reorderProjects(array $items): ApiResult
    {
        if ($items === []) {
            throw new ApiInvalidArgumentException('Field projects must contain at least one entry.', 400);
        }

        $pairs = [];
        foreach ($items as $item) {
            if (!isset($item['projectId'], $item['sortOrder'])) {
                throw new ApiInvalidArgumentException('Each entry requires projectId and sortOrder.', 400);
            }

            $projectId = ProjectId::fromString((string) $item['projectId']);
            $this->loadProjectOrFail($projectId);

            $pairs[] = [
                'projectId' => $projectId->asString(),
                'sortOrder' => (int) $item['sortOrder'],
            ];
        }

        $this->repository->updateSortOrder($pairs);

        return ApiResult::from(JsonResult::from('Project order updated.'));
    }

    public function loadProjectOrFail(ProjectId $projectId): Project
    {
        $project = $this->repository->getById($projectId);

        if ($project === null) {
            throw new ProjectNotFoundException();
        }

        return $project;
    }

    /** Laedt Tags und Assets nach — beide liegen in eigenen Tabellen. */
    private function hydrate(Project $project): void
    {
        $project->setTags($this->tagRepository->getTagsForProject($project->getProjectId()));
        $project->setAssets($this->assetRepository->getByProject($project->getProjectId()));
    }

    private function assertSlugIsFree(ProjectSlug $slug, ?ProjectId $ignoredProjectId): void
    {
        $existing = $this->repository->getBySlug($slug, false);

        if ($existing === null) {
            return;
        }

        if ($ignoredProjectId !== null
            && $existing->getProjectId()->asString() === $ignoredProjectId->asString()) {
            return;
        }

        throw new ProjectSlugAlreadyExistsException();
    }

    private function syncTags(ProjectId $projectId, array $data): void
    {
        if (!array_key_exists('tagIds', $data)) {
            return;
        }

        $tagIds = $data['tagIds'] ?? [];

        if (!is_array($tagIds)) {
            throw new ApiInvalidArgumentException('Field tagIds must be an array of integers.', 400);
        }

        if (count($tagIds) > self::MAX_TAGS_PER_PROJECT) {
            throw new ApiInvalidArgumentException('A project must not have more than 20 tags.', 400);
        }

        $this->tagRepository->assertTagsExist($tagIds);
        $this->tagRepository->detachTags($projectId);
        $this->tagRepository->attachTags($projectId, $tagIds);
    }
}
```

- [ ] **Step 2: Öffentliche Read-Actions**

`src/Api/Project/Action/GetAllProjectsAction.php`:

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/projects
class GetAllProjectsAction extends ApiAction
{
    public function __construct(
        private readonly ProjectService $service,
    ) {
    }

    /**
     * Oeffentliche Route: filtert immer hart auf sichtbare Projekte. Der
     * Auth-Status wird hier bewusst NICHT ausgewertet — das Dashboard nutzt
     * die geschuetzte /projects/manage-Route.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getAllProjects(true)->getResponse($response);
    }
}
```

`src/Api/Project/Action/GetProjectAction.php`:

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use LukaLtaApi\Value\Project\ProjectSlug;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/projects/luka-lta-api
class GetProjectAction extends ApiAction
{
    public function __construct(
        private readonly ProjectService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $slug = ProjectSlug::fromString((string) $request->getAttribute('slug'));

        return $this->service->getProjectBySlug($slug)->getResponse($response);
    }
}
```

- [ ] **Step 3: Geschützte Read-Actions**

`src/Api/Project/Action/GetManagedProjectsAction.php`:

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/projects/manage --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
class GetManagedProjectsAction extends ApiAction
{
    public function __construct(
        private readonly ProjectService $service,
    ) {
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getAllProjects(false)->getResponse($response);
    }
}
```

`src/Api/Project/Action/GetManagedProjectAction.php`:

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use LukaLtaApi\Value\Project\ProjectId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/projects/manage/<uuid> --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
class GetManagedProjectAction extends ApiAction
{
    public function __construct(
        private readonly ProjectService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $projectId = ProjectId::fromString((string) $request->getAttribute('projectId'));

        return $this->service->getProjectById($projectId)->getResponse($response);
    }
}
```

- [ ] **Step 4: Write-Actions**

`src/Api/Project/Action/CreateProjectAction.php`:

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/projects --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --header 'Content-Type: application/json' --data '{"name":"My New App"}'
class CreateProjectAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly ProjectService   $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'name' => ['required' => true, 'location' => 'body'],
        ]);

        return $this->service->createProject((array) $request->getParsedBody())->getResponse($response);
    }
}
```

`src/Api/Project/Action/UpdateProjectAction.php`:

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use LukaLtaApi\Value\Project\ProjectId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X PATCH http://localhost/api/v1/projects/<uuid> --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --header 'Content-Type: application/json' --data '{"status":"active"}'
class UpdateProjectAction extends ApiAction
{
    public function __construct(
        private readonly ProjectService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $projectId = ProjectId::fromString((string) $request->getAttribute('projectId'));

        return $this->service
            ->updateProject($projectId, (array) $request->getParsedBody())
            ->getResponse($response);
    }
}
```

`src/Api/Project/Action/DeleteProjectAction.php`:

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use LukaLtaApi\Value\Project\ProjectId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X DELETE http://localhost/api/v1/projects/<uuid> --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
class DeleteProjectAction extends ApiAction
{
    public function __construct(
        private readonly ProjectService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $projectId = ProjectId::fromString((string) $request->getAttribute('projectId'));

        return $this->service->deleteProject($projectId)->getResponse($response);
    }
}
```

`src/Api/Project/Action/ReorderProjectsAction.php`:

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X PATCH http://localhost/api/v1/projects/order --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --header 'Content-Type: application/json'
//        --data '{"projects":[{"projectId":"<uuid>","sortOrder":0}]}'
class ReorderProjectsAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly ProjectService   $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'projects' => ['required' => true, 'location' => 'body'],
        ]);

        $items = (array) $request->getParsedBody()['projects'];

        return $this->service->reorderProjects($items)->getResponse($response);
    }
}
```

- [ ] **Step 5: Routen registrieren**

Imports in `src/Slim/RouteMiddlewareCollector.php` ergänzen:

```php
use LukaLtaApi\Api\Project\Action\CreateProjectAction;
use LukaLtaApi\Api\Project\Action\DeleteProjectAction;
use LukaLtaApi\Api\Project\Action\GetAllProjectsAction;
use LukaLtaApi\Api\Project\Action\GetManagedProjectAction;
use LukaLtaApi\Api\Project\Action\GetManagedProjectsAction;
use LukaLtaApi\Api\Project\Action\GetProjectAction;
use LukaLtaApi\Api\Project\Action\ReorderProjectsAction;
use LukaLtaApi\Api\Project\Action\UpdateProjectAction;
```

Direkt **nach** der in Task 4 angelegten `/projects/tags`-Gruppe ergänzen:

```php
            // Projects — geschuetzte Verwaltung (Dashboard)
            $app->group('/projects', function (RouteCollectorProxy $projects) {
                $projects->get('/manage', GetManagedProjectsAction::class);
                $projects->get('/manage/{projectId}', GetManagedProjectAction::class);
                $projects->post('', CreateProjectAction::class);
                $projects->patch('/order', ReorderProjectsAction::class);
                $projects->patch('/{projectId}', UpdateProjectAction::class);
                $projects->delete('/{projectId}', DeleteProjectAction::class);
            })->add(AuthMiddleware::class);
```

Die **öffentlichen** Routen kommen **unmittelbar nach** der eben ergänzten geschützten `/projects`-Gruppe — also ebenfalls innerhalb derselben Closure, auf derselben Einrückungsebene (12 Spaces), **nicht** oben bei den Blog-Routen:

```php
            // Projects — public read routes
            $app->get('/projects', GetAllProjectsAction::class);
            $app->get('/projects/{slug}', GetProjectAction::class);
```

**Reihenfolge-Kontrolle (korrigiert — vorherige Fassung dieses Plans war hier falsch):** Die gesamte Routen-Registrierung liegt in **einer** Closure und Slim matcht in Registrierungsreihenfolge, erster Treffer gewinnt. Ein früherer Entwurf sagte, die öffentlichen Routen gehörten zu den Blog-Routen (dort, wo `$app->get('/blog', ...)` steht). Das ist **falsch**: diese Zeilen stehen deutlich **vor** der in Task 4 angelegten `/projects/tags`-Gruppe, womit `{slug}` sowohl `/projects/tags` als auch `/projects/manage` verschlucken würde.

Verbindliche Abfolge:

1. `/projects/tags`-Gruppe (Task 4, steht bereits)
2. geschützte `/projects`-Gruppe (dieser Task, mit `/manage` und `/order`)
3. öffentliche `/projects/{projectId}/assets/{assetId}` (Task 8)
4. öffentliche `/projects` und `/projects/{slug}` (dieser Task) — **zuletzt**

Lege die öffentlichen Routen also hinter die geschützte Gruppe. Task 8 schiebt seine öffentliche Asset-Route später **zwischen** 2 und 4 ein. Step 7 prüft per curl explizit, dass `GET /projects/manage` nicht als Slug aufgelöst wird.

- [ ] **Step 6: Lint**

Run: `just lint`
Erwartet: keine Findings.

- [ ] **Step 7: Verifizieren per curl**

```bash
# Anlegen
PROJECT=$(curl -s -X POST http://localhost/api/v1/projects "${AUTH[@]}" \
  -H 'Content-Type: application/json' \
  -d '{"name":"Test Project","shortDescription":"Kurz","status":"active","websiteUrl":"https://example.tld","techStack":["PHP","MySQL"],"tagIds":[1]}')
echo "$PROJECT" | jq
PROJECT_ID=$(echo "$PROJECT" | jq -r '.data.project.id')
```
Erwartet: Status 201, `slug: "test-project"`, `sortOrder: 0`, `tags` enthält den in Task 4 erzeugten Tag, `techStack: ["PHP","MySQL"]`.

```bash
# Review Focus 1: doppelter Slug -> 409, nicht 500
curl -s -X POST http://localhost/api/v1/projects "${AUTH[@]}" \
  -H 'Content-Type: application/json' -d '{"name":"Test Project"}' | jq '.status'
```
Erwartet: `409`.

```bash
# Ungueltige URL -> 400
curl -s -X POST http://localhost/api/v1/projects "${AUTH[@]}" \
  -H 'Content-Type: application/json' -d '{"name":"Bad Url","websiteUrl":"not-a-url"}' | jq '.status'
```
Erwartet: `400`.

```bash
# Unbekannte tagId -> 404
curl -s -X POST http://localhost/api/v1/projects "${AUTH[@]}" \
  -H 'Content-Type: application/json' -d '{"name":"Bad Tag","tagIds":[99999]}' | jq '.status'
```
Erwartet: `404`.

```bash
# Routen-Reihenfolge: /projects/manage darf NICHT als Slug interpretiert werden
curl -s http://localhost/api/v1/projects/manage "${AUTH[@]}" | jq '.message'
```
Erwartet: `"Projects fetched."` (nicht `"Project not found."`).

```bash
# Review Focus 2: unsichtbares Projekt nicht oeffentlich auffindbar
curl -s -X PATCH "http://localhost/api/v1/projects/$PROJECT_ID" "${AUTH[@]}" \
  -H 'Content-Type: application/json' -d '{"isVisible":false}' | jq '.data.project.isVisible'
curl -s http://localhost/api/v1/projects | jq '.data.projects | length'
curl -s http://localhost/api/v1/projects/test-project | jq '.status'
curl -s http://localhost/api/v1/projects/test-project -H 'Authorization: Bearer garbage' | jq '.status'
curl -s http://localhost/api/v1/projects/manage "${AUTH[@]}" | jq '.data.projects | length'
```
Erwartet: `false`, dann `0`, dann `404`, dann **ebenfalls `404`** (gefälschter Header hilft nicht), dann `1`.

```bash
# Sortierung
curl -s -X PATCH http://localhost/api/v1/projects/order "${AUTH[@]}" \
  -H 'Content-Type: application/json' \
  -d "{\"projects\":[{\"projectId\":\"$PROJECT_ID\",\"sortOrder\":5}]}" | jq '.message'
curl -s "http://localhost/api/v1/projects/manage/$PROJECT_ID" "${AUTH[@]}" \
  | jq '.data.project.sortOrder'
```
Erwartet: `"Project order updated."`, dann `5`.

```bash
# Review Focus 4 (Teil 1): Loeschen laesst Tags im Dictionary stehen
curl -s -o /dev/null -w '%{http_code}\n' -X DELETE "http://localhost/api/v1/projects/$PROJECT_ID" \
  "${AUTH[@]}"
curl -s http://localhost/api/v1/projects/tags "${AUTH[@]}" | jq '.data.tags | length'
```
Erwartet: `204`, dann `1` (Tag "Analytics" lebt weiter).

```bash
# Ohne Token
curl -s -o /dev/null -w '%{http_code}\n' -X POST http://localhost/api/v1/projects \
  -H 'Content-Type: application/json' -d '{"name":"Nope"}'
```
Erwartet: `401`.

- [ ] **Step 8: Commit**

```bash
git add src/Api/Project/ src/Slim/RouteMiddlewareCollector.php
git commit -m "$(cat <<'EOF'
feat: add project CRUD service, actions and routes

Public read routes filter on is_visible server-side and ignore the
Authorization header entirely; management routes sit behind AuthMiddleware.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: MinIO-Erweiterung und Asset-Service

**Files:**
- Modify: `src/Repository/S3Repository.php`
- Create: `src/Api/Project/Service/ProjectAssetService.php`

**Interfaces:**
- Consumes: `ProjectAsset`, `ProjectAssetType`, `ProjectAssets` (Task 3), `ProjectAssetRepository` (Task 5), `ProjectId` (Task 3).
- Produces: `S3Repository::uploadProjectAsset(UploadedFileInterface, string $objectKey): void`, `->deleteObject(string $objectKey): void`, `->getObject(string $objectKey): ?array` (`['body' => string, 'contentType' => string]`); `ProjectAssetService::upload(ProjectId, ProjectAssetType, UploadedFile, ?string $altText): ProjectAsset`, `->delete(string $assetId): void`, `->deleteAllForProject(ProjectId): void`, `->loadAssetOrFail(string $assetId): ProjectAsset`, `->getObjectForAsset(ProjectAsset): ?array`.

- [ ] **Step 1: `S3Repository` erweitern**

Die drei Methoden ans Ende der Klasse anfügen; bestehende Methoden nicht verändern:

```php
    public function uploadProjectAsset(UploadedFileInterface $uploadedFile, string $objectKey): void
    {
        try {
            $this->s3Client->putObject([
                'Bucket' => $this->awsBucket,
                'Key' => $objectKey,
                'Body' => $uploadedFile->getStream()->getContents(),
                'ContentType' => $uploadedFile->getClientMediaType(),
                'ACL' => 'public-read',
            ]);
        } catch (AwsException $exception) {
            throw new ApiDatabaseException(
                'AWS S3 upload error: ' . $exception->getMessage(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }

    public function deleteObject(string $objectKey): void
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->awsBucket,
                'Key' => $objectKey,
            ]);
        } catch (AwsException $exception) {
            throw new ApiDatabaseException(
                'AWS S3 delete error: ' . $exception->getMessage(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }

    public function getObject(string $objectKey): ?array
    {
        try {
            if (!$this->s3Client->doesObjectExist($this->awsBucket, $objectKey)) {
                return null;
            }

            $result = $this->s3Client->getObject([
                'Bucket' => $this->awsBucket,
                'Key' => $objectKey,
            ]);
        } catch (S3Exception $exception) {
            throw new ApiDatabaseException(
                'AWS S3 retrieval error: ' . $exception->getMessage(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }

        return [
            'body' => (string) $result->get('Body'),
            'contentType' => (string) $result->get('ContentType'),
        ];
    }
```

- [ ] **Step 2: `ProjectAssetService.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ProjectAssetNotFoundException;
use LukaLtaApi\Exception\ProjectAssetUploadException;
use LukaLtaApi\Repository\ProjectAssetRepository;
use LukaLtaApi\Repository\S3Repository;
use LukaLtaApi\Value\Project\Asset\ProjectAsset;
use LukaLtaApi\Value\Project\Asset\ProjectAssetType;
use LukaLtaApi\Value\Project\ProjectId;
use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Uuid;

class ProjectAssetService
{
    /** 5 MiB in bytes */
    private const int MAX_FILE_SIZE = 5 * 1024 * 1024;

    /** Erlaubte Bild-Formate. Bewusst inkl. webp, anders als der Avatar-Upload. */
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly ProjectAssetRepository $repository,
        private readonly S3Repository           $s3Repository,
    ) {
    }

    public function upload(
        ProjectId             $projectId,
        ProjectAssetType      $type,
        UploadedFileInterface $uploadedFile,
        ?string               $altText,
    ): ProjectAsset {
        $extension = $this->validate($uploadedFile);

        // logo/cover existieren pro Projekt nur einmal: altes Asset inkl.
        // MinIO-Objekt vor dem Anlegen des neuen entfernen.
        if ($type->isSingleton()) {
            $existing = $this->repository->getByProject($projectId)->ofType($type);

            if ($existing !== null) {
                $this->deleteAsset($existing);
            }
        }

        $sortOrder = $type === ProjectAssetType::SCREENSHOT
            ? $this->repository->getNextSortOrder($projectId)
            : 0;

        $objectKey = sprintf(
            'projects/%s/%s/%s.%s',
            $projectId->asString(),
            $type->value,
            Uuid::uuid4()->toString(),
            $extension,
        );

        $this->s3Repository->uploadProjectAsset($uploadedFile, $objectKey);

        $asset = ProjectAsset::create($projectId, $type, $objectKey, $altText, $sortOrder);

        return $this->repository->create($asset);
    }

    public function delete(string $assetId): void
    {
        $this->deleteAsset($this->loadAssetOrFail($assetId));
    }

    /**
     * Die DB-CASCADE auf project_assets raeumt nur Zeilen auf — die
     * MinIO-Objekte muss die Anwendung selbst entfernen, deshalb vor dem
     * Loeschen des Projekts aufrufen.
     */
    public function deleteAllForProject(ProjectId $projectId): void
    {
        foreach ($this->repository->getByProject($projectId) as $asset) {
            $this->deleteAsset($asset);
        }
    }

    public function loadAssetOrFail(string $assetId): ProjectAsset
    {
        $asset = $this->repository->getById($assetId);

        if ($asset === null) {
            throw new ProjectAssetNotFoundException();
        }

        return $asset;
    }

    public function getObjectForAsset(ProjectAsset $asset): ?array
    {
        return $this->s3Repository->getObject($asset->getObjectKey());
    }

    private function deleteAsset(ProjectAsset $asset): void
    {
        $this->s3Repository->deleteObject($asset->getObjectKey());
        $this->repository->delete($asset->getAssetId());
    }

    private function validate(UploadedFileInterface $uploadedFile): string
    {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new ProjectAssetUploadException(
                'File upload failed with error code ' . $uploadedFile->getError(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
            );
        }

        $mimeType = (string) $uploadedFile->getClientMediaType();

        if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new ProjectAssetUploadException(
                'Invalid file type. Only JPG, PNG and WebP are allowed.',
                StatusCodeInterface::STATUS_BAD_REQUEST,
            );
        }

        if ($uploadedFile->getSize() > self::MAX_FILE_SIZE) {
            throw new ProjectAssetUploadException(
                'File size exceeds the maximum limit of 5MB.',
                StatusCodeInterface::STATUS_BAD_REQUEST,
            );
        }

        return self::ALLOWED_MIME_TYPES[$mimeType];
    }
}
```

- [ ] **Step 3: Lint**

Run: `just lint`
Erwartet: keine Findings.

- [ ] **Step 4: Commit**

```bash
git add src/Repository/S3Repository.php src/Api/Project/Service/ProjectAssetService.php
git commit -m "$(cat <<'EOF'
feat: add MinIO object delete/get and project asset lifecycle service

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 8: Asset-Actions, Routen und Aufräumen beim Projekt-Löschen

**Files:**
- Create: `src/Api/Project/Action/UploadProjectAssetAction.php`
- Create: `src/Api/Project/Action/DeleteProjectAssetAction.php`
- Create: `src/Api/Project/Action/GetProjectAssetAction.php`
- Modify: `src/Api/Project/Service/ProjectService.php` (Asset-Cleanup beim Löschen)
- Modify: `src/Slim/RouteMiddlewareCollector.php`

**Interfaces:**
- Consumes: `ProjectAssetService` (Task 7), `ProjectService` (Task 6).
- Produces: Routen `POST /projects/{projectId}/assets`, `DELETE /projects/{projectId}/assets/{assetId}`, `GET /projects/{projectId}/assets/{assetId}`.

- [ ] **Step 1: `ProjectService` um Asset-Cleanup erweitern**

Konstruktor-Parameter ergänzen (nach `$assetRepository`):

```php
        private readonly ProjectAssetService    $assetService,
```

Und `deleteProject()` ersetzen:

```php
    public function deleteProject(ProjectId $projectId): ApiResult
    {
        $this->loadProjectOrFail($projectId);

        // MinIO-Objekte zuerst: die DB-CASCADE entfernt nur die Asset-Zeilen.
        $this->assetService->deleteAllForProject($projectId);
        $this->repository->delete($projectId);

        return ApiResult::from(
            JsonResult::from('Project deleted.'),
            StatusCodeInterface::STATUS_NO_CONTENT
        );
    }
```

- [ ] **Step 2: `UploadProjectAssetAction.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectAssetService;
use LukaLtaApi\Api\Project\Service\ProjectService;
use LukaLtaApi\Exception\ProjectAssetUploadException;
use LukaLtaApi\Value\Project\Asset\ProjectAssetType;
use LukaLtaApi\Value\Project\ProjectId;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/projects/<uuid>/assets
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>' --form 'type=logo' --form 'file=@logo.png'
class UploadProjectAssetAction extends ApiAction
{
    public function __construct(
        private readonly ProjectService      $projectService,
        private readonly ProjectAssetService $assetService,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $projectId = ProjectId::fromString((string) $request->getAttribute('projectId'));
        $this->projectService->loadProjectOrFail($projectId);

        $body = (array) $request->getParsedBody();
        $type = ProjectAssetType::fromString((string) ($body['type'] ?? ''));

        $uploadedFile = $request->getUploadedFiles()['file'] ?? null;

        if ($uploadedFile === null) {
            throw new ProjectAssetUploadException('Form field file is required.', 400);
        }

        $altText = isset($body['altText']) && trim((string) $body['altText']) !== ''
            ? trim((string) $body['altText'])
            : null;

        $asset = $this->assetService->upload($projectId, $type, $uploadedFile, $altText);

        return ApiResult::from(
            JsonResult::from('Project asset uploaded.', [
                'asset' => $asset->toArray($this->projectService->getAssetBaseUrl()),
            ]),
            201,
        )->getResponse($response);
    }
}
```

- [ ] **Step 3: `DeleteProjectAssetAction.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectAssetService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X DELETE http://localhost/api/v1/projects/<uuid>/assets/<assetId>
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
class DeleteProjectAssetAction extends ApiAction
{
    public function __construct(
        private readonly ProjectAssetService $assetService,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $assetId = (string) $request->getAttribute('assetId');
        $this->assetService->delete($assetId);

        return ApiResult::from(JsonResult::from('Project asset deleted.'), 204)->getResponse($response);
    }
}
```

- [ ] **Step 4: `GetProjectAssetAction.php`**

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectAssetService;
use LukaLtaApi\Exception\ProjectAssetNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/projects/<uuid>/assets/<assetId> --output logo.png
class GetProjectAssetAction extends ApiAction
{
    public function __construct(
        private readonly ProjectAssetService $assetService,
    ) {
    }

    /**
     * Liefert die Bytes ueber die eigene API statt per MinIO-URL — Endpoint und
     * Credentials des Object Storage bleiben damit serverseitig. Route ist
     * oeffentlich, weil das Portfolio Bilder ohne Login laden muss.
     */
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $asset  = $this->assetService->loadAssetOrFail((string) $request->getAttribute('assetId'));
        $object = $this->assetService->getObjectForAsset($asset);

        if ($object === null) {
            throw new ProjectAssetNotFoundException('Project asset object not found in storage.');
        }

        $response->getBody()->write($object['body']);

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', $object['contentType'])
            ->withHeader('Cache-Control', 'public, max-age=86400');
    }
}
```

- [ ] **Step 5: Routen registrieren**

Imports ergänzen:

```php
use LukaLtaApi\Api\Project\Action\DeleteProjectAssetAction;
use LukaLtaApi\Api\Project\Action\GetProjectAssetAction;
use LukaLtaApi\Api\Project\Action\UploadProjectAssetAction;
```

In der geschützten `/projects`-Gruppe aus Task 6 **vor** `$projects->patch('/{projectId}', ...)` ergänzen:

```php
                $projects->post('/{projectId}/assets', UploadProjectAssetAction::class);
                $projects->delete('/{projectId}/assets/{assetId}', DeleteProjectAssetAction::class);
```

Die öffentliche Asset-Route gehört in dieselbe Closure, auf dieselbe Einrückungsebene (12 Spaces), **zwischen** die geschützte `/projects`-Gruppe und die öffentliche Route `/projects/{slug}` aus Task 6. Steht sie nach `{slug}`, verschluckt der Platzhalter sie:

```php
            $app->get('/projects/{projectId}/assets/{assetId}', GetProjectAssetAction::class);
```

- [ ] **Step 6: Lint**

Run: `just lint`
Erwartet: keine Findings.

- [ ] **Step 7: Verifizieren per curl**

```bash
# Testprojekt + Testbild anlegen
PROJECT=$(curl -s -X POST http://localhost/api/v1/projects "${AUTH[@]}" \
  -H 'Content-Type: application/json' -d '{"name":"Asset Test"}')
PROJECT_ID=$(echo "$PROJECT" | jq -r '.data.project.id')
printf '\x89PNG\r\n\x1a\n' > /tmp/claude-asset-test.png
dd if=/dev/urandom bs=1024 count=4 >> /tmp/claude-asset-test.png 2>/dev/null

# Upload Logo
ASSET=$(curl -s -X POST "http://localhost/api/v1/projects/$PROJECT_ID/assets" \
  "${AUTH[@]}" -F 'type=logo' -F 'file=@/tmp/claude-asset-test.png;type=image/png')
echo "$ASSET" | jq
ASSET_ID=$(echo "$ASSET" | jq -r '.data.asset.id')
```
Erwartet: Status 201, `data.asset.url` zeigt auf `/projects/<projectId>/assets/<assetId>`.

```bash
# Proxy-Auslieferung (oeffentlich, ohne Token)
curl -s -o /dev/null -w '%{http_code} %{content_type}\n' \
  "http://localhost/api/v1/projects/$PROJECT_ID/assets/$ASSET_ID"
```
Erwartet: `200 image/png`.

```bash
# Review Focus 3: Replace hinterlaesst nur ein Asset
curl -s -X POST "http://localhost/api/v1/projects/$PROJECT_ID/assets" \
  "${AUTH[@]}" -F 'type=logo' -F 'file=@/tmp/claude-asset-test.png;type=image/png' \
  | jq -r '.data.asset.id'
curl -s "http://localhost/api/v1/projects/manage/$PROJECT_ID" "${AUTH[@]}" \
  | jq '{logo: .data.project.logo.id, screenshots: (.data.project.screenshots | length)}'
docker compose -f docker-compose.development.yml exec -T mysql \
  sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" luka_lta_api' \
  -e "SELECT type, COUNT(*) FROM project_assets GROUP BY type;"
```
Erwartet: neue Asset-ID, `logo` zeigt die **neue** ID, genau **eine** Zeile vom Typ `logo`. Das alte Objekt ist aus MinIO entfernt — Gegenprobe: der alte Asset-Endpoint liefert jetzt 404:
```bash
curl -s -o /dev/null -w '%{http_code}\n' "http://localhost/api/v1/projects/$PROJECT_ID/assets/$ASSET_ID"
```
Erwartet: `404`.

```bash
# Review Focus 5: falscher MIME-Type -> 400, zu grosse Datei -> 400
printf 'nope' > /tmp/claude-asset-test.txt
curl -s -X POST "http://localhost/api/v1/projects/$PROJECT_ID/assets" \
  "${AUTH[@]}" -F 'type=logo' -F 'file=@/tmp/claude-asset-test.txt;type=text/plain' \
  | jq '.status'
dd if=/dev/urandom of=/tmp/claude-asset-big.png bs=1M count=6 2>/dev/null
curl -s -X POST "http://localhost/api/v1/projects/$PROJECT_ID/assets" \
  "${AUTH[@]}" -F 'type=logo' -F 'file=@/tmp/claude-asset-big.png;type=image/png' \
  | jq '.status'
```
Erwartet: beide `400`.

```bash
# Screenshots: mehrfach erlaubt, sort_order zaehlt hoch
for i in 1 2; do
  curl -s -X POST "http://localhost/api/v1/projects/$PROJECT_ID/assets" \
    "${AUTH[@]}" -F 'type=screenshot' \
    -F 'file=@/tmp/claude-asset-test.png;type=image/png' | jq -r '.data.asset.sortOrder'
done
```
Erwartet: `0`, dann `1`.

```bash
# Review Focus 4 (Teil 2): Projekt loeschen entfernt MinIO-Objekte
SHOT_ID=$(curl -s "http://localhost/api/v1/projects/manage/$PROJECT_ID" "${AUTH[@]}" \
  | jq -r '.data.project.screenshots[0].id')
curl -s -o /dev/null -w '%{http_code}\n' -X DELETE "http://localhost/api/v1/projects/$PROJECT_ID" \
  "${AUTH[@]}"
curl -s -o /dev/null -w '%{http_code}\n' "http://localhost/api/v1/projects/$PROJECT_ID/assets/$SHOT_ID"
docker compose -f docker-compose.development.yml exec -T mysql \
  sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" luka_lta_api' \
  -e "SELECT COUNT(*) AS leftover FROM project_assets;"
rm -f /tmp/claude-asset-test.png /tmp/claude-asset-test.txt /tmp/claude-asset-big.png
```
Erwartet: `204`, dann `404`, `leftover = 0`.

- [ ] **Step 8: Commit**

```bash
git add src/Api/Project/ src/Slim/RouteMiddlewareCollector.php
git commit -m "$(cat <<'EOF'
feat: add project asset upload, delete and proxy routes

Project deletion now removes MinIO objects before the DB cascade drops
the asset rows, since the cascade cannot reach object storage.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 9: Import-Command für die 6 Altprojekte

**Files:**
- Create: `src/Command/Project/ImportLegacyProjectsCommand.php`
- Modify: `bin/app.php`

**Interfaces:**
- Consumes: `ProjectService` (Task 6/8), `ProjectAssetService` (Task 7), `ProjectRepository` (Task 5).
- Produces: CLI-Command `projects:import-legacy`.

- [ ] **Step 1: Quelldaten und Bilder bereitstellen**

Die Projektdaten aus dem Portfolio-Repo lesen und die Bilder in den API-Container-Kontext kopieren:

```bash
cat /Users/lliebenthal/projects/luka-lta/src/lib/projects-data.ts
ls /Users/lliebenthal/projects/luka-lta/public/static/images/projects/
mkdir -p /Users/lliebenthal/projects/luka-lta-api/var/legacy-projects
cp /Users/lliebenthal/projects/luka-lta/public/static/images/projects/* \
   /Users/lliebenthal/projects/luka-lta-api/var/legacy-projects/
```

`var/` in `.gitignore` ergänzen (einmaliges Import-Material, gehört nicht ins Repo):

```bash
grep -q '^/var/' .gitignore || printf '/var/\n' >> .gitignore
```

- [ ] **Step 2: Command schreiben**

Trage im `LEGACY_PROJECTS`-Array **alle 6 Projekte** exakt mit den Werten aus `projects-data.ts` ein. Der bisherige `id`-Wert wird **unverändert** als `slug` übernommen — davon hängen bestehende Links `/project/:projectId` ab. Vorlage für einen Eintrag (restliche fünf analog ergänzen, Reihenfolge wie im Array = `sortOrder`):

```php
<?php

declare(strict_types=1);

namespace LukaLtaApi\Command\Project;

use LukaLtaApi\Api\Project\Service\ProjectAssetService;
use LukaLtaApi\Repository\ProjectRepository;
use LukaLtaApi\Value\Project\Asset\ProjectAssetType;
use LukaLtaApi\Value\Project\Project;
use LukaLtaApi\Value\Project\ProjectSlug;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Einmaliger Import der zuvor im Portfolio-Frontend hartkodierten Projekte.
 * Idempotent: bereits vorhandene Slugs werden uebersprungen, damit ein
 * zweiter Lauf keine Duplikate erzeugt.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
#[AsCommand(name: 'projects:import-legacy', description: 'Imports the formerly hardcoded portfolio projects')]
class ImportLegacyProjectsCommand extends Command
{
    /** Verzeichnis, in das die Bilder aus dem Portfolio-Repo kopiert wurden */
    private const string IMAGE_SOURCE_DIR = __DIR__ . '/../../../var/legacy-projects';

    /** Projektdaten 1:1 aus luka-lta/src/lib/projects-data.ts uebernommen */
    private const array LEGACY_PROJECTS = [
        [
            'slug'             => 'luka-lta-api',
            'name'             => 'Luka LTA API',
            'shortDescription' => '<description aus projects-data.ts>',
            'description'      => '<longDescription aus projects-data.ts>',
            'techStack'        => ['PHP', 'Slim', 'MySQL'],
            'websiteUrl'       => '<liveUrl>',
            'liveLabel'        => null,
            'repositoryUrl'    => '<repoUrl oder null>',
            'repositoryOwner'  => '<repoOwner oder null>',
            'repositoryName'   => '<repoName oder null>',
            'role'             => '<role>',
            'projectYear'      => 2025,
            'isClientProject'  => false,
            'screenshots'      => ['<dateiname-1.png>', '<dateiname-2.png>'],
        ],
        // ... die uebrigen fuenf Projekte in Array-Reihenfolge
    ];

    public function __construct(
        private readonly ProjectRepository   $repository,
        private readonly ProjectAssetService $assetService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sortOrder = 0;

        foreach (self::LEGACY_PROJECTS as $data) {
            $slug = ProjectSlug::fromString($data['slug']);

            if ($this->repository->getBySlug($slug, false) !== null) {
                $output->writeln(sprintf('Skipped %s (already imported)', $data['slug']));
                $sortOrder++;
                continue;
            }

            $project = Project::create([
                ...$data,
                'status'    => 'active',
                'isVisible' => true,
                'sortOrder' => $sortOrder,
            ]);

            $this->repository->create($project);
            $this->importImages($project, $data['screenshots'], $output);

            $output->writeln(sprintf('Imported %s', $data['slug']));
            $sortOrder++;
        }

        return Command::SUCCESS;
    }

    /**
     * Das Altmodell kennt kein Logo und kein Cover — der erste Screenshot wird
     * daher zusaetzlich als logo und cover hochgeladen, damit die Darstellung
     * ab dem ersten Tag vollstaendig ist. Beide lassen sich spaeter im
     * Dashboard durch echte Assets ersetzen.
     */
    private function importImages(Project $project, array $screenshots, OutputInterface $output): void
    {
        foreach (array_values($screenshots) as $index => $fileName) {
            $uploadedFile = $this->asUploadedFile($fileName);

            if ($uploadedFile === null) {
                $output->writeln(sprintf('  WARNING: image %s not found, skipped', $fileName));
                continue;
            }

            $this->assetService->upload($project->getProjectId(), ProjectAssetType::SCREENSHOT, $uploadedFile, null);

            if ($index !== 0) {
                continue;
            }

            foreach ([ProjectAssetType::LOGO, ProjectAssetType::COVER] as $type) {
                $duplicate = $this->asUploadedFile($fileName);

                if ($duplicate === null) {
                    continue;
                }

                $this->assetService->upload($project->getProjectId(), $type, $duplicate, null);
            }
        }
    }

    private function asUploadedFile(string $fileName): ?UploadedFile
    {
        $path = self::IMAGE_SOURCE_DIR . '/' . basename($fileName);

        if (!is_file($path)) {
            return null;
        }

        $mimeType = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png'          => 'image/png',
            'webp'         => 'image/webp',
            'jpg', 'jpeg'  => 'image/jpeg',
            default        => null,
        };

        if ($mimeType === null) {
            return null;
        }

        return new UploadedFile(
            (new StreamFactory())->createStreamFromFile($path),
            basename($path),
            $mimeType,
            filesize($path) ?: null,
            UPLOAD_ERR_OK,
        );
    }
}
```

**Vor dem Schreiben prüfen:** ob `Slim\Psr7\UploadedFile` diesen Konstruktor hat (Stream-Variante) — ansonsten `new UploadedFile($path, $name, $mimeType, $size, UPLOAD_ERR_OK)` mit Dateipfad verwenden:

```bash
grep -n "public function __construct" -A 12 vendor/slim/psr7/src/UploadedFile.php
```

`$data['screenshots']` enthält in `projects-data.ts` root-relative Pfade (`/static/images/projects/x.png`) — `basename()` löst das auf die kopierten Dateien auf. Die Keys `screenshots` und `slug` werden von `Project::create()` ignoriert, weil `applyChanges()` sie nicht kennt bzw. `slug` explizit verarbeitet.

- [ ] **Step 3: Command registrieren**

In `bin/app.php` dem bestehenden Muster folgen (Import + `$application->add(...)`), genau wie bei `CleanupAlertsCommand`:

```bash
grep -n "Command" bin/app.php
```

- [ ] **Step 4: Lint**

Run: `just lint`
Erwartet: keine Findings.

- [ ] **Step 5: Import ausführen und verifizieren**

```bash
docker compose -f docker-compose.development.yml run --rm php-fpm php bin/app.php projects:import-legacy
```
Erwartet: 6 Zeilen `Imported <slug>`, keine WARNING-Zeilen.

```bash
curl -s http://localhost/api/v1/projects | jq '[.data.projects[] | {slug, sortOrder, screenshots: (.screenshots|length), logo: (.logo != null)}]'
```
Erwartet: 6 Projekte, `sortOrder` 0..5 in Array-Reihenfolge der Altdaten, jedes mit ≥1 Screenshot und `logo: true`.

```bash
# Slug-Kontinuitaet: jeder alte id-Wert muss als Slug aufloesbar sein
for slug in $(grep -oE "id: '[^']+'" /Users/lliebenthal/projects/luka-lta/src/lib/projects-data.ts | cut -d"'" -f2); do
  printf '%s -> ' "$slug"
  curl -s -o /dev/null -w '%{http_code}\n' "http://localhost/api/v1/projects/$slug"
done
```
Erwartet: jede Zeile endet mit `200`.

```bash
# Idempotenz
docker compose -f docker-compose.development.yml run --rm php-fpm php bin/app.php projects:import-legacy
curl -s http://localhost/api/v1/projects | jq '.data.projects | length'
```
Erwartet: 6 Zeilen `Skipped ...`, danach weiterhin `6`.

- [ ] **Step 6: Commit**

```bash
git add src/Command/Project/ bin/app.php .gitignore
git commit -m "$(cat <<'EOF'
feat: add one-off import command for formerly hardcoded portfolio projects

Keeps the old id values as slugs so existing /project/:id links stay valid.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 10: CORS für die Portfolio-Domain prüfen

**Files:**
- Modify (nur falls nötig): `src/Slim/CorsResponseManager.php`

**Interfaces:**
- Consumes: alle öffentlichen Projects-Routen aus Task 6/8.
- Produces: funktionierender Cross-Origin-Zugriff für das Portfolio-Frontend.

- [ ] **Step 1: Aktuelle CORS-Konfiguration lesen**

```bash
cat src/Slim/CorsResponseManager.php
grep -rn "CorsResponseManager\|Access-Control-Allow-Origin" src/ | head
```

- [ ] **Step 2: Preflight und echten Request gegen die Portfolio-Origin testen**

Die Origin des Portfolios aus dessen Config ermitteln:

```bash
grep -rn "VITE_API\|API_URL\|baseURL" /Users/lliebenthal/projects/luka-lta/src /Users/lliebenthal/projects/luka-lta/.env.development 2>/dev/null | head
```

Dann:

```bash
curl -s -D - -o /dev/null -X OPTIONS http://localhost/api/v1/projects \
  -H 'Origin: http://localhost:5173' \
  -H 'Access-Control-Request-Method: GET' | grep -i 'access-control'
curl -s -D - -o /dev/null http://localhost/api/v1/projects \
  -H 'Origin: http://localhost:5173' | grep -i 'access-control'
```

Erwartet: `Access-Control-Allow-Origin` enthält die Origin (oder `*`).

- [ ] **Step 3: Falls Header fehlt — Origin ergänzen**

Nur dann `CorsResponseManager` anpassen und dem bestehenden Mechanismus folgen (keine neue Konfigurationsquelle einführen). Danach Step 2 wiederholen, bis die Header gesetzt sind.

- [ ] **Step 4: Lint**

Run: `just lint`
Erwartet: keine Findings.

- [ ] **Step 5: Commit (nur falls Step 3 Änderungen gebracht hat)**

```bash
git add src/Slim/CorsResponseManager.php
git commit -m "$(cat <<'EOF'
fix: allow portfolio origin for public project routes

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

Gab es keine Änderung: kein Commit, nur im Abschlussbericht festhalten, dass CORS bereits passte.

---

## Abschluss Teil 1

Nach Task 10 ist die API vollständig: öffentliche Leserouten für das Portfolio, geschützte Verwaltung für das Dashboard, Tag-Dictionary, Asset-Lifecycle auf MinIO und die 6 migrierten Altprojekte. Teil 2 (Dashboard-UI) und Teil 3 (Portfolio-Umstellung) werden als eigene Pläne geschrieben, sobald Teil 1 verifiziert ist.
