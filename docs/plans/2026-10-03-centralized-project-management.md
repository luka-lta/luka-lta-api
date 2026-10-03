# Zentralisierte Projektverwaltung — Analyse & Architekturplan

> Status: **Entwurf, wartet auf Freigabe**. Kein Code wurde implementiert.
> Betrifft drei Repos: `luka-lta` (package-Name `luka-lta-frontend`, Portfolio-Seite), `luka-lta-backend` (Admin-Dashboard, React/Vite), `luka-lta-api` (PHP/Slim-Backend, Quelle der Wahrheit).

---

## A. Ist-Analyse

### A.1 Wo liegen die Projekte aktuell?

Einzige Quelle: `luka-lta/src/lib/projects-data.ts` — ein File, exportiert `interface Project` und ein Array `projects: Project[]` mit **6 Einträgen** (luka-lta-api, mexcal, luka-lta-backend, kindled, dj-guide, luka-lta-frontend).

Aktuelle Felder (aus dem Interface):

| Feld | Typ | Pflicht | Beispiel |
|---|---|---|---|
| `id` | string | ja | `"luka-lta-api"` (dient zugleich als Slug/Routing-Key) |
| `title` | string | ja | `"Luka LTA API"` |
| `description` | string | ja | Kurzbeschreibung für Card |
| `longDescription` | string | ja | Fließtext für Detailseite |
| `techStack` | string[] | ja | `["PHP", "Slim", "MySQL"]` |
| `screenshots` | string[] | ja | Pfade unter `public/static/images/projects/*.png` |
| `liveUrl` | string | ja | externe URL |
| `repoUrl` | string | nein | GitHub-URL |
| `repoOwner` | string | nein | für GitHub-Stats-Hook |
| `repoName` | string | nein | für GitHub-Stats-Hook |
| `role` | string | ja | eigene Rolle im Projekt |
| `year` | number | ja | Jahr |
| `liveLabel` | string | nein | Button-Text-Override |
| `clientProject` | boolean | nein | Kundenprojekt-Flag |

**Nicht vorhanden:** `status`, `category`, `tags`, `sortOrder`, `isActive`/`isVisible`. Die Array-Reihenfolge *ist* aktuell die Sortierung.

### A.2 Wer konsumiert diese Daten?

Nur **drei** Stellen (verifiziert per Grep, keine weiteren):

1. `src/components/Landing/Projects.tsx` — Homepage-Grid. Erstes Array-Element = "featured", Rest paginiert (`INITIAL_VISIBLE = 2`). Nutzt `screenshots[0]`, `techStack`, `clientProject`, `year`, `repoUrl`, `title`, `description`.
2. `src/feature/project/index.tsx` — Detailseite unter `/project/:projectId`, sucht per `id`. Nutzt `longDescription`, volle `screenshots[]`-Carousel, `role`, `clientProject`, `liveLabel`, `liveUrl`. Ruft `useGithubStats(repoOwner, repoName)` auf.
3. `src/AppRouter.tsx` — verdrahtet nur die Route.

### A.3 Projektspezifische Sonderfälle?

**Keine gefunden.** Kein `if (project.slug === "...")`, kein `if (project.name === "...")` irgendwo im Code. Der GitHub-Stats-Call sieht wie ein Sonderfall aus, ist aber generisch — er wird von den Feldern `repoOwner`/`repoName` gesteuert, die jedes Projekt haben kann. Das ist also keine Projekt-spezifische Funktion, sondern ein generisches Feature, das in jedem Projekt-Datensatz aktiviert werden kann, wenn die Felder gesetzt sind.

→ **Ergebnis zu Punkt 4/28 der Anfrage:** Es gibt aktuell *keine* echte projektspezifische technische Integration (kein Analytics-Endpoint o.ä. im Code). Die in der Anfrage genannten Beispiele (Trackspire-Analytics, Homelab-Monitoring) sind hypothetisch, nicht im Bestand. Ich baue daher **keine** separate "Project Integrations"-Tabelle für v1 — das wäre verfrühte Abstraktion ohne aktuellen Bedarf. Stattdessen: ein schlankes, nullable `metadata JSON`-Feld auf `projects` für zukünftige, nicht-kritische Zusatzdaten. Sollte tatsächlich mal ein strukturiertes Integrations-Feature nötig werden (z. B. ein Secret für einen Analytics-Endpoint), dann zu diesem Zeitpunkt eine dedizierte kleine Tabelle `project_integrations` (project_id, key, value, is_secret) — nicht jetzt.

### A.4 Bilder

Lokale Dateien unter `luka-lta/public/static/images/projects/*.png|webp`, referenziert als root-relative Pfade. Kein MinIO-Bezug heute — Migration nötig.

### A.5 Backend-Architektur (luka-lta-api)

