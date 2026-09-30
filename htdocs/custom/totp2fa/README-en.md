## Current version: 2.1 [2023-09-14]

Compatible with Dolibarr v. 7.X-18.X

## Description

Main function: activate a second factor to user authentication when login to Dolibarr with the standard TOTP (Time-based One-Time-Password), compatible with TOTPs generators Authy, Google Authenticator, Aegis for Android, etc...

Features:

- It show on usual Dolibarr login page a third text input control to put a TOTP 6-digit code (temporal code to be used only once).
- This 3rd control must be populated by users which have enabled this Two Factor (2FA) system.
- So it is a 6-digit code optional: some users can have it enabled but others not.
- Only is possible to enable the 2FA by the same user. Admin users cannot do it.
- Admin users (or users with assigned permissions over other users) can ONLY know which other users has enabled the 2FA and disable it.
- The only one who can see the TOTP secret key is the corresponding user.
- The module always show to the user its secret key and the QR code to be scanned by a mobile app.
- When activating 2FA for your user you can set manually your secret TOTP key, specially useful to administer several Dolibarr instances.
- Optionally, you can set a period of time (1 day/week/month) to remember a successfully logged device, not asking the TOTP again during this time
- Each user can activate the possibility of being able to request the sending of the 6-digit code to their email from the login page
- It utilizes Dolibarr's native MaxMind module for IP geolocation to apply visitor filters based on their country. This allows you to establish a whitelist of countries that are considered valid sources for your Dolibarr system. Requests originating from other countries will be denied.

My main concern -at least in this first version- has been to keep the system AS SIMPLE AS POSSIBLE. Easy but secure/private.

Your comments and suggestions are always welcomed!

Once you buy this module you will be able to download any update in the future, for ever.

## Interface language translations

Until now: English / Catalan / Spanish / French / German

Your translations are welcome.

## Installation

The usual to any other module of Dolibarr. Recommended on /htdocs/custom directory.

Note: you probably want to visit Settings of the module to set a period of time to temporally remember logged devices(1 day/week/month). I not give the option to remember "forever" because it's convenient to ask the TOTP 6-digit code each X time, to increase security and to help you not to lose your TOTPs generating app ;-)

## Update

1. replace the existing PHP files at /htdocs/custom/totp2fa directory
2. go to Settings > Modules and then disable and enable again the module, this will run changes on database if needed
3. visit the settings of this module, and do at least one time a SAVE of settings with new configuration. It will preserve the existing options but it will probably add new ones.

## User guide

- English: https://imasdeweb.com/index.php?pag=m_blog&gad=detalle_entrada&entry=89
- Spanish: https://imasdeweb.com/index.php?pag=m_blog&gad=detalle_entrada&entry=88

## License

LICENSE: GPL v3

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 3
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

## Versions Log

See file **ChangeLog.md** or see [ChangeLog.md](ChangeLog.md) file.
