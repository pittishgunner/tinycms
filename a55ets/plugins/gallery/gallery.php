<?php
class gallery extends THelpers {
	function runPlugin(){
		return array(
			'needs'=>array(array(
				'jquery',
				'plugins/gallery/fancybox',
				'plugins/gallery/gallery.js'
			),array('jquery','jqueryui','jquery.ui.nestedSortable.js','uploadify')),
			'contentSearch'=>$this->search(),
			'contentReplace'=>$this->replace(),
			'adminMenu'=>array(
				_t("Gallery","gallery")." &raquo;|gallery" => array(
						_t("Create new gallery","gallery")."|gallery_form",
						_t("Manage galleries","gallery")."|gallery",
						_t("Clean Unused Files","gallery")."|gallery_clean"
					),
				),
		);
	}
	private function search(){
		$r=array();
		if (isset($this->galleries)&&!empty($this->galleries)) foreach ($this->galleries as $k=>$v) $r[]="[[GALLERY-".$k."]]";
		return $r;
	}
	private function replace(){
		$ra=array();
		if (isset($this->galleries)&&!empty($this->galleries)) { foreach ($this->galleries as $k=>$v) {			
			$r='<div class="galleryPlugin" id="galleryPlugin_'.$k.'">';
			$j=json_decode($v);
			if ($j) $jj=json_decode($j->galleryContent);
			if ($jj) foreach ($jj as $image) { 
				$full=$thumb=SR.'themes/'.THEME_FOLDER.'/resources/gallery/'.$image->fn;
				if ($j->thumbs=="yes") {
					if (!is_dir('themes/'.THEME_FOLDER.'/resources/gallery/thumbs')) @mkdir('themes/'.THEME_FOLDER.'/resources/gallery/thumbs');
					if (!file_exists('themes/'.THEME_FOLDER.'/resources/gallery/thumbs/'.$image->fn)) {
						if (file_exists('themes/'.THEME_FOLDER.'/resources/gallery/'.$image->fn)) {
							require_once __DIR__."/Kohana/Image.php";
							require_once __DIR__."/Kohana/Image/GD.php";
							$im=Image::factory('themes/'.THEME_FOLDER.'/resources/gallery/'.$image->fn);
							$im->resize(intval($j->tmaxw), intval($j->tmaxh), "0x04");
							$im->save('themes/'.THEME_FOLDER.'/resources/gallery/thumbs/'.$image->fn,75);	
						}
					}
					$thumb=SR.'themes/'.THEME_FOLDER.'/resources/gallery/thumbs/'.$image->fn;
				}
				$r.='<div class="gal_item"><a class="gal_link" title="'.$image->desc.'" href="'.$full.'" target="_blank" rel="gallery"><img src="'.$thumb.'" alt="'.$image->desc.'" title="'.$image->desc.'" /></a><span class="gal_descrip">'.$image->desc.'</span></div>';				
			}
			$r.='</div>';
			$ra[]=$r;
		} }
		return $ra;
	}
	public function ActionAdmin_gallery(){global $languages;
		if (MULTI&&isset($_GET['copy'])&&$_GET['copy']) {
			$source=__DIR__."/".$_GET['copy'].".madd";
			foreach ($languages as $k=>$l) if ($k<>0) {
				$dest=__DIR__."/".$_GET['copy'].".".$l.".madd";				
				if (!file_exists($dest)) copy($source,$dest);
			}
			$_SESSION['infoMessage']=_t("The gallery was copied to the remaining untranslated languages","gallery");
			$this->redir("gallery");
		}
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),'#'=>_t("Manage galleries","gallery")));
		$ret='<table border="1" cellpadding="0" cellspacing="0" width="100%" id="adTable"><thead><tr><th>'._t("Gallery name","gallery").'</th><th>'._t("Gallery shortcode","gallery").'</th>';
		if (MULTI) $ret.='<th>'._e("Available Languages").'</th>';
		$ret.='<th width="100">'._e("Actions").'</th></thead><tbody>';		
		if (isset($this->galleries)&&!empty($this->galleries)) foreach ($this->galleries as $k=>$list) {
			$listName=$k;
			$subs=file_exists(__DIR__."/".$k.'.madds');
			$ret.='<tr><td>'.ucfirst($listName).'</td><td>&#91;&#91;GALLERY-'.$listName.'&#93;&#93;</td>';
			if (MULTI) {
				$langs=$languages[0].','; $alc=1;
				foreach ($languages as $kk=>$l) if ($kk<>0&&file_exists(__DIR__.'/'.$k.'.'.$l.'.madd')) { $langs.=$l.','; $alc++; }
				$ret.='<td align="center">'.substr($langs,0,-1).(MULTI&&$alc<>count($languages)?' <a href="'.SR.ADMIN.'/gallery&copy='.$listName.'">'._e("Copy to all languages").'</a>':'').'</td>';
			}
			$ret.='<th><a href="'.SR.ADMIN.'/gallery_form&who='.$listName.'">'._e("Edit").'</a>&nbsp;|&nbsp;<a href="'.SR.ADMIN.'/gallery_delete&who='.$listName.'">'._e("Delete").'</a></th></tr>';
		} else $ret.='<tr><td colspan="3" align="center">'._t("No galleries were found","gallery").'</td></tr>';
		$ret.='</tbody></table>';
		return $ret;
	}
	public function ActionAdmin_gallery_form(){ global $languages;
		$new=(isset($_GET["who"])&&$_GET["who"]?false:true);if(MULTI) if (isset($_GET['lang'])&&in_array($_GET['lang'],$languages)) $lang=$_GET['lang']; else $lang=$languages[0];
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),SR.ADMIN.'/gallery'=>_t("Manage galleries","gallery"),'#'=>$new?_t("Create new gallery","gallery"):_t("Edit gallery","gallery")));
		$ret='<style>#galleryContainer {} #maincontent li.item, #maincontent li.ph { cursor:move; position:relative; vertical-align:top; display:inline-block; width:120px; height:150px;border:solid 1px #ccc; padding:4px; margin:4px 4px 0px 0; background:#fff; text-align:center; } .item input[type="text"] {width:100px; margin:4px 0 0 0px; padding:0; display:inline-block;} .item .del {display:block;width:16px;height:16px;font-size:16px;line-height:16px;text-align:center;padding:4px;border:solid 1px #ccc; border-radius:3px;position:absolute;right:-10px;top:-10px;background:#ccc; z-index:99} #uploadWrapper { position:relative; height:34px;} #file_upload  { position:absolute; right:5px; top:0px; z-index:99; } #maincontent li.ph { background:#FF0; } li img { max-height:120px; max-width:120px; }</style>';
		if ($new) {
			$p=new stdClass;$p->galleryName='';$p->galleryContent='[]';$p->resize='no';$p->maxw='1024';$p->maxh='768';$p->thumbs='no';$p->tmaxw='200';$p->tmaxh='200';
		} else {
			if (isset($_GET['who'])&&$_GET['who']) { $who=$_GET['who']; } else $this->redir("gallery");
			if (isset($this->galleries[$who])) { 
				$j=json_decode($this->galleries[$who]);
				$p=$j;
				if(MULTI&&$lang<>$languages[0]) {
					$fl=$this->getFile(__DIR__."/".$who.".".$lang.".madd");
					$jl=json_decode($fl);
					if ($jl) $p=$jl;
				}
			} else $this->redir("gallery");
		}
		if (isset($_POST['sent'])&&$_POST['sent']=="yEs") {
			if (!$_POST['galleryName']) $ret.='<div class="error">'._t('Gallery name cannot be blank',"gallery").'</div>';
			else {
				if (MULTI&&$lang<>$languages[0]) {
					if (file_put_contents(__DIR__."/".$who.".".$lang.".madd",json_encode($_POST))) {
						$_SESSION['infoMessage']=_t("The gallery was saved","gallery");
						$this->redir("gallery_form&who=".$who."&lang=".$lang);
					}
				} else {				
					$newName=$this->slugify($_POST['galleryName']);
					$oktowrite="";
					if ($newName==$who) {
						$oktowrite=$who;
					} else {
						if ($who) unlink(__DIR__."/".$who.".madd");
						$oktowrite=$newName;
					}
					if ($oktowrite) {
						
						if (file_put_contents(__DIR__."/".$oktowrite.".madd",json_encode($_POST))) {
							$_SESSION['infoMessage']=_t("The gallery was saved","gallery");
							$this->redir("gallery_form&who=".$oktowrite);
						}
					}
				}//end else if (MULTI&&$lang<>$languages[0]) {
			}
		}
		$timestamp = time();
		$ret.='<div class="form">
			<form action="" method="post"><input type="hidden" name="sent" value="yEs"/><textarea id="gallery" name="galleryContent" style="display:none;width:650px;height:120px;">'.$p->galleryContent.'</textarea>';
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
			<div class="row"><label>'._t("Gallery name","gallery").' *:</label> <input type="text" name="galleryName" value="'.$p->galleryName.'" '.(MULTI&&$lang<>$languages[0]?' readonly="readonly"':'').'/> <small>'._t("Gallery names can only contain lowercase, alphanumeric characters","gallery").'</small></div>
			<div class="row"><label class="bigLabel">'._t("Automatically resize images ?","gallery").'</label> <input type="radio" name="resize" id="resize_no" value="no" '.($p->resize=="no"?' checked="checked"':'').'> <label class="tinyLabel" for="resize_no">'._e("No").'</label> <input type="radio" name="resize" id="resize_yes" value="yes" '.($p->resize=="yes"?' checked="checked"':'').'><label class="tinyLabel" for="resize_yes">'._e("Yes").'</label>  </div>
			<div class="row"'.($p->resize=="no"?' style="display:none;"':'').' id="resizeRow"><label>'._t("Maximum dimensions in pixels","gallery").'</label> <input type="text" name="maxw" id="maxw" value="'.$p->maxw.'" style="width:40px;"/> <label for="maxw" class="mediumLabel">'._t("Maximum width","gallery").'</label> <input type="text" name="maxh" id="maxh" value="'.$p->maxh.'" style="width:40px;"/> <label for="maxh" class="mediumLabel">'._t("Maximum height","gallery").'</label>  </div>			
			<div class="row"><label class="bigLabel">'._t("Use thumbnails?","gallery").'</label> <input type="radio" name="thumbs" id="thumbs_no" value="no" '.($p->thumbs=="no"?' checked="checked"':'').'> <label class="tinyLabel" for="thumbs_no">'._e("No").'</label> <input type="radio" name="thumbs" id="thumbs_yes" value="yes" '.($p->thumbs=="yes"?' checked="checked"':'').'><label class="tinyLabel" for="thumbs_yes">'._e("Yes").'</label>  </div>			
			<div class="row"'.($p->thumbs=="no"?' style="display:none;"':'').' id="thumbsRow"><label>'._t("Maximum dimensions in pixels","gallery").'</label> <input type="text" name="tmaxw" id="tmaxw" value="'.$p->tmaxw.'" style="width:40px;"/> <label for="tmaxw" class="mediumLabel">'._t("Maximum width","gallery").'</label> <input type="text" name="tmaxh" id="tmaxh" value="'.$p->tmaxh.'" style="width:40px;"/> <label for="tmaxh" class="mediumLabel">'._t("Maximum height","gallery").'</label>  </div>
			
			<div class="row">
			<div id="uploadWrapper"><div id="queue"></div><input id="file_upload" name="file_upload" type="file" multiple></div>
			</div>
			<div class="row" id="galleryContainer">			
			</div>
			<div class="row"><input type="submit" name="send" value="'._t("Save gallery","gallery").'" /></div>
			</form>
		</div>';		
		$ret.='<script type="text/javascript">
			$(function() {
				var resize=$("input[type=radio][name=resize]:checked").val(); var maxw=$("#maxw").val(); var maxh=$("#maxh").val();
				$("input[type=radio][name=resize]").change(function(){if($(this).val()=="no") $("#resizeRow").slideUp(); else $("#resizeRow").slideDown(); resize=$("input[type=radio][name=resize]:checked").val(); });
				$("input[type=radio][name=thumbs]").change(function(){if($(this).val()=="no") $("#thumbsRow").slideUp(); else $("#thumbsRow").slideDown(); });
				$("#maxw, #maxh").change(function(){maxw=$("#maxw").val();maxh=$("#maxh").val();});
				$("#file_upload").uploadify({
					"formData"     : {"timestamp" : "'.$timestamp.'","token"     : "'.md5('uBQaQDEHYgEb+Fw09Tcc39'.$timestamp).'","resize":resize,"maxh":maxh,"maxw":maxw},
					"swf"      : "'.SR.'a55ets/static/up10d1fy/uploadify.swf",
					"uploader" : "'.SR.ADMIN.'/gallery_up1oad",
					"buttonText":"'._e("Select image(s)").'",
					"fileTypeDesc": "Image Files (*.jpg,*.jpeg,*.png,*.gif)",
					"fileTypeExts": "*.jpg;*.jpeg;*.png;*.gif",
					"onUploadStart": function(file) {
		                $("#file_upload").uploadify("settings", "formData", {"timestamp" : "'.$timestamp.'","token"     : "'.md5('uBQaQDEHYgEb+Fw09Tcc39'.$timestamp).'","resize":resize,"maxh":maxh,"maxw":maxw});
        			},														
					"onUploadSuccess": function(file, data, response) {
						if (data.indexOf("error:")!=-1) alert(data);
						else {
							var j=$.parseJSON($("#gallery").val());
							var newo={"fn":data,"desc":""};
							j.unshift(newo);
							$("#gallery").val(JSON.stringify(j));
							showFiles();							
						}
					}
				});					
				showFiles("no");
			});
			function showFiles(fade){
				if (fade==undefined) $("#galleryContainer").fadeOut().html("");
				else $("#galleryContainer").html("");
				var s="<ol class=\"sortable\" id=\"galleryOLlist\">";
				var j=$.parseJSON($("#gallery").val());				
				$.each(j,function(i,e){
					s=s+\'<li class="item" id="item_\'+i+\'"><div><a href="#" class="del" data-id="\'+i+\'">X</a><img src="'.SR.'themes/'.THEME_FOLDER.'/resources/gallery/\'+e.fn+\'"/><input type="text" class="input_t" data-id="\'+i+\'" value="\'+e.desc+\'" id="tslide_\'+i+\'" placeholder="'._t("Description","gallery").'" /></div></li>\';
				});
				s=s+"</ol>";
				$("#galleryContainer").html(s).fadeIn();
				onChanges();
			}
			function onChanges(){
				$(".del").click(function(e){e.preventDefault();
					var id=$(this).attr("data-id");
					var j=$.parseJSON($("#gallery").val());
					j.splice(id,1);
					$("#gallery").val(JSON.stringify(j));
					$("#item_"+id).hide("explode");
					showFiles("no");
					
				});	
				$(".item input").change(function(){
					var id=$(this).attr("data-id");
					var j=$.parseJSON($("#gallery").val());
					j[id].desc=$(this).val();
					$("#gallery").val(JSON.stringify(j));
					showFiles();
				});
				$("ol.sortable").nestedSortable({
					disableNesting: "no-nest",
					forcePlaceholderSize: true,
					handle: "div",
					items: "li",
					opacity: .6,
					placeholder: "ph",
					tabSize: 1125,
					tolerance: "pointer",
					toleranceElement: "> div",
					maxLevels: 1,
					update: saveOrder
				});
			}
			var saveOrder = function(){
				var arraied = $("#galleryOLlist").nestedSortable("toHierarchy", {startDepthCount: 0});
				var j=$.parseJSON($("#gallery").val());
				var obj=new Array();
				for (var i=0;i<arraied.length;i++) obj[i]=j[arraied[i].id];
				$("#gallery").val(JSON.stringify(obj));
				showFiles("no");
			};
		</script>';
		return $ret;
	}
	public function ActionAdmin_gallery_delete(){global $languages;
		if (isset($_GET['who'])&&$_GET['who']) $who=$_GET['who']; else $this->redir("gallery");
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),SR.ADMIN.'/gallery'=>_t("Manage galleries","gallery"),'#'=>_t("Are you sure you want to delete this gallery","gallery")));
		if (!isset($this->galleries[$who])) return '<div class="error">'._t('This gallery does not exist',"gallery").'</div>';
		if (isset($_POST['noDelete'])) $this->redir("gallery");		
		if (isset($_POST['yesDelete'])) { 
			if (unlink(__DIR__."/".$who.".madd")) {
				if (MULTI) foreach($languages as $l) if ($l<>$languages[0]) @unlink(__DIR__."/".$who.".".$l.".madd");				
				return '<div class="success">'._e('Succesfully deleted:').' '.ucfirst($who).'</div>';
			} else return '<div class="error">'._e('Cannot delete:').' '.$who.'</div>';
		}
		$ret='<div class="form"><form action="" method="post">
			<div class="row error">'._t("Are you sure you want to delete this gallery","gallery").' '.ucfirst($who).'</div>
			<div class="row"><input type="submit" name="yesDelete" value="'._e('Yes').'" /> <input type="submit" name="noDelete" value="'._e('No').'" /></div>
			</form>
		</div>';		
		return $ret;
	}
	public function ActionAdmin_gallery_clean(){
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),SR.ADMIN.'/gallery'=>_t("Manage galleries","gallery"),'#'=>_t("Clean Unused Files","gallery")));
		$ondisk=array();$inpages=array();
		if (glob("themes/".THEME_FOLDER."/resources/gallery/*")) foreach (glob("themes/".THEME_FOLDER."/resources/gallery/*") as $f) if (!is_dir($f)) $ondisk[]=str_replace("themes/".THEME_FOLDER."/resources/gallery/","",$f);
		$glob=glob(__DIR__."/*.madd");
		if ($glob) foreach ($glob as $fn) {
			$f=$this->getFile($fn);
			$j=json_decode($f);
			if ($j) $jj=json_decode($j->galleryContent);
			if ($jj) foreach ($jj as $k=>$_item)
				$inpages[]=$_item->fn;
		}
		$dif=array_diff($ondisk,$inpages);
		if (empty($dif)) return '<div class="info">'._t("Gallery folder is clean","gallery").'</div>';
		if (isset($_POST['noDelete'])) $this->redir("gallery");		
		if (isset($_POST['yesDelete'])&&!empty($dif)) { 
			foreach ($dif as $d) {
				@unlink("themes/".THEME_FOLDER."/resources/gallery/".$d);
				@unlink("themes/".THEME_FOLDER."/resources/gallery/thumbs/".$d);
			}
			$_SESSION['infoMessage']=_t("Gallery Files cleaned up succesfully","gallery");
			$this->redir("gallery");			
		}
		$ret='<div class="form"><form action="" method="post">
			<div class="row error">'._t("The following files will be deleted. Are you sure you want to continue?","gallery").'</div>
			<div style="border:solid 1px #ccc; padding:10px; margin:10px 0;background:#fff;">
			<div style="height:220px; overflow:auto;">';
			foreach ($dif as $d) $ret.='<img src="'.SR.'themes/'.THEME_FOLDER.'/resources/gallery/'.$d.'" width="100" />';
		$ret.='</div>
			</div>
			<div class="row"><input type="submit" name="yesDelete" value="'._e('Yes').'" /> <input type="submit" name="noDelete" value="'._e('No').'" /></div>
			</form>
		</div>';
			
		return $ret;		
	}
	public function ActionAdmin_gallery_up1oad(){
		$targetFolder = 'themes/'.THEME_FOLDER.'/resources/gallery'; // Relative to the root
		if (!is_dir($targetFolder)) @mkdir($targetFolder,0777,true);
		$verifyToken = md5('uBQaQDEHYgEb+Fw09Tcc39'.(isset($_POST['timestamp'])?$_POST['timestamp']:''));
		if (!empty($_FILES) && $_POST['token'] == $verifyToken) {
			if (!is_dir($targetFolder)) @mkdir($targetFolder);			
			$tempFile = $_FILES['Filedata']['tmp_name'];
			$targetPath =$targetFolder;
			$fileTypes = array('jpg','jpeg','gif','png'); // File extensions
			$fileParts = pathinfo($_FILES['Filedata']['name']);			
			$fileName=microtime(true).'-'.$this->slugify($fileParts['filename']).'.'.$fileParts['extension'];
			$targetFile = rtrim($targetPath,'/') . '/' . $fileName;
			if (in_array($fileParts['extension'],$fileTypes)) {
				if (move_uploaded_file($tempFile,$targetFile)) {
					if (isset($_POST['resize'])&&$_POST['resize']=="yes") {
						require_once __DIR__."/Kohana/Image.php";
						require_once __DIR__."/Kohana/Image/GD.php";
						$im=Image::factory($targetFile);
						$im->resize(intval(@$_POST['maxw']), intval(@$_POST['maxh']), "0x04");
						$im->save(NULL,75);
					}
					echo $fileName;
				}
			} else {
				echo 'error: '._e('Invalid file type.');
			}
		}
		exit;
	}
	function __construct(){global $languages;
		if ((MULTI&&$this->getCurlang()&&$this->getCurlang()<>$languages[0])) {
			$galleries=glob(__DIR__."/*.".$this->getCurlang().".madd");
			if ($galleries) foreach ($galleries as $gallery) {
				$rep=str_replace(array(__DIR__."/",".madd",".".$this->getCurlang()),array("","",""),$gallery);
				$f=$this->getFile($gallery);
				if ($f) $this->galleries[$rep]=$f;
			}
		} else {
			$galleries=glob(__DIR__."/*.madd");
			if ($galleries) foreach ($galleries as $gallery) {
				$rep=str_replace(array(__DIR__."/",".madd"),array("",""),$gallery);
				if (!strstr($rep,".")) {
					$f=$this->getFile($gallery);
					$this->galleries[$rep]=$f;
				}
			}
		}
	}
}
?>