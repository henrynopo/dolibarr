<?php
/* Copyright (C) 2017 Sergi Rodrigues <proyectos@imasdeweb.com>
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
//ini_set('display_errors',1);ini_set('display_startup_errors',1);error_reporting(-1);

define('NOCSRFCHECK',1);

$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php");
if (! $res && preg_match('/\/imasdeweb([^\/]*)\//',$_SERVER["PHP_SELF"],$reg)) $res=@include("../../../../dolibarr".$reg[1]."/htdocs/main.inc.php"); // Used on dev env only
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

dol_include_once("core/lib/admin.lib.php");
dol_include_once("core/class/html.formadmin.class.php");

if (!$user->admin) accessforbidden();

$langs->load("admin");
$langs->load("other");
$langs->load("totp2fa@totp2fa");
$langs->load("languages");
$langs->load("dict");

/***************************************************
 *
 *	Actions / prepare data
 *
****************************************************/

		include_once(__DIR__."/../lib/functions.lib.php");
		include_once(__DIR__."/../class/totp_ip.class.php");
		
		$maxmind_db_path = DOL_DATA_ROOT.'/geoipmaxmind';
		$b_geoip = Totp_IP::b_geoip();
		$sett_countries = !empty($conf->global->TOTP2FA_MODULE_SETT_COUNTRIES) ? $conf->global->TOTP2FA_MODULE_SETT_COUNTRIES : '';
		$a_sett_countries = $sett_countries!='' ? explode(',',$sett_countries) : array();
			$ex_version = explode('.',PHP_VERSION);
		$php_version = floatval($ex_version[0].'.'.$ex_version[1]);
		$msg = '';

    // == request action by GET/POST

		//if (!empty($_POST)) {echo _var($_POST,'$_POST')._var($_FILES,'$_FILES');die;}

        if ($user->admin && !empty($_POST['remove_country_code'])){ // add a new country to filter by country-IP

				$remove_country_code = substr(mb_strtolower(trim($_POST['remove_country_code'])),0,2);
				if ($remove_country_code!=''){
					$new_a_sett_countries = array();
					foreach ($a_sett_countries as $code){
						if (!empty($code) && $code!=$remove_country_code){
							$new_a_sett_countries[] = $code;
						}
					}
					$a_sett_countries = $new_a_sett_countries;
					Totp_IP::save_SETT_COUNTRIES($a_sett_countries);
				}
				
        }else if ($user->admin && !empty($_POST['country_code'])){ // add a new country to filter by country-IP
			
				$new_country_code = substr(mb_strtolower(trim($_POST['country_code'])),0,2);
				if (!in_array($new_country_code,$a_sett_countries)){
					$a_sett_countries[] = $new_country_code;
					Totp_IP::save_SETT_COUNTRIES($a_sett_countries);
				}

		}else if ($user->admin && !empty($_FILES['mmdb_file']) // upload a new MaxMind geo-ip database
					&& $_FILES['mmdb_file']['error']=='0'
					&& !empty($_FILES['mmdb_file']['name'])){ 

				// check that the directory $maxmind_db_path exists
				if (!is_dir($maxmind_db_path)) @mkdir($maxmind_db_path,0777);

				// upload the MaxMind file
				if (!move_uploaded_file($_FILES['mmdb_file']['tmp_name'], $maxmind_db_path.'/GeoLite2-Country.mmdb')){
					$msg = str_replace('{filepath}',$maxmind_db_path.'/GeoLite2-Country.mmdb',$langs->trans("totp2fa_Config10_err2"));
				}

				// configure and activate the MaxMind Dolibarr native module
				if (file_exists($maxmind_db_path.'/GeoLite2-Country.mmdb')){

					$error = 0;
					$res1  = dolibarr_set_const($db, "GEOIP_VERSION", '2', 'chaine', 0, '', $conf->entity);
					if (!($res1 > 0)) $error++;
				
					$res2  = dolibarr_set_const($db, "GEOIPMAXMIND_COUNTRY_DATAFILE", $maxmind_db_path.'/GeoLite2-Country.mmdb', 'chaine', 0, '', $conf->entity);
					if (!($res2 > 0)) $error++;

					if ($error > 0){
						$msg .= $langs->trans("totp2fa_Config10_err1");
					}else{
						$resarray = activateModule('modGeoIPMaxmind');
					}
				}
							
				if ($msg == '') {
					setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
					header("Location: ".$_SERVER["PHP_SELF"]."?token=".newToken());

				}else {
					setEventMessages($msg, null, 'errors');
				}

		}