- **Pattern:** Action → Service → Repository, PSR-4 `LukaLtaApi\`. Vorlage: Blog-Domain.
- **Routing-Vorlage (Blog):**
  ```php
  $app->get('/blog', GetAllBlogsAction::class);           // öffentlich, ungruppiert
  $app->get('/blog/{blogId}', GetBlogAction::class);

  $app->group('/blog', function (RouteCollectorProxy $blog) {
      $blog->post('', CreateBlogAction::class);
      $blog->put('/{blogId}', UpdateBlogAction::class);
      $blog->delete('/{blogId}', DeleteBlogAction::class);
  })->add(AuthMiddleware::class);
  ```
- **Auth:** `AuthMiddleware` prüft nur JWT-Gültigkeit — **kein Rollen-/Permission-System** für Dashboard-Aktionen. `ApiKeyPermissionMiddleware` + `permissions.sql` ist ein komplett separates System nur für externe API-Keys (z. B. Metrics-Ingest), hat mit der Admin-Projektverwaltung nichts zu tun. → Für Projects reicht `AuthMiddleware` auf den Write-Routen, exakt wie bei Blog/Homelab/Calendar.
- **Validation:** Keine Library — manuelle Pflichtfeld-Prüfung in der Action (`['required' => bool, 'location' => 'body'|'query']`), Format-/Eindeutigkeits-Validierung (Slug, URL) passiert manuell in Service/Repository über Value Objects.
- **Exceptions:** flache Hierarchie, alle erweitern `ApiException(message, httpStatusCode, ?previous, context)`. Konvention: `*NotFoundException` pro Domain (`BlogNotFoundException`, `TagNotFoundException`).
- **Migrationen:** flache Dateien in `data/mysql/`, meist ein File pro Tabelle (alphabetische Init-Reihenfolge), **alle Foreign Keys zentral in `zz_foreign_keys.sql`** (läuft alphabetisch zuletzt). **Kein Soft-Delete irgendwo im Code** — Deaktivierung läuft über explizite Boolean-Flags (z. B. `link_collection.is_active`/`deactivated`), getrennt vom Löschen.
- **MinIO (`S3Repository`):** Ein Bucket, Zugangsdaten über Env (`AWS_BUCKET`, `AWS_VERSION`, `AWS_REGION`, `AWS_ENDPOINT`), Client via `MinIOFactory`, in `ApplicationConfig.php` nur als PHP-DI-Factory verdrahtet. Bisher nur für Avatare genutzt:
  - `uploadFile(UploadedFileInterface, UserId): string` — Object Key hart codiert als `avatars/{userId}.{ext}`, ACL `public-read`.
  - `getAvatarImageFromS3(UserId): ?array` — **proxied Bytes durch PHP** (`GET /avatar/{userId}`), kein direkter öffentlicher MinIO-Link. `User::getAvatarUrl()` baut `https://api.luka-lta.dev/api/v1/avatar/{userId}`.
  - **Lücke:** Kein `delete()`/`replace()` auf `S3Repository` — muss für Projekt-Assets ergänzt werden.
  - `AvatarService` validiert MIME (`image/jpeg`, `image/png`) + 5 MB Limit, wirft `ApiAvatarUploadException`. Dient als Vorlage für `ProjectAssetService`.

### A.6 Frontend-Architektur (luka-lta-backend, Admin-Dashboard)

- **Sidebar** (`src/components/layout/data/sidebar-data.ts`): Gruppen sind `General`, `Access-Management`, `Linktree-Management`, `Homelab`, `Personal` (Calendar, Weather), `Blog-Management`, `Other`. Konvention: eigenständige CRUD-Bereiche bekommen eine eigene `"X-Management"`-Gruppe, nicht `Personal` (das ist für Read-only-Widgets). → Neue Gruppe **`Portfolio-Management`** mit Eintrag **`Projects`**, konsistent mit Blog-/Linktree-Management.
- **API-Layer-Vorlage (Calendar-Domain):** `src/api/calendar/schema.ts` (Zod-Schemas + inferred Types), `endpoints.ts` (reine async Funktionen über `api`-Axios-Instanz, Response mit Zod geparst), `hooks.ts` (`useQuery`/`useMutation`, Query-Key-Konvention `["calendar", ...]`, jede Mutation invalidiert domain-weit `["calendar"]`).
- **Upload-Vorlage (Avatar, einziger existierender Upload-Flow):** `src/components/form/AvatarInput.tsx` (react-hook-form-gebunden, FileReader-Preview), `src/api/axios.ts` exportiert `api` (JSON) und `apiForm` (multipart), `updateSelfUser(formData)` → `apiForm.post('/self/', formData)`.
- **Table/Management-UI-Vorlage:** `src/feature/user/components/UserTable.tsx` — `@tanstack/react-table`, `SortableHead`, `TableSkeleton` (Loading), `Pagination`, `Empty/EmptyMedia/EmptyTitle` (Empty State), `DropdownMenu` für Zeilen-Aktionen, `Status`/`StatusIndicator` (kibo-ui) für Badges.
- **Confirm-Dialog:** `src/components/ui/alert-dialog.tsx` (shadcn AlertDialog) vorhanden.
- **Edit-Pattern:** Sheet (Slide-over), nicht Dialog (`EditUserSheet.tsx`). Create nutzt Dialog bei kleinen Formularen (`CreateApiKeyDialog.tsx`).
- **Drag & Drop:** `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/modifiers` bereits in `package.json` — Sortierung ohne neue Dependency machbar.

---

## B. Datenmodell

### B.1 Tabelle `projects`

| Feld | Typ | Null | Default | Bemerkung |
|---|---|---|---|---|
| `project_id` | char(36) | NOT NULL | — | PK, UUID (Konvention wie `blog_posts.blog_id`) |
| `name` | varchar(100) | NOT NULL | — | |
| `slug` | varchar(100) | NOT NULL | — | UNIQUE, Routing-Key (ersetzt heutiges `id`) |
| `short_description` | varchar(255) | NULL | NULL | für Cards |
| `description` | text | NULL | NULL | Fließtext (bisher `longDescription`) |
| `status` | enum('development','beta','active','paused','archived') | NOT NULL | `'development'` | zentral definiert, kein Frontend-String-Literal |
| `is_visible` | tinyint(1) | NOT NULL | `1` | steuert Sichtbarkeit auf dem öffentlichen Portfolio, unabhängig von `status` |
| `category` | varchar(50) | NULL | NULL | einfacher String, keine eigene Tabelle (Punkt 8: "nicht unnötig komplex") |
| `tech_stack` | json | NULL | NULL | Array von Strings (bisheriges Feld, eigenständig von Tags) |
| `website_url` | varchar(1024) | NULL | NULL | bisher `liveUrl` |
| `live_label` | varchar(100) | NULL | NULL | Button-Text-Override |
| `repository_url` | varchar(1024) | NULL | NULL | |
| `repository_owner` | varchar(100) | NULL | NULL | für GitHub-Stats-Hook (generisch, kein Sonderfall) |
| `repository_name` | varchar(100) | NULL | NULL | |
| `demo_url` | varchar(1024) | NULL | NULL | aktuell ungenutzt im Code, aber von der Anfrage gefordert |
| `documentation_url` | varchar(1024) | NULL | NULL | aktuell ungenutzt im Code, aber von der Anfrage gefordert |
| `role` | varchar(100) | NULL | NULL | |
| `project_year` | smallint | NULL | NULL | `year` ist reserviertes Wort, daher `project_year` |
| `is_client_project` | tinyint(1) | NOT NULL | `0` | bisher `clientProject` |
| `metadata` | json | NULL | NULL | Reserve für zukünftige, unkritische Zusatzdaten (siehe A.3) |
| `sort_order` | int | NOT NULL | `0` | |
| `created_at` | datetime | NOT NULL | `CURRENT_TIMESTAMP` | |
| `updated_at` | datetime | NOT NULL | `CURRENT_TIMESTAMP` ON UPDATE | |

