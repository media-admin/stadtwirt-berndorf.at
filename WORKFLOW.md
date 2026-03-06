# Development Workflow

**Version:** 1.4.0 | **Letzte Aktualisierung:** 2026-03-04

---

## Täglicher Workflow

```bash
# 1. Dev-Server starten
npm run dev

# 2. Entwickeln (SCSS/JS/PHP ändern → Browser aktualisiert automatisch)

# 3. Vor Commit: Production-Build testen
npm run build

# 4. Committen
git add .
git commit -m "feat: kurze Beschreibung"
git push
```

---

## Branches

| Branch | Zweck |
|---|---|
| `main` | Produktionsstand – nur via Merge |
| `feature/*` | Neue Features |
| `fix/*` | Bugfixes |
| `hotfix/*` | Kritische Fixes direkt auf main |

```bash
# Feature starten
git checkout -b feature/mein-feature

# Nach Fertigstellung
git checkout main
git merge feature/mein-feature
git push
```

---

## Commit-Konvention

```
release: v1.4.0          Neues Release
feat: hero-video Support  Neues Feature
fix: modal schliesst nicht Bugfix
security: nonce rotation  Security-Fix
refactor: scss cleanup    Kein Feature-Change
chore: deps aktualisiert  Dependencies, Cleanup
docs: README aktualisiert Nur Dokumentation
```

---

## Release-Prozess

```bash
# 1. Versionen bumpen (alle drei Stellen!)
#    package.json                                          → "version": "X.Y.Z"
#    themes/custom-theme/style.css                        → Version: X.Y.Z
#    plugins/media-lab-agency-core/media-lab-agency-core.php → Version: X.Y.Z + Konstante

# 2. CHANGELOG.md – neuen Abschnitt hinzufügen

# 3. Build testen
npm run build

# 4. Commit & Tag
git add -A
git commit -m "release: vX.Y.Z"
git tag -a vX.Y.Z -m "Version X.Y.Z – Kurzbeschreibung"
git push origin main --follow-tags
```

---

## Neues Kundenprojekt

```bash
# Automatisch via Script
./scripts/setup-project.sh

# Oder manuell:
# 1. Repo klonen / forken
# 2. media-lab-agency-core unverändert lassen
# 3. custom-theme umbenennen und anpassen
# 4. SMTP in wp-config.php konfigurieren
# 5. npm run build
# 6. Deployment via scripts/deploy-production.js
```

Vollständige Anleitung: [docs/10_DEPLOYMENT.md](docs/10_DEPLOYMENT.md)

---

## Design-Updates (Figma → Code)

```bash
# 1. Design-Tokens prüfen
#    cms/wp-content/themes/custom-theme/assets/src/scss/abstracts/_variables.scss

# 2. Tokens aktualisieren (Farben, Spacing, Typografie)

# 3. Betroffene Komponenten anpassen

# 4. Build + visuell prüfen
npm run build

# 5. Committen
git commit -m "feat: design tokens Q1 2026 update"
```

---

## Troubleshooting

### Build schlägt fehl

```bash
rm -rf node_modules
npm install
npm run build
```

### Theme nicht aktiv

```bash
cd cms
wp theme activate custom-theme
wp cache flush
```

### SCSS-Variable nicht gefunden

Prüfen ob `@use '../abstracts' as *;` am Anfang des Partials steht.

### console.log taucht in Production auf

`npm run build` ausführen – Terser entfernt sie. Im Dev-Server (`npm run dev`) bleiben sie bewusst erhalten.

---

Mehr Details: [docs/06_DEVELOPMENT.md](docs/06_DEVELOPMENT.md) | [docs/07_TROUBLESHOOTING.md](docs/07_TROUBLESHOOTING.md)