/***************************************************
 *
 *	View
 *
****************************************************/

$help_url='';
$title = $langs->trans('ModuleTotp2FaName');
llxHeader('',$title,$help_url);

// = first header row (section title & go back link)

    $linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
    print_fiche_titre($title,$linkback,'user');
    print '<br />';

// = tabs of the section

    $h=0;

    $head[$h][0] = 'config-totp.php';
    $head[$h][1] = $langs->trans("totp2fa_Config").' TOTP';
    $head[$h][2] = 'tabconfig-totp';
    $h++;

    $head[$h][0] = 'config-ip.php';
    $head[$h][1] = $langs->trans("totp2fa_Config").' IP';
    $head[$h][2] = 'tabconfig-ip';
    $h++;

    $head[$h][0] = 'about.php';
    $head[$h][1] = $langs->trans("totp2fa_About");
    $head[$h][2] = 'tababout';
    $h++;

    $head[$h][0] = 'changelog.php';
    $head[$h][1] = 'Changelog';
    $head[$h][2] = 'tabchangelog';
    $h++;

    $head[$h][0] = 'userguide.php';
    $head[$h][1] = $langs->trans("totp2fa_UserGuide");
    $head[$h][2] = 'tabuserguide';
    $h++;

// = init current tab

    dol_fiche_head($head, 'tabconfig-ip', '',-1,'');
    
?>

<!-- ** PDF GENERAL ** SETTINGS -->