Indizes: `PRIMARY KEY (project_id)`, `UNIQUE KEY uq_project_slug (slug)`, `KEY project_sort (sort_order)`, `KEY project_visibility (is_visible, status)`.

Kein Soft-Delete (kein Precedent im Codebase, s. A.5) — hartes `DELETE`, Sichtbarkeit läuft separat über `is_visible`/`status`.

### B.2 Tabelle `project_assets`

Logo, Cover und Screenshot-Galerie werden **nicht** als einzelne Spalten (`logo_key`, `cover_key`, ...) im `projects`-Model geführt — das würde das Modell aufblähen und mit variabler Screenshot-Anzahl ohnehin nicht funktionieren. Stattdessen eine generische Asset-Tabelle:

| Feld | Typ | Null | Default | Bemerkung |
|---|---|---|---|---|
| `asset_id` | char(36) | NOT NULL | — | PK |
| `project_id` | char(36) | NOT NULL | — | FK → `projects.project_id` ON DELETE CASCADE |
| `type` | enum('logo','cover','screenshot') | NOT NULL | — | |
| `object_key` | varchar(255) | NOT NULL | — | MinIO-Key |
| `alt_text` | varchar(150) | NULL | NULL | |
| `sort_order` | int | NOT NULL | `0` | nur für `screenshot` relevant |
| `created_at` | datetime | NOT NULL | `CURRENT_TIMESTAMP` | |

Indizes: `PRIMARY KEY (asset_id)`, `KEY project_asset_lookup (project_id, type, sort_order)`.

`logo`/`cover` sind Singleton pro Projekt — **applikationsseitig** erzwungen (Service löscht beim Ersetzen zuerst die alte Zeile + das alte MinIO-Objekt, dann legt er die neue an), keine DB-Unique-Constraint, weil `screenshot` mehrfach vorkommen darf und eine gemeinsame Constraint das nicht abbilden kann.

FK-Eintrag kommt nach bestehender Konvention in `zz_foreign_keys.sql`:
```sql
ALTER TABLE `project_assets`
    ADD CONSTRAINT `fk_project_asset_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE;
```

**Wichtig:** Die DB-CASCADE löscht nur die Zeilen, **nicht** die MinIO-Objekte. Der `DeleteProjectService` muss vor dem DB-Delete explizit über alle `project_assets`-Zeilen iterieren und `S3Repository::deleteObject()` für jede aufrufen.

### B.3 Tags — eigene Tabellen (wiederverwendbar)

Tags werden **nicht** als JSON-Array auf `projects` gespeichert, sondern relational, damit sie über Projekte hinweg wiederverwendbar und per Autocomplete auswählbar sind. Das Schema spiegelt bewusst 1:1 die bereits existierende Blog-Tag-Struktur (`blog_tags` + `blog_post_tags`) — gleiche Konvention, keine neue Architektur. Eigene Projekt-Tag-Tabellen (statt Mitnutzung von `blog_tags`) entsprechen dem bestehenden Muster domänenspezifischer Tag-Dictionaries und vermeiden Kopplung zwischen Blog und Portfolio.

**Dictionary `project_tags`** (gespiegelt von `blog_tags`):

| Feld | Typ | Null | Default | Bemerkung |
|---|---|---|---|---|
| `tag_id` | int | NOT NULL | AUTO_INCREMENT | PK (int wie `blog_tags.tag_id`, kein UUID) |
| `name` | varchar(50) | NOT NULL | — | Anzeigename, UNIQUE |
| `slug` | varchar(50) | NOT NULL | — | UNIQUE (wie `uq_blog_tags_slug`) |
| `created_at` | datetime | NOT NULL | `CURRENT_TIMESTAMP` | |

Indizes: `PRIMARY KEY (tag_id)`, `UNIQUE KEY uq_project_tags_name (name)`, `UNIQUE KEY uq_project_tags_slug (slug)`.

Die Collation `utf8mb4_0900_ai_ci` ist case-insensitive — `"Analytics"` und `"analytics"` kollidieren damit automatisch über die Unique-Constraint, es braucht keine eigene Case-Folding-Logik im Code.

**Join `project_tag_assignments`** (gespiegelt von `blog_post_tags`):

| Feld | Typ | Null | Default | Bemerkung |
|---|---|---|---|---|
| `assignment_id` | int | NOT NULL | AUTO_INCREMENT | PK |
| `project_id` | char(36) | NOT NULL | — | FK → `projects.project_id` ON DELETE CASCADE |
| `tag_id` | int | NOT NULL | — | FK → `project_tags.tag_id` ON DELETE CASCADE |

Indizes: `PRIMARY KEY (assignment_id)`, `UNIQUE KEY uq_project_tag_assignment (project_id, tag_id)`, `KEY project_tag_reverse (tag_id)` (für "welche Projekte nutzen diesen Tag").

FKs nach bestehender Konvention in `zz_foreign_keys.sql`:
```sql
ALTER TABLE `project_tag_assignments`
    ADD CONSTRAINT `fk_project_tag_assignment_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE;

ALTER TABLE `project_tag_assignments`
    ADD CONSTRAINT `fk_project_tag_assignment_tag` FOREIGN KEY (`tag_id`) REFERENCES `project_tags` (`tag_id`) ON DELETE CASCADE;
```

