# Homelab-Topologie: kuratiert per MCP, Draft → Review → Approve

Stand: 2026-11-15 · Momentaufnahme, kein Ist-Zustand.

## Ziel

Die Topologie-Karte zeigt nur noch **kuratierte** Beziehungen. Claude liest das Setup über einen
MCP-Endpoint der API, erstellt einen **Draft** (Proposal) und der Nutzer prüft und approvt ihn im
Admin-Dashboard. Automatische Kanten-Erkennung entfällt vollständig.

## Entscheidungen

| Thema | Entscheidung |
|---|---|
| Kanten | nur manuell/kuratiert; `TopologyGraphBuilder` berechnet keine Kanten mehr |
| Hosts/Container | bleiben Agent-Daten, erscheinen in der Karte **nur**, wenn ein kuratierter Knoten sie referenziert; Status live |
| MCP-Ort | in der API, HTTP (`/api/v1/mcp`), Streamable-HTTP-Transport |
| Schreibrecht MCP | nur Proposals anlegen — kein Approve, kein direkter Schreibzugriff auf die Topologie |
| Approve | nur im Admin-Dashboard (JWT), pro Item oder gesamt |

## Datenmodell

### Stabile Identität

Docker-Container-IDs ändern sich bei jedem Recreate. Kuratierte Daten referenzieren deshalb einen
**Service-Schlüssel**:

- `compose.project::compose.service` aus den Labels, sonst Containername.
- Neue Spalte `homelab_containers.service_key` (beim Ingest abgeleitet, indexiert).
- Auflösung `service_key` → aktueller Container (für Live-Status) beim Graph-Build.

### Kuratierte Topologie

Bestehende Tabellen `homelab_topology_nodes` / `homelab_topology_edges` werden zur einzigen Quelle.
Ein Knoten kann ein reiner Knoten sein (Internet, Cloudflare, Domain) oder eine Referenz:

- `ref_type` enum(`none`, `host`, `service`) + `ref_id` (`host_id` bzw. `service_key`).
- Kanten verbinden nur noch Topologie-Knoten (`source_node_id`, `target_node_id`), nicht mehr
  polymorphe Host-/Container-IDs. Dadurch bleiben Kanten beim Redeploy gültig.
- Status: Referenz-Knoten übernehmen den Live-Status des Hosts/Containers; nicht auflösbare Referenz
  → `unknown` + Hinweis „Referenz verwaist“.

Migration: neue Datei (bestehende nicht anfassen); vorhandene manuelle Kanten werden, wo möglich,
auf Knoten umgeschrieben, sonst verworfen (aktuell ein Host, wenige manuelle Einträge — vorher
prüfen und im PR nennen).

### Proposals

```
homelab_topology_proposals
  proposal_id, title, summary, status enum(draft, applied, rejected, superseded),
  created_by (z. B. "mcp:<api-key-name>"), created_at, reviewed_at

homelab_topology_proposal_items
  item_id, proposal_id, operation enum(add_node, update_node, remove_node, add_edge, remove_edge),
  payload json, reasoning text, evidence json, status enum(pending, accepted, rejected)
```

- `reasoning`/`evidence` sind Pflicht — der Reviewer sieht, **warum** Claude eine Kante vorschlägt
  (z. B. Label `traefik.http.routers.grafana.rule`, Env-Key `MYSQL_HOST`).
- Items referenzieren Knoten per stabiler ID oder per temporärem Schlüssel innerhalb des Proposals
  (für „neuer Knoten + Kante dorthin“ im selben Draft).
- Apply läuft in einer Transaktion; Items, die beim Apply nicht mehr passen (Knoten gelöscht),
  scheitern einzeln mit Grund, statt das ganze Proposal zu kippen.
- Neues Proposal markiert ältere offene Drafts als `superseded` (genau ein offener Draft).

## API

### Admin (JWT, `AuthMiddleware`)

- `GET /homelab/topology` — kuratierter Graph + Live-Status
- `GET /homelab/topology/proposals` · `GET /homelab/topology/proposals/{id}`
- `POST /homelab/topology/proposals/{id}/apply` — Body: akzeptierte Item-IDs
- `POST /homelab/topology/proposals/{id}/reject`
- bestehende Node/Edge-CRUD bleibt für Handkorrekturen

### MCP (`/mcp`, API-Key + Permission `Manage Homelab Topology Proposals`)

Tools:

