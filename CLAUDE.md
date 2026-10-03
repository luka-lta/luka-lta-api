# CLAUDE.md

Leitfaden für Claude Code in diesem Repo. Diese Datei ist zugleich der **Gesamt-Überblick**
über das luka-lta-System (API + zwei Frontends).

## System-Überblick

Drei **getrennte Git-Repos** als Geschwister-Verzeichnisse unter `~/projects/` — kein Monorepo,
kein Workspace. Änderungen an mehreren Teilen brauchen mehrere Commits in mehreren Repos.

| Verzeichnis | Repo | Rolle |
|---|---|---|
| `luka-lta-api/` | `luka-lta-api` | PHP/Slim-API. Einzige Quelle der Wahrheit für Daten. |
| `luka-lta/` | `luka-lta-frontend` | Öffentliches Portfolio für potenzielle Kunden. → @../luka-lta/CLAUDE.md |
| `luka-lta-backend/` | `luka-lta-backend` | Interner Admin-Bereich. Trotz Name ein **Frontend**. → @../luka-lta-backend/CLAUDE.md |

Verzeichnisname ≠ Repo-Name beim Portfolio (`luka-lta` → `luka-lta-frontend`). `luka-lta-backend`
enthält **keinen** Backend-Code.

## Wie die Teile zusammenspielen

Beide Frontends sprechen über `VITE_API_URL` gegen `/api/v1`. Zwei Auth-Wege, jeweils mit
zwingendem `Origin`-Header:

| Aufrufer | Header | Middleware | Reicht für |
|---|---|---|---|
| Portfolio | `X-API-Key` + `Origin` | `ApiKeyPermissionMiddleware` (Permission-Name pro Route-Gruppe, Origin muss zum Key passen) | nur explizit gegroupte Ingest-Routen |
| Portfolio (Rest) | — | keine | öffentliche Routen: `GET /projects`, `GET /projects/{slug}`, `GET /blog*`, `GET /linkCollection*`, `POST /click/track/{tag}` |
| Admin | `Authorization` + `Origin` | `AuthMiddleware` | alle geschützten Gruppen |

JWT wird **roh** im `Authorization`-Header erwartet — **kein** `Bearer`-Präfix.
Es gibt keine geteilten Typen oder Packages: Zod-Schemas werden in beiden Frontends
unabhängig gepflegt und müssen bei API-Änderungen in **beiden** nachgezogen werden.

## Projektübergreifende Regeln

- Frontends immer **Vite + TypeScript**, `strict` bleibt an, **kein `any`** (auch nicht `as any`).
  Unbekanntes als `unknown` typisieren und parsen.
- API-Antworten an der Grenze mit Zod parsen, nicht blind casten.
- Keine neuen Dependencies ohne Rückfrage — in keinem der drei Repos.
- Env-Variablen nur per Name dokumentieren, Werte nie in Code/Doku/Commit.
- Breaking API-Änderung: betroffene Frontends im selben Arbeitsgang mitziehen, sonst explizit melden.

### Env-Variablennamen (Werte nie dokumentieren)

- Portfolio: `VITE_API_URL`, `VITE_API_KEY`, `VITE_API_LOGIN_ROUTE`, `VITE_G_TAG`, `VITE_SCRIPT_URL`, `VITE_SITE_ID`, `VITE_TRACKSPIRE_HOST`; Vercel-Function: `SMTP_USER`, `SMTP_PASS`, `CONTACT_TO`
- Admin: `VITE_API_URL`
- API (Container-Env): `MYSQL_*`, `REDIS_*`, `JWT_SECRET`, `JWT_NORMAL_EXPIRATION_TIME`, `JWT_EXTENDED_EXPIRATION_TIME`, `LOG_LEVEL`, `LOG_FILE_PATH`, `AWS_*`, `TELEGRAM_BOT_TOKEN`, `API_BASE_URL`

---

# luka-lta-api