Ein Tag bleibt im Dictionary bestehen, wenn ein Projekt gelöscht wird (nur die Assignment-Zeile verschwindet) — genau das macht ihn wiederverwendbar.

---

## C. Backend (luka-lta-api)

### C.1 Neue Klassen (Action/Service/Repository-Pattern, nach Blog-Vorlage)

```
src/Api/Project/
├── Action/
│   ├── GetAllProjectsAction.php          (öffentlich, GET /projects)
│   ├── GetProjectAction.php              (öffentlich, GET /projects/{slug})
│   ├── GetAllProjectsForManagementAction.php  (geschützt, GET /projects/manage)
│   ├── GetProjectForManagementAction.php      (geschützt, GET /projects/manage/{projectId})
│   ├── CreateProjectAction.php           (geschützt, POST /projects)
│   ├── UpdateProjectAction.php           (geschützt, PATCH /projects/{projectId})
│   ├── DeleteProjectAction.php           (geschützt, DELETE /projects/{projectId})
│   ├── ReorderProjectsAction.php         (geschützt, PATCH /projects/order)
│   ├── UploadProjectAssetAction.php      (geschützt, POST /projects/{projectId}/assets, multipart)
│   ├── DeleteProjectAssetAction.php      (geschützt, DELETE /projects/{projectId}/assets/{assetId})
│   ├── ListProjectTagsAction.php         (geschützt, GET /project-tags — Autocomplete-Pool)
│   └── CreateProjectTagAction.php        (geschützt, POST /project-tags — Inline-Anlage aus der UI)
├── Service/
│   ├── ProjectService.php                (CRUD-Logik, Slug-Eindeutigkeit, Mapping Value Objects ↔ Entity)
│   ├── ProjectAssetService.php           (MIME/Size-Validation, Object-Key-Building, Replace-Logik)
│   └── ProjectTagService.php             (Tag-Dictionary: Anlage mit Slugify + Dedupe, Zuordnungs-Sync)
```

```
src/Repository/
├── ProjectRepository.php      (loadBySlug, loadById, loadAll(bool $onlyVisible), insert, update, delete, updateSortOrder)
├── ProjectAssetRepository.php (loadByProject, loadById, insert, delete, deleteByProject)
└── ProjectTagRepository.php   (loadAll, loadById, loadByName, loadBySlug, insert,
                                loadTagsForProject(ProjectId), syncAssignments(ProjectId, int[] $tagIds))
```

```
src/Value/Project/
├── ProjectId.php          (UUID-Wrapper)
├── ProjectSlug.php        (Validierung: lowercase, a-z0-9-, max 100)
├── ProjectStatus.php      (Enum-Wrapper, erlaubte Werte zentral)
├── ProjectName.php
├── ProjectTag.php         (tagId, name, slug)
└── ProjectTags.php        (Collection, implements Countable/IteratorAggregate/JsonSerializable)
```

```
src/Exception/
├── ProjectNotFoundException.php
├── ProjectSlugAlreadyExistsException.php
├── ProjectTagNotFoundException.php
└── ProjectAssetUploadException.php
```

**Tag-Verhalten:**
- `POST /project-tags` mit `{"name": "analytics"}` → slugifiziert den Namen, prüft Dictionary. Existiert der Tag schon (name **oder** slug), wird der **bestehende** Tag zurückgegeben (idempotent, HTTP 200) statt ein 409 zu werfen. Grund: Das KiboUI-"Create a Tag"-Element feuert beim Tippen eines bereits vorhandenen Namens sonst einen unnötigen Fehler — idempotentes Anlegen ist hier das korrekte Verhalten, nicht ein Konflikt.
- Projekt-Create/Update bekommen `tagIds: int[]`. `ProjectService` validiert, dass alle IDs im Dictionary existieren (sonst `ProjectTagNotFoundException` → 404), danach `ProjectTagRepository::syncAssignments()` (Diff: entfernte Assignments löschen, neue einfügen) in derselben Transaktion wie das Projekt-Update.
- Es werden **keine** Tags implizit über freie Strings beim Projekt-Speichern angelegt — Anlage läuft immer explizit über `POST /project-tags`. Damit bleibt das Dictionary die einzige Wahrheit und es entstehen keine Tipp-Fehler-Duplikate über verschiedene Projekte hinweg.

### C.2 Routing (`RouteMiddlewareCollector`, nach Blog-Vorlage)

```php
$app->get('/projects', GetAllProjectsAction::class);
$app->get('/projects/{slug}', GetProjectAction::class);

$app->group('/projects', function (RouteCollectorProxy $projects) {
    $projects->get('/manage', GetAllProjectsForManagementAction::class);
    $projects->get('/manage/{projectId}', GetProjectForManagementAction::class);
    $projects->post('', CreateProjectAction::class);
    $projects->patch('/order', ReorderProjectsAction::class);
    $projects->patch('/{projectId}', UpdateProjectAction::class);
    $projects->delete('/{projectId}', DeleteProjectAction::class);
    $projects->post('/{projectId}/assets', UploadProjectAssetAction::class);
    $projects->delete('/{projectId}/assets/{assetId}', DeleteProjectAssetAction::class);
})->add(AuthMiddleware::class);

$app->group('/project-tags', function (RouteCollectorProxy $tags) {
    $tags->get('', ListProjectTagsAction::class);
    $tags->post('', CreateProjectTagAction::class);
})->add(AuthMiddleware::class);
```

