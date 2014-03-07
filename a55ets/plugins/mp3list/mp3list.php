<?php
class mp3list extends THelpers {
	function runPlugin(){
		return array(
			'needs'=>array(array(
				'jquery',
				'plugins/mp3list/audiojs',
				'plugins/mp3list/mp3list.js'
			),array('jquery','jqueryui','jquery.ui.nestedSortable.js','uploadify')),
			'contentSearch'=>$this->search(),
			'contentReplace'=>$this->replace(),
			'adminMenu'=>array(
				_t("Mp3 Lists","mp3list")." &raquo;|mp3list" => array(
						_t("Create new mp3 list","mp3list")."|mp3list_form",
						_t("Manage Mp3 Lists","mp3list")."|mp3list",
						_t("Clean Unused Files","mp3list")."|mp3list_clean"
					),
				),
		);
	}
	private function search(){
		$r=array();
		if (isset($this->mp3lists)&&!empty($this->mp3lists)) foreach ($this->mp3lists as $k=>$v) $r[]="[[MP3LIST-".$k."]]";
		return $r;
	}
	private function replace(){
		$ra=array();
		if (isset($this->mp3lists)&&!empty($this->mp3lists)) { foreach ($this->mp3lists as $k=>$v) {			
			$r='<div class="Mp3List" id="Mp3List_'.$k.'"><audio preload="auto"></audio><ol>';
			$j=json_decode($v);
			if ($j) $jj=json_decode($j->mp3listContent);
			if ($jj) foreach ($jj as $mp3) { 
				$url=SR.'themes/'.THEME_FOLDER.'/resources/mp3list/'.$mp3->fn;
				$r.='<li class="ml_item" data-src="'.$url.'"><div class="mli_l"><div class="mli_t">'.$mp3->t.'</div><div class="mli_st">'.$mp3->st.'</div></div><div class="mli_r"><div class="mli_d"><a data-src="'.$url.'" href="'.$url.'" target="_blank">'._t("Download mp3","mp3list",(MULTI?$this->getCurlang():'')).'</a></div></div></li>';				
			}
			$r.='</ol></div>';
			$ra[]=$r;
		} }
		return $ra;
	}
	public function ActionAdmin_mp3list(){global $languages;
		if (MULTI&&isset($_GET['copy'])&&$_GET['copy']) {
			$source=__DIR__."/".$_GET['copy'].".madd";
			foreach ($languages as $k=>$l) if ($k<>0) {
				$dest=__DIR__."/".$_GET['copy'].".".$l.".madd";				
				if (!file_exists($dest)) copy($source,$dest);
			}
			$_SESSION['infoMessage']=_t("The mp3 list was copied to the remaining untranslated languages","mp3list");
			$this->redir("mp3list");
		}
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),'#'=>_t("Manage Mp3 Lists","mp3list")));
		$ret='<table border="1" cellpadding="0" cellspacing="0" width="100%" id="adTable"><thead><tr><th>'._t("Mp3 List name","mp3list").'</th><th>'._t("Mp3 List Shortcode","mp3list").'</th>';
		if (MULTI) $ret.='<th>'._e("Available Languages").'</th>';
		$ret.='<th width="100">'._t("Actions","mp3list").'</th></thead><tbody>';		
		if (isset($this->mp3lists)&&!empty($this->mp3lists)) foreach ($this->mp3lists as $k=>$list) {
			$listName=$k;
			$subs=file_exists(__DIR__."/".$k.'.madds');
			$ret.='<tr><td>'.ucfirst($listName).'</td><td>&#91;&#91;MP3LIST-'.$listName.'&#93;&#93;</td>';
			if (MULTI) {
				$langs=$languages[0].','; $alc=1;
				foreach ($languages as $kk=>$l) if ($kk<>0&&file_exists(__DIR__.'/'.$k.'.'.$l.'.madd')) { $langs.=$l.','; $alc++; }
				$ret.='<td align="center">'.substr($langs,0,-1).(MULTI&&$alc<>count($languages)?' <a href="'.SR.ADMIN.'/mp3list&copy='.$listName.'">'._e("Copy to all languages").'</a>':'').'</td>';
			}
			$ret.='<th><a href="'.SR.ADMIN.'/mp3list_form&who='.$listName.'">'._t("Edit","mp3list").'</a>&nbsp;|&nbsp;<a href="'.SR.ADMIN.'/mp3list_delete&who='.$listName.'">'._t("Delete","mp3list").'</a></th></tr>';
		} else $ret.='<tr><td colspan="3" align="center">'._t("No mp3 lists were found","mp3list").'</td></tr>';
		$ret.='</tbody></table>';
		return $ret;
	}
	public function ActionAdmin_mp3list_form(){ global $languages;
		$new=(isset($_GET["who"])&&$_GET["who"]?false:true);if(MULTI) if (isset($_GET['lang'])&&in_array($_GET['lang'],$languages)) $lang=$_GET['lang']; else $lang=$languages[0];
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),SR.ADMIN.'/mp3list'=>_t("Manage Mp3 Lists","mp3list"),'#'=>$new?_t("Create new mp3 list","mp3list"):_t("Edit mp3 list","mp3list")));
		$ret='<style>#mp3Container {} .item { cursor:move; position:relative; vertical-align:top; display:block; height:30px; line-height:30px; border:solid 1px #ccc; padding:4px; margin:4px 0 0px 0; background:#fff;  } .item input[type="text"] {width:180px; margin:4px 0 0 4px; padding:0; display:inline-block;} .item .del {display:block;width:16px;height:16px;font-size:16px;line-height:16px;text-align:center;padding:4px;border:solid 1px #ccc; border-radius:3px;position:absolute;right:2px;top:2px;background:#ccc;} #uploadWrapper { position:relative; height:34px;} #file_upload  { position:absolute; right:5px; top:0px; z-index:99; } .ph { background:#FF0; width:100%; height:32px; }</style>';
		if ($new) {
			$p=new stdClass;$p->mp3listName='';$p->mp3listContent='[]';
		} else {
			if (isset($_GET['who'])&&$_GET['who']) { $who=$_GET['who']; } else $this->redir("mp3list");
			if (isset($this->mp3lists[$who])) { 
				$j=json_decode($this->mp3lists[$who]);
				$p=$j;
				if(MULTI&&$lang<>$languages[0]) {
					$fl=$this->getFile(__DIR__."/".$who.".".$lang.".madd");
					$jl=json_decode($fl);
					if ($jl) $p=$jl;
				}
			} else $this->redir("mp3list");
		}
		if (isset($_POST['sent'])&&$_POST['sent']=="yEs") {
			if (!$_POST['mp3listName']) $ret.='<div class="error">'._t('List name cannot be blank',"mp3list").'</div>';
			else {
				if (MULTI&&$lang<>$languages[0]) {
					if (file_put_contents(__DIR__."/".$who.".".$lang.".madd",json_encode($_POST))) {
						$_SESSION['infoMessage']=_t("The list was saved","mp3list");
						$this->redir("mp3list_form&who=".$who."&lang=".$lang);
					}
				} else {				
					$newName=$this->slugify($_POST['mp3listName']);
					$oktowrite="";
					if ($newName==$who) {
						$oktowrite=$who;
					} else {
						if ($who) unlink(__DIR__."/".$who.".madd");
						$oktowrite=$newName;
					}
					if ($oktowrite) {
						
						if (file_put_contents(__DIR__."/".$oktowrite.".madd",json_encode($_POST))) {
							$_SESSION['infoMessage']=_t("The list was saved","mp3list");
							$this->redir("mp3list_form&who=".$oktowrite);
						}
					}
				}//end else if (MULTI&&$lang<>$languages[0]) {
			}
		}
		$timestamp = time();
		$ret.='<div class="form">
			<form action="" method="post"><input type="hidden" name="sent" value="yEs"/><textarea id="mp3list" name="mp3listContent" style="display:none;width:650px;height:120px;">'.$p->mp3listContent.'</textarea>';
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
			<div class="row"><label>'._t("Mp3 List name","mp3list").' *:</label> <input type="text" name="mp3listName" value="'.$p->mp3listName.'" '.(MULTI&&$lang<>$languages[0]?' readonly="readonly"':'').'/> <small>'._t("Mp3 List can only contain lowercase, alphanumeric characters","mp3list").'</small></div>
			<div class="row">
			<div id="uploadWrapper"><div id="queue"></div><input id="file_upload" name="file_upload" type="file" multiple></div>
			</div>
			<div class="row" id="mp3Container">			
			</div>
			<div class="row"><input type="submit" name="send" value="'._t("Save List","mp3list").'" /></div>
			</form>
		</div>';		
		$ret.='<script type="text/javascript">
			$(function() {
				$("#file_upload").uploadify({
					"formData"     : {"timestamp" : "'.$timestamp.'","token"     : "'.md5('uBQaQDEHYgEb+Fw09Tcc39'.$timestamp).'"},
					"swf"      : "'.SR.'a55ets/static/up10d1fy/uploadify.swf",
					"uploader" : "'.SR.ADMIN.'/mp3list_up1oad",
					"buttonText":"'._t("Select file(s)","mp3list").'",
					"fileTypeDesc": "Mp3 Files (*.mp3)",
					"fileTypeExts": "*.mp3",							
					"onUploadSuccess": function(file, data, response) {
						if (data.indexOf("error:")!=-1) alert(data);
						else {
							var j=$.parseJSON($("#mp3list").val());
							var newo={"fn":data,"t":"","st":""};
							j.unshift(newo);
							$("#mp3list").val(JSON.stringify(j));
							showFiles();							
						}
					}
				});
				showFiles("no");
			});
			function showFiles(fade){
				if (fade==undefined) $("#mp3Container").fadeOut().html("");
				else $("#mp3Container").html("");
				var s="<ol class=\"sortable\" id=\"mp3OLlist\">";
				var j=$.parseJSON($("#mp3list").val());				
				$.each(j,function(i,e){
					s=s+\'<li class="item" id="item_\'+i+\'"><div><a href="#" class="del" data-id="\'+i+\'">X</a><input type="text" class="input_t" data-id="\'+i+\'" value="\'+e.t+\'" id="tslide_\'+i+\'" placeholder="'._t("Title","mp3list").'" /><input type="text" class="input_st" data-id="\'+i+\'" value="\'+e.st+\'" id="stslide_\'+i+\'" placeholder="'._t("Subtitle","mp3list").'" /> <strong>\'+e.fn+\'</strong></div></li>\';
				});
				s=s+"</ol>";
				$("#mp3Container").html(s).fadeIn();
				onChanges();
			}
			function onChanges(){
				$(".del").click(function(e){e.preventDefault();
					var id=$(this).attr("data-id");
					var j=$.parseJSON($("#mp3list").val());
					j.splice(id,1);
					$("#mp3list").val(JSON.stringify(j));
					$("#item_"+id).hide("explode");
					showFiles("no");
					
				});	
				$(".item input").change(function(){
					var id=$(this).attr("data-id");
					var j=$.parseJSON($("#mp3list").val());
					if ($(this).hasClass("input_st")) j[id].st=$(this).val();
					else j[id].t=$(this).val();
					$("#mp3list").val(JSON.stringify(j));
					showFiles();
				});
				$("ol.sortable").nestedSortable({
					disableNesting: "no-nest",
					forcePlaceholderSize: true,
					handle: "div",
					items: "li",
					opacity: .6,
					placeholder: "ph",
					tabSize: 25,
					tolerance: "pointer",
					toleranceElement: "> div",
					maxLevels: 1,
					update: saveOrder
				});
			}
			var saveOrder = function(){
				var arraied = $("#mp3OLlist").nestedSortable("toHierarchy", {startDepthCount: 0});
				var j=$.parseJSON($("#mp3list").val());
				var obj=new Array();
				for (var i=0;i<arraied.length;i++) obj[i]=j[arraied[i].id];
				$("#mp3list").val(JSON.stringify(obj));
				showFiles("no");
			};
		</script>';
		return $ret;
	}
	public function ActionAdmin_mp3list_delete(){global $languages;
		if (isset($_GET['who'])&&$_GET['who']) $who=$_GET['who']; else $this->redir("mp3list");
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),SR.ADMIN.'/mp3list'=>_t("Manage Mp3 Lists","mp3list"),'#'=>_t("Are you sure you want to delete this mp3list","mp3list")));
		if (!isset($this->mp3lists[$who])) return '<div class="error">'._t('This mp3 list does not exist',"mp3list").'</div>';
		if (isset($_POST['noDelete'])) $this->redir("mp3list");		
		if (isset($_POST['yesDelete'])) { 
			if (unlink(__DIR__."/".$who.".madd")) {
				if (MULTI) foreach($languages as $l) if ($l<>$languages[0]) @unlink(__DIR__."/".$who.".".$l.".madd");				
				return '<div class="success">'._t('Succesfully deleted:',"mp3list").' '.ucfirst($who).'</div>';
			} else return '<div class="error">'._t('Cannot delete:',"mp3list").' '.$who.'</div>';
		}
		$ret='<div class="form"><form action="" method="post">
			<div class="row error">'._t("Are you sure you want to delete this mp3list","mp3list").' '.ucfirst($who).'</div>
			<div class="row"><input type="submit" name="yesDelete" value="'._t('Yes',"mp3list").'" /> <input type="submit" name="noDelete" value="'._t('No',"mp3list").'" /></div>
			</form>
		</div>';		
		return $ret;
	}
	public function ActionAdmin_mp3list_clean(){
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),SR.ADMIN.'/mp3list'=>_t("Manage Mp3 Lists","mp3list"),'#'=>_t("Clean Unused Files","mp3list")));
		$ondisk=array();$inpages=array();
		if (glob("themes/".THEME_FOLDER."/resources/mp3list/*")) foreach (glob("themes/".THEME_FOLDER."/resources/mp3list/*") as $f) $ondisk[]=str_replace("themes/".THEME_FOLDER."/resources/mp3list/","",$f);
		$glob=glob(__DIR__."/*.madd");
		if ($glob) foreach ($glob as $fn) {
			$f=$this->getFile($fn);
			$j=json_decode($f);
			if ($j) $jj=json_decode($j->mp3listContent);
			if ($jj) foreach ($jj as $k=>$_item)
				$inpages[]=$_item->fn;
		}
		$dif=array_diff($ondisk,$inpages);
		if (empty($dif)) return '<div class="info">'._t("Mp3 folder is clean","mp3list").'</div>';
		if (isset($_POST['noDelete'])) $this->redir("mp3list");		
		if (isset($_POST['yesDelete'])&&!empty($dif)) { 
			foreach ($dif as $d) {
				unlink("themes/".THEME_FOLDER."/resources/mp3list/".$d);
			}
			$_SESSION['infoMessage']=_t("Mp3 Files cleaned up succesfully","mp3list");
			$this->redir("mp3list");			
		}
		$ret='<div class="form"><form action="" method="post">
			<div class="row error">'._t("The following files will be deleted. Are you sure you want to continue?","mp3list").'</div>
			<div style="border:solid 1px #ccc; padding:10px; margin:10px 0;background:#fff;">
			<div style="height:220px; overflow:auto;">';
			foreach ($dif as $d) $ret.='themes/'.THEME_FOLDER.'/resources/mp3list/'.$d.'<br>';
		$ret.='</div>
			</div>
			<div class="row"><input type="submit" name="yesDelete" value="'._t('Yes',"mp3list").'" /> <input type="submit" name="noDelete" value="'._t('No',"mp3list").'" /></div>
			</form>
		</div>';
			
		return $ret;		
	}
	public function ActionAdmin_mp3list_up1oad(){
		$targetFolder = 'themes/'.THEME_FOLDER.'/resources/mp3list'; // Relative to the root
		if (!is_dir($targetFolder)) @mkdir($targetFolder,0777,true);
		$verifyToken = md5('uBQaQDEHYgEb+Fw09Tcc39'.(isset($_POST['timestamp'])?$_POST['timestamp']:''));
		if (!empty($_FILES) && $_POST['token'] == $verifyToken) {
			if (!is_dir($targetFolder)) @mkdir($targetFolder);			
			$tempFile = $_FILES['Filedata']['tmp_name'];
			$targetPath =$targetFolder;
			$fileTypes = array('mp3'); // File extensions
			$fileParts = pathinfo($_FILES['Filedata']['name']);			
			$fileName=microtime(true).'-'.$this->slugify($fileParts['filename']).'.'.$fileParts['extension'];
			$targetFile = rtrim($targetPath,'/') . '/' . $fileName;
			if (in_array($fileParts['extension'],$fileTypes)) {
				if ($_FILES['Filedata']['error']==0) 
					if (move_uploaded_file($tempFile,$targetFile))
						echo $fileName;
					else echo 'error: cannot move file';	
				else echo 'error: '.$_FILES['Filedata']['error'];						
			} else {
				echo 'error: '._e('Invalid file type.');
			}
		}
		exit;
	}
	function __construct(){global $languages;
		if ((MULTI&&$this->getCurlang()&&$this->getCurlang()<>$languages[0])) {
			$mp3lists=glob(__DIR__."/*.".$this->getCurlang().".madd");
			if ($mp3lists) foreach ($mp3lists as $mp3list) {
				$rep=str_replace(array(__DIR__."/",".madd",".".$this->getCurlang()),array("","",""),$mp3list);
				$f=$this->getFile($mp3list);
				if ($f) $this->mp3lists[$rep]=$f;
			}
		} else {
			$mp3lists=glob(__DIR__."/*.madd");
			if ($mp3lists) foreach ($mp3lists as $mp3list) {
				$rep=str_replace(array(__DIR__."/",".madd"),array("",""),$mp3list);
				if (!strstr($rep,".")) {
					$f=$this->getFile($mp3list);
					$this->mp3lists[$rep]=$f;
				}
			}
		}
	}
}
?>