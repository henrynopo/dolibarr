<?php
/* Copyright (C) 2020 Sergi Rodrigues <proyectos@imasdeweb.com>
 *
 * Licensed under the GNU GPL v3 or higher (See file gpl-3.0.html)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

// == ACTIVATE the ERROR reporting
ini_set('display_errors',1);ini_set('display_startup_errors',1);error_reporting(-1);

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1); // Disables token renewal
}

$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php");
if (! $res && preg_match('/\/imasdeweb([^\/]*)\//',$_SERVER["PHP_SELF"],$reg)) $res=@include("../../../dolibarr".$reg[1]."/htdocs/main.inc.php"); // Used on dev env only
if (! $res) die("Include of main fails");

// == MODULE DOCUMENT_ROOT & URL_ROOT
	if (!defined('TOTP2FA_MODULE_DOCUMENT_ROOT')){
		if (file_exists(DOL_DOCUMENT_ROOT.'/custom/totp2fa/core/modules/modTotp2fa.class.php')){
			define('TOTP2FA_MODULE_DOCUMENT_ROOT',DOL_DOCUMENT_ROOT.'/custom/totp2fa');
			define('TOTP2FA_MODULE_URL_ROOT',DOL_URL_ROOT.'/custom/totp2fa');
		}else{
			define('TOTP2FA_MODULE_DOCUMENT_ROOT',DOL_DOCUMENT_ROOT.'/totp2fa');
			define('TOTP2FA_MODULE_URL_ROOT',DOL_URL_ROOT.'/totp2fa');
		}
	}
	
	require_once TOTP2FA_MODULE_DOCUMENT_ROOT.'/class/totp.class.php';

global $user;			
$user->getrights('totp2fa');

$langs->load("totp2fa@totp2fa");

// == Get parameters
    $op = GETPOST('op','alpha');
    
    switch ($op){
		
        case 'generate_2FA_sk':
        
			list($secret,$qr_img) = Totp::generate_sk();
			$_SESSION['totp2fa_sk'] = $secret; // to be validated in the next user pass with a first 6-digit code
            echo json_encode(array('ok'=>'1','sk'=>$secret,'qr_img'=>$qr_img));
            
            break;

        case 'set_2FA_sk':
        
			list($secret,$qr_img) = Totp::set_sk($_POST['sk']); // if the $_POST['sk'] is a valid secret, this function returns the same secret and the QR image, if not it returns empty secret
			$_SESSION['totp2fa_sk'] = $secret; // to be validated in the next user pass with a first 6-digit code
            echo json_encode(array('ok'=>'1','sk'=>$secret,'qr_img'=>$qr_img));
            
            break;

        case 'validate_2FA_sk':
        
			$id        = GETPOST('id');
			$hashed_id = GETPOST('hid');
			$c6        = GETPOST('c6');
			if ($hashed_id != md5('1441hashkey'.$id)){
				echo json_encode(array('ok'=>'0'));
				break;
			}
				
			// security check, only onself can enable the 2FA authentication
			$cancreateselfpassword = ($user->id == $id) && !empty($user->rights->user->self->password);
			if (!$cancreateselfpassword){
				echo json_encode(array('ok'=>'0','msg'=>'No permisssion.'));
				break;
			}
				
			$sk = $_SESSION['totp2fa_sk'];
			$valid = Totp::validate_sk($sk,$c6);
			
			$byemail = '';
			if ($valid=='1'){
				list($error,$byemail) = Totp::setUserSecret($id,$sk);
				if (!empty($error)) $valid = '0';
			}
			
            echo json_encode(array('ok'=>$valid, 'byemail'=>$byemail));
            
            break;
            
		
		case 'send_code_by_email': // to the user when enabling the 2FA through email
		
			// = user_id
				$user_id = '';
				// a) when the user is enabling the 2FA in her user card, once logged
				if (!empty($_SESSION['dol_login'])){
					$username = $_SESSION['dol_login'];
					$user_id  = Totp::getUserID($username);
				}

			// = return if there is no user, this shouldn't happen never, it's a safety check
				if (!$user_id || empty($user_id) || $user_id=='' || $user_id==0){
					echo json_encode(array('ok'=>'0','msg'=>'Not found user.')); die();
				}
				
			// = get the email address of this user
				$user_email  = Totp::getUserEmail($user_id);
				if (empty($user_email)){
					echo json_encode(array('ok'=>'0','msg'=>'⛔ '.html_entity_decode($langs->trans('totp2fa_NoUserMail')))); die();
				}
				
			// = prepare mail data
				$c6      = Totp::generate_c6($_SESSION['totp2fa_sk'],time()+5);
				$target  = $user_email;
				$subject = html_entity_decode(str_replace('&#039;',"'",$langs->trans("totp2fa_6digit"))).' ['.$c6.']';
				$body    = $c6;
				$isHTML  = 1;

			// = email From
				$emailFrom = '';
				if (isset($conf->global->MAIN_MAIL_EMAIL_FROM) && !empty($conf->global->MAIN_MAIL_EMAIL_FROM)){
					$emailFrom = trim($conf->global->MAIN_MAIL_EMAIL_FROM);
				}
				if ($emailFrom==''){
					if (isset($conf->global->MAIN_INFO_SOCIETE_MAIL) && !empty($conf->global->MAIN_INFO_SOCIETE_MAIL)){
						$emailFrom = trim($conf->global->MAIN_INFO_SOCIETE_MAIL);
					}
				}
				if ($emailFrom==''){
					// return error message !!!
				}
				
			// = send email
				include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
				$mailfile = new CMailFile($subject, $target, $emailFrom, $body, array(),array(),array(),'','',0,$isHTML);
				if ($mailfile->sendfile()){
					$_SESSION['totp2fa_byemail_enabled'] = '1';
					echo json_encode(array('ok'=>'1','msg'=>'✅ '.html_entity_decode($langs->trans('totp2fa_MailSent'))));
				}else{
					$msg = html_entity_decode($langs->trans("ErrorFailedToSendMail",$emailFrom,$target)).":\n\n ".$mailfile->error;
					echo json_encode(array('ok'=>'0','msg'=>$msg));
				}
            
            break;
		
    }
    die();
