## Aktuelle Version: 2.1 [2023-09-14]

Kompatibel mit Dolibarr v. 7.X-18.X

## Beschreibung

Hauptfunktion: Aktivierung eines zweiten Faktors zur Benutzerauthentifizierung beim Anmelden bei Dolibarr mit dem Standard TOTP (Time-based One-Time-Password), kompatibel mit TOTP-Generatoren wie Authy, Google Authenticator, Aegis für Android usw...

Funktionen:

- Auf der üblichen Dolibarr-Anmeldeseite wird ein drittes Texteingabefeld angezeigt, um einen 6-stelligen TOTP-Code einzugeben (temporärer Code, der nur einmal verwendet wird).
- Dieses 3. Feld muss von Benutzern ausgefüllt werden, die dieses Zwei-Faktor-System (2FA) aktiviert haben.
- Es handelt sich also um einen optionalen 6-stelligen Code: Einige Benutzer können ihn aktiviert haben, andere nicht.
- Nur der Benutzer selbst kann das 2FA aktivieren. Admin-Benutzer können dies nicht tun.
- Admin-Benutzer (oder Benutzer mit zugewiesenen Berechtigungen über andere Benutzer) können NUR sehen, welche anderen Benutzer das 2FA aktiviert haben und es deaktivieren.
- Der Einzige, der den TOTP-Geheimschlüssel sehen kann, ist der entsprechende Benutzer.
- Das Modul zeigt dem Benutzer immer seinen Geheimschlüssel und den QR-Code zum Scannen mit einer mobilen App an.
- Bei der Aktivierung des 2FA für Ihren Benutzer können Sie Ihren geheimen TOTP-Schlüssel manuell festlegen, besonders nützlich, um mehrere Dolibarr-Instanzen zu verwalten.
- Optional können Sie einen Zeitraum festlegen (1 Tag/Woche/Monat), um ein erfolgreich angemeldetes Gerät zu speichern, sodass der TOTP während dieser Zeit nicht erneut abgefragt wird.
- Jeder Benutzer kann die Möglichkeit aktivieren, das Senden des 6-stelligen Codes an ihre E-Mail von der Anmeldeseite aus anzufordern.
- Es verwendet das native MaxMind-Modul von Dolibarr für die IP-Geolokalisierung, um Besucherfilter auf Basis ihres Landes anzuwenden. Damit können Sie eine Whitelist von Ländern erstellen, die als gültige Quellen für Ihr Dolibarr-System gelten. Anfragen aus anderen Ländern werden abgelehnt.

Mein Hauptanliegen -zumindest in dieser ersten Version- war es, das System SO EINFACH WIE MÖGLICH zu halten. Einfach, aber sicher/privat.

Ihre Kommentare und Vorschläge sind immer willkommen!

Nach dem Kauf dieses Moduls können Sie in der Zukunft beliebige Updates herunterladen, für immer.

## Übersetzungen der Benutzeroberfläche

Bis jetzt: Englisch / Katalanisch / Spanisch / Französisch / Deutsch

Ihre Übersetzungen sind willkommen.

## Installation

Wie bei jedem anderen Dolibarr-Modul. Empfohlen im Verzeichnis /htdocs/custom.

Hinweis: Sie möchten wahrscheinlich die Einstellungen des Moduls besuchen, um einen Zeitraum festzulegen, in dem angemeldete Geräte temporär gespeichert werden (1 Tag/Woche/Monat). Ich biete nicht die Option "für immer speichern", weil es sinnvoll ist, den TOTP 6-stelligen Code von Zeit zu Zeit abzufragen, um die Sicherheit zu erhöhen und Ihnen zu helfen, Ihre TOTP-generierende App nicht zu verlieren ;-)

## Update

1. Ersetzen Sie die vorhandenen PHP-Dateien im Verzeichnis /htdocs/custom/totp2fa
2. Gehen Sie zu Einstellungen > Module und deaktivieren und aktivieren Sie dann das Modul erneut, um bei Bedarf Änderungen in der Datenbank durchzuführen.
3. Besuchen Sie die Einstellungen dieses Moduls und speichern Sie mindestens einmal die Einstellungen mit einer neuen Konfiguration. Die bestehenden Optionen werden beibehalten, aber es werden wahrscheinlich neue hinzugefügt.

## Benutzerhandbuch

- Englisch: https://imasdeweb.com/index.php?pag=m_blog&gad=detalle_entrada&entry=89
- Spanisch: https://imasdeweb.com/index.php?pag=m_blog&gad=detalle_entrada&entry=88

## Lizenz

LIZENZ: GPL v3

Dieses Programm ist freie Software; Sie können es weiterverbreiten und/oder
modifizieren unter den Bedingungen der GNU General Public License,
wie sie von der Free Software Foundation veröffentlicht wird; entweder Version 3
der Lizenz oder (nach Ihrer Wahl) eine spätere Version.

Dieses Programm wird in der Hoffnung verteilt, dass es nützlich sein wird,
aber OHNE JEGLICHE GARANTIE; sogar ohne die implizite Garantie der
MARKTFÄHIGKEIT oder EIGNUNG FÜR EINEN BESTIMMTEN ZWECK. Siehe die
GNU General Public License für weitere Details.

Sie sollten eine Kopie der GNU General Public License
zusammen mit diesem Programm erhalten haben. Wenn nicht, schreiben Sie an die Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

## Versionsprotokoll

Siehe Datei **ChangeLog.md** oder schauen Sie in die [ChangeLog.md](ChangeLog.md) Datei.