Das Tag-Dictionary ist bewusst **nur** geschützt erreichbar — das öffentliche Portfolio braucht keinen Tag-Pool, es bekommt die Tags eines Projekts bereits eingebettet in der Projekt-Response (s. C.7).

Begründung für `/projects/manage*` statt generischem Auth-Overload auf `/projects`: Es gibt im Codebase **kein** Precedent für "optionale Auth" (Route, die je nach Login-Status unterschiedlich filtert). Zwei klar getrennte Routen sind simpler und folgen dem einzigen tatsächlich vorhandenen Muster (öffentlich ungruppiert + geschützt gruppiert).

### C.3 Validation

- Pflichtfelder (`name`, `slug`) über das bestehende manuelle Rule-Array-System in der Action.
- Slug-Format (`^[a-z0-9]+(-[a-z0-9]+)*$`) + Eindeutigkeit (`ProjectRepository::loadBySlug()` vor Insert/Update prüfen) im `ProjectService`.
- URL-Felder: `filter_var($value, FILTER_VALIDATE_URL)` im Service, kein neues Package.
- `status`: gegen `ProjectStatus::ALLOWED` validieren, sonst `ApiInvalidArgumentException`.
- `tech_stack`: Array von Strings, max. Länge pro Eintrag (50) + max. Anzahl (20) serverseitig begrenzen.
- `tagIds`: Array von Integers, jede ID muss im Dictionary existieren, max. Anzahl (20) pro Projekt.
- Tag-`name` (bei `POST /project-tags`): nicht leer, max. 50 Zeichen, Slug wird serverseitig generiert (lowercase, `a-z0-9-`) — der Client schickt keinen Slug.

### C.4 Asset-Lifecycle (`ProjectAssetService`)

1. **Upload:** MIME-Check (`image/jpeg`, `image/png`, `image/webp` — bewusst vollständiger als die Avatar-Vorlage, die `webp` fälschlich nicht erlaubt), Size-Limit 5 MB (Konstante `MAX_PROJECT_ASSET_SIZE`), Object Key `projects/{projectId}/{type}/{assetId}.{ext}`.
2. **Replace (Logo/Cover):** Service lädt bestehende Asset-Zeile für `(projectId, type)`, falls vorhanden: `S3Repository::deleteObject(altObjectKey)` **vor** dem Insert der neuen Zeile, alte DB-Zeile löschen, dann neue Zeile anlegen (atomar in einer DB-Transaktion).
3. **Delete:** Vor Zeilen-Löschung erst `S3Repository::deleteObject()`, dann `ProjectAssetRepository::delete()`.
4. **Projekt-Löschung:** `ProjectService::delete()` lädt zuerst alle `project_assets` des Projekts, ruft für jede `deleteObject()` auf, erst dann `ProjectRepository::delete()` (DB-Cascade räumt die Zeilen danach ohnehin weg, aber die App muss die S3-Objekte selbst entfernen — das macht niemand sonst).
5. **Wiederverwendungs-Check (Punkt 11 der Anfrage):** In diesem Modell ist jede `project_assets`-Zeile 1:1 einem MinIO-Objekt zugeordnet, es gibt keine Asset-Wiederverwendung über Projekte hinweg — ein Check "wird das Asset noch anderswo verwendet" entfällt dadurch bewusst (das wäre premature, da der Bedarf dafür aktuell nicht existiert).

### C.5 `S3Repository`-Erweiterung

Fehlende Methode ergänzen:
```php
public function deleteObject(string $objectKey): void
{
    $this->s3Client->deleteObject([
        'Bucket' => $this->bucket,
        'Key' => $objectKey,
    ]);
}
```

### C.6 Ausliefern der Bilder

Wie beim Avatar: **kein** direkter öffentlicher MinIO-Link ans Frontend, sondern Proxy-Route `GET /projects/{projectId}/assets/{assetId}` (öffentlich, da Portfolio-Seite unauthentifiziert Bilder laden muss), die Bytes + Content-Type aus MinIO durchreicht. Begründung: bestehendes Pattern 1:1 übernehmen (CLAUDE.md: "keine neue Architektur erfinden, wenn ein passendes Pattern existiert"), MinIO-Endpoint/Credentials bleiben vollständig serverseitig (Punkt 31 der Anfrage).

### C.7 API-Response-Form

```json
{
  "id": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "name": "Trackspire",
  "slug": "trackspire",
  "shortDescription": "Web Analytics SaaS",
  "description": "...",
  "status": "active",
  "isVisible": true,
  "category": "SaaS",
  "tags": [
    { "id": 1, "name": "Analytics", "slug": "analytics" },
    { "id": 4, "name": "SaaS", "slug": "saas" }
  ],
  "techStack": ["PHP", "Slim", "MySQL"],
  "websiteUrl": "https://trackspire.app",
  "liveLabel": null,
  "repositoryUrl": "https://github.com/luka-lta/trackspire",
  "repositoryOwner": "luka-lta",
  "repositoryName": "trackspire",
  "demoUrl": null,
  "documentationUrl": null,
  "role": "Fullstack",
  "year": 2025,
  "isClientProject": false,
  "sortOrder": 1,
  "logo": { "url": "https://api.luka-lta.dev/api/v1/projects/3fa8.../assets/9c1b...", "alt": "Trackspire" },
  "cover": { "url": "...", "alt": null },
  "screenshots": [{ "id": "...", "url": "...", "alt": null, "sortOrder": 0 }],
  "createdAt": "2026-01-01T00:00:00Z",
  "updatedAt": "2026-01-01T00:00:00Z"
}
```

Eigene Response-DTO (`ProjectResource` o. ä.), **nicht** die interne Entity direkt serialisiert (Punkt "Trennung interner und veröffentlichter Daten" aus CLAUDE.md).

---

## D. MinIO