| Tool | Art | Inhalt |
|---|---|---|
| `list_hosts` | read | Hosts mit Name, Typ, Status |
| `list_services` | read | Container je Host: `service_key`, Image, Status, Netze, Ports, Labels, Env-**Keys** |
| `get_topology` | read | aktueller kuratierter Graph |
| `get_open_proposal` | read | offener Draft inkl. Items |
| `create_proposal` | write | Titel, Zusammenfassung, Items mit Begründung |

Bewusst **nicht**: Apply, Delete, direkte Node/Edge-Writes.

## Sicherheit

- **Keine Env-Werte über MCP.** Nur Keys. Der Agent schwärzt bisher nur nach Key-Muster
  (`collector.py`, `SECRET_KEY_PATTERN`) — DSNs wie `DATABASE_URL=mysql://user:pass@host` rutschen
  durch. MCP liefert daher grundsätzlich nur Keys; optional Host-Teil aus URL-Werten ohne Userinfo.
- Labels können ebenfalls Geheimnisse enthalten (z. B. `basicauth.users`): Labels mit
  Secret-Muster im Key schwärzen.
- Eigener API-Key, eigene Permission; Origin-Bindung wie bei den anderen API-Keys prüfen
  (MCP-Clients senden ggf. keinen `Origin` — Verhalten der Middleware klären, nicht aufweichen
  ohne Entscheidung).
- Proposal-Inhalte sind untrusted: Typen/Relationen serverseitig gegen Whitelist validieren,
  Längen begrenzen, im Frontend nur escaped rendern.
- claude.ai-Remote-Connectoren verlangen OAuth; mit API-Key-Header funktioniert der Endpoint aus
  Claude Code (`claude mcp add --transport http … --header`). OAuth ist out of scope.

## MCP-Implementierung

Offizielles SDK `mcp/sdk` (v0.10), Streamable-HTTP-Transport direkt in Slim (`HomelabMcpAction`).
Ohne Session-Store (sessionless): jeder Request ist eigenständig, kein Redis nötig, `GET`/`DELETE` → 405.
SDK-Standard-Middleware (CORS, DNS-Rebinding) ist per `PassthroughMiddleware` ersetzt — Zugriff nur mit
API-Key-Header, CORS setzt die API selbst. Tool-Handler rufen dieselben Services wie das Dashboard.

Einrichtung (Header kommt vom Client):

```
claude mcp add --transport http homelab https://<api-host>/api/v1/homelab/mcp \
  --header 'X-API-Key: <api-key>' --header 'Origin: <key-origin>'
```

API-Key mit Permission `Propose Homelab Topology` (ID 101) im Dashboard anlegen.

## Admin-Dashboard (`luka-lta-backend`)

- Proposal-Banner auf der Topologie-Seite („Draft von Claude: 12 Änderungen“).
- Review-Modus: Karte zeigt Draft als Overlay (neu = grün, entfernt = rot gestrichelt).
- Seitenleiste: Items mit Begründung/Evidenz, Checkbox je Item, „Übernehmen“ / „Ablehnen“.
- Host-Filter (Multi-Select) und Hosts als Gruppen-Knoten statt `hosted_on`-Kanten.
- Zod-Schemas für Proposal/Item neu; Topologie-Schema an Knoten-Kanten anpassen.

## Was entfällt

- `TopologyGraphBuilder::buildHostedOnEdges/buildNetworkEdges/buildDependsOnEdges/buildRoutesToEdges`
- `homelab_containers.node_role` + `PATCH /homelab/containers/{id}/role` (Rolle = Typ des
  kuratierten Knotens)
- Labels/Netze bleiben gespeichert — als Lesestoff für Claude, nicht für den Graph.

## Reihenfolge

1. Migration: `service_key`, Knoten-Referenzen, Kanten auf Knoten, Proposal-Tabellen
2. Graph-Build auf kuratiert umstellen, Auto-Kanten entfernen; Frontend-Schema im selben Zug
   (Breaking Change)
3. Proposal-Service + Admin-Endpoints
4. MCP-Endpoint + Tools + API-Key-Permission
5. Review-UI + Host-Filter im Dashboard
6. Erster echter Durchlauf: Claude liest Ansible-Repo + MCP, erstellt Draft, Review

## Offen

- Draft als Overlay direkt auf der Karte (aktuell Liste im Review-Panel).
- Migrationen laufen auf frischem Volume nicht durch (`04_` ändert eine Tabelle vor deren Anlage) —
  Altlast, unabhängig von diesem Feature.
