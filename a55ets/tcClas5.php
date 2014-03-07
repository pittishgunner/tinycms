<?php
session_start();
defined('TC_PATH') or define('TC_PATH',dirname(__FILE__));
class TinyCMS extends THelpers {
	private $st,$et;
	public $cpath,$curl,$csegments,$cpage;
	public $ma = array('mt'=>'','md'=>'','mk'=>'','co'=>'','la'=>'','lc'=>'','rc'=>'');
	public $isAdminZone=false;
	public $isAdminLogged=false;
	public $AdminAction;
	private $am=array(); 
	private $curlang='';
	/*pluggable*/
	public $contentExtraSearch=array();
	public $contentExtraReplace=array();
	public $amExtraItems=array();
	public $_plugins=array();
	public $BeNeeds=array("jquery","elrte","jqueryui","uploadify","jquery.ui.nestedSortable.js","jquery.jeditable.mini.js","admin.css","admin.js");
	public $FeNeeds=array("jquery");
	
	function __construct(){
		if (file_exists("install.php")) { echo '<meta charset="UTF-8"><h3>'._e("Fatal error: please remove install.php from the server").'</h3>'; exit; }
		if (LANG=="ro") date_default_timezone_set("Europe/Bucharest");
		elseif (LANG=="de") date_default_timezone_set("Europe/Berlin");
		else date_default_timezone_set("Europe/London");
		$this->init();
	}
	public function ver(){
		return "1.0b";
	}
	public function init(){ global $languages;
		if (!file_exists(DATA_FOLDER."public/404.madd")) $this->installTinyCMS();
		$this->st = microtime(true);
		$mtime = microtime();
		$cleanUrl=$this->cleanurl(@$_GET['r']);
		$fp=isset($_GET['r'])?str_replace(URL_ENDING,".madd",$cleanUrl):"index.madd";
		if (MULTI&&$fp=="index.madd") {
			header("Location: ".SR.$languages[0]."/index".URL_ENDING); exit;
		}
		if (MULTI) { $tar=explode("/",$fp); $lang=isset($tar[0])?$tar[0]:$languages[0]; if (!in_array($lang,$languages)) $lang=$languages[0]; $this->curlang=$lang; $this->setCurlang($lang); }
		if (MULTI&&$lang."/"==$fp) { header("Location: ".SR.$lang."/index".URL_ENDING); exit; }//$fp=$lang."/index.madd";
		if (isset($_SESSION['AdminCode'])&&$_SESSION['AdminCode']==PASS) $this->isAdminLogged=true;
		if (strstr($cleanUrl,ADMIN)) {
			$fp=DATA_FOLDER."private/l_admin.madd";
			$this->AdminAction=str_replace(array(ADMIN,URL_SEPARATOR),array("",""),$cleanUrl);
			if ($this->AdminAction=="up1oad") {
				$this->AdminUpload();exit;
			} else {
				if (strstr($this->AdminAction,"up1oad")){
				} else {
					if (!$this->isAdminLogged){//||!method_exists($this,"ActionAdmin_".$this->AdminAction)) {					
						if ($this->AdminAction<>"login") {
							$this->AdminAction="login";
							$this->redir($this->AdminAction);
						}
						$this->AdminAction="login";
					}
				}
			}
			//p($this->AdminAction);			
			$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN."/dash"=>_e("Admin"),"#"=>ucwords(str_replace("_"," ",$this->AdminAction))));
			$this->isAdminZone=true;
		} else {
			if (MULTI&&$lang<>$languages[0]) {
				$fp=substr($fp,3); $cleanUrl=substr($cleanUrl,3); 
				if (file_exists(str_replace("__","_".$lang."_",DATA_FOLDER)."public/".$fp)) $fp=str_replace("__","_".$lang."_",DATA_FOLDER)."public/".$fp;
				else { header('HTTP/1.0 404 Not Found'); $fp=DATA_FOLDER."public/404.madd"; }
			} else {
				if (MULTI&&$lang==$languages[0]) { $fp=substr($fp,3); $cleanUrl=substr($cleanUrl,3); }
				if (file_exists(DATA_FOLDER."public/".$fp)) $fp=DATA_FOLDER."public/".$fp;
				else { header('HTTP/1.0 404 Not Found'); $fp=DATA_FOLDER."public/404.madd"; }
			}
		}
		$this->readCFile($fp);
		if ($this->isAdminZone) return;
		$this->cpath=$fp;
		$this->curl=($fp==DATA_FOLDER."public/404.madd"?"404".URL_ENDING:($cleanUrl?$cleanUrl:"index".URL_ENDING));
		$this->csegments=explode(URL_SEPARATOR,str_replace(URL_ENDING,"",$this->curl));
		$this->cpage=end($this->csegments);
		if (count($this->csegments)==1) {
			if ($this->cpage<>"index") {
				$homePage=json_decode($this->getFile((MULTI&&$this->curlang&&$this->curlang<>$languages[0]?str_replace("__","_".$this->curlang."_",DATA_FOLDER):DATA_FOLDER)."public/index.madd"));
				$this->setCrumbs(array((MULTI?SR.$this->curlang.'/':SR)=>$homePage[0],"#"=>$this->ma["mt"]));
			} else $this->setCrumbs(array("#"=>$this->ma["mt"]));
		} else {
			$ar=array();
			$homePage=json_decode($this->getFile((MULTI&&$this->curlang&&$this->curlang<>$languages[0]?str_replace("__","_".$this->curlang."_",DATA_FOLDER):DATA_FOLDER)."public/index.madd"));			
			$ar[(MULTI?SR.$this->curlang.'/':SR)]=$homePage[0];
			for ($i=0;$i<count($this->csegments)-1;$i++) {
				$c=json_decode($this->getFile((MULTI&&$this->curlang&&$this->curlang<>$languages[0]?str_replace("__","_".$this->curlang."_",DATA_FOLDER):DATA_FOLDER)."public/".$this->csegments[$i].".madd"));//TODO pe 3 nivele
				$ar[SR.(MULTI&&$this->curlang?$this->curlang.'/':'').$this->csegments[$i].URL_ENDING]=$c[0];
			}
			$ar["#"]=$this->ma["mt"];
			$this->setCrumbs($ar);
		}
	}
	public function run(){
		global $plugins;
		if (is_array($plugins)&&!empty($plugins)) {
			$returned=array();
			foreach ($plugins as $plugin) {
				$this->_plugins[$plugin]=new $plugin();
				$returned=$this->_plugins[$plugin]->runPlugin();
				if (isset($returned['needs'])) {
					$this->FeNeeds=array_merge($this->FeNeeds,$returned['needs'][0]);
					$this->BeNeeds=array_merge($this->BeNeeds,$returned['needs'][1]);
				}
				if (count($returned['contentSearch'])==count($returned['contentReplace'])) {
					$this->contentExtraSearch=array_merge($this->contentExtraSearch,$returned['contentSearch']);
					$this->contentExtraReplace=array_merge($this->contentExtraReplace,$returned['contentReplace']);
				}
				$this->amExtraItems=array_merge($this->amExtraItems,$returned['adminMenu']);
				
			}
			$this->FeNeeds=array_unique($this->FeNeeds);$this->BeNeeds=array_unique($this->BeNeeds);
		}
		if (!file_exists("themes/".THEME_FOLDER)) { echo "ERROR: "._e("Theme folder does not exist."); exit; }
		if (!file_exists("themes/".THEME_FOLDER."/".$this->ma["la"].".html")) { echo "ERROR: "._e("Theme layout file:")." ".$this->ma["la"].".html "._e("does not exist."); exit; }
		$layoutContent=$this->getFile("themes/".THEME_FOLDER."/".$this->ma["la"].".html");
		if ($this->ma["la"]<>"l_full_content") {
			$mainLayoutContent=$this->getFile("themes/".THEME_FOLDER."/l_full_content.html");
			$layoutContent=str_replace("[[CONTENT]]",$layoutContent,$mainLayoutContent);
		}
		echo $this->replaceShorts($layoutContent);		
	}
	private function readCFile($fp){global $languages;
		if (!file_exists($fp)) return;
		$content=$this->getFile($fp);
		$content=json_decode($content);
		$i=0;foreach($this->ma as $k=>$v) {
			if ($k=="lc") {	
				$this->ma["lc"]=$this->getFile((MULTI&&$languages[0]<>$this->curlang?str_replace("__","_".$this->curlang."_",DATA_FOLDER):DATA_FOLDER)."private/left_sidebar.madd");
			} elseif ($k=="rc") {
				$this->ma["rc"]=$this->getFile((MULTI&&$languages[0]<>$this->curlang?str_replace("__","_".$this->curlang."_",DATA_FOLDER):DATA_FOLDER)."private/right_sidebar.madd");
			} else $this->ma[$k]=isset($content[$i])?$content[$i]:"";
			$i++;
		}
	}
	private function replaceShorts($s){
		$se=array("[[TITLE]]","[[METADESCRIPTION]]","[[METAKEYWORDS]]","[[CONTENT]]","[[LEFTCONTENT]]","[[RIGHTCONTENT]]","[[THEMEPATH]]","[[HOMEURL]]","[[CURRENT_PAGE]]","[[LANGUAGELINKS]]","[[CURRENT_YEAR]]",'<body>');
		$re=array($this->ma["mt"],$this->ma["md"],$this->ma["mk"],($this->isAdminZone?'<div id="AdminWrapper">'.$this->actionAdmin().'</div>':$this->ma["co"]),$this->ma["lc"],$this->ma["rc"],SR."themes/".THEME_FOLDER."/",(MULTI&&$this->curlang?SR.$this->curlang."/":SR),$this->cpage,$this->getLangLinks(),date("Y"),'<body class="'.$this->ma['la'].(isset($this->cpage)&&$this->cpage?' bPage bPage_'.$this->cpage:'').'">');
		if (!$this->isAdminZone) {
			$se=array_merge($se,$this->contentExtraSearch);
			$re=array_merge($re,$this->contentExtraReplace);
		}
		$ret=str_replace($se,$re,$s);
		if (strstr($ret,"[[MENU")) {
			$matches=array();
			preg_match_all("/\[\[MENU-(.*?)\]\]/", $ret, $matches);
			if (!empty($matches))
				foreach ($matches[0] as $k=>$v)
					$ret=str_replace($v,$this->getMenu($matches[1][$k],"ul",(MULTI&&$this->curlang?$this->curlang:'')),$ret);
		}
		if (strstr($ret,"[[CRUMBS]]")){
			$breads="";
			$bc=$this->getCrumbs();
			if (!empty($bc)) { $i=0; foreach ($bc as $k=>$v) { $i++;
				$breads.=($k=="#"?'<span'.($i==1?' class="home"':'').'>'.$v.'</span>':'<a href="'.$k.'">'.$v.'</a>&nbsp;&raquo;&nbsp;');
			}}
			$ret=str_replace("[[CRUMBS]]",$breads,$ret);
		}
		$ret=str_replace(array("</head>","</body>"),array($this->getHead()."</head>",$this->getFoot()."</body>"),$ret);
		return $ret;
	}
	private function getMenu($m,$t,$lang=""){global $languages;
		$ret="";
		if (!file_exists(DATA_FOLDER."menus/".$m.".madd")) return;
		if (!$lang||$lang==$languages[0]) $fgc=$this->getFile(DATA_FOLDER."menus/".$m.".madd");
		else $fgc=$this->getFile(str_replace("__","_".$lang."_",DATA_FOLDER)."menus/".$m.".madd"); 
		$lines=json_decode($fgc); $menu=array();		
		if ($lines) foreach ($lines as $line) $menu[$line[0]]=array("p"=>$line[1],"n"=>$line[2],"u"=>$line[3]);
		$orderedMenu=array();
		$i=0; foreach ($menu as $k=>$v) { if (isset($v['p'])) { $orderedMenu[$v['p']][$i]=array("id"=>$k,"n"=>$v["n"],"u"=>$v["u"]); $i++; } }
		$ret=$this->recursiveMenu($orderedMenu,0,0,$t);
		return $ret;
	}
	private function recursiveMenu($m,$p,$l,$t){global $languages;
		static $ret;
		if($l==0) $ret= '';
		if (!isset($m[$p])) {return $ret; }
		if(isset($m[$p])&&count($m[$p]) > 0){
			$ret .= "<".$t.' class="level_'.$l.($l==0&&$t=="ol"?' sortable':'').'">';
			$i=0;
			foreach ($m[$p] as $model) {$i++;
				if ($t=="ol")
					$ret.='<li id="list_'.$model["id"].'"><div class="aseListItem"><div class="editable" id="editableId_'.$model["id"].'" data-url="'.$model["u"].'" data-text="'.$model["n"].'">'.$model["n"].'</div><a href="#" class="atodel" rel="'.$model["id"].'">X</a><a href="#" class="atourl" rel="'.$model["id"].'">URL</a></div>';
				else {
					$url=$model["u"];$target='';
					if (strstr($url,"http://")||strstr($url,"https://")) {
						$parsed=parse_url($url);
						if ($_SERVER['HTTP_HOST']<>$parsed['host']) $target=' target="_blank"';
					} else {
						if (MULTI&&$this->curlang) $url=(substr($url,0,1)=="/"?substr(SR,0,-1):SR.$this->curlang.'/').$url;
						else $url=(substr($url,0,1)=="/"?substr(SR,0,-1):SR).$url;
					}
					$tempsegments=explode(URL_SEPARATOR,$model["u"]);
					$ret.='<li class="'.($i==count($m[$p])?"last":'').($i==1?"first":'').($this->cpage.URL_ENDING==end($tempsegments)?' active':'').'"><a href="'.$url.'"'.$target.'>'.$model["n"].'</a>';
				}
				$this->recursiveMenu($m,$model["id"],$l+1,$t);
				$ret.='</li>';
			}
			$ret .= "</".$t.">";
		}	
		return $ret;
	}
	/**/
	private function actionAdmin(){
		$str="ActionAdmin_".$this->AdminAction;
		$s='';
		if (isset($_SESSION["infoMessage"])&&$_SESSION["infoMessage"]<>'') {
			$s='<div class="info">'.$_SESSION["infoMessage"].'</div>';
			$_SESSION["infoMessage"]="";
			unset($_SESSION["infoMessage"]);
		}
		if (method_exists($this,$str)) {
			return $s.$this->$str();
		} else {
			foreach ($this->_plugins as $p) {
				if (method_exists($p,$str)) return $s.$p->$str();
			}
		}
		$this->redir("login");
	}
	private function ActionAdmin_dash(){
		$this->setCrumbs(array(SR=>_e("Home"),"#"=>_e("Admin")));
		return '<h3>'._e('Administrator Dashboard').'</h3>'.$this->getAdminMenu();
	}
	private function ActionAdmin_login(){
		if ($this->isAdminLogged) return _e('You are already logged in. Click ').'<a href="'.SR.ADMIN.'/logout">'._e('here').'</a> '._e('to log out.');
		$r='';$err='';
		if (isset($_POST['send'])) {
			if (!$_POST['pas5']) $err=_e('Code cannot be empty');
			else {
				if ($_POST['pas5']&&md5($_POST['pas5'])<>PASS) $err=_e('Incorect code');
				else {
					$_SESSION["AdminCode"]=PASS;
					$this->isAdminLogged=true;
					$this->redir("dash");
				}
			}
		}
		if ($err) $r.='<div class="error">'.$err.'</div>';
		$r.='
		<div class="form">
			<form action="" method="post">
			<div class="row"><label for="password">'._e('Your code please').': </label> <input type="password" name="pas5" value=""/></div>
			<div class="row"><label> </label> <input type="submit" name="send" value="'._e('Login').'" /></div>
			</form>
		</div>';
		return $r;
	}
	private function ActionAdmin_logout(){
		$_SESSION["AdminCode"]="";
		unset($_SESSION["AdminCode"]);
		$this->isAdminLogged=false;
		$this->redir("login");
	}
	private function ActionAdmin_settings(){global $languages,$plugins;
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),"#"=>_e("Settings")));
		$r='';$pasEr='';$extSucc='';$genSucc='';
		if (isset($_POST['extSent'])&&$_POST['extSent']=='yEs') {
			if ($_POST['extHead']) { file_put_contents(DATA_FOLDER."private/extraHead.madd",$_POST["extHead"]); $extSucc=_e("Extra html code saved succesfully"); }
			else @unlink(DATA_FOLDER."private/extraHead.madd");
			if ($_POST['extHead']) { file_put_contents(DATA_FOLDER."private/extraFoot.madd",$_POST["extFoot"]); $extSucc=_e("Extra html code saved succesfully"); }
			else @unlink(DATA_FOLDER."private/extraFoot.madd");
		}
		if (isset($_POST['genSent'])&&$_POST['genSent']=='yEs') {
			if ($_POST['multi']=="yes"&&!$_POST['siteLanguages']) $genSucc=_e("You must input at least one primary language");
			else {
				$of=$this->getFile("a55ets/s3tt1nGs.php");
				$savedLangs=array();$savedPlugins=array();
				$tar=explode(",",$_POST['siteLanguages']);
				foreach ($tar as $_tar) $savedLangs[]=$this->slugify(trim($_tar));
				if (isset($_POST['enabledPLugins'])&&!empty($_POST['enabledPLugins']))
					foreach ($_POST['enabledPLugins'] as $_pl) $savedPlugins[]=$_pl;
				$search=array('define("LANG","'.LANG.'");','define("THEME_FOLDER","'.THEME_FOLDER.'");','define("MULTI",'.(MULTI?'true':'false').');','$languages=array('.(!empty($languages)?'"':'').implode('","',$languages).(!empty($languages)?'"':'').');','$plugins=array('.(!empty($plugins)?'"':'').implode('","',$plugins).(!empty($plugins)?'"':'').');');
				$replace=array('define("LANG","'.$_POST['tinyLang'].'");','define("THEME_FOLDER","'.$_POST['siteTheme'].'");','define("MULTI",'.($_POST['multi']=="yes"?'true':'false').');','$languages=array('.(!empty($savedLangs)?'"':'').implode('","',$savedLangs).(!empty($savedLangs)?'"':'').');','$plugins=array('.(!empty($savedPlugins)?'"':'').implode('","',$savedPlugins).(!empty($savedPlugins)?'"':'').');');
				$nf=file_put_contents("a55ets/s3tt1nGs.php",str_replace($search,$replace,$of));
				$_SESSION['infoMessage']=_e("General Settings saved succesfully");		
				$this->redir("settings");		
			}
		}
		if (isset($_POST['passSent'])&&$_POST['passSent']=='yEs') {
			if (!$_POST['opas5']) $pasEr=_e('Fill in your current password');
			elseif (!$_POST['npas5']) $pasEr=_e('Fill in your new password');
			else {
				if ($_POST['opas5']&&md5($_POST['opas5'])<>PASS) $pasEr=_e('Your password is incorrect');
				else {
					$of=$this->getFile("a55ets/s3tt1nGs.php");
					$nf=file_put_contents("a55ets/s3tt1nGs.php",str_replace('define("PASS","'.md5($_POST['opas5']).'");','define("PASS","'.md5($_POST['npas5']).'");',$of));
					$_SESSION['infoMessage']=_e("Your password was changed. Please login again");
					$this->ActionAdmin_logout();
				}
			}			
		}
		$themes=array();
		$g=glob("themes/*"); if ($g) foreach ($g as $dir) {
			if (file_exists($dir."/index.php")) {
				$fc=$this->getFile($dir."/index.php");
				$lines=explode("\n",$fc);
				if ($lines) foreach ($lines as $l) {
					$temp=explode(": ",$l);
					if (count($temp)==2) $themes[str_replace("themes/","",$dir)][$temp[0]]=$temp[1];
				}
			}
		}
		if ($genSucc<>'')	$r.='<div class="success">'.$genSucc.'</div>';
		$r.='<div class="form">
			<form action="" method="post"><input type="hidden" name="genSent" value="yEs"/><div class="row"><strong>'._e("General").'</strong></div>
			<div class="row"><label for="tinyLang">'._e('TinyCMS Admin Language').': </label> <select style="width:80px;" name="tinyLang" id="tinyLang">';
			if (glob("a55ets/lang_*.php")) foreach (glob("a55ets/lang_*.php") as $fn) {
				$l=str_replace(array("a55ets/lang_",".php"),array("",""),$fn);
				$r.='<option value="'.$l.'"'.(LANG==$l?' selected="selected"':'').'>'.$l.'</option>';
			}
		$r.='</select></div>
			<div class="row"><label for="siteTheme">'._e('Site Theme').': </label>';
			if (!empty($themes)) foreach ($themes as $dir=>$theme) {
				$r.='<div class="set_theme"><label for="siteTheme_'.$dir.'">'.(isset($theme['Preview'])?'<img src="data:image/jpg;base64,'.$theme['Preview'].'"/>':'').'</label> <input type="radio" name="siteTheme" id="siteTheme_'.$dir.'" value="'.$dir.'"'.($dir==THEME_FOLDER?' checked="checked"':'').' /> '.(isset($theme['URL'])?'<a href="'.$theme['URL'].'" target="_blank">':'').(isset($theme['Name'])?$theme['Name']:'').(isset($theme['URL'])?'</a>':'').'<br />'._e("by: ").(isset($theme['Email'])?'<a href="mailto:'.$theme['Email'].'">':'').(isset($theme['Author'])?$theme['Author']:'').(isset($theme['Email'])?'</a>':'').'</div>';			
			}
		$r.='</div>
			<div class="row"><label>'._e("Is the website multilingual ?").'</label> <input type="radio" name="multi" id="multi_no" value="no" '.(!MULTI?' checked="checked"':'').'> <label class="smallLabel" for="multi_no">'._e("No").'</label> <input type="radio" name="multi" id="multi_yes" value="yes" '.(MULTI?' checked="checked"':'').'><label class="smallLabel" for="multi_yes">'._e("Yes").'</label>  </div>
			<div class="row"'.(!MULTI?' style="display:none;"':'').' id="multiRow"><label for="siteLanguages">'._e("Website languages").'</label> <input type="text" name="siteLanguages" id="siteLanguages" value="'.implode(",",$languages).'"/> <small>'._e("Separate by comma. No spaces. First one is the primary language stored in the").' '.DATA_FOLDER.' '._e("folder").'</small> </div>
			<div class="row"><label>'._e("Enabled plugins").'</label> ';
			if (glob("a55ets/plugins/*")) foreach (glob("a55ets/plugins/*") as $dn) {
				$pluginName=str_replace("a55ets/plugins/","",$dn);
				if (file_exists($dn.'/'.$pluginName.'.php')) {
					$r.='<input type="checkbox" name="enabledPLugins[]" value="'.$pluginName.'" id="enabledPLugins_'.$pluginName.'" '.(in_array($pluginName,$plugins)?' checked="checked"':'').'/> <label for="enabledPLugins_'.$pluginName.'" class="smallLabel">'.$pluginName.'</label>';
				}
			}				
		$r.='</div>
			<div class="row"><label> </label> <input type="submit" name="send" value="'._e('Save').'" /></div>
			</form>
		</div>';	
		if ($extSucc<>'')	$r.='<div class="success">'.$extSucc.'</div>';
		$r.='<div class="form">
			<form action="" method="post"><input type="hidden" name="extSent" value="yEs"/><div class="row"><strong>'._e("Extra html code").'</strong></div>
			<div class="row"><label for="extHead">'._e('Extra html code to put in the header').': </label> <textarea name="extHead">'.$this->getFile(DATA_FOLDER."private/extraHead.madd").'</textarea> <small>'._e("This code will be inserted in all the pages before the &lt;head/&gt; tag.").'</small></div>
			<div class="row"><label for="extFoot">'._e('Extra html code to put in the footer').': </label> <textarea name="extFoot">'.$this->getFile(DATA_FOLDER."private/extraFoot.madd").'</textarea> <small>'._e("This code will be inserted in all the pages before the &lt;body/&gt; tag.").'</small></div>
			<div class="row"><label> </label> <input type="submit" name="send" value="'._e('Save').'" /></div>
			</form>
		</div>';		
		if ($pasEr<>'')	$r.='<div class="error">'.$pasEr.'</div>';
		$r.='<div class="form">
			<form action="" method="post"><input type="hidden" name="passSent" value="yEs"/><div class="row"><strong>'._e("Password change").'</strong></div>
			<div class="row"><label for="opassword">'._e('Your old password please').': </label> <input type="password" name="opas5" id="opas5" value=""/></div>
			<div class="row"><label for="npassword">'._e('Your new password please').': </label> <input type="password" name="npas5" id="npas5" value=""/></div>
			<div class="row"><label> </label> <input type="submit" name="send" value="'._e('Change Password').'" /></div>
			</form>
		</div>
		<script>$(function() { $("input[type=radio][name=multi]").change(function(){if($(this).val()=="no") $("#multiRow").slideUp(); else $("#multiRow").slideDown(); }); });</script>
		';
		return $r;
	}
	private function assetDirs(){
		if (!is_dir('themes/'.THEME_FOLDER.'/js')) @mkdir('themes/'.THEME_FOLDER.'/js');
		if (!is_dir('themes/'.THEME_FOLDER.'/css')) @mkdir('themes/'.THEME_FOLDER.'/css');
		$dirs=array(
			'jquery'=>'<script src="'.SR.'a55ets/static/jquery/jquery-2.1.0.min.js" type="text/javascript"></script>'."\n".
				'<script src="'.SR.'a55ets/static/jquery/jquery-migrate-1.2.1.min.js" type="text/javascript">var $.curCSS = $.css;</script>'."\n",				
			'elrte'=>'<script src="'.SR.'a55ets/static/e1rte/js/elrte.min.js" type="text/javascript"></script>'."\n"
				.(file_exists('a55ets/static/e1rte/js/i18n/elrte.'.LANG.'.js')?'<script src="'.SR.'a55ets/static/e1rte/js/i18n/elrte.'.LANG.'.js" type="text/javascript"></script>'."\n":'')
				.'<link  href="'.SR.'a55ets/static/e1rte/css/elrte.min.css" rel="stylesheet" type="text/css" />'."\n",
			'jqueryui'=>'<script src="'.SR.'a55ets/static/jqueryui/jquery-ui.min.js" type="text/javascript"></script>'."\n".
				'<link  href="'.SR.'a55ets/static/jqueryui/base/jquery-ui.min.css" rel="stylesheet" type="text/css" />'."\n",				
			'uploadify'=>'<script src="'.SR.'a55ets/static/up10d1fy/jquery.uploadify.min.js" type="text/javascript"></script>'."\n".
				'<link  href="'.SR.'a55ets/static/up10d1fy/uploadify.css" rel="stylesheet" type="text/css" />'."\n",
			'cycle2'=>'<script src="'.SR.'a55ets/static/cycle2/jquery.cycle2.min.js" type="text/javascript"></script>'."\n".
				'<script src="'.SR.'a55ets/static/cycle2/jquery.cycle2.center.min.js" type="text/javascript"></script>'."\n",
		);
		if (file_exists("themes/".THEME_FOLDER."/js/jquery-2.1.0.min.js")&&file_exists("themes/".THEME_FOLDER."/js/jquery-migrate-1.2.1.min.js")) {
			$dirs['fe_jquery']='<script src="'.SR.'themes/'.THEME_FOLDER.'/js/jquery-2.1.0.min.js" type="text/javascript"></script>'."\n".
				'<script src="'.SR.'themes/'.THEME_FOLDER.'/js/jquery-migrate-1.2.1.min.js" type="text/javascript">var $.curCSS = $.css;</script>'."\n";
		} else {
			if (copy("a55ets/static/jquery/jquery-2.1.0.min.js",'themes/'.THEME_FOLDER.'/js/jquery-2.1.0.min.js')&&
				copy("a55ets/static/jquery/jquery-migrate-1.2.1.min.js",'themes/'.THEME_FOLDER.'/js/jquery-migrate-1.2.1.min.js')) {
				$dirs['fe_jquery']='<script src="'.SR.'themes/'.THEME_FOLDER.'/js/jquery-2.1.0.min.js" type="text/javascript"></script>'."\n".
					'<script src="'.SR.'themes/'.THEME_FOLDER.'/js/jquery-migrate-1.2.1.min.js" type="text/javascript">var $.curCSS = $.css;</script>'."\n";
			}			
		}
		return $dirs;
	}
	private function getHead(){
		$dirs=$this->assetDirs();
		$ret='';
		if ($this->isAdminZone) {
			$ret.='<script type="text/javascript">var SR="'.SR.'";var ELI='.(file_exists("themes/".THEME_FOLDER."/css/elrte-inner.css")?'"'.SR.'themes/'.THEME_FOLDER.'/css/elrte-inner.css"':'false').';var lang="'.LANG.'"; var translations=new Array(); translations["save"]="'._e("Save").'"; translations["cancel"]="'._e("Cancel").'"; translations["delete"]="'._e("Delete").'";</script>'."\n";
			foreach ($this->BeNeeds as $bn) {
				if (substr($bn,-4)==".css") {
					$ret.='<link  href="'.(strstr($bn,"http")?$bn:SR.'a55ets/static/'.$bn).'" rel="stylesheet" type="text/css" />'."\n";
				} elseif (substr($bn,-3)==".js") {
					$ret.='<script src="'.(strstr($bn,"http")?$bn:SR.'a55ets/static/'.$bn).'" type="text/javascript"></script>'."\n";
				} else {
					if (isset($dirs[$bn])) $ret.=$dirs[$bn];
				}
			}			
		} else {
			foreach ($this->FeNeeds as $fn) {
				if (substr($fn,-4)==".css") {
					$fi=pathinfo($fn);
					if (file_exists("themes/".THEME_FOLDER."/css/".$fi['basename'])) {
						$ret.='<link  href="'.SR."themes/".THEME_FOLDER."/css/".$fi['basename'].'" rel="stylesheet" type="text/css" />'."\n";
					} else {
						if (file_exists("a55ets/".$fn)&&copy("a55ets/".$fn,"themes/".THEME_FOLDER."/css/".$fi['basename']))
							$ret.='<link  href="'.SR."themes/".THEME_FOLDER."/css/".$fi['basename'].'" rel="stylesheet" type="text/css" />'."\n";
					}
				} elseif (substr($fn,-3)==".js") {
					$fi=pathinfo($fn);
					if (file_exists("themes/".THEME_FOLDER."/js/".$fi['basename'])) {
						$ret.='<script src="'.SR.'themes/'.THEME_FOLDER."/js/".$fi['basename'].'" type="text/javascript"></script>'."\n";
					} else {
						if (file_exists("a55ets/".$fn)&&copy("a55ets/".$fn,"themes/".THEME_FOLDER."/js/".$fi['basename']))
							$ret.='<script src="'.SR.'themes/'.THEME_FOLDER."/js/".$fi['basename'].'" type="text/javascript"></script>'."\n";
					}
				} else {					
					if (isset($dirs['fe_'.$fn])) $ret.=$dirs['fe_'.$fn];
					else $ret.=$this->getPluginFolderAssets($fn);
				}
			}			
		}
		$extraHead=$this->getFile(DATA_FOLDER."private/extraHead.madd");		
		return $ret.(BRAND?'<meta name="generator" content="TinyCMS '.$this->ver().'" />'."\n":'').($extraHead?$extraHead."\n":'');
	}
	private function getFoot(){
		$extraFoot=$this->getFile(DATA_FOLDER."private/extraFoot.madd");
		if (DEBUG&&$this->AdminAction!="up1oad") {
			$this->et = microtime(true);
			$incf=get_included_files();
			$size=memory_get_peak_usage(true);$unit=array('b','kb','mb','gb','tb','pb');
			$debug='<pre style="border:solid 1px #ccc; margin:20px; border-radius:6px; padding:16px;background:#fff">';
			$debug.="Execution:	".(number_format($this->et-$this->st,6))." sec<br />Mem usage:	".round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i]."<br />";
			$debug.="<strong>Included files:</strong> ".count($incf).'<br />'.print_r($incf,true)."<br />";
			//p($incf);
			$debug.="<strong>Read files</strong>: ".$this->getReadFiles();
			$debug.='<a href="'.SR.ADMIN.'/translations">Translation files repair</a><br />';
			$debug.="<strong>SESSION:</strong> ".print_r($_SESSION,true);
			$debug.="<strong>GET:</strong> ".print_r($_GET,true);
			$debug.="<strong>POST:</strong> ".print_r($_POST,true);
			//$debug.= "<strong>CLASSES:</strong> ".print_r(get_declared_classes(),true);
			//$debug.= "<strong>FUNCTIONS:</strong> ".print_r(get_defined_functions(),true);
			$debug.="</pre>";
		} else $debug="";
		return ($extraFoot?$extraFoot:'').($this->isAdminLogged?'<div id="AdminMenu">'.$this->getAdminMenu().'</div>':'').$debug;
	}
	private function getAdminMenu(){global $languages;
		$AdminMenuH = array(_e("Dashboard")."|dash",_e("Pages &raquo;")."|pages"=>array(_e("Create")."|pages_form",_e("Manage")."|pages",_e("Clean Unused Images")."|pages_clean"),_e("Menus &raquo;")."|menus" =>array(_e("Create")."|menus_form",_e("Manage")."|menus",),_e("Sidebars")."|sidebar");
		$AdminMenuF = array(_e("Settings")."|settings",(BRAND?_e("Help")."|http://www.tinycms.info/help/":""),_e("Logout")."|logout");
		if ($this->cpage) $AdminMenuF[]=_e("Edit this page")."|pages_form&who=".(count($this->csegments)>1?$this->csegments[0]."/":'').$this->cpage.'.madd'.(MULTI&&$this->curlang?'&lang='.$this->curlang:'');//TODO multilevel
		else $AdminMenuF[]=_e("View site")."|http://".$_SERVER['HTTP_HOST'].SR;
		if ($this->AdminAction=="pages_form"&&isset($_GET['who'])) 
			$AdminMenuF[]=_e("View page")."|http://".$_SERVER['HTTP_HOST'].SR.(MULTI?$languages[0].'/':'').str_replace(".madd",URL_ENDING,$_GET['who']);
			
		$AdminMenu=array_merge($AdminMenuH,$this->amExtraItems,$AdminMenuF);
		$ret='<style type="text/css">#AdminMenu {position:fixed; top:10px; left:10px;}#AdminMenu .AdminMenu { border:solid 1px #ccc; background:#fff;border-radius:5px;box-shadow:0px 0px 3px 3px rgba(150, 150, 150, 0.25); }#AdminMenu .AdminMenu ul,.AdminMenu li { margin:0; padding:0; list-style:none; } #AdminMenu .AdminMenu li { position:relative; } .AdminMenu li a { font-family:Arial; font-size:14px; text-decoration:none;color:#f00; display:block; padding:6px 12px; width:130px; } #AdminMenu .AdminMenu li ul { display:none; position:absolute;background:#fff;border-radius:5px;box-shadow:0px 0px 3px 3px rgba(150, 150, 150, 0.25); left:154px; top:0px; }#AdminMenu .AdminMenu li ul li a {width:180px;}#AdminMenu .AdminMenu li:hover ul { display:block; } #AdminMenu .AdminMenu li.first a { '.(BRAND?'background-image:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABYAAAAcCAYAAABlL09dAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyJpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMy1jMDExIDY2LjE0NTY2MSwgMjAxMi8wMi8wNi0xNDo1NjoyNyAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNiAoV2luZG93cykiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6N0NBQjQwNEI5ODdEMTFFMzgwNzNENUQ4MUJCMzIwODIiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6N0NBQjQwNEM5ODdEMTFFMzgwNzNENUQ4MUJCMzIwODIiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDo3Q0FCNDA0OTk4N0QxMUUzODA3M0Q1RDgxQkIzMjA4MiIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDo3Q0FCNDA0QTk4N0QxMUUzODA3M0Q1RDgxQkIzMjA4MiIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/PnefONoAAAOeSURBVHja1FZtiFRVGH7POfeee2dnXHeNFG39Yv2RFmwmWchCIKjgFwZR0C9DFKp/0f+IfkYgBAlCBVFYREGIf0TMzXXsQ1fxA2ORLTXdUWZ2vu6dO/fjnLf3zOzIrjNs49L+6MLDueedc5/3Pe953vcMR0RYCHBYoGfBiK1HDR9u/rhtEcbWtsF3j73Wu3H8C113ssbGCAkymAxkYzTziDMYzldhqFTrKuLFWsGn5dGXD3BMnSI3X5Nt83+Rio+4TNYVL6/2oltPB8yO3iDbKOE7wvB8id8kHGS0Kq7pTG5kIGCWTshmE15FgDMI7Di973w0rXMRm2g+ebhQxqw8tr4Hi2srIFTLLIh8t2LsBOX3FxoPka23MzEa4DOEbwhpRpo04FxBfC/dX8muCbXtQ8ISiABDobHaHycPNlSCdTtypcODXn1XTIfYpgoi2SQAv9UIT9FhR8i5cRRqSwSVZS4v1Pc92OTKIvPuL5J+3RK569CrsNdRKM3nCWdXsJPcrr6y5QW2fPEK34sKSlpemEkZZ64WXNbSLhvr61+2t+CptcnVFVHlEgvDEFBIMFHS8zvhj446zq9eerSwaimzlDpM0yco2umdEGgbuUDB98nA3fdkFsPgNtNczPz8NEF1zLFIlLbj5IiIkj2ESRErMOCJImINKcrtzziUKlULHgsL5G0W8aluVHGSsJ1wbVapMgV34iWZi3lekSye+dN9woVudXxtmvzMrF3lfXm+NmyRVJIZ5l8JpcepvEnCHmoGPwBj4OTL0FMuwmV80SniEk/AQ03/9PjdjYFH+n7dyRW/tKc8EBRoDgfcG2qoJlloVmjCyPzaJmIiavX95ORIs7PZqTH1EvJGRcFES2bz68fMiA7eJhwVpI7raqP0MU2+8DeyBa3CtcX8b5C3JISf39KDT97Vq0IL4hFKP/RIBX2pBO4U3fYC6fLRFOVBHzNiHJ7bscG9cc6PbPjq4nIo1iwY/bPvX4hNGE5Pc6TiAEnv1Iwac7Jwnnnn7NSz7zs3+ydO3FwJ2b/6gIoTUraeg5hKlfll4Cc/IwrVkIauBk0HjYvI5NLys7mJY6fvrd9q2WI8LVWOzNWOvaJFCrYDUCmAOEsXhYoapaubjWb2pSnsS5mUNB7TNH2+Jbt2YosI/RKw/CQwbwrApe0r6ohsrnNGI7WBaVGsIbhtxOGPH4ATGOK/qXatZvSsK/EYuWUIceNwcbot/m/+sPwjwAAexsIoWCJ6igAAAABJRU5ErkJggg==");':'').' background-repeat:no-repeat; background-position:left center; padding:6px 12px 6px 26px; width:116px; border-bottom:solid 1px #ccc; } #AdminMenu .AdminMenu li li.first a { background:#fff; padding:6px 12px;border:none; }.AdminMenu li a:hover, .AdminMenu li a.active{ background-color:#ccc; } #AdminMenu .AdminMenu li li.first a:hover, #AdminMenu .AdminMenu li li.first a.active  { background-color:#ccc; width:180px;}#AdminMenu .AdminMenu li li{white-space:nowrap;}</style>';
		$ret.='<div class="AdminMenu">'.$this->makeMenuFromArray($AdminMenu,SR.ADMIN.'/','AdminMenuUL','first','last','active').'</div>';
		return $ret;
	}
	private function ActionAdmin_pages_form(){ global $languages;
		$new=(isset($_GET["who"])&&$_GET["who"]?false:true); if(MULTI) if (isset($_GET['lang'])&&in_array($_GET['lang'],$languages)) $lang=$_GET['lang']; else $lang=$languages[0];
		$ret='';$newwho='';
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),SR.ADMIN.'/pages'=>_e("Manage Pages"),"#"=>$new?_e("Create Page"):_e("Edit Page")));
		if ($new) {
			$j=array(isset($_POST['title'])?$_POST['title']:'',isset($_POST['desc'])?$_POST['desc']:'',isset($_POST['keys'])?$_POST['keys']:'',isset($_POST['content'])?$_POST['content']:'',isset($_POST['layout'])?$_POST['layout']:"l_full_content");
			$who="";
		} else {
			if (isset($_GET['who'])&&$_GET['who']) { $who=$_GET['who']; } else $this->redir("pages");
			$f=$this->getFile(DATA_FOLDER."public/".$who);
			if (!$f) return '<div class="error">'._e('This page does not exist').'</div>';
			$j=json_decode($f);$otitle=$j[0];
			//p($j);
			if(MULTI&&$lang<>$languages[0]) {
				$fl=$this->getFile(str_replace("__","_".$lang."_",DATA_FOLDER)."public/".$who);
				$jl=json_decode($fl);
				if ($jl) { foreach ($j as $k=>$v) /*if ($k<>4)*/ $j[$k]=$jl[$k]; }
				else foreach ($j as $k=>$v) /*if ($k<>4)*/ $j[$k]="";
				//p($j);p($jl);
			}
		}
		if (isset($_POST['sent'])&&$_POST['sent']=='yEs') {
			if ($_POST['url']) {
				$newwhofolder=explode(URL_SEPARATOR,str_replace(" ","",$_POST['url']));
				foreach ($newwhofolder as $k=>$v) { $newwhofolder[$k]=trim($newwhofolder[$k]); if ($newwhofolder[$k]=="") unset($newwhofolder[$k]); }
				$newwho=$this->slugify(array_pop($newwhofolder)).'.madd';
				if (!empty($newwhofolder)) { $fols='';
					foreach ($newwhofolder as $v) $fols.=$v.URL_SEPARATOR;
					$newwho=$fols.$newwho;
				}
			}
			if (!$_POST['title']) $ret.='<div class="error">'._e("Title is mandatory").'</div>';
			else {
				if (!$_POST['url']) $ret.='<div class="error">'._e("URL is mandatory").'</div>';
				else { $oktowrite="";
					$content=json_encode(array(0=>$_POST['title'],1=>$_POST['desc'],2=>$_POST['keys'],3=>$_POST['content'],4=>$_POST['layout']));
					if (MULTI&&$lang<>$languages[0]) {
						if ($this->writeTrans($lang,"public/".$who,$content)) {
							$_SESSION['infoMessage']=_e("The page was saved");
							$this->redir("pages_form&who=".$who."&lang=".$lang);
						}
					} else {
						if (!empty($newwhofolder)) { $fols='';
							foreach ($newwhofolder as $v) {  $fols.=$v.URL_SEPARATOR;
								@mkdir(DATA_FOLDER."public/".$fols);
							}
						}
						if ($who==$newwho) $oktowrite=$who;
						else {
							if ($who) { 
								@unlink(DATA_FOLDER."public/".$who); 
								if (MULTISITE) foreach($languages as $l) if ($l<>$languages[0]) @unlink(str_replace("__","_".$l."_",DATA_FOLDER)."public/".$who); 
							}
							if (file_exists(DATA_FOLDER."public/".$newwho)) { $ret.='<div class="error">'._e("There is already a page with this url").'</div>'; $oktowrite="";}
							else $oktowrite=$newwho;
						}
						if ($oktowrite) {
							if (file_put_contents(DATA_FOLDER."public/".$oktowrite,$content)) {
								$_SESSION['infoMessage']=_e("The page was saved");
								$this->redir("pages_form&who=".$oktowrite);
							}
						}
					}//end MULTI&&$lang<>$languages[0]					
				}
			}
		}
		$timestamp = time();
		$ret.='<h3>'.($new?_e("Create Page"):_e("Edit Page").' '.(MULTI&&$lang<>$languages[0]?$languages[0].":".$otitle.' - '.$lang.':"'.$j[0]:'"'.$j[0]).'"').'</h3><div class="form">
			<form action="" method="post"><input type="hidden" name="sent" value="yEs"/>';
		if (MULTI&&!$new) { 
			$ret.='<div class="row"><label>'._e("You are currently editing:").' <img src="'.SR.'a55ets/static/flags/'.$lang.'.png" /></label>';
			foreach ($languages as $k=>$l) if ($l<>$lang) {
				$curr=$_SERVER['REQUEST_URI'];
				if (strstr($curr,'&lang=')) $curr=str_replace('&lang='.$lang,'',$curr);
				$ret.='<a href="'.$curr.'&lang='.$l.'" class="flag"><img src="'.SR.'a55ets/static/flags/'.$l.'.png" /></a>';
			}
			$ret.='</div>';
		}		
		$ret.='<div class="row"><label>'._e('Title').' *: </label> <input type="text" name="title" value="'.$j[0].'" id="Page_title"/></div>
			<div class="row"><label>'._e('URL').' *: </label> <small>http://'.$_SERVER['HTTP_HOST'].SR.'</small><input type="text" name="url" value="'.(isset($_POST['url'])?$_POST['url']:str_replace(".madd","",$who)).'" '.($who=="404.madd"||$who=="index.madd"||(MULTI&&$lang<>$languages[0])?' readonly="readonly"':'').' id="Page_url"/><small>'.URL_ENDING.'</small><div id="uploadWrap"><div id="queue"></div><input id="file_upload" name="file_upload" type="file" multiple></div></div>
			<div class="row" style="position:relative;"><textarea id="editor" name="content">'.$j[3].'</textarea>';
			if ($this->contentExtraSearch&&!empty($this->contentExtraSearch)) {
				$ret.='<div id="shortCodes"><h3>'._e("Available Shortcodes").'</h3>';
				foreach ($this->contentExtraSearch as $sc) $ret.='<p><a href="#" class="insertShortcode">'.$sc.'</a></p>';
				$ret.='</div>';
			}
		$ret.='</div>
			<div class="row"><label>'._e('Layout').': </label> <select name="layout" './*(MULTI&&$lang<>$languages[0]?' disabled="disabled"':'').*/'>';
			if (glob("themes/".THEME_FOLDER."/l_*.html")) foreach (glob("themes/".THEME_FOLDER."/l_*.html") as $f) {
				$value=str_replace(array("themes/".THEME_FOLDER."/",".html"),array("",""),$f);
				$option=ucfirst(str_replace(array("themes/".THEME_FOLDER."/l_",".html","_"),array("",""," "),$f));
				$ret.='<option value="'.$value.'"'.($value==$j[4]?' selected="selected"':'').'>'.$option.'</option>';
			}
			$ret.='</select>'./*(MULTI&&$lang<>$languages[0]?'<input type="hidden" name="layout" value="'.$j[4].'"/>':'').*/'</div>
			<div class="row"><label>'._e('Meta Description').': </label> <input type="text" name="desc" value="'.$j[1].'"/></div>
			<div class="row"><label>'._e('Meta Keywords').': </label> <input type="text" name="keys" value="'.$j[2].'"/></div>						
			<div class="row"><label> </label> <input type="submit" name="send" value="'.($new?_e("Create Page"):_e("Save Page")).'" /></div>
			</form>
		</div>
		<script>
			$(function() {
				$("#file_upload").uploadify({
					"formData"     : {"timestamp" : "'.$timestamp.'","token"     : "'.md5('uBQzQDBHYgEb+Fw09T(c39'.$timestamp).'"},
					"swf"      : "'.SR.'a55ets/static/up10d1fy/uploadify.swf",
					"uploader" : "'.SR.ADMIN.'/up1oad",
					"buttonText":"'._e("Select image(s)").'",
					"fileTypeDesc": "Image Files (*.jpg,*.jpeg,*.png,*.gif)",
					"fileTypeExts": "*.jpg;*.jpeg;*.png;*.gif",							
					"onUploadSuccess": function(file, data, response) {
						if (data.indexOf("error:")!=-1) alert(data);
						else {
							//alert(data);
							var editor = $("#editor").elrte()[0].elrte;
							editor.selection.insertText(\'<img src="'.SR.'themes/'.THEME_FOLDER.'/images/pages/\'+data+\'" />\');
						}
					}
				});
			});
		</script>';
		return $ret;
		
	}
	private function ActionAdmin_pages_delete(){ global $languages;
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),SR.ADMIN.'/pages'=>_e("Manage Pages"),"#"=>_e("Page Deletion")));
		if (isset($_GET['who'])&&$_GET['who']) $who=$_GET['who']; else $this->redir("pages");
		if ($who=="index.madd"||$who=="404.madd") return '<div class="error">'._e('This is a system page and cannot be deleted').'</div>';
		$f=$this->getFile(DATA_FOLDER."public/".$who);
		if (!$f) return '<div class="error">'._e('This page does not exist').'</div>';
		if (isset($_POST['noDelete'])) $this->redir("pages");		
		if (isset($_POST['yesDelete'])) { 
			if (unlink(DATA_FOLDER."public/".$who)) {
				if (MULTI) foreach($languages as $l) if ($l<>$languages[0]) @unlink(str_replace("__","_".$l."_",DATA_FOLDER)."public/".$who); 
				return '<div class="success">'._e('Succesfully deleted:').' '.$who.'<br />'._e("Remember to delete the menu item if it exists").'</div>';
			} else return '<div class="error">'._e('Cannot delete:').' '.$who.'</div>';
		}
		$j=json_decode($f);
		$ret='<div class="form"><form action="" method="post">
			<div class="row error">'._e("Are you sure you want to delete the following page:").'</div>
			<div style="border:solid 1px #ccc; padding:10px; margin:10px 0;background:#fff;"><h2>'.$j[0].'</h2>
			<div style="height:150px; overflow:auto;">'.$j[3].'</div>
			</div>
			<div class="row"><input type="submit" name="yesDelete" value="'._e('Yes').'" /> <input type="submit" name="noDelete" value="'._e('No').'" /></div>
			</form>
		</div>';		
		return $ret;
	}
	private function ActionAdmin_pages_clean(){ global $languages;
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),SR.ADMIN.'/pages'=>_e("Manage Pages"),"#"=>_e("Page images cleanup")));
		$ondisk=array();$inpages=array();
		if (glob("themes/".THEME_FOLDER."/images/pages/*")) foreach (glob("themes/".THEME_FOLDER."/images/pages/*") as $f) $ondisk[]=str_replace("themes/".THEME_FOLDER."/images/pages/","",$f);
		$pages=glob_recursive(DATA_FOLDER."public/*.madd");
		$sidebars=glob_recursive(DATA_FOLDER."private/*_sidebar.madd");
		$both=array_merge($pages,$sidebars);
		if (MULTI) {
			foreach ($languages as $k=>$v) if ($k<>0) {
				$langs=array();
				$langs=glob_recursive(str_replace("__","_".$v."_",DATA_FOLDER)."public/*.madd");
				if ($langs) $both=array_merge($both,$langs);
			}
		}
		//p($langs);p($pages);p($sidebars);p($both);exit;		
		if ($both&&!empty($both)) foreach ($both as $page) {
			$f=$this->getFile($page);
			$j=json_decode($f);
			$html=isset($j[3])?$j[3]:$f;
			if ($html) {
				$dom = new DOMDocument();				
				@$dom->loadHTML($html);
				$list = $dom->getElementsByTagName('img');
				if ($list) foreach ($list as $_list) {
					$src=$_list->getAttribute("src");
					if (strstr($src,"themes/".THEME_FOLDER."/images/pages")) $inpages[]=str_replace(array(SR."themes/".THEME_FOLDER."/images/pages/","themes/".THEME_FOLDER."/images/pages/"),array(""),$src);
				}
			}
		}
		$dif=array_diff($ondisk,$inpages);
		if (empty($dif)) return '<div class="info">'._e("Images folder is clean").'</div>';
		if (isset($_POST['noDelete'])) $this->redir("pages");		
		if (isset($_POST['yesDelete'])&&!empty($dif)) { 
			foreach ($dif as $d) {
				unlink("themes/".THEME_FOLDER."/images/pages/".$d);
			}
			$_SESSION['infoMessage']=_e("Page Images cleaned up succesfully");
			$this->redir("pages");			
		}
		$ret='<div class="form"><form action="" method="post">
			<div class="row error">'._e("The following images will be deleted. Are you sure you want to continue?").'</div>
			<div style="border:solid 1px #ccc; padding:10px; margin:10px 0;background:#fff;">
			<div style="height:220px; overflow:auto;">';
			foreach ($dif as $d) $ret.='<img src="'.SR.'themes/'.THEME_FOLDER.'/images/pages/'.$d.'" width="90" style="float:left; margin:0 4px 4px 0;" />';
		$ret.='</div>
			</div>
			<div class="row"><input type="submit" name="yesDelete" value="'._e('Yes').'" /> <input type="submit" name="noDelete" value="'._e('No').'" /></div>
			</form>
		</div>';
			
		return $ret;
		
	}
	private function ActionAdmin_pages(){ global $languages;
		if (MULTI&&isset($_GET['copy'])&&$_GET['copy']) {
			$source=DATA_FOLDER."public/".$_GET['copy'];
			foreach ($languages as $k=>$l) if ($k<>0) {
				$dest=str_replace("__","_".$l."_",$source);
				if (!file_exists($dest)) {
					$pi=pathinfo($dest);
					if (!is_dir($pi['dirname'])) @mkdir($pi['dirname'],0777,true);
					copy($source,$dest);
				}				
			}
			$_SESSION['infoMessage']=_e("The page was copied to the remaining untranslated languages");
			$this->redir("pages");
		}
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),"#"=>_e("Manage Pages")));
		$ret='<table border="1" cellpadding="0" cellspacing="0" width="100%" id="adTable"><thead><tr><th width="260">'._e("URL").'</th><th>'._e("Title").'</th>';
		if (MULTI) $ret.='<th>'._e("Available Languages").'</th>';
		$ret.='<th width="100">'._e("Actions").'</th></thead><tbody>';
		$pages=glob_recursive(DATA_FOLDER."public/*.madd");
		if ($pages&&!empty($pages)) foreach ($pages as $page) {
			$f=json_decode($this->getFile($page));
			$url=str_replace(array(DATA_FOLDER."public/",".madd"),array("",URL_ENDING),$page);
			$ret.='<tr><td>'.$url.'</td><td>'.$f[0].'</td>';
			if (MULTI) {
				$langs=$languages[0].','; $alc=1;
				foreach ($languages as $k=>$l) if ($k<>0&&file_exists(str_replace("__","_".$l."_",$page))) { $langs.=$l.','; $alc++; }
				$ret.='<td align="center">'.substr($langs,0,-1).(MULTI&&$alc<>count($languages)?' <a href="'.SR.ADMIN.'/pages&copy='.str_replace(DATA_FOLDER."public/","",$page).'">'._e("Copy to all languages").'</a>':'').'</td>';
			}
			$ret.='<th><a href="'.SR.ADMIN.'/pages_form&who='.str_replace(DATA_FOLDER."public/","",$page).'">'._e("Edit").'</a>'.($url<>"404".URL_ENDING&&$url<>"index".URL_ENDING?'&nbsp;|&nbsp;<a href="'.SR.ADMIN.'/pages_delete&who='.str_replace(DATA_FOLDER."public/","",$page).'">'._e("Delete").'</a>':'').'</th></tr>';
		} else $ret.='<tr><td colspan="3" align="center">'._e("No pages were found").'</td></tr>';
		$ret.='</tbody></table>';
		return $ret;
	}
	private function ActionAdmin_sidebar(){global $languages;
		if (MULTI&&isset($_GET['copy'])&&$_GET['copy']) {
			$source=DATA_FOLDER."private/".$_GET['copy'];
			foreach ($languages as $k=>$l) if ($k<>0) {
				$dest=str_replace("__","_".$l."_",$source);
				if (!file_exists($dest)) {
					$pi=pathinfo($dest);
					if (!is_dir($pi['dirname'])) @mkdir($pi['dirname'],0777,true);
					copy($source,$dest);
				}				
			}
			$_SESSION['infoMessage']=_e("The sidebar was copied to the remaining untranslated languages");
			$this->redir("sidebar");
		}
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),"#"=>_e("Sidebar Content")));
		$ret='<table border="1" cellpadding="0" cellspacing="0" width="100%" id="adTable"><thead><tr><th>'._e("Sidebar").'</th>';
		if (MULTI) $ret.='<th>'._e("Available Languages").'</th>';
		$ret.='<th width="100">'._e("Actions").'</th></thead><tbody>';
		$pages=glob(DATA_FOLDER."private/*_sidebar.madd");
		if ($pages&&!empty($pages)) foreach ($pages as $page) {
			$ret.='<tr><td>'.ucfirst(str_replace(array(DATA_FOLDER."private/",".madd","_"),array("","_"," "),$page)).'</td>';
			if (MULTI) {
				$langs=$languages[0].','; $alc=1;
				foreach ($languages as $k=>$l) if ($k<>0&&file_exists(str_replace("__","_".$l."_",$page))) { $langs.=$l.','; $alc++; }
				$ret.='<td align="center">'.substr($langs,0,-1).(MULTI&&$alc<>count($languages)?' <a href="'.SR.ADMIN.'/sidebar&copy='.str_replace(DATA_FOLDER."private/","",$page).'">'._e("Copy to all languages").'</a>':'').'</td>';
			}			
			$ret.='<th><a href="'.SR.ADMIN.'/sidebar_form&who='.str_replace(DATA_FOLDER."private/","",$page).'">'._e("Edit").'</a></th></tr>';
		} else $ret.='<tr><td colspan="3" align="center">'._e("No sidebars were found").'</td></tr>';
		$ret.='</tbody></table>';
		return $ret;
	}
	private function ActionAdmin_sidebar_form(){ global $languages;
		if (isset($_GET['who'])&&$_GET['who']) { $who=$_GET['who']; } else $this->redir("sidebar"); 
		if(MULTI) if (isset($_GET['lang'])&&in_array($_GET['lang'],$languages)) $lang=$_GET['lang']; else $lang=$languages[0];
		$f=$this->getFile(DATA_FOLDER."private/".$who);
		if ($f===false) return '<div class="error">'._e('This sidebar does not exist').'</div>';
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),SR.ADMIN.'/sidebar'=>_e("Sidebars"),"#"=>_e("Sidebar").': '.ucfirst(str_replace(array(".madd","_"),array(""," "),$who))));
		if(MULTI&&$lang<>$languages[0])	$f=$this->getFile(str_replace("__","_".$lang."_",DATA_FOLDER)."private/".$who);
		$ret='';
		$timestamp = time();
		if (isset($_POST['sent'])&&$_POST['sent']=='yEs') {
			if (MULTI&&$lang<>$languages[0]) {
				if ($this->writeTrans($lang,"private/".$who,$_POST['content'])) {
					$_SESSION['infoMessage']=_e("The sidebar was saved");
					$this->redir("sidebar_form&who=".$who."&lang=".$lang);
				}
			} else 			
				if (file_put_contents(DATA_FOLDER."private/".$who,$_POST['content'])) {
					$_SESSION['infoMessage']=_e("The sidebar was saved");
					$this->redir("sidebar_form&who=".$who);
				}
		}
		$ret.='<div class="form">
			<form action="" method="post"><input type="hidden" name="sent" value="yEs"/>';
		if (MULTI) { 
			$ret.='<div class="row"><label>'._e("You are currently editing:").' <img src="'.SR.'a55ets/static/flags/'.$lang.'.png" /></label>';
			foreach ($languages as $k=>$l) if ($l<>$lang) {
				$curr=$_SERVER['REQUEST_URI'];
				if (strstr($curr,'&lang=')) $curr=str_replace('&lang='.$lang,'',$curr);
				$ret.='<a href="'.$curr.'&lang='.$l.'" class="flag"><img src="'.SR.'a55ets/static/flags/'.$l.'.png" /></a>';
			}
			$ret.='</div>';
		}			
		$ret.='	
			<div class="row"><label>'._e("Sidebar").': '.ucfirst(str_replace(array(".madd","_"),array(""," "),$who)).'</label> <div id="uploadWrap"><div id="queue"></div><input id="file_upload" name="file_upload" type="file" multiple></div></div>
			<div class="row" style="position:relative"><textarea id="editor" name="content">'.$f.'</textarea>';
			if ($this->contentExtraSearch&&!empty($this->contentExtraSearch)) {
				$ret.='<div id="shortCodes"><h3>'._e("Available Shortcodes").'</h3>';
				foreach ($this->contentExtraSearch as $sc) $ret.='<p><a href="#" class="insertShortcode">'.$sc.'</a></p>';
				$ret.='</div>';
			}
		$ret.='	</div>
			<div class="row"><label> </label> <input type="submit" name="send" value="'._e("Save Sidebar").'" /></div>
			</form>
		</div>
		<script>
			$(function() {
				$("#file_upload").uploadify({
					"formData"     : {"timestamp" : "'.$timestamp.'","token"     : "'.md5('uBQzQDBHYgEb+Fw09T(c39'.$timestamp).'"},
					"swf"      : "'.SR.'a55ets/static/up10d1fy/uploadify.swf",
					"uploader" : "'.SR.ADMIN.'/up1oad",
					"buttonText":"'._e("Select image(s)").'",
					"fileTypeDesc": "Image Files (*.jpg,*.jpeg,*.png,*.gif)",
					"fileTypeExts": "*.jpg;*.jpeg;*.png;*.gif",							
					"onUploadSuccess": function(file, data, response) {
						if (data.indexOf("error:")!=-1) alert(data);
						else {
							//alert(data);
							var editor = $("#editor").elrte()[0].elrte;
							editor.selection.insertText(\'<img src="'.SR.'themes/'.THEME_FOLDER.'/images/pages/\'+data+\'" />\');
						}
					}
				});
			});
		</script>';
		
		return $ret;
	}
	/**/
	private function ActionAdmin_menus(){ global $languages;	
		if (MULTI&&isset($_GET['copy'])&&$_GET['copy']) {
			$source=DATA_FOLDER."menus/".$_GET['copy'].".madd";
			foreach ($languages as $k=>$l) if ($k<>0) {
				$dest=str_replace("__","_".$l."_",$source);
				if (!file_exists($dest)) {
					$pi=pathinfo($dest);
					if (!is_dir($pi['dirname'])) @mkdir($pi['dirname'],0777,true);
					copy($source,$dest);
				}				
			}
			$_SESSION['infoMessage']=_e("The menu was copied to the remaining untranslated languages");
			$this->redir("menus");
		}	
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),"#"=>_e("Menus")));
		$ret='<table border="1" cellpadding="0" cellspacing="0" width="100%" id="adTable"><thead><tr><th>'._e("Menu Name").'</th><th>'._e("Menu Shortcode").'</th>';
		if (MULTI) $ret.='<th>'._e("Available Languages").'</th>';
		$ret.='<th width="100">'._e("Actions").'</th></thead><tbody>';
		$pages=glob(DATA_FOLDER."menus/*.madd");
		if ($pages&&!empty($pages)) foreach ($pages as $page) {
			$menu=str_replace(array(DATA_FOLDER."menus/",".madd"),array("",""),$page);
			$ret.='<tr><td>'.ucfirst($menu).'</td><td>&#91;&#91;MENU-'.$menu.'&#93;&#93;</td>';
			if (MULTI) {
				$langs=$languages[0].','; $alc=1;
				foreach ($languages as $k=>$l) if ($k<>0&&file_exists(str_replace("__","_".$l."_",$page))) { $langs.=$l.','; $alc++; }
				$ret.='<td align="center">'.substr($langs,0,-1).(MULTI&&$alc<>count($languages)?' <a href="'.SR.ADMIN.'/menus&copy='.$menu.'">'._e("Copy to all languages").'</a>':'').'</td>';
			}			
			$ret.='<th><a href="'.SR.ADMIN.'/menus_form&who='.$menu.'">'._e("Edit").'</a>&nbsp;|&nbsp;<a href="'.SR.ADMIN.'/menus_delete&who='.$menu.'">'._e("Delete").'</a></th></tr>';
		} else $ret.='<tr><td colspan="3" align="center">'._e("No menus were found").'</td></tr>';
		$ret.='</tbody></table>';
		return $ret;
	}
	private function ActionAdmin_menus_delete(){global $languages;
		if (isset($_GET['who'])&&$_GET['who']) $who=$_GET['who']; else $this->redir("pages");
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),SR.ADMIN."/menus"=>_e("Menus"),"#"=>_e("Delete Menu").' '.ucfirst($who)));
		$f=$this->getFile(DATA_FOLDER."menus/".$who.".madd");
		if ($f===false) return '<div class="error">'._e('This menu does not exist').'</div>';
		if (isset($_POST['noDelete'])) $this->redir("menus");		
		if (isset($_POST['yesDelete'])) { 
			if (unlink(DATA_FOLDER."menus/".$who.".madd")) {
				if (MULTI) foreach($languages as $l) if ($l<>$languages[0]) @unlink(str_replace("__","_".$l."_",DATA_FOLDER)."menus/".$who.".madd"); 
				return '<div class="success">'._e('Succesfully deleted:').' '.ucfirst($who).'<br />'._e("Remember to edit your template if this menu shortcode exists in it").': &#91;&#91;MENU-'.$who.'&#93;&#93;</div>';
			} else return '<div class="error">'._e('Cannot delete:').' '.$who.'</div>';
		}
		$ret='<div class="form"><form action="" method="post">
			<div class="row error">'._e("Are you sure you want to delete this menu:").' '.ucfirst($who).'</div>
			<div class="row"><input type="submit" name="yesDelete" value="'._e('Yes').'" /> <input type="submit" name="noDelete" value="'._e('No').'" /></div>
			</form>
		</div>';		
		return $ret;		
	}
	private function ActionAdmin_menus_form(){ global $languages;
		$new=(isset($_GET["who"])&&$_GET["who"]?false:true);
		if(MULTI) if (isset($_GET['lang'])&&in_array($_GET['lang'],$languages)) $lang=$_GET['lang']; else $lang=$languages[0];
		$ret='';
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),SR.ADMIN."/menus"=>_e("Menus"),"#"=>$new?_e("Create Menu"):_e("Edit Menu")));
		if ($new) {
			$who="";$f="[]";
		} else {
			if (isset($_GET['who'])&&$_GET['who']) { $who=$_GET['who']; } else $this->redir("menu");
			if (file_exists(DATA_FOLDER."menus/".$who.".madd")) { $f=$this->getFile(DATA_FOLDER."menus/".$who.".madd"); } else $this->redir("menus");
			if(MULTI&&$lang<>$languages[0]) {
				$fl=$this->getFile(str_replace("__","_".$lang."_",DATA_FOLDER)."menus/".$who.".madd");
				if ($fl) $f=$fl;
				else $f="[]";
			}
		}
		if (isset($_POST['sent'])&&$_POST['sent']=="yEs") {
			if (!$_POST['menuName']) $ret.='<div class="error">'._e('Menu name cannot be blank').'</div>';
			else {
				$newName=$this->slugify($_POST['menuName']);
				if (MULTI&&$lang<>$languages[0]) {
					if ($this->writeTrans($lang,"menus/".$who.".madd",$_POST['menuContent'])) {
						$_SESSION['infoMessage']=_e("The menu was saved");
						$this->redir("menus_form&who=".$who."&lang=".$lang);
					}
				} else {				
					$oktowrite="";
					if ($newName==$who) {
						$oktowrite=$who;
					} else {
						if ($who) unlink(DATA_FOLDER."menus/".$who.".madd");
						if (MULTISITE) foreach($languages as $l) if ($l<>$languages[0]) @unlink(str_replace("__","_".$l."_",DATA_FOLDER)."menus/".$who.".madd"); 
						$oktowrite=$newName;
					}
					if ($oktowrite) {
						if (file_put_contents(DATA_FOLDER."menus/".$oktowrite.".madd",$_POST['menuContent'])) {
							$_SESSION['infoMessage']=_e("The menu was saved");
							$this->redir("menus_form&who=".$oktowrite);
						}
					}
				}//end if (MULTI&&$lang<>$languages[0]) {
			}
		}
		$ret.='<div class="form">
			<form action="" method="post"><input type="hidden" name="sent" value="yEs"/><textarea id="menuContent" name="menuContent" style="display:none;">'.(isset($_POST['menuContent'])?$_POST['menuContent']:$f).'</textarea>';
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
			<div class="row"><label>'._e("Menu Name").' *:</label> <input type="text" name="menuName" value="'.(isset($_POST['menuName'])?$_POST['menuName']:$who).'"'.(MULTI&&$lang<>$languages[0]?' readonly="readonly"':'').' /> <small>'._e("Menu Name can only contain lowercase, alphanumeric characters").'</small></div>
			<div class="row">
				<div id="menusLeft"><div class="row"><div class="legend">'._e("Pages").'</div><div id="addPages">';
				if (MULTI&&$lang<>$languages[0]) $pages=glob_recursive(str_replace("__","_".$lang."_",DATA_FOLDER)."public/*.madd");
				else $pages=glob_recursive(DATA_FOLDER."public/*.madd");
				if ($pages&&!empty($pages)) foreach ($pages as $page) { if (strstr($page,"404")) continue;
					$f=json_decode($this->getFile($page));
					if (MULTI&&$lang<>$languages[0]) $rep=array(str_replace("__","_".$lang."_",DATA_FOLDER)."public/",".madd");
					else $rep=array(DATA_FOLDER."public/",".madd");
					$ret.='<label><input type="checkbox" name="items[]" value="'.str_replace($rep,array("",URL_ENDING),$page).'|*|'.$f[0].'" /> '.$f[0].'</label>';
				}
		$ret.='</div><input type="button" name="additem" id="addpagesButton" value="'._e("Add Selection").'" />
		</div><br />
		<div class="row"><div class="legend">'._e("Custom Links").'</div>
		<div id="addLinks">
            <label>'._e("Menu item name").':</label><input type="text" size="15" id="static_nume" name="static_nume" value="" />
            <label>'._e("Menu item URL").':</label><input type="text" size="15" id="static_url" name="static_url" value="#" />
			<div>'._e("Insert a full url element (http://....),<br />root based url (/...) or # for no url").'</div>
			<input type="button" name="additem" id="additem" value="'._e("Add menu item").'" />
        </div></div>
		</div><div id="menusRight"><div id="edMenu">';
		$menu=$this->getMenu($who,"ol",MULTI&&$lang<>$languages[0]?$lang:"");
		$ret.=($menu?$menu:'<ol class="sortable"></ol>');
		$ret.='</div><div id="underMenus">'._e("NOTES:<br />- You can double click any menu item to edit it's name").'</div></div>
			</div>
			<div class="row"><input type="submit" name="send" value="'.($new?_e("Create Menu"):_e("Save Menu")).'" /></div>
			</form>
		</div><div class="noshow" id="confirm_delete_dialog">'._e("Are you sure you want to delete this menu item and it's children ?").'</div><div class="noshow" id="url_dialog"><input type="text" id="item_url" value="" /></div>';
		
		return $ret;
	}
	/**/
	private function AdminUpload(){
		$targetFolder = 'themes/'.THEME_FOLDER.'/images/pages'; // Relative to the root
		if (!is_dir($targetFolder)) @mkdir($targetFolder);
		$verifyToken = md5('uBQzQDBHYgEb+Fw09T(c39'.(isset($_POST['timestamp'])?$_POST['timestamp']:''));
		
		if (!empty($_FILES) && $_POST['token'] == $verifyToken) {
			$tempFile = $_FILES['Filedata']['tmp_name'];
			$targetPath =$targetFolder;
			$fileTypes = array('jpg','jpeg','gif','png'); // File extensions
			$fileParts = pathinfo($_FILES['Filedata']['name']);			
			$fileName=microtime(true).$this->slugify($fileParts['filename']).'.'.$fileParts['extension'];
			$targetFile = rtrim($targetPath,'/') . '/' . $fileName;
			if (in_array($fileParts['extension'],$fileTypes)) {
				move_uploaded_file($tempFile,$targetFile);
				echo $fileName;
			} else {
				echo 'error: '._e('Invalid file type.');
			}
		}
		exit;
	}
	/**/
	public function ActionAdmin_translations(){
		if (!DEBUG) return;
		if (isset($_POST['sent'])&&$_POST['sent']=="yEs"&&isset($_POST['noDelete'])) $this->redir("dash");
		$langData=array();
		$fc=$this->getFile("a55ets/tcClas5.php");
		preg_match_all('|_e\(([\'"])(.*?)([\'"])\)|',$fc,$matches);
		$need=array();
		if ($matches[2]) foreach ($matches[2] as $v) $need[$v]='';
		uksort($need,'strcasecmp');
		if (glob("a55ets/lang_*.php")) foreach (glob("a55ets/lang_*.php") as $fn) {
			$lang=str_replace(array("a55ets/lang_",".php"),array(),$fn);
			require $fn;
			$diff=array_diff_key($need,$t);
			$extra=array_diff_key($t, $need);			
			$langData[$fn]=array("need"=>$diff,"extra"=>$extra);
		}
		if (glob("a55ets/plugins/*")) foreach (glob("a55ets/plugins/*") as $dn) {
			$matches=array();
			$pluginName=str_replace("a55ets/plugins/","",$dn);
			if (file_exists($dn.'/'.$pluginName.'.php')) {
				$fc=$this->getFile($dn.'/'.$pluginName.'.php');
				preg_match_all('|_t\(([\'"])(.*?)([\'"]),([\'"])(.*?)([\'"])\)|',$fc,$matches);
				$need=array();
				if ($matches[2]) foreach ($matches[2] as $v) $need[$v]='';
				uksort($need,'strcasecmp');
				if (glob($dn."/lang_*.php")) foreach (glob($dn."/lang_*.php") as $fn) {
					$lang=str_replace(array($dn."/lang_",".php"),array(),$fn);
					require $fn;
					$diff=array_diff_key($need,$t);
					$extra=array_diff_key($t, $need);			
					$langData[$fn]=array("need"=>$diff,"extra"=>$extra);
				}
			}
		}
		if (isset($_POST['sent'])&&$_POST['sent']=="yEs"&&isset($_POST['yesDelete'])) {
			foreach ($langData as $file=>$data) {
				require $file;
				if (!empty($data['need'])) foreach ($data['need'] as $k=>$v) $t[$k]='';
				if (!empty($data['extra'])) foreach ($data['extra'] as $k=>$v) unset($t[$k]);
				uksort($t,'strcasecmp');
				$fh = fopen($file, 'w+');
				fwrite($fh, '<?php $t= '.var_export($t, true).'; ?>');
				fclose($fh);
			}
			$_SESSION['infoMessage']="The translation files have been written.";
			$this->redir("translations");
		}
		$ret='';
		$ret.='<div class="form">
			<form action="" method="post"><input type="hidden" name="sent" value="yEs"/>
			<div class="row"><strong>The following data needs to be added/removed:</strong></div>';
			$showButts=false;
			foreach ($langData as $file=>$data) {
				if (empty($data['need'])&&empty($data['extra'])) continue;
				$ret.='<div class="row"><strong>'.(strstr($file,"plugins")?"Plugin file: ":"Core file: ").'</strong>'.str_replace("a55ets/","",$file).'<br /><br />';
				if (!empty($data['need'])) { $showButts=true;
					$ret.='<strong>To add</strong>:<br /><ol>';
					$i=0;foreach ($data['need'] as $k=>$v) {$i++;
						$ret.='<li>'.htmlentities($k).'</li>';
					}
					$ret.='</ol><br />';
				}
				if (!empty($data['extra'])) { $showButts=true;
					$ret.='<strong>To remove</strong>:<br /><ol>';
					$i=0;foreach ($data['extra'] as $k=>$v) {$i++;
						$ret.='<li>'.htmlentities($k).'</li>';
					}
					$ret.='</ol><br />';
				}
				$ret.='</div>';
			}
		if ($showButts)	$ret.='<div class="row"><input type="submit" name="yesDelete" value="Write The Files" /> <input type="submit" name="noDelete" value="Cancel" /></div>';
		else $ret.='<div class="row"><strong>Everything is ok</strong></div>';
		$ret.='
			</form>
			</div>';
		return $ret;
	}
	private function getLangLinks(){ global $languages;
		$r='';
		if (MULTI) {
			foreach ($languages as $l)	if ($l<>$this->curlang) {
				$url=SR.$l.'/'.(count($this->csegments)>1?$this->csegments[0]."/":'').$this->cpage.URL_ENDING;
				if (file_exists(($l==$languages[0]?DATA_FOLDER:str_replace("__","_".$l."_",DATA_FOLDER))."public/".(count($this->csegments)>1?$this->csegments[0]."/":'').$this->cpage.'.madd'))
					$r.='<a href="'.$url.'" class="languageUrl language_'.$l.'" rel="alternate" hreflang="'.$l.'">'.$l.'</a>'; //todo multilevel and different slugs per language
			}
			return $r;			
		} else return $r;
	}
	private function getPluginFolderAssets($dir){
		$pi=pathinfo($dir);$r='';
		if (!is_dir("themes/".THEME_FOLDER."/".$pi['filename'])) 
			if (!@mkdir("themes/".THEME_FOLDER."/".$pi['filename'])) return $r;
		$g=glob("a55ets/".$dir.'/*'); 
		if ($g) foreach ($g as $fn) {
			$_pi=pathinfo($fn);
			$to="themes/".THEME_FOLDER."/".$pi['filename']."/".$_pi['basename'];
			if (file_exists($to)||copy($fn,$to)) {
				if (substr($fn,-4)==".css") $r.='<link  href="'.SR.$to.'" rel="stylesheet" type="text/css" />'."\n";
				elseif (substr($fn,-3)==".js") $r.='<script src="'.SR.$to.'" type="text/javascript"></script>'."\n";
			}
		}
		return $r;
	}	
	public static function autoload($className){
		$classFile=TC_PATH."/plugins/".$className."/".$className.".php";
		if(is_file($classFile))	include $classFile;
	}
}
$rc=array();$bc=array();$curlang='';
abstract class THelpers {
	public function getCurlang(){
		global $curLang;
		return $curLang;
	}
	public function setCurlang($lang){
		global $curLang;
		$curLang=$lang;
		return $curLang;
	}	
	public function getCrumbs(){
		global $bc;
		return $bc;
	}
	public function setCrumbs($ar=array()){
		global $bc;
		$bc=$ar;
		return $bc;
	}
	public function getFile($file){
		if (!file_exists($file)) return false;
		$f=file_get_contents($file);
		if (DEBUG) {
			global $rc;
			$trace=debug_backtrace();
			$rc[$trace[1]['class']][]=$file;
		}
		if ($f!==false) return $f;
		else return false;
	}
	public function redir($w){
		header("Location: ".SR.ADMIN."/".$w);
		exit;
	}
	public function getReadFiles(){
		global $rc;
		return (count($rc,COUNT_RECURSIVE)-count($rc))."<br />".print_r($rc,true);
	}
	public function slugify($text){ 
		$text = preg_replace('~[^\\pL\d]+~u', '-', $text);		
		$text = trim($text, '-');		
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);		
		$text = strtolower($text);		
		$text = preg_replace('~[^-\w]+~', '', $text);		
		if (empty($text))return 'n-a';		
		return $text;
	}
	public function makeMenuFromArray($items,$lr='',$ulId='',$fClass='',$lClass='',$aClass='',$ulClasses='level_',$level=0) {
		$ret = "";
		$indent = str_repeat(" ", $level * 2);
		$ret .= sprintf("%s<ul".($level==0?' id="'.$ulId.'"':'')." class=\"".$ulClasses.($level/2)."\">\n", $indent);
		$indent = str_repeat(" ", ++$level * 2);
		$i=0; foreach ($items as $item => $subitems) { $i++;
			if (!is_numeric($item)) {
				$ret .= sprintf("%s<li class=\"".($i==1?$fClass:"").($i==count($subitems)&&$i==count($items)?$lClass:"")."\">",$indent);
				$nl=explode("|",$item);
				if (isset($nl[1])) {
					$parsed=parse_url($nl[1]);
					if (isset($parsed['host'])&&$_SERVER['HTTP_HOST']<>$parsed['host']) $target='target="_blank"'; else $target='';
					$ret.='<a href="'.(strstr($nl[1],'http://')?$nl[1].'"':$lr.$nl[1].'"').' '.$target.' '.($_SERVER["REQUEST_URI"]==$lr.$nl[1]?' class="'.$aClass.'"':'').'>'.$nl[0].'</a>';
				} else $ret.=$nl[0];
			}
			if (is_array($subitems)) {
				$ret .= "\n";
				$ret .= $this->makeMenuFromArray($subitems,$lr,$ulId,$fClass,$lClass,$aClass,$ulClasses,$level+1);
				$ret .= $indent;
			} else if (strcmp($item, $subitems)){
				$ret .= sprintf("%s<li class=\"".($i==1?$fClass:"").($i==count($items)?$lClass:"")."\">",$indent);
				$nl=explode("|",$subitems);
				if (isset($nl[1])) {
					$parsed=parse_url($nl[1]);
					if (isset($parsed['host'])&&$_SERVER['HTTP_HOST']<>$parsed['host']) $target='target="_blank"'; else $target='';
					$ret.='<a href="'.(strstr($nl[1],'http://')?$nl[1].'"':$lr.$nl[1].'"').' '.$target.' '.($_SERVER["REQUEST_URI"]==$lr.$nl[1]?' class="'.$aClass.'"':'').'>'.$nl[0].'</a>';			
				} else $ret.=$nl[0];			
			}
			$ret .= sprintf("</li>\n", $indent);
		}
		$indent = str_repeat(" ", --$level * 2);
		$ret .= sprintf("%s</ul>\n", $indent);
		return $ret;
	}
	public function cleanurl($url) {
	   $url = preg_replace('~[^\\pL0-9_/.]+~u', '-', $url);
	   $url = trim($url, "-");
	   $url = iconv("utf-8", "us-ascii//TRANSLIT", $url);
	   $url = strtolower($url);
	   $url = preg_replace('~[^-a-z0-9_/.]+~', '', $url);
	   return $url;
	}
	public function writeTrans($lang,$file,$content){
		$toPut=str_replace("__","_".$lang."_",DATA_FOLDER).$file;
		$fpc=@file_put_contents($toPut,$content);
		if ($fpc) return true;
		else {
			$pi=pathinfo($toPut);
			if (!mkdir($pi["dirname"],0777,true)) return false;
			$fpc=@file_put_contents($toPut,$content);
			if ($fpc) return true;
			else return false;
		}
	}
}
function _e($s,$ret=true){
	if (file_exists("a55ets/lang_".LANG.".php")) include "a55ets/lang_".LANG.".php";
	else if ($ret) return $s; else echo $s;
	$r=(isset($t[$s]))?($t[$s]?$t[$s]:$s):$s;
	if ($ret) return $r;
	else echo $r;
}
function _t($s,$plugin="",$felang=""){ if (!$plugin) return $s;
	if ($felang)
		if (file_exists("a55ets/plugins/".$plugin."/lang_".$felang.".php")) include "a55ets/plugins/".$plugin."/lang_".$felang.".php";
		else
			if (file_exists("a55ets/plugins/".$plugin."/lang_".LANG.".php")) include "a55ets/plugins/".$plugin."/lang_".LANG.".php";
			else return $s;
	else
		if (file_exists("a55ets/plugins/".$plugin."/lang_".LANG.".php")) include "a55ets/plugins/".$plugin."/lang_".LANG.".php";
		else return $s;
	$r=(isset($t[$s]))?($t[$s]?$t[$s]:$s):$s;
	return $r;
}
function p($o,$ret=false){ $out='<pre>'.print_r($o,true).'</pre>'; if ($ret) return $out; else echo $out; }
function v($o){ echo '<pre>'.var_dump($o).'</pre>'; }
if (!function_exists('glob_recursive')){function glob_recursive($pattern, $flags = 0){
	$files = glob($pattern, $flags);        
	if (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT)) 
		foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
			$files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
	return $files;
}}
spl_autoload_register(array('TinyCMS','autoload'));
?>