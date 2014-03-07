<?php
class forms extends THelpers {
	private $forms=array();
	function runPlugin(){
		return array(
			'needs'=>array(array('plugins/forms/forms.css'),array('jquery','jqueryui','jquery.ui.nestedSortable.js')),
			'contentSearch'=>$this->search(),
			'contentReplace'=>$this->replace(),
			'adminMenu'=>array(
				_t("Forms &raquo;","forms")."|forms" => array(
						_t("Create Form","forms")."|forms_form",
						_t("Manage Forms","forms")."|forms"
					),
				),
		);
	}
	private function search(){
		$r=array();
		if ($this->forms&&!empty($this->forms)) foreach ($this->forms as $k=>$v) $r[]="[[FORM-".$k."]]";
		return $r;
	}
	private function sendEmail($j,$p){
		$ec=json_decode($j->formContent);		
		if ($j->formET&&$j->formSUB&&$j->formBOD&&$ec) {
			$to=$j->formET;
			$subject=$j->formSUB;
			$message='<html><head><title>'.$j->formSUB.'</title><body>';
			$m=$j->formBOD;
			$m=nl2br($m);
			preg_match_all("/\{label:(.*?)\}/", $m, $labels);
			preg_match_all("/\{value:(.*?)\}/", $m, $subs);
			$slugs=array();
			if ($labels&&!empty($labels))
				foreach ($labels[0] as $k=>$label) {
					$name=$ec[$labels[1][$k]-1]->n;
					$m=str_replace($label,$name,$m);
					$slugs[$labels[1][$k]]=$this->slugify($name);
				}
			if ($subs&&!empty($subs))
				foreach ($subs[0] as $k=>$sub) {
					$with=$p[$slugs[$subs[1][$k]]];
					$s='';
					if (is_array($with)&&!empty($with)) {
						foreach ($with as $_k=>$_v) {
							$s.=$_v.'<br />';
						}
					} else $s=$with;
					$s=nl2br($s);
					$m=str_replace($sub,$s,$m);
				}
			$message.=$m;
			$message.='</body>';
			$headers  = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";			
			//$headers .= 'To: '.($j->formNT).' <'.($j->formET).'>'."\r\n";
			$headers .= 'From: '.($j->formNF).' <'.($j->formEF).'>'."\r\n";
			$headers .= 'Reply-To: '.($j->formNF).' <'.($j->formEF).'>'."\r\n";
			if (@mail($to,$subject,$message,$headers)) return true;
			else return false;			
		} else return false;	
	}
	private function storeSub($j,$p){
		$ec=json_decode($j->formContent);
		$fn=__DIR__."/".$this->slugify($j->formName).".madds";
		$ar=array();
		if (file_exists($fn)) {
			$fc=$this->getFile($fn);
			$toWrite=json_decode($fc);	
		} else $toWrite=array();
		foreach ($ec as $_ec) {
			if (isset($p[$this->slugify($_ec->n)])&&$_ec->t!='submit'&&$_ec->t!='reset') {
				$s=$p[$this->slugify($_ec->n)];
				$ar[$_ec->n]=is_array($s)?implode(", ",$s):$s;
			}
		}
		$toWrite[]=$ob=new stdClass();$ob->dt=date("Y-m-d H:i:s");$ob->ip=$_SERVER['REMOTE_ADDR'];$ob->data=$ar;$ob->full=$p;
		if (file_put_contents($fn,json_encode($toWrite))) return true;
		else return false;
	}
	private function replace(){
		$r=array();
		if ($this->forms&&!empty($this->forms)) { foreach ($this->forms as $k=>$v) {
			$j=json_decode($v);
			if ($j) {
				$error=array($k=>" ");$errows=array($k=>array());
				$p=$_POST;
				if (isset($p['formSent_'.$k])&&$p['formSent_'.$k]=="yEs") {
					$error[$k]="";
					$jj=json_decode($j->formContent);
					if ($jj) foreach ($jj as $kk=>$vv) {	
						$slug=$this->slugify($vv->n);
						if ($vv->t=="captcha") {
							if (!isset($p['recaptcha_response_field'])||!$p['recaptcha_response_field']) {
								$error[$k].=_t("The reCpatcha is required");
								$errows[$k][$slug]="";
							} else {
								require_once "a55ets/plugins/forms/recaptchalib.php";
								$ops=explode("\n",$vv->e);
								$privatekey=isset($ops[1])?$ops[1]:'';
								$resp=recaptcha_check_answer($privatekey,$_SERVER["REMOTE_ADDR"],$p["recaptcha_challenge_field"],$p["recaptcha_response_field"]);
								if (!$resp->is_valid) { 
									$error[$k].=_t("The reCpatcha is invalid");
									$errows[$k][$slug]="";
								}
							}
						}
						if ($vv->r!="no"&&$vv->t<>"captcha") {							
							if (!isset($p[$slug])||!$p[$slug]) {
								$error[$k].=_t('The field',"forms").': "'.$vv->n.'" '._t('is required',"forms").'<br />';
								$errows[$k][$slug]="";
							}
							if ($vv->r=="email"&&isset($p[$slug])&&$p[$slug]&&!preg_match('^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$^',$p[$slug])) {
								$error[$k].=_t('The field',"forms").': "'.$vv->n.'" '._t('is not a valid email address',"forms").'<br />';
								$errows[$k][$slug]="";
							}
							if ($vv->r=="number"&&isset($p[$slug])&&$p[$slug]&&intval($p[$slug])==0) {
								$error[$k].=_t('The field',"forms").': "'.$vv->n.'" '._t('is not a valid number',"forms").'<br />';
								$errows[$k][$slug]="";
							}
						}
					}
				}
				if (!$error[$k]) {
					$message="";
					switch ($j->formSubmit) {
						case "store": if (!$this->storeSub($j,$p)) $message.=_t("There was an error storing your message.<br />","forms"); break;
						case "send": if (!$this->sendEmail($j,$p)) $message.=_t("There was an error sending your message.<br />","forms"); break;
						case "storeandsend":
							if (!$this->storeSub($j,$p)) $message.=_t("There was an error storing your message.<br />","forms");
							if (!$this->sendEmail($j,$p)) $message.=_t("There was an error sending your message.<br />","forms");
						break;
					}					
					$message.=$j->formME;
					$_SESSION['formPlugin_Message_'.$k]=$message;
					header("Location: ".$_SERVER['REQUEST_URI']);
					exit;
				}
				if (isset($_SESSION['formPlugin_Message_'.$k])) {
					$s='<div class="messageSucces">'.$_SESSION['formPlugin_Message_'.$k].'</div>';
					$_SESSION['formPlugin_Message_'.$k]="";
					unset($_SESSION['formPlugin_Message_'.$k]);
					$r[]=$s;
				} else {
					$jj=json_decode($j->formContent);$beforeOptions='';
					$s='<div id="FP_'.$k.'" class="formsWrapper">';
					if ($error[$k]&&$error[$k]<>' ') $s.='<div class="messageErrors">'.$error[$k].'</div>';		
					if ($jj) foreach ($jj as $kk=>$vv) { if ($vv->t=="captcha") {$ops=explode("\n",$vv->e); if (isset($ops[2])) $beforeOptions='<script type="text/javascript">var RecaptchaOptions = {theme:"'.$ops[2].'"}</script>'; } }			
					$s.=$beforeOptions.'<form action="" method="post"><input type="hidden" name="formSent_'.$k.'" value="yEs">';
					
					if ($jj) foreach ($jj as $kk=>$vv) {
						$slug=$this->slugify($vv->n);
						$ss='<div class="formRow '.$vv->t.'Type'.($vv->r<>"no"?' required':'').(isset($errows[$k][$slug])?' hasError':'').'" id="FR_'.$slug.'"><div class="formLabel"><label for="'.$slug.'">'.($vv->t!="submit"&&$vv->t!="reset"?$vv->n:'').($vv->r=="no"?'':' <span class="required">(*)</span>').'</label></div><div class="formInput">';
						switch($vv->t) {
							case "text":
								$ss.='<input type="text" name="'.$slug.'" id="'.$slug.'" value="'.(isset($p[$slug])?$p[$slug]:'').'">';
							break;
							case "textarea":
								$ss.='<textarea name="'.$slug.'" id="'.$slug.'">'.(isset($p[$slug])?$p[$slug]:'').'</textarea>';
							break;
							case "submit":
								$ss.='<input type="submit" name="'.$slug.'" id="'.$slug.'" value="'.$vv->n.'">';
							break;
							case "reset":
								$ss.='<input type="reset" name="'.$slug.'" id="'.$slug.'" value="'.$vv->n.'">';
							break;
							case "select":
								$ss.='<select name="'.$slug.'" id="'.$slug.'">';
								$ops=explode("\n",$vv->e);
								if ($ops) foreach ($ops as $op) {
									$op=trim($op);
									if (strstr($op,"|c")) { $def=true; $op=str_replace("|c","",$op); }
									else $def=false;
									$ss.='<option value="'.$op.'"'.(isset($p[$slug])&&$p[$slug]==str_replace("|c","",$op)?' selected="selected"':($def&&!isset($p[$slug])?' selected="selected"':'')).'>'.$op.'</option>';
								}
								$ss.='</select>';
							break;
							case "checks":
								$ops=explode("\n",$vv->e);
								if ($ops) foreach ($ops as $op) {
									$op=trim($op);
									$sslug=$this->slugify($op);								
									if (strstr($op,"|c")) { $def=true; $op=str_replace("|c","",$op); }
									else $def=false;								
									$ss.='<div class="checkbox"><label><input type="checkbox" name="'.$slug.'['.$sslug.']" value="'.$op.'"'.(isset($p[$slug][$sslug])&&$p[$slug][$sslug]==str_replace("|c","",$op)?' checked="checked"':($def&&!isset($p['formSent'])?' checked="checked"':'')).'/> '.$op.'</label></div>';
								}
							break;
							case "radio":
							$ops=explode("\n",$vv->e);
								if ($ops) foreach ($ops as $op) {
									$op=trim($op);
									if (strstr($op,"|c")) { $def=true; $op=str_replace("|c","",$op); }
									else $def=false;
									$ss.='<div class="checkbox"><label><input type="radio" name="'.$slug.'" value="'.$op.'"'.(isset($p[$slug])&&$p[$slug]==str_replace("|c","",$op)?' checked="checked"':($def&&!isset($p[$slug])?' checked="checked"':'')).'/> '.$op.'</label></div>';
								}
							break;
							case "captcha":
								$ops=explode("\n",$vv->e);
								$ss.='<div class="captcha"><script type="text/javascript" src="http://www.google.com/recaptcha/api/challenge?k='.$ops[0].'"></script><noscript><iframe src="http://www.google.com/recaptcha/api/noscript?k='.$ops[0].'" height="300" width="400" frameborder="0"></iframe><br><textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea><input type="hidden" name="recaptcha_response_field" value="manual_challenge"></noscript></div>';
							break;
						}
						$s.=$ss;
						$s.='</div></div>';
					}
					$s.='</form></div>';
					$r[]=$s;
				}
			} else $r[]='';
		} }
		return $r;		
	}
	public function ActionAdmin_forms(){global $languages;
		if (MULTI&&isset($_GET['copy'])&&$_GET['copy']) {
			$source=__DIR__."/".$_GET['copy'].".madd";
			foreach ($languages as $k=>$l) if ($k<>0) {
				$dest=__DIR__."/".$_GET['copy'].".".$l.".madd";				
				if (!file_exists($dest)) copy($source,$dest);
			}
			$_SESSION['infoMessage']=_t("The form was copied to the remaining untranslated languages","forms");
			$this->redir("forms");
		}	
		$this->setCrumbs(array(SR=>_t("Home","forms"),SR.ADMIN.'/dash'=>_t("Admin","forms"),'#'=>_t("Manage Forms","forms")));
		$ret='<table border="1" cellpadding="0" cellspacing="0" width="100%" id="adTable"><thead><tr><th>'._t("Form Name","forms").'</th><th>'._t("Form Shortcode","forms").'</th>';
		if (MULTI) $ret.='<th>'._e("Available Languages").'</th>';
		$ret.='<th width="100">'._t("Actions","forms").'</th></thead><tbody>';		
		if ($this->forms&&!empty($this->forms)) foreach ($this->forms as $k=>$forms) {
			$formsName=$k;
			$subs=file_exists(__DIR__."/".$k.'.madds');
			$ret.='<tr><td>'.ucfirst($formsName).'</td><td>&#91;&#91;FORM-'.$formsName.'&#93;&#93;</td>';
			if (MULTI) {
				$langs=$languages[0].','; $alc=1;
				foreach ($languages as $kk=>$l) if ($kk<>0&&file_exists(__DIR__.'/'.$k.'.'.$l.'.madd')) { $langs.=$l.','; $alc++; }
				$ret.='<td align="center">'.substr($langs,0,-1).(MULTI&&$alc<>count($languages)?' <a href="'.SR.ADMIN.'/forms&copy='.$formsName.'">'._e("Copy to all languages").'</a>':'').'</td>';
			}
			$ret.='<th>'.($subs?'<a href="'.SR.ADMIN.'/forms_subs&who='.$formsName.'">'._t("Submissions","forms").'</a>&nbsp;|&nbsp;':'').'<a href="'.SR.ADMIN.'/forms_form&who='.$formsName.'">'._t("Edit","forms").'</a>&nbsp;|&nbsp;<a href="'.SR.ADMIN.'/forms_delete&who='.$formsName.'">'._t("Delete","forms").'</a></th></tr>';
		} else $ret.='<tr><td colspan="3" align="center">'._t("No forms were found","forms").'</td></tr>';
		$ret.='</tbody></table>';
		return $ret;
	}
	public function ActionAdmin_forms_form(){global $languages;
		$new=(isset($_GET["who"])&&$_GET["who"]?false:true);if(MULTI) if (isset($_GET['lang'])&&in_array($_GET['lang'],$languages)) $lang=$_GET['lang']; else $lang=$languages[0];
		$this->setCrumbs(array(SR=>_t("Home","forms"),SR.ADMIN.'/dash'=>_t("Admin","forms"),SR.ADMIN.'/forms'=>_t("Manage Forms","forms"),'#'=>$new?_t("Create new form","forms"):_t("Edit form","forms")));
		$ret='<style>.icon { display:inline-block; vertical-align:middle; margin:0 2px 0 0; width:32px; height:32px; background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAWAAAAAgCAYAAAAsTqKUAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA2ZpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMy1jMDExIDY2LjE0NTY2MSwgMjAxMi8wMi8wNi0xNDo1NjoyNyAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDo0MUU0RTA5RjMyOEVFMzExOUJCQkFDMUI0Njc2M0RBRCIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDpBOTU0NDI3Nzk1NDAxMUUzOTI5QkU3MzRGNzI5NTU1QSIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDpBOTU0NDI3Njk1NDAxMUUzOTI5QkU3MzRGNzI5NTU1QSIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ1M2IChXaW5kb3dzKSI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOkU0REQ3RDRFNDA5NUUzMTE4NjU0RDhGMjUzNkY3ODZEIiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjQxRTRFMDlGMzI4RUUzMTE5QkJCQUMxQjQ2NzYzREFEIi8+IDwvcmRmOkRlc2NyaXB0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"); background-color: #efefef; border:solid 1px #ccc;  border-radius:3px; }.text{background-position:0px 0px;}.textarea{background-position:-192px 0px;}.select{background-position:-32px 0px;}.checks{background-position:-64px 0px;}.radio{background-position:-96px 0px;}.submit{background-position:-128px 0px;}.reset{background-position:-160px 0px;}.delete{background-position:-224px 0px;float:right;}.ardwn{background-position:-256px 0px;float:right;}.arup{background-position:-288px 0px;float:right;}.captcha{background-position:-320px 0px;} .sortable li {margin:8px auto;background:#fff; padding:3px;border:solid 1px #ccc;  border-radius:3px;}.lisub{background:#fff; padding:3px;border:solid 1px #ccc;  border-radius:3px;margin:4px 0 0 0;display:none;} #formList,#help { display:inline-block; vertical-align:top;}#formList{ width:500px; margin:0 20px 0 30px; }#help{padding:4px 10px;border:solid 1px #ccc; background:#fff; border-radius:3px;margin:8px 0 0 0; width:368px; }.sortable li.placeholder{background:#FF0;width:500px;}.innerli{cursor:move;}.sortable li.err, a.err{border:solid 1px #f00;}.row small{font-size:11px;}ol.sortable {list-style-type:decimal}#formList .row label { width:100px;}#formList .row select { width:340px; }#formList .row textarea { width:330px; }</style>';
		if ($new) {
			$p=new stdClass;$p->formName='';$p->formContent='[]';$p->formSubmit='store';$p->formEF='';$p->formNF='';$p->formET='';$p->formNT='';$p->formSUB='';$p->formBOD='';$p->formME=_t("Thank you!","forms");
		} else {
			if (isset($_GET['who'])&&$_GET['who']) { $who=$_GET['who']; } else $this->redir("forms");
			if (isset($this->forms[$who])) { 
				$j=json_decode($this->forms[$who]);
				$p=$j;
				if(MULTI&&$lang<>$languages[0]) {
					$fl=$this->getFile(__DIR__."/".$who.".".$lang.".madd");
					$jl=json_decode($fl);
					if ($jl) $p=$jl;
				}
			} else $this->redir("forms");
		}
		if (isset($_POST['sent'])&&$_POST['sent']=="yEs") {
			if (!$_POST['formName']) $ret.='<div class="error">'._t('Form name cannot be blank',"forms").'</div>';
			else {
				if (MULTI&&$lang<>$languages[0]) {
					if (file_put_contents(__DIR__."/".$who.".".$lang.".madd",json_encode($_POST))) {
						$_SESSION['infoMessage']=_t("The form was saved","forms");
						$this->redir("forms_form&who=".$who."&lang=".$lang);
					}
				} else {				
					$newName=$this->slugify($_POST['formName']);
					$oktowrite="";
					if ($newName==$who) {
						$oktowrite=$who;
					} else {
						if ($who) unlink(__DIR__."/".$who.".madd");
						$oktowrite=$newName;
					}
					if ($oktowrite) {
						
						if (file_put_contents(__DIR__."/".$oktowrite.".madd",json_encode($_POST))) {
							$_SESSION['infoMessage']=_t("The form was saved","forms");
							$this->redir("forms_form&who=".$oktowrite);
						}
					}
				}//end else if (MULTI&&$lang<>$languages[0]) {
			}
		}
		$timestamp = time();
		$ret.='<div class="form">
			<form action="" method="post"><input type="hidden" name="sent" value="yEs"/><textarea id="formContent" name="formContent" style="display:none;width:650px;height:120px;">'.$p->formContent.'</textarea>';
		if (MULTI&&!$new) { 
			$ret.='<div class="row"><label>'._e("You are currently editing:").' <img src="'.SR.'a55ets/static/flags/'.$lang.'.png" /></label>';
			foreach ($languages as $k=>$l) if ($l<>$lang) {
				$curr=$_SERVER['REQUEST_URI'];
				if (strstr($curr,'&lang=')) $curr=str_replace('&lang='.$lang,'',$curr);
				$ret.='<a href="'.$curr.'&lang='.$l.'" class="flag"><img src="'.SR.'a55ets/static/flags/'.$l.'.png" /></a>';
			}
			$ret.='</div>';
		}			
		$ret.='	
			<div class="row"><label>'._t("Form name","forms").' *:</label> <input type="text" name="formName" value="'.$p->formName.'" '.(MULTI&&$lang<>$languages[0]?' readonly="readonly"':'').'/> <small>'._t("Form Name can only contain lowercase, alphanumeric characters","forms").'</small></div>
			<div class="row"><label>'._t("When submitted","forms").' *:</label>
			<label class="mediumLabel"><input '.(MULTI&&$lang<>$languages[0]?' disabled="disabled"':'').' type="radio" name="formSubmit" value="store"'.($p->formSubmit=="store"?' checked="checked"':'').' /> '._t("Store in file","forms").'</label>
			<label class="mediumLabel"><input '.(MULTI&&$lang<>$languages[0]?' disabled="disabled"':'').' type="radio" name="formSubmit" value="send"'.($p->formSubmit=="send"?' checked="checked"':'').' /> '._t("Send an email","forms").'</label>
			<label class="mediumLabel"><input '.(MULTI&&$lang<>$languages[0]?' disabled="disabled"':'').' type="radio" name="formSubmit" value="storeandsend"'.($p->formSubmit=="storeandsend"?' checked="checked"':'').' /> '._t("Store in file and send an email","forms").'</label>'.(MULTI&&!$new&&$lang<>$languages[0]?'<input type="hidden" name="formSubmit" value="'.$p->formSubmit.'"/>':'').'
			</div>
			<div class="row togglem"'.($p->formSubmit=="store"?' style="display:none;"':'').'><label>'._t("Email FROM","forms").'</label>
			<input type="text" name="formEF" value="'.$p->formEF.'" placeholder="'._t("FROM E-mail address","forms").'" />
			<input type="text" name="formNF" value="'.$p->formNF.'" placeholder="'._t("FROM name","forms").'" />
			</div>
			<div class="row togglem"'.($p->formSubmit=="store"?' style="display:none;"':'').'><label>'._t("Email TO","forms").'</label>
			<input type="text" name="formET" value="'.$p->formET.'" placeholder="'._t("TO E-mail address","forms").'" />
			<input type="text" name="formNT" value="'.$p->formNT.'" placeholder="'._t("TO name","forms").'" />
			</div>
			<div class="row togglem"'.($p->formSubmit=="store"?' style="display:none;"':'').'><label>'._t("Email Subject","forms").'</label>
			<input type="text" name="formSUB" value="'.$p->formSUB.'" placeholder="'._t("Email Subject","forms").'" />
			</div>
			<div class="row togglem"'.($p->formSubmit=="store"?' style="display:none;"':'').'><label>'._t("Email Body","forms").'</label>
			<textarea name="formBOD" id="emailBody" placeholder="'._t("Email Body","forms").'">'.$p->formBOD.'</textarea> <small>'._t("Example:<br />{label:1}: {value:1} will become: The name of the #1 element: submitted  value<br />{label:2}: {value:2} will become: The name of the #2 element: submitted  value<br />etc.","forms").'</small>
			</div>
			<div class="row"><label>'._t("Once submitted show message","forms").'</label>
			<textarea name="formME">'.$p->formME.'</textarea>
			</div>
			<div class="row" style="text-align:right;" id="addRow"><label>'._t("Add a new element","forms").':</label> <a href="#" class="icon text" title="'._t("Text input","forms").'"></a><a href="#" class="icon textarea" title="'._t("Textarea","forms").'"></a><a href="#" class="icon select" title="'._t("Select dropdown","forms").'"></a><a href="#" class="icon checks" title="'._t("Checboxes list","forms").'"></a><a href="#" class="icon radio" title="'._t("Radio list","forms").'"></a><a href="#" class="icon captcha" title="'._t("Captcha","forms").'"></a><a href="#" class="icon submit" title="'._t("Submit button","forms").'"></a><a href="#" class="icon reset" title="'._t("Reset button","forms").'"></a></div>
			<div class="row"><ol class="sortable" id="formList">';
			$fels=json_decode($p->formContent);
			if ($fels) { $i=0; foreach ($fels as $fel) {
				//p($fel);
				$ret.='<li class="toggle" data-feltype="'.$fel->t.'"><div class="innerli"><span class="icon '.$fel->t.'"></span> <input value="'.$fel->n.'" placeholder="'._t("Element name","forms").'"/> <a href="#" class="icon ardwn"></a><a href="#" class="icon delete"></a><div class="lisub"><div class="row"><label>'._t("Required","forms").': </label><select><option value="no"'.($fel->r=="no"?' selected="selected"':'').'>'._t("No","forms").'</option><option value="yes"'.($fel->r=="yes"?' selected="selected"':'').'>'._t("Yes","forms").'</option><option value="email"'.($fel->r=="email"?' selected="selected"':'').'>'._t("Yes and validate as an email","forms").'</option><option value="number"'.($fel->r=="number"?' selected="selected"':'').'>'._t("Yes and validate as a number","forms").'</option></select></div><div class="row"><label>'._t("Options","forms").': </label><textarea>'.$fel->e.'</textarea></div></div></div></li>';
			}}
			$ret.='</ol><div id="help">'._t("<strong>Notes:</strong><br /><br />- Element names are mandatory and can can only contain lowercase, alphanumeric characters.<br />- You can drag and drop the form elements to change their order.<br />- For &lt;select&gt; dropdowns, checkboxes lists and radio lists separate items with new rows, you can add &quot;|c&quot; at the end of the line to make that item the default one.<br />- For reCpatcha fields you can change the keys and theme to your own in the Options textarea, separate public key, private key and theme by a new line, the first is the public key then the private key and on the last line the theme name. Theme can be red, white, blackglass or clean","forms").'</div></div>
			<div class="row"><input id="saveForm" type="submit" name="send" value="'.($new?_t("Create Form","forms"):_t("Save Form","forms")).'" /></div>
			</form>
		</div>';		
		$ret.='<script type="text/javascript">
		$(document).ready(function(e) {
			$(\'input[name="formSubmit"]\').change(function(){
				if ($(this).val()=="store")	$(".togglem").slideUp(); else $(".togglem").slideDown();
			});
			$("#addRow .icon").click(function(e){e.preventDefault();
				var type=$(this).attr("class").replace("icon ","").replace(" err","");
				var item=$(\'<li class="toggle" data-feltype="\'+type+\'"><div class="innerli"><span class="icon \'+type+\'"></span> <input value="" placeholder="'._t("Element name","forms").'"/> <a href="#" class="icon ardwn"></a><a href="#" class="icon delete"></a><div class="lisub"><div class="row"><label>'._t("Required","forms").': </label><select><option value="no">'._t("No","forms").'</option><option value="yes">'._t("Yes","forms").'</option><option value="email">'._t("Yes and validate as an email","forms").'</option><option value="number">'._t("Yes and validate as a number","forms").'</option></select></div><div class="row"><label>'._t("Options","forms").': </label><textarea>\'+(type=="captcha"?"6Lctne4SAAAAAFQDWq4quiqdShhJZuCAT8BTCV9o"+"\n"+"6Lctne4SAAAAALJGRkkEhGXMRZw4ww789ukF0hNZ"+"\n"+"white":"")+\'</textarea></div></div></div></li>\').hide();
				$("ol.sortable").append(item);
			    item.show("explode",600,function(){rewCont();item.find(".ardwn").trigger("click");});
				var c=$("ol.sortable li").length;
				$("#emailBody").val($("#emailBody").val()+"\n"+"{label:"+c+"}: {value:"+c+"}"+"\n");
			});
			rewCont();
			$("ol.sortable").nestedSortable({
				disableNesting: "no-nest",
				forcePlaceholderSize: true,
				handle: "div.innerli",
				items: "li.toggle",
				opacity: .6,
				placeholder: "placeholder",
				tabSize: 2500,
				tolerance: "pointer",
				toleranceElement: "> div.innerli",
				maxLevels: 1,
				update: rewCont
			});			
			$("ol.sortable li input, ol.sortable li select, ol.sortable li textarea").live("change",function(){ rewCont(); });
			$(".delete").live("click",function(e){e.preventDefault();
				if (confirm("'._t("Are you sure you want to delete this form element?","forms").'")) {
					var p=$(this).parent().parent();
					p.hide("explode",800);p.remove();					
					rewCont();
				}
			});
			$(".ardwn, .arup").live("click",function(e){e.preventDefault();
				var lis=$(this).parent().find(".lisub");
				if (lis.is(":visible")) {
					lis.slideUp("fast");$(this).removeClass("arup");$(this).addClass("ardwn");
				} else {
					lis.slideDown("fast");$(this).removeClass("ardwn");$(this).addClass("arup");
				}
			});
			$("#saveForm").click(function(e){
				var ok=true; var sub=false;
				$.each($("ol.sortable li"),function(i,e){
					var n=$(this).find("input");
					if (n.val()=="") {
						$(this).addClass("err");
						ok=false;
					} else $(this).removeClass("err");
					if ($(this).attr("data-feltype")=="submit") sub=true;
				});
				if (sub==false) $("#addRow .submit").addClass("err");
				else $("#addRow .submit").removeClass("err");
				if (ok&&sub) return true;
				else return false;
			});
		});
		function rewCont(){
			var c=new Array();
			$.each($("ol.sortable li"),function(i,e){
				var cl=new Object();
				var t=$(this).attr("data-feltype");
				var n=$(this).find("input");
				var r=$(this).find("select");
				var e=$(this).find("textarea");
				if (t=="submit"||t=="reset") {
					$(this).find(".ardwn, .lisub").hide().removeClass("ardwn");
				} else {
					if (t=="text"||t=="textarea") { e.parent().hide(); }
					else { 
						if (t=="captcha") {
							r.parent().hide();
						} else r.find("option:eq(3)").remove(); r.find("option:eq(2)").remove();
					}
				}				
				cl.n=n.val();cl.t=t;cl.r=r.val();cl.e=e.val();
				c[i]=cl;
			});
			$("#formContent").val(JSON.stringify(c));
		}
		</script>';
		return $ret;
	}
	public function ActionAdmin_forms_delete(){global $languages;
		if (isset($_GET['who'])&&$_GET['who']) $who=$_GET['who']; else $this->redir("forms");
		$this->setCrumbs(array(SR=>_t("Home","forms"),SR.ADMIN.'/dash'=>_t("Admin","forms"),SR.ADMIN.'/forms'=>_t("Manage Forms","forms"),'#'=>_t("Are you sure you want to delete this form","forms")));
		if (!isset($this->forms[$who])) return '<div class="error">'._t('This form does not exist',"forms").'</div>';
		if (isset($_POST['noDelete'])) $this->redir("forms");		
		if (isset($_POST['yesDelete'])) { 
			if (unlink(__DIR__."/".$who.".madd")) {
				if (MULTI) foreach($languages as $l) if ($l<>$languages[0]) @unlink(__DIR__."/".$who.".".$l.".madd");				
				return '<div class="success">'._t('Succesfully deleted:',"forms").' '.ucfirst($who).'</div>';
			} else return '<div class="error">'._t('Cannot delete:',"forms").' '.$who.'</div>';
		}
		$ret='<div class="form"><form action="" method="post">
			<div class="row error">'._t("Are you sure you want to delete this form","forms").' '.ucfirst($who).'</div>
			<div class="row"><input type="submit" name="yesDelete" value="'._t('Yes',"forms").'" /> <input type="submit" name="noDelete" value="'._t('No',"forms").'" /></div>
			</form>
		</div>';		
		return $ret;
	}
	public function ActionAdmin_forms_subs(){
		if (isset($_GET['who'])&&$_GET['who']) $who=$_GET['who']; else $this->redir("forms");
		$this->setCrumbs(array(SR=>_t("Home","forms"),SR.ADMIN.'/dash'=>_t("Admin","forms"),SR.ADMIN.'/forms'=>_t("Manage Forms","forms"),'#'=>_t("Form submissions for","forms").": ".ucfirst($who)));
		$fc=$this->getFile(__DIR__."/".$who.".madds");
		if (!$fc) return '<div class="error">'._t('This form does not have submissions',"forms").'</div>';
		$ret='';$j=json_decode($fc);
		//p($j[0]->data);
		if ($j&&!empty($j)) {
			$ret.='<table border="1" cellpadding="0" cellspacing="0" width="100%" id="adTable"><thead><tr><th>'._t("Date","forms").'</th><th>'._t("IP","forms").'</th>';
			foreach ($j[0]->data as $k=>$v) $ret.='<th>'.$k.'</th>';			
			$ret.='</thead><tbody>';		
			foreach ($j as $k=>$sub) {
				$ret.='<tr><td>'.$sub->dt.'</td><td>'.$sub->ip.'</td>';
				foreach ($sub->data as $col) $ret.='<td>'.$col.'</td>';
				$ret.='<tr>';
			}
		$ret.='</tbody></table>';
		}
		return $ret;
	}
	function __construct(){global $languages;
		if ((MULTI&&$this->getCurlang()&&$this->getCurlang()<>$languages[0])) {
			$forms=glob(__DIR__."/*.".$this->getCurlang().".madd");
			if ($forms) foreach ($forms as $form) {
				$rep=str_replace(array(__DIR__."/",".madd",".".$this->getCurlang()),array("","",""),$form);
				$f=$this->getFile($form);
				if ($f) $this->forms[$rep]=$f;
			}
		} else {
			$forms=glob(__DIR__."/*.madd");
			if ($forms) foreach ($forms as $form) {
				$rep=str_replace(array(__DIR__."/",".madd"),array("",""),$form);
				if (!strstr($rep,".")) {
					$f=$this->getFile($form);
					$this->forms[$rep]=$f;
				}
			}
		}
	}
}
?>