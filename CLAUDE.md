# CLAUDE.md

Anweisungen für Claude Code in diesem Projekt (PHP-Website für Turnierverwaltung).

## Kanarienvogel-Regel
Beginne **jede** Antwort an den Nutzer mit der Nennung des Namens "Richard" (z. B. als Anrede im ersten Satz). Grund: Der Nutzer nutzt dies als Indikator dafür, ob diese CLAUDE.md-Anweisungen noch zuverlässig befolgt werden. Fehlt der Name in einer Antwort, ist das für ihn das Signal, dass die Session zu lang geworden ist und er eine neue Session starten sollte. Diese Regel gilt für jede Antwort, unabhängig von Thema oder Länge, und hat Vorrang vor sonstigen Kürze-Vorgaben.

## Git-Workflow (automatisch, ohne Rückfrage)
Nach **jeder** inhaltlichen Änderung an Dateien in diesem Projekt:
1. Geänderte Dateien gezielt stagen (`git add <dateien>`, kein `git add -A`, keine Secrets/`local_secrets/`-Inhalte mitcommitten)
2. Commit mit kurzer, aussagekräftiger Message erstellen
3. `git push` zum aktuellen Branch auf `origin` durchführen

Diese Anweisung autorisiert Commit + Push im Voraus – keine erneute Rückfrage nötig, außer bei ungewöhnlichen Fällen (z. B. Merge-Konflikte, nötiger Force-Push, versehentlich gefundene Secrets). Das Remote-Repo gehört zur Organisation "MyTournament" – bei Unsicherheiten, ob eine Änderung dorthin gepusht werden soll (z. B. weil andere Personen im Repo mitarbeiten), im Zweifel kurz nachfragen.

## Build
Kein Build-Schritt nötig – klassisches PHP-Projekt ohne Compile-/Bundle-Schritt.

## Play Store / App Store
Nicht anwendbar – dieses Projekt ist kein App-Store-Produkt.
