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

/*
    these are to guarantee compatibility to Dolibarr versions previous to 10 and 11, where didn't exist newToken() and currentToken()
*/
if (!function_exists('newToken')){
	function newToken(){
		return empty($_SESSION['newtoken']) ? '' : $_SESSION['newtoken'];
	}
}
if (!function_exists('currentToken')){
	function currentToken(){
		return isset($_SESSION['token']) ? $_SESSION['token'] : '';
	}
}

/*
    this function should not be necessary, but i've not understood why the dolibarr price() function doesn't render thousands separator
*/
function TOTP_render_view($viewname,Array $vars){
	global $langs, $db, $conf;
	
	// == passed vars
		if (count($vars)>0){
			foreach($vars as $__k__=>$__v__){
				${$__k__} = $__v__;
			}
		}
		
	// == we begin a new output
		ob_start();
		include(__DIR__.'/views/'.$viewname.'.php');
		
	// == we get the current output
		$render = ob_get_clean();

		return $render;
}

/* 
 * I must use this function_exists() because the same functions are used by all IMASDEWEB modules 
 * and it avoid to re-define twice the same functions causing a PHP error
 */

if (!function_exists('_var_export')){
	
	function _var($arr, $title=''){
			return _var_export($arr, $title);
	}
	
	function _var_export($arr, $title='', $b_htmlspecialchars=1, $is_object = '',$ii=0){
		$ii++;
		if ($ii==1){ // root DIV
			$html = "\n<div class='_var_export' style='font-family:monospace;word-break:break-all;'>";
		}else{
			$html = "\n<div style='margin-left:100px;'>";
		}

		if (is_resource($arr)){
			$arr = 'RESOURCE OF TYPE: '.get_resource_type($arr); // -> "convert" resource to string
			$is_object = false;
		}else if (is_object($arr)){
			$is_object = true;
			$arr = get_object_vars($arr);
		}else if ($is_object==''){
			$is_object = false;
		}

		$n_elements = 0;
		if (is_array($arr)){
				$n_elements = count($arr);
				if ($n_elements==0){
					$html .= "&nbsp;";
				}else{
					foreach ($arr as $k=>$ele){
						$html .= "\n\t<div style='float:left;'><b style='".($is_object ? 'background-color:rgba(0,0,0,0.1);padding:2px':'')."'>$k <span class='arrow' style='color:#822;'>&rarr;</span> </b></div>"
								."\n\t<div style='border:1px #ddd solid;font-size:10px;font-family:sans-serif;'>";
						$html .= is_object($ele) || is_resource($ele)? _var_export(get_object_vars($ele),'',$b_htmlspecialchars,true,$ii) : _var_export($ele,'',$b_htmlspecialchars,false,$ii);
						$html .= "</div>";
						$html .= "\n\t<div style='float:none;clear:both;'></div>";
					}
				}
		}else if ($arr===NULL){
				$html .= "&nbsp;";

		}else if (substr($arr,0,2)=='{"' || substr($arr,0,3)=='[{"'){
				$json = f_json_decode($arr);
				if (is_array($json)){
					$n_elements = count($json);
					$html .=  htmlspecialchars($arr).'<br /><br />'._var_export($json,'',$b_htmlspecialchars,false,$ii).'<br />';
				}else{
					$html .=  $arr;
				}

		}else if ($arr === 'b:0;' || substr($arr,0,2)=='a:'){
				$uns = f_unserialize($arr);
				if (is_array($uns)){
					$n_elements = count($uns);
					$html .= htmlspecialchars($arr).'<br /><br />'._var_export($uns,'',$b_htmlspecialchars,false,$ii).'<br />';
				}else{
					$html .= $b_htmlspecialchars==1 ? htmlspecialchars($arr) : $arr;
				}
			}else{
				$n_elements = 1;
				$html .= $b_htmlspecialchars==1 ? htmlspecialchars($arr) : $arr;
			}
		$html .= "</div>";

		// title
		if (!empty($title)){
			$html = '<h3>'.$title.' <span style="opacity:0.5;font-family:monospace;">['.$n_elements.']</span></h3>'.$html;
		}

		return $html;
	}
}

if (!function_exists('f_unserialize')){
	
	function f_unserialize($string,$return_always_an_array=true){
		$string = trim($string);
		if (!is_string($string) || $string=='' || substr($string,0,2)!='a:'){
			if ($return_always_an_array)
				return array();
			else
				return $string;
		}
		$uns = @unserialize($string);
		if (!is_array($uns) || (count($uns)==0 && strlen($string)>'4')){
			$string = f_fix_serialized($string);
			$uns2 = @unserialize($string);
			if (is_array($uns2)){
				return $uns2;
			}else{
				return array();
			}
		}else if (is_array($uns)){
			return $uns;
		}else{
			return array();
		}
	}
}

if (!function_exists('f_json_decode')){
	
	function f_json_decode($string,$return_always_an_array=true){
		$string = trim($string);
		if (!is_string($string) || $string==''
			|| (substr($string,0,1)!='{' && substr($string,0,1)!='[') ){
			if ($return_always_an_array)
				return array();
			else
				return $string;
		}
		$uns = json_decode($string,true); // the second parameter (true) force the convertion of objects to associative arrays
		if (is_array($uns)){
			return $uns;
		}else{
			if ($return_always_an_array)
				return array();
			else
				return $string;
		}
	}
}

/*
 * a:32:{i:1;s:12:"2010/02/22";i:2;... -> a:32:{i:1;s:10:"2010/02/22";i:2;...
 */
if (!function_exists('f_fix_serialized')){
	
		function f_fix_serialized($string){
			$reg = '/s:(\d+):"(.*?)";/';
			if (preg_match($reg,$string)){
				$string = preg_replace_callback($reg, function ($matches){ return "s:".strlen($matches[2]).":\"".$matches[2]."\";"; }, $string);
			}
			return $string;
		}
}
