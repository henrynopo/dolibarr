-------------------------------------
   TOTP2FA MODULE CHANGELOG
-------------------------------------

## v 2.1 [2023-09-14]
- Checked compatibility with Dolibarr 18.X and PHP 8.1
- Module settings, About tab: added the content of README file in different languages.
- Used the Dolibarr dolMd2Html() native function to convert Markdown of CHANGELOG and README files to HTML.

## v 2.0 [2023-05-08]
- Added UserGuide tab on Settings of the module.
- Added an easy way to upload MaxMind GeoLite2-Country.mmdb and auto-activation and configuration of the Dolibarr Maxmind module for geolocation. Now we can use IP-Country filter/rules without depending of server configuration and PHP version.

## v 1.9 [2023-05-01]
- Checked compatibility with Dolibarr 17.X
- Added ChangeLog information on the modal information on Settings/Modules section.

## v 1.8 [2023-02-20]
- Fixed a compatibility issue with safety TOKEN system used in Dolibarr since Dolibarr 12.X

## v 1.7 [2022-12-16]
- Added the option to enable the email sending of the temporary 6-digit code from the login page. It's an option for each user, not for all the users. This let some users to use the mail to receive the 6-digit code instead of use a TOTp generator app. 

## v 1.6 [2022-09-12]
- Checked compatibility with Dolibarr 16.X
- Hidden a message of "device saved" when the user who is accesing has not enabled 2FA 

## v 1.5 [2022-04-18]
- Added the ability to REMEMBER a device and not ask the TOTP during X time (1 day/week/month), for a certain user.

## v 1.4 [2022-03-13]
- Bugfixed an UI interface on user card.
- Added an access filter by the country to which the visitor's IP belongs (optional, and it requires php7.4-geoip)

## v 1.3 [2022-03-07]
- Upgraded to be compatible with Dolibarr 15.X

## v 1.2 [2022-02-10]
- New: when the user is activating the 2FA, now she can set her own TOTP secret key. In this way is alot more easier to use the same secret on several Dolibarr instances, which is specially useful when you works as administrator.

## v 1.1 [2022-02-07]
- Bugfixed translations on English and German. Thanks to Chris Keydel.

## v 1.0 [2022-02-02]
- Initial version. Compatible from Dolibarr 7.X to 14.X

