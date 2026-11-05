# local_vdsnoemail

Hält für die VdS Moodle-Instanz das Custom User Profile Field `noemail`
(Checkbox) aktuell: Jeder User mit einer `random-...@vds.de`-Adresse wird darin
markiert, und `local_recertify` nutzt dieses Feld als Kennzeichen für „User ohne
echte E-Mail-Adresse“.

## Unterstützte Moodle-Versionen

| Moodle-Version | Branch |
| -------------- | ------ |
| Moodle 4.5     | main   |

## Beschreibung

Vor `local_recertify` 1.2.0 wurde der „Blue Collar Worker“ anhand einer
hardcodierten Regex auf der E-Mail-Adresse erkannt
(`/^random-([0-9a-z]+)@vds\.de$/i`). Ab 1.2.0 ist diese Erkennung
generisch über ein konfigurierbares User-Profilfeld geregelt. Dieses Plugin
enthält die VdS-spezifische Regel und schreibt ihr Ergebnis in das Profilfeld.

Bei Installation und beim Upgrade auf 1.1.0 läuft eine idempotente Migration:

1. Existiert das Profilfeld `noemail` noch nicht, werden die Kategorie
   `Recertify` (falls nötig) und das Checkbox-Feld angelegt
   (`locked`, nur für Admins sichtbar).
2. Die Plugin-Einstellung `local_recertify/noemailprofilefield` wird auf
   `noemail` gesetzt, sofern sie noch leer ist. Ist dort ein anderes Feld
   konfiguriert, wird dieses verwendet.
3. Alle nicht gelöschten User werden abgeglichen: `random-...@vds.de` → `1`,
   bisher markierte User mit anderer Adresse → `0`.

Danach halten Event-Observer auf `\core\event\user_created` und
`\core\event\user_updated` das Feld aktuell. Neu angelegte generische Konten
werden also automatisch markiert, und ein User, der eine echte Adresse
bekommt, verliert die Markierung.

Hinweis: Die Markierung folgt immer der E-Mail-Adresse. Ein manuell gesetzter
Haken wird beim nächsten Speichern des Users überschrieben.

## Installation

### Per Git
    git clone <repo-url> <moodle-root>/local/vdsnoemail

### Manuell
1. ZIP herunterladen und entpacken.
2. Inhalt nach `<moodle-root>/local/vdsnoemail/` kopieren.
3. Site Administration > Notifications aufrufen, Migration läuft beim
   Installieren automatisch.

## Nach der Installation

Das Plugin muss installiert bleiben, damit neue und geänderte User markiert
werden. Wird es deinstalliert, bleiben Profilfeld und Werte erhalten (sie
gehören Moodle Core), werden aber nicht mehr aktualisiert.

User, die ohne Moodle-Events angelegt oder geändert werden (z. B. per direktem
SQL), werden erst beim nächsten Upgrade dieses Plugins erfasst.

## Anforderungen

- Moodle 4.5 oder höher
- PHP 8.1 oder höher
- `local_recertify` 1.2.0 (Version 2026042900) oder höher

## Lizenz

Licensed under the [GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html).
