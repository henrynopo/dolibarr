## Versió actual: 2.1 [2023-09-14]

Compatible amb Dolibarr v. 7.X-18.X

## Descripció

Funció principal: activa un segon factor d'autenticació per a l'usuari quan inicia sessió a Dolibarr amb el TOTP estàndard (Time-based One-Time-Password), compatible amb els generadors TOTP Authy, Google Authenticator, Aegis per a Android, etc...

Característiques:

- Mostra a la pàgina d'inici de sessió habitual de Dolibarr un tercer camp de text per introduir un codi TOTP de 6 dígits (codi temporal per utilitzar només una vegada).
- Aquest 3r control ha de ser omplert pels usuaris que tinguin activat aquest sistema de Doble Factor (2FA).
- Així, és un codi opcional de 6 dígits: alguns usuaris poden tenir-ho activat, però altres no.
- Només és possible activar el 2FA pel mateix usuari. Els usuaris administradors no poden fer-ho.
- Els usuaris administradors (o usuaris amb permisos assignats sobre altres usuaris) NOMÉS poden saber quins altres usuaris han activat el 2FA i desactivar-ho.
- L'únic que pot veure la clau secreta TOTP és l'usuari corresponent.
- El mòdul sempre mostra a l'usuari la seva clau secreta i el codi QR per ser escanejat amb una aplicació mòbil.
- Quan s'activa el 2FA per al teu usuari pots establir manualment la teva clau secreta TOTP, especialment útil per administrar diverses instàncies de Dolibarr.
- Opcionalment, pots establir un període de temps (1 dia/setmana/mes) per recordar un dispositiu que ha iniciat sessió amb èxit, sense demanar el TOTP de nou durant aquest temps.
- Cada usuari pot activar la possibilitat de poder sol·licitar l'enviament del codi de 6 dígits al seu correu electrònic des de la pàgina d'inici de sessió.
- Utilitza el mòdul nadiu MaxMind de Dolibarr per a geolocalització IP per aplicar filtres de visitants basats en el seu país. Això et permet establir una llista blanca de països que es consideren fonts vàlides per al teu sistema Dolibarr. Les sol·licituds provinents d'altres països seran denegades.

La meva principal preocupació -almenys en aquesta primera versió- ha estat mantenir el sistema EL MÉS SENZILL POSSIBLE. Fàcil però segur/privat.

Els vostres comentaris i suggeriments són sempre benvinguts!

Una vegada compri aquest mòdul podràs descarregar qualsevol actualització en el futur, per sempre.

## Traduccions de l'interfície

Fins ara: Anglès / Català / Espanyol / Francès / Alemany

Les vostres traduccions són benvingudes.

## Instal·lació

Lo habitual per a qualsevol altre mòdul de Dolibarr. Recomanat al directori /htdocs/custom.

Nota: probablement voldràs visitar la configuració del mòdul per establir un període de temps per recordar temporalment els dispositius iniciats (1 dia/setmana/mes). No dono l'opció de recordar "per sempre" perquè és convenient demanar el codi TOTP de 6 dígits cada X temps, per augmentar la seguretat i ajudar-te a no perdre la teva aplicació generadora de TOTPs ;-)

## Actualització

1. reemplaça els fitxers PHP existents al directori /htdocs/custom/totp2fa
2. ves a Configuració > Mòduls i després desactiva i activa de nou el mòdul, això executarà canvis a la base de dades si cal
3. visita la configuració d'aquest mòdul, i fes almenys una vegada un GUARDAR de la configuració amb la nova configuració. Es conservaran les opcions existents però probablement s'afegiran de noves.

## Guia d'usuari

- Anglès: https://imasdeweb.com/index.php?pag=m_blog&gad=detalle_entrada&entry=89
- Espanyol: https://imasdeweb.com/index.php?pag=m_blog&gad=detalle_entrada&entry=88

## Llicència

LLICÈNCIA: GPL v3

Aquest programa és programari lliure; pots redistribuir-lo i/o
modificar-lo sota els termes de la Llicència Pública General GNU
publicada per la Free Software Foundation; bé la versió 3
de la Llicència, o (a la teva elecció) qualsevol versió posterior.

Aquest programa es distribueix amb l'esperança que sigui útil,
però SENSE CAP GARANTIA; sense ni tan sols la garantia implícita de
COMERCIALITZACIÓ o APTITUD PER A UN PROPÒSIT PARTICULAR. Vegeu la
Llicència Pública General GNU per a més detalls.

Hauries d'haver rebut una còpia de la Llicència Pública General GNU
juntament amb aquest programa; si no és així, escriu a la Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

## Registre de versions

Veure el fitxer **ChangeLog.md** o mira el fitxer [ChangeLog.md](ChangeLog.md).

