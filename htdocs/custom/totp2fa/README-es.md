## Versión actual: 2.1 [2023-09-14]

Compatible con Dolibarr v. 7.X-18.X

## Descripción

Función principal: activar un segundo factor para autenticación de usuario al iniciar sesión en Dolibarr con el TOTP estándar (Contraseña Única basada en Tiempo), compatible con generadores TOTP como Authy, Google Authenticator, Aegis para Android, etc...

Características:

- Muestra en la página de inicio de sesión de Dolibarr un tercer campo de texto para introducir un código TOTP de 6 dígitos (código temporal para ser usado solo una vez).
- Este 3er campo debe ser completado por usuarios que han habilitado este sistema de Dos FActores (2FA).
- Así que es un código de 6 dígitos opcional: algunos usuarios pueden tenerlo habilitado pero otros no.
- Sólo es posible habilitar el 2FA por el mismo usuario. Los usuarios administradores no pueden hacerlo.
- Los usuarios administradores (o usuarios con permisos asignados sobre otros usuarios) SOLO pueden saber qué otros usuarios han habilitado el 2FA y desactivarlo.
- El único que puede ver la clave secreta TOTP es el usuario correspondiente.
- El módulo siempre muestra al usuario su clave secreta y el código QR para ser escaneado por una aplicación móvil.
- Al activar 2FA para tu usuario puedes establecer manualmente tu clave secreta TOTP, especialmente útil para administrar varias instancias de Dolibarr.
- Opcionalmente, puedes establecer un período de tiempo (1 día/semana/mes) para recordar un dispositivo que ha iniciado sesión con éxito, sin pedir el TOTP nuevamente durante ese tiempo.
- Cada usuario puede activar la posibilidad de poder solicitar el envío del código de 6 dígitos a su correo electrónico desde la página de inicio de sesión.
- Utiliza el módulo nativo MaxMind de Dolibarr para geolocalización IP para aplicar filtros de visitantes según su país. Esto te permite establecer una lista blanca de países que se consideran fuentes válidas para tu sistema Dolibarr. Las solicitudes originadas desde otros países serán denegadas.

Mi principal preocupación -al menos en esta primera versión- ha sido mantener el sistema LO MÁS SIMPLE POSIBLE. Fácil pero seguro/privado.

¡Tus comentarios y sugerencias son siempre bienvenidos!

Una vez que compres este módulo, podrás descargar cualquier actualización en el futuro, para siempre.

## Traducciones del idioma de la interfaz

Hasta ahora: Inglés / Catalán / Español / Francés / Alemán

Tus traducciones son bienvenidas.

## Instalación

Lo habitual para cualquier otro módulo de Dolibarr. Recomendado en el directorio /htdocs/custom.

Nota: probablemente quieras visitar la Configuración del módulo para establecer un período de tiempo para recordar temporalmente los dispositivos registrados (1 día/semana/mes). No doy la opción de recordar "para siempre" porque es conveniente pedir el código TOTP de 6 dígitos cada cierto tiempo, para aumentar la seguridad y ayudarte a no perder tu aplicación generadora de TOTPs ;-)

## Actualización

1. reemplaza los archivos PHP existentes en el directorio /htdocs/custom/totp2fa.
2. ve a Configuración > Módulos y luego desactiva y vuelve a activar el módulo, esto ejecutará cambios en la base de datos si es necesario.
3. visita la configuración de este módulo y guarda al menos una vez las configuraciones con la nueva configuración. Conservará las opciones existentes pero probablemente agregará nuevas.

## Guía del usuario

- Inglés: https://imasdeweb.com/index.php?pag=m_blog&gad=detalle_entrada&entry=89
- Español: https://imasdeweb.com/index.php?pag=m_blog&gad=detalle_entrada&entry=88

## Licencia

LICENCIA: GPL v3

Este programa es software libre; puedes redistribuirlo y/o
modificarlo bajo los términos de la Licencia Pública General GNU
publicada por la Free Software Foundation; ya sea la versión 3
de la Licencia, o (a tu elección) cualquier versión posterior.

Este programa se distribuye con la esperanza de que sea útil,
pero SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita de
COMERCIABILIDAD o APTITUD PARA UN PROPÓSITO PARTICULAR. Consulta la
Licencia Pública General GNU para más detalles.

Deberías haber recibido una copia de la Licencia Pública General GNU
junto con este programa; si no es así, escribe a la Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

## Registro de versiones

Consulta el archivo **ChangeLog.md** o mira el archivo [ChangeLog.md](ChangeLog.md).