- Bucket: bestehender Bucket wiederverwenden (kein neuer), Prefix `projects/` zur Trennung von `avatars/`.
- Object Key: `projects/{projectId}/{type}/{assetId}.{ext}` — eindeutig, kollisionsfrei, nach Typ aufräumbar.
- Zugriff: ACL `public-read` wie beim Avatar, aber **Auslieferung weiterhin über PHP-Proxy** (s. C.6) statt direkter öffentlicher URL — Konsistenz + keine Notwendigkeit, Presigned URLs einzuführen, da bereits ein funktionierendes Proxy-Pattern existiert.
- Upload/Replace/Delete: s. C.4.

---

## E. Frontend — Admin-Dashboard (luka-lta-backend)

### E.1 API-Layer

```
src/api/projects/
├── schema.ts     (Zod: ProjectSchema, ProjectListSchema, ProjectAssetSchema,
                    ProjectTagSchema, CreateProjectInputSchema)
├── endpoints.ts   (getProjects, getProject, getManagedProjects, getManagedProject,
                    createProject, updateProject, deleteProject, reorderProjects,
                    uploadProjectAsset(projectId, type, formData), deleteProjectAsset)
└── hooks.ts       (useProjects, useProject, useManagedProjects, useManagedProject,
                    useCreateProject, useUpdateProject, useDeleteProject,
                    useReorderProjects, useUploadProjectAsset, useDeleteProjectAsset)

src/api/project-tags/
├── schema.ts     (Zod: ProjectTagSchema, ProjectTagListSchema)
├── endpoints.ts   (getProjectTags, createProjectTag)
└── hooks.ts       (useProjectTags, useCreateProjectTag)
```

Query-Key-Konvention wie Calendar: `["projects"]` bzw. `["project-tags"]` domain-weit, jede Mutation invalidiert ihren Domain-Key. `useCreateProjectTag` invalidiert `["project-tags"]`, damit ein neu angelegter Tag sofort im Autocomplete aller Formulare auftaucht.

### E.2 Sidebar

`sidebar-data.ts`: neue Gruppe `Portfolio-Management` mit Eintrag `Projects` → Route `/dashboard/projects`. Begründung s. A.6 — passt zur bestehenden Konvention eigenständiger `-Management`-Gruppen, nicht unter `Personal`.

### E.3 Projects-Management-Seite

**Entscheidung Cards statt Table:** Projekte haben ein visuelles Leitmerkmal (Logo), das Mockup in der Anfrage zeigt auch Cards. `UserTable` ist die richtige Vorlage für reine Datentabellen ohne starkes visuelles Element — hier nicht passend. Stattdessen: Card-Liste, aber **Wiederverwendung** der Bausteine aus `UserTable`: `DropdownMenu` (Edit/Delete-Aktionen), `TableSkeleton`-Äquivalent als Card-Skeleton, `Empty/EmptyMedia/EmptyTitle` für den Empty State, `Status`/`StatusIndicator` (kibo-ui) für den Status-Badge.

```
src/feature/project-management/
├── index.tsx                  (Seite: Header "+ Project"-Button, Card-Liste, DnD-Reorder)
├── components/
│   ├── ProjectCard.tsx
│   ├── ProjectCardSkeleton.tsx
│   ├── CreateProjectSheet.tsx
│   ├── EditProjectSheet.tsx
│   ├── DeleteProjectDialog.tsx     (AlertDialog)
│   └── ProjectAssetUpload.tsx      (generalisiert aus AvatarInput)
└── project-management-context.tsx  (currentRow/open-State wie bei users-context.tsx)
```

### E.4 Create/Edit

Sheet statt Dialog (Begründung: Feldanzahl > normale Dialog-Größe, Precedent `EditUserSheet`). Ein Formular-Schema (zod) für beide Richtungen (Create ohne `id`, Edit mit). Felder exakt wie unter B.1 (ohne `metadata`, das ist kein UI-Feld in v1). Logo/Cover-Upload über generalisierte `AvatarInput`-Variante, Screenshots als Mehrfach-Upload-Liste mit Sortierung.

