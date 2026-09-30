## Version actuelle: 2.1 [2023-09-14]

Compatible avec Dolibarr v. 7.X-18.X

## Description

Fonction principale : activez un second facteur pour l'authentification utilisateur lors de la connexion à Dolibarr avec le TOTP standard (Time-based One-Time-Password), compatible avec les générateurs TOTPs tels que Authy, Google Authenticator, Aegis pour Android, etc...

Fonctionnalités:

- Il affiche sur la page de connexion Dolibarr habituelle un troisième champ de saisie pour entrer un code TOTP à 6 chiffres (code temporaire à utiliser une seule fois).
- Ce 3ème champ doit être renseigné par les utilisateurs ayant activé ce système à Deux Facteurs (2FA).
- C'est donc un code à 6 chiffres facultatif : certains utilisateurs peuvent l'avoir activé, d'autres non.
- Seul l'utilisateur concerné peut activer le 2FA. Les utilisateurs admin ne peuvent pas le faire.
- Les utilisateurs admin (ou les utilisateurs ayant des permissions attribuées sur d'autres utilisateurs) peuvent SEULEMENT savoir quels autres utilisateurs ont activé le 2FA et le désactiver.
- Le seul à pouvoir voir la clé secrète TOTP est l'utilisateur concerné.
- Le module affiche toujours à l'utilisateur sa clé secrète et le QR code à scanner avec une application mobile.
- Lors de l'activation du 2FA pour votre utilisateur, vous pouvez définir manuellement votre clé secrète TOTP, particulièrement utile pour administrer plusieurs instances Dolibarr.
- Optionnellement, vous pouvez définir une période (1 jour/semaine/mois) pour mémoriser un appareil connecté avec succès, sans redemander le TOTP pendant cette période.
- Chaque utilisateur peut activer la possibilité de demander l'envoi du code à 6 chiffres à son e-mail depuis la page de connexion.
- Il utilise le module MaxMind natif de Dolibarr pour la géolocalisation IP pour appliquer des filtres de visiteurs basés sur leur pays. Cela vous permet d'établir une liste blanche de pays considérés comme des sources valides pour votre système Dolibarr. Les demandes provenant d'autres pays seront refusées.

Mon principal souci - du moins dans cette première version - a été de garder le système AUSSI SIMPLE QUE POSSIBLE. Facile mais sûr/privé.

Vos commentaires et suggestions sont toujours les bienvenus!

Une fois que vous avez acheté ce module, vous pourrez télécharger toute mise à jour à l'avenir, à jamais.

## Traductions des langues de l'interface

Jusqu'à maintenant : Anglais / Catalan / Espagnol / Français / Allemand

Vos traductions sont les bienvenues.

## Installation

Comme d'habitude pour tout autre module de Dolibarr. Recommandé dans le répertoire /htdocs/custom.

Note : vous voudrez probablement visiter les Paramètres du module pour définir une période pour mémoriser temporairement les appareils connectés (1 jour/semaine/mois). Je ne donne pas l'option de se souvenir "pour toujours" car il est pratique de demander le code TOTP à 6 chiffres chaque X temps, pour augmenter la sécurité et pour vous aider à ne pas perdre votre application génératrice de TOTPs ;-)

## Mise à jour

1. remplacez les fichiers PHP existants dans le répertoire /htdocs/custom/totp2fa
2. allez à Paramètres > Modules puis désactivez et réactivez à nouveau le module, cela exécutera des modifications sur la base de données si nécessaire
3. visitez les paramètres de ce module et sauvegardez au moins une fois les paramètres avec la nouvelle configuration. Cela conservera les options existantes mais en ajoutera probablement de nouvelles.

## Guide de l'utilisateur

- Anglais : https://imasdeweb.com/index.php?pag=m_blog&gad=detalle_entrada&entry=89
- Espagnol : https://imasdeweb.com/index.php?pag=m_blog&gad=detalle_entrada&entry=88

## Licence

LICENCE : GPL v3

Ce programme est un logiciel libre; vous pouvez le redistribuer et/ou
le modifier selon les termes de la GNU General Public License
telle que publiée par la Free Software Foundation ; soit la version 3
de la licence, ou (à votre choix) toute version ultérieure.

Ce programme est distribué dans l'espoir qu'il sera utile,
mais SANS AUCUNE GARANTIE ; sans même la garantie implicite de
QUALITÉ MARCHANDE ou D'ADÉQUATION À UN USAGE PARTICULIER. Voir le
GNU General Public License pour plus de détails.

Vous devriez avoir reçu une copie de la GNU General Public License
avec ce programme ; sinon, écrivez à la Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

## Historique des versions

Voir le fichier **ChangeLog.md** ou consulter le fichier [ChangeLog.md](ChangeLog.md).