**PHP 8.4 / Slim 4**, PSR-4 `LukaLtaApi\` → `src/`. DI über PHP-DI.

## Befehle

```bash
just dev            # docker-compose.development.yml up
just stop
just install        # composer install (host)
just lint-cs        # phpcs — DAS Lint-Gate
just lint-path src/Api/Project   # phpcs auf Teilpfade, ohne Altlast-Rauschen
just build [development|production]
```

`just lint` **bricht ab**: phpmd läuft nicht unter PHP 8.4 (pdepend-Inkompatibilität), phpcs
kommt danach nicht mehr dran. Für neuen Code `just lint-path <pfad>` nutzen — `src/` enthält
Altbestand mit Line-Length-Warnings.

Einzelbefehl im Container: `docker compose -f docker-compose.development.yml run --rm php-fpm-api <cmd>`
— Service heißt `php-fpm-api`, nicht `php-fpm`.

## Request-Lifecycle

`public/index.php` → `SlimFactory` → `RouteMiddlewareCollector::registerApiRoutes()` →
`Action` (erbt `ApiAction`) → `Service` → `Repository`.
`ApiAction::__invoke` fängt `ApiException` (HTTP-Code aus `getCode()`) und sonst `Throwable` → 500.
Antworten via `ApiResult::from(ResultInterface, $status)->getResponse($response)`.

## Verzeichnis-Map

| Pfad | Zweck |
|---|---|
| `src/Api/<Domain>/Action/` | ein Action-Objekt pro Endpoint |
| `src/Api/<Domain>/Service/` | Logik der Domain; Actions bleiben dünn (Request parsen, Response bauen) |
| `src/Repository/` | sämtlicher DB-/Redis-/S3-Zugriff, flach, kein Domain-Subdir |
| `src/Value/` | immutable Value Objects, named constructors (`from`, `fromArray`) |
| `src/Exception/` | müssen `ApiException` erben, sonst wird daraus ein 500 |
| `src/Command/<Domain>/` | Symfony-Console, Einstieg `bin/app.php`, Zeitplan in `crontab` |
| `data/mysql/*.sql` | Migrations |

## Gotchas

- **Routen-Reihenfolge**: statische Segmente müssen vor Catch-all-Parametern registriert werden.
  `/projects/manage`, `/projects/order`, `/projects/tags` stehen bewusst **vor** `GET /projects/{slug}`.
  Neue statische `/projects/<x>`-Route → den Namen zusätzlich in die Reserved-Slug-Prüfung des
  `ProjectService` aufnehmen, sonst beschattet ein angelegter Slug die Route.
- **Migrations** laufen über `docker-entrypoint-initdb.d` in **alphabetischer** Dateinamen-Reihenfolge:
  erst `01_`–`05_`, dann alles Unnummerierte alphabetisch. Bei FK-Abhängigkeiten Namen entsprechend
  wählen. Sie laufen nur bei leerem Volume — Schema-Änderung heißt lokal `docker volume rm`.
- **CORS** antwortet `Access-Control-Allow-Origin: *` mit `Allow-Headers: *`, obwohl Auth
  Origin-gebunden prüft. Nicht die Autorisierungsgrenze — die liegt in den Middlewares.
- Datenstores: MySQL (Primär), Redis (Session-/Link-Caching), MinIO/S3 (Avatare **und**
  Projekt-Assets unter `projects/`-Prefix im Bucket `avatars`). **Kein ClickHouse** —
  Web-Tracking liegt im separaten `trackspire`-Projekt.
- Zeitstempel gehen als SQL-UTC ohne Zonen-Marker raus (`yyyy-MM-dd HH:mm:ss`). Clients parsen
  das als UTC — Format nicht einseitig ändern.
- Längere Design-Dokumente liegen in `docs/plans/` und sind Momentaufnahmen, kein Ist-Zustand.

## Nicht tun

- Keine neuen Dependencies ohne Rückfrage (`composer.json` / `package.json`).
- Keine Zugangsdaten, Keys oder Kundendaten in Code, Doku oder Commit — auch nicht als "Beispiel".
- `docker-compose.*.yml` und `crontab` nicht nebenbei umbauen; Secrets dort nie durch echte ersetzen.
- Kein `SELECT *`, keine Logik in Actions, keine Exception ohne `ApiException`-Basis.
- Keine Migration rückwirkend ändern — neue Datei anlegen.
- Nicht `just lint` als Erfolgsnachweis zitieren (bricht ab); `just lint-path` nutzen.