**Tag-Feld — KiboUI Tags-Component** (https://www.kibo-ui.com/components/tags):

```
src/components/ui/kibo-ui/tags/   (per shadcn/KiboUI-Registry installieren, nicht handschreiben)
```

Verdrahtung in `CreateProjectSheet` / `EditProjectSheet`:
- `useProjectTags()` liefert den auswählbaren Pool (`TagsList` / `TagsItem`).
- Formular-State hält `tagIds: number[]`; ausgewählte Tags rendern als `TagsValue`-Chips mit Remove-Button.
- Das "Create a Tag"-Element der Component ruft `useCreateProjectTag().mutateAsync({ name })` auf. Da der Backend-Endpoint idempotent ist (s. C.1), liefert er bei bereits existierendem Namen den bestehenden Tag zurück — die zurückgegebene `id` wird in `tagIds` aufgenommen, kein Fehlerpfad nötig.
- Dadurch ist ein Tag sofort nach der Anlage auch für **andere** Projekte auswählbar (Wiederverwendung), ohne dass das Projekt gespeichert sein muss.
- Dritter Zustand: Pool leer → Component zeigt nur das Create-Eingabefeld; kein separater Empty-State nötig.

Tags sind in diesem Modell ein eigenständiger, projektübergreifender Pool — es gibt bewusst **keine** separate "Tag-Verwaltungsseite" in v1 (Umbenennen/Löschen von Tags wurde nicht gefordert; `DELETE /project-tags/{id}` lässt sich später nachrüsten, dann mit Prüfung auf vorhandene Assignments).

### E.5 Delete

`AlertDialog` mit dem in der Anfrage vorgegebenen Text ("Projekt löschen? ... Dabei werden auch zugehörige Projekt-Assets entfernt ..."). Kein Vorab-Check auf Fremd-Referenzen nötig — es gibt keine andere Entität im Backend, die auf `projects` verweist (neu eingeführte Tabelle, keine Bestandsreferenzen).

### E.6 Sortierung

`@dnd-kit` (bereits Dependency) für Drag-and-Drop in der Card-Liste, `onDragEnd` sammelt neue Reihenfolge, debounced Call an `useReorderProjects` (`PATCH /projects/order` mit `[{projectId, sortOrder}]`).

### E.7 Loading/Error/Empty

- Loading: `ProjectCardSkeleton` × 3-6.
- Error: Inline-Banner + "Erneut versuchen"-Button (`refetch()` aus React Query).
- Empty: `Empty/EmptyMedia/EmptyTitle` + Text "Noch keine Projekte" + "Projekt erstellen"-Button (öffnet `CreateProjectSheet`).

---

## F. Migration bestehender Projekte

1. Neues Symfony-Console-Command `src/Command/Project/ImportLegacyProjectsCommand.php` in `luka-lta-api`.
2. Die 6 Projekte aus `projects-data.ts` werden **manuell** als PHP-Array im Command hinterlegt (nur 6 Stück, kein Scraping nötig) — `id` → `slug` (Werte **unverändert** übernehmen, damit bestehende URLs/Bookmarks nicht brechen), `title` → `name`, Rest 1:1 nach B.1.
3. Lokale Bilddateien aus `luka-lta/public/static/images/projects/` müssen für den Import erreichbar sein (z. B. per Mount/Copy in ein Input-Verzeichnis im `luka-lta-api`-Container) — Command lädt sie über `ProjectAssetService::upload()` hoch:
   - alle Einträge aus `screenshots[]` → Asset-Typ `screenshot`, `sort_order` = Array-Index,
   - `screenshots[0]` wird **zusätzlich dupliziert** als Typ `cover` **und** als Typ `logo` (eigene MinIO-Objekte, eigene Keys — **entschieden**: duplizieren, nicht leer lassen, damit die Portfolio-Darstellung ab Minute eins vollständig ist und nichts manuell nachgepflegt werden muss). Logo/Cover können danach jederzeit über die neue UI durch echte Assets ersetzt werden; der Replace-Pfad löscht das duplizierte Objekt dabei sauber mit.
4. `status` für alle importierten Projekte auf `'active'`, `is_visible = true` setzen (entspricht dem heutigen Zustand: alle 6 sind live).
5. **Tags starten leer** — das Altmodell hat kein Tag-Feld (s. A.1), es wird nichts erfunden. `tech_stack` wird dagegen 1:1 aus `techStack` übernommen (eigenes Feld, kein Tag). Tags werden nach Go-Live über die neue UI gepflegt.
6. Nach erfolgreichem Import gegen Dev-DB: Abgleich-Test (Zeilenanzahl + Feldwerte == Quellarray).
7. Erst nach erfolgreichem Import **und** funktionierendem Frontend-Umbau (Abschnitt E/weiter unten) wird `projects-data.ts` gelöscht.

---

## G. Sicherheit

- Schreibende Routen (`POST`/`PATCH`/`DELETE` unter `/projects`) hinter `AuthMiddleware` — identisch zu Blog/Homelab/Calendar, kein neues Auth-System.
- Lesende Routen (`GET /projects`, `GET /projects/{slug}`) bewusst öffentlich — Portfolio-Seite ist unauthentifiziert, muss aber **serverseitig** auf `is_visible = 1` gefiltert werden (nie client-seitig filtern — sonst leaken verdeckte/archivierte Projekte über die Netzwerk-Response).
- Asset-Proxy-Route (`GET /projects/{id}/assets/{assetId}`) ebenfalls öffentlich (Bilder müssen ohne Login ladbar sein), aber liefert nur Bytes, keine MinIO-Credentials/Bucket-Infos.
- Upload-Validation serverseitig (MIME-Allowlist, Size-Limit) — Frontend-Validation ist nur UX, kein Sicherheitsmechanismus (Punkt 30/31 der Anfrage).
- CORS: `CorsResponseManager` wurde laut Git-Status gerade verändert — vor Implementierung prüfen, ob die Portfolio-Domain (`luka-lta.dev` o. ä.) in den erlaubten Origins für die neuen öffentlichen GET-Routen enthalten ist.

---

## H. Tests

### Backend (PHPUnit)
- Projekt erstellen (happy path + fehlende Pflichtfelder → 400)
- Slug-Uniqueness (zweites Projekt mit gleichem Slug → `ProjectSlugAlreadyExistsException` → 409)
- Projekt abrufen (öffentlich, nur sichtbare; `manage`-Route, auch unsichtbare)
- Projekt aktualisieren (Teilupdate via PATCH, `updated_at` ändert sich)
- Projekt löschen (inkl. Assert, dass `deleteObject` pro Asset aufgerufen wurde — Mock `S3Repository`)
- Reorder (Batch-Update, Reihenfolge korrekt persistiert)
- Asset-Upload: falscher MIME-Type → 400, zu groß → 400, erfolgreicher Upload → 201 + korrekter Object Key
- Asset-Replace (Logo zweimal hochladen → nur eine Zeile, altes Objekt gelöscht)
- Asset-Delete
- Tag anlegen (neu → 201 mit neuer ID; existierender Name → 200 mit **bestehender** ID, keine zweite Zeile)
- Tag-Name case-insensitive dedupliziert (`"Analytics"` nach `"analytics"` erzeugt keinen zweiten Tag)
- Tag-Zuordnung: Projekt mit `tagIds` speichern → Assignments korrekt, erneutes Speichern mit entferntem Tag löscht nur das Assignment, **nicht** den Tag im Dictionary
- Unbekannte `tagId` beim Projekt-Speichern → 404
- Projekt löschen → Assignments weg, Tags im Dictionary bleiben erhalten
- Authorization: Write-Routen und `/project-tags` ohne Token → 401
- Migration-Command: Zeilenanzahl + Feldwerte nach Import == Quellarray

### Frontend (Dashboard)
- Projekte laden (Loading-Skeleton → Daten)
- Error-State + Retry
- Empty-State + "Projekt erstellen"-CTA
- Create-Flow (Formular-Validation, Submit, Cache-Invalidierung, neue Karte erscheint ohne Reload)
- Edit-Flow (Werte vorbefüllt, Speichern aktualisiert Karte)
- Delete-Flow (Confirm-Dialog, Abbrechen tut nichts, Bestätigen entfernt Karte)
- Upload-Flow (Logo ersetzen, altes Bild verschwindet aus UI)
- Reorder (Drag ändert sichtbare Reihenfolge + persistiert)
- Deaktivierte Projekte bleiben in der Verwaltung sichtbar, aber Badge zeigt Status korrekt
- Tags: bestehenden Tag aus dem Pool auswählen, neuen Tag über "Create a Tag" anlegen (erscheint danach im Pool), Tag wieder entfernen

---

## I. Risiken / Breaking Changes

1. **Slug-Kontinuität:** Die heutigen `id`-Werte (z. B. `"luka-lta-api"`) müssen 1:1 als `slug` übernommen werden, sonst brechen bestehende `/project/:projectId`-Links. Muss im Migrations-Command hart verifiziert werden.
2. **Kein Logo im Altmodell** (entschieden): `screenshots[0]` wird beim Import als `logo` **und** `cover` dupliziert (s. F.3). Restrisiko: Ein Screenshot ist kein gutes Logo — die Darstellung ist initial technisch korrekt, aber optisch suboptimal, bis echte Logos über die UI hochgeladen werden. Bewusst akzeptiert, weil es jederzeit ohne Code-Änderung korrigierbar ist.
3. **react-query im Portfolio-Frontend** (entschieden): TanStack Query wird in `luka-lta` eingeführt, inklusive derselben Struktur wie im Dashboard (`src/api/<domain>/{schema,endpoints,hooks}.ts`, Zod-geparste Responses, Axios-Instanz). Zu prüfen beim Start von Schritt 10: ob `@tanstack/react-query` dort bereits als Dependency existiert (das Repo hat schon einen `src/api/github/`-Ordner mit react-query-ähnlichen Hooks — vermutlich ist Query bereits vorhanden und muss nur nachgenutzt werden). Falls der QueryClientProvider fehlt, wird er in `main.tsx`/`App.tsx` ergänzt.
4. **Bildmigration braucht Dateizugriff:** Der Import läuft im `luka-lta-api`-Backend, die Quellbilder liegen aber im `luka-lta`-Repo. Für die einmalige Migration müssen die Dateien dem API-Prozess verfügbar gemacht werden (lokal: einfacher Pfad-Mount; für Prod: einmaliger manueller Kopiervorgang).
5. **CORS-Lücke möglich:** Falls die öffentlichen `/projects`-Routen von der Portfolio-Domain aus unauthentifiziert aufgerufen werden und diese Domain noch nicht in `CorsResponseManager` gelistet ist, schlägt das im Browser fehl — vor Frontend-Umbau verifizieren.
6. **Reihenfolge wichtig:** Backend + Migration müssen vollständig stehen und verifiziert sein, **bevor** `projects-data.ts` entfernt wird — sonst Totalausfall der Portfolio-Seite.
7. **S3Repository-Erweiterung ungetestet:** `deleteObject()` existiert noch nicht, muss gegen echtes Dev-MinIO getestet werden (keine Mocks für den ersten Funktionsnachweis).

---

## Implementierungsreihenfolge

1. Datenmodell + Migrationsdateien (`projects.sql`, `project_assets.sql`, `project_tags.sql`, `project_tag_assignments.sql`, `zz_foreign_keys.sql` ergänzen) gegen Dev-DB.
2. Backend: Value Objects, Exceptions, Repositories (inkl. `ProjectTagRepository`).
3. `S3Repository::deleteObject()` + `ProjectAssetService` (Validation, Key-Building, Replace-Logik) — gegen echtes Dev-MinIO verifizieren.
4. Service-Layer (`ProjectService`, `ProjectTagService`) + Actions + Routing (inkl. `/project-tags`).
5. Backend-Tests (Abschnitt H).
6. Migrations-Command, Testlauf gegen Dev-DB inkl. Bild-Import (Screenshots + duplizierte Logo/Cover-Assets).
7. Dashboard: API-Layer (`src/api/projects/`, `src/api/project-tags/`).
8. Dashboard: KiboUI Tags-Component über die Registry installieren.
9. Dashboard: Sidebar-Eintrag, Projects-Management-Seite, Create/Edit-Sheet (inkl. Tags-Feld), Delete-Dialog, Upload-Komponente, Reorder.
10. Dashboard-Tests.
11. CORS-Check, danach Portfolio-Frontend (`luka-lta`) auf API umstellen: react-query-Layer anlegen, `Projects.tsx` und `feature/project/index.tsx` von `projects-data.ts` auf API umstellen (Routing weiterhin über `slug`).
12. Hardcode entfernen: `projects-data.ts` + lokale Bilddateien löschen, erst nach verifiziertem Parallelbetrieb.
13. End-to-End-Check (alle 6 Altprojekte identisch sichtbar wie vorher) + Cleanup.

---

## Entschiedene Punkte (Freigabe 2026-10-03)

- **Logo/Cover für Altprojekte:** `screenshots[0]` wird beim Import dupliziert (F.3).
- **Portfolio-Frontend:** react-query wird eingeführt, Struktur analog Dashboard (I.3).
- **Management-Darstellung:** Cards (E.3).
- **Tags:** relationale Tabellen `project_tags` + `project_tag_assignments` (B.3), wiederverwendbarer projektübergreifender Pool, UI über KiboUI Tags inkl. "Create a Tag" (E.4), Backend-Anlage idempotent (C.1).