<?php if (!$b_geoip){ ?>

	<?= load_fiche_titre($langs->trans("totp2fa_Config10"),'','') ?>
	
	<p>
		<?= $langs->trans("totp2fa_Config10_msg1") ?>
		<br /><br /> 
		<?= str_replace(array("[a]","[/a]"),array("<a href='https://www.maxmind.com/en/accounts/853252/geoip/downloads' target='_blank'>","</a>"),$langs->trans("totp2fa_Config10_msg2")) ?>
	</p>

	<!-- UPLOAD mmdb form -->

	<form id="totp2faForm_mmdb" action_="<?= $_SERVER["PHP_SELF"] ?>" method="post" enctype="multipart/form-data">
		<input type="hidden" name="token" value="<?= newToken() ?>" />

		<table style='width:auto;margin:2rem;'>
			<tr>
				<td style="text-align:center;background-color:#8882;padding:2rem;">
					<p><b><?= $langs->trans("totp2fa_Config10_msg3") ?></b><br />&nbsp;</p>
					<p>
						<input type="file" name="mmdb_file" accept=".mmdb" />
						<a href="#" onclick="$('#totp2faForm_mmdb').submit();return false;" class="button">
							<?= dol_escape_htmltag($langs->trans("totp2fa_Upload")) ?></a>
					</p>
				</td>
			</tr>
		</table>

	</form>


<?php }else{


	include_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
	$form = new Form($db);
?>

	<!-- EXAMPLE IP geo-locations -->

	<?= load_fiche_titre($langs->trans("totp2fa_Config10"),'','') ?>

	<table style='width:auto;margin:2rem;'>
		<tr>
			<td style="text-align:left;background-color:#8882;padding:1rem 1.5rem;">
				<p style='line-height:1.5em;'>
				<?php
					$ip = getUserRemoteIP();
					$country_code = mb_strtoupper(dolGetCountryCodeFromIp($ip));
					$flag = dol_print_ip($ip,1);
					echo '<b>'.$langs->trans("totp2fa_Config10_your_IP").' '.$ip.' &rarr; </b> '.($country_code=='' ? '???' : $flag .' '. $country_code).'<br />';

					$ip = '8.8.8.8'; // should be USA
					$country_code = mb_strtoupper(dolGetCountryCodeFromIp($ip));
					$flag = dol_print_ip($ip,1);
					echo '<b>'.$langs->trans("totp2fa_Config10_thisother_IP").' '.$ip.' &rarr; </b> '.($country_code=='' ? '???' : $flag .' '. $country_code).'<br />';
				
					$ip = '177.228.68.44'; // shoudl be mexico
					$country_code = mb_strtoupper(dolGetCountryCodeFromIp($ip));
					$flag = dol_print_ip($ip,1);
					echo '<b>'.$langs->trans("totp2fa_Config10_thisother_IP").' '.$ip.' &rarr; </b> '.($country_code=='' ? '???' : $flag .' '. $country_code).'<br />';

					$ip = '2a01:e0a:7e:4a60:429a:23ff:f7b8:dc8a'; // should be FRANCE
					$country_code = mb_strtoupper(dolGetCountryCodeFromIp($ip));
					$flag = dol_print_ip($ip,1);
					echo '<b>'.$langs->trans("totp2fa_Config10_thisother_IP").' '.$ip.' &rarr; </b> '.($country_code=='' ? '???' : $flag .' '. $country_code) ?><br />
				</p>
			</td>
		</tr>
	</table>

	<!-- Configure COUNTRY FILTER -->

	<?= load_fiche_titre($langs->trans("totp2fa_Config03"),'','') ?>

	<form id="totp2faForm" name="totp2faForm" action="<?= $_SERVER["PHP_SELF"] ?>" method="post">
		<input type="hidden" name="token" value="<?= newToken() ?>" />

		<table class="noborder" style="width:auto;min-width:70%;margin:2rem;">
			<tr class="liste_titre">
				<td width="50%"><?= $langs->trans("Name") ?></td>
				<td width="50%"><?= $langs->trans("Value") ?></td>
			</tr>
			
			<!-- number of decimals for stock quantities -->
			<tr>
				<td><?= $langs->trans("totp2fa_Config05") ?></td>
				<td>
					<div id='totp2fa_contries' class='block' style='margin:1em;margin-left:0;'>
						<?php
							if (count($a_sett_countries)==0){
								echo "<em>".$langs->trans("totp2fa_Config06")."</em>";
							}else{
								foreach ($a_sett_countries as $code){
									echo "<a href='#' onclick=\"$('#remove_country_code').val('".$code."');$('#totp2faForm').submit();return false;\"><i class='fa fa-trash'></i> ".$langs->trans("Country".mb_strtoupper($code))."</a>";
								}
							}
						?>
					</div>
					<input type="hidden" name="remove_country_code" id="remove_country_code" value="" />
					<style>
						#totp2fa_contries a{background-color:rgba(200,200,200,0.5);color:black;display:inline-block;margin:10px;padding:5px 10px;border-radius:5px;font-weight:bold;}
						#totp2fa_contries a:hover{background-color:white;text-decoration:none;}
					</style>
					<?= $form->select_country('', 'country_code','', 0, 'maxwidth200','code2') ?>
					<button onclick="$('#totp2faForm').submit();return false;"><i class='fa fa-plus-square'></i> <?= $langs->trans("totp2fa_Config07") ?></button>
				</td>
			</tr>
		</table>

		<!-- SUBMIT button -->

		<p style="text-align:left;margin:2rem 0;">
			<a href="#" onclick="$('#totp2faForm').submit();return false;" class="button"><?= dol_escape_htmltag($langs->trans("Save")) ?></a>
		</p>

	</form>
	
<?php } ?>

<style>
	input.alertedfield, select.alertedfield, textarea.alertedfield{background-color:yellow!important;}
	.alertedcontainer td, .alertedcontainer td.fieldrequired{color:red!important;}
	.block{padding:0.5rem;background-color:rgba(100,100,100,0.05);border-radius:3px;border:1px rgba(100,100,100,0.2) solid;}
</style>

<?php 

dol_fiche_end();


clearstatcache();

dol_htmloutput_mesg($mesg);


llxFooter();

$db->close();
