<?php
class sliders extends THelpers {
	function runPlugin(){
		return array(
			'needs'=>array(array('jquery','plugins/sliders/cycle2'),array('jquery','uploadify')),
			'contentSearch'=>$this->search(),
			'contentReplace'=>$this->replace(),
			'adminMenu'=>array(
				_t("Sliders &raquo;","sliders")."|sliders" => array(
						_t("Create Slider","sliders")."|sliders_form",
						_t("Manage Sliders","sliders")."|sliders",
						_t("Clean Unused Images","sliders")."|sliders_clean"
					),
				),
		);
	}
	private function search(){
		$r=array();
		if (isset($this->sliders)&&$this->sliders&&!empty($this->sliders)) foreach ($this->sliders as $k=>$v) $r[]="[[SLIDER-".$k."]]";
		return $r;
	}
	private function replace(){
		$r=array();
		if (isset($this->sliders)&&$this->sliders&&!empty($this->sliders)) { foreach ($this->sliders as $k=>$v) {
			$j=explode('|*|',$v);
			if (isset($j[0])&&isset($j[1])) {
				$s='<div class="cycle-slideshow" id="slider_'.$k.'" data-cycle-slides="> div.slide" data-cycle-auto-height="calc" data-cycle-center-horz="true" data-cycle-center-vert="true" '.$j[0].'>';
				$dej=json_decode($j[1]);
				if ($dej&&!empty($dej)) foreach ($dej as $slide) {
					$s.='<div class="slide">'.($slide->u?'<a href="'.$slide->u.'">':'').'<img src="'.SR.'themes/'.THEME_FOLDER.'/images/slides/'.$slide->i.'" />'.($slide->u?'</a>':'').'</div>';
				}
				$s.='</div>';
				$r[]=$s;
			} else $r[]='';
		} }
		return $r;		
	}
	public function ActionAdmin_sliders(){
		$this->setCrumbs(array(SR=>_t("Home","sliders"),SR.ADMIN.'/dash'=>_t("Admin","sliders"),'#'=>_t("Manage Sliders","sliders")));
		$ret='<table border="1" cellpadding="0" cellspacing="0" width="100%" id="adTable"><thead><tr><th>'._t("Slider Name","sliders").'</th><th>'._t("Slider Shortcode","sliders").'</th><th width="100">'._t("Actions","sliders").'</th></thead><tbody>';		
		if (isset($this->sliders)&&$this->sliders&&!empty($this->sliders)) foreach ($this->sliders as $k=>$slider) {
			$sliderName=$k;
			$ret.='<tr><td>'.ucfirst($sliderName).'</td><td>&#91;&#91;SLIDER-'.$sliderName.'&#93;&#93;</td><th><a href="'.SR.ADMIN.'/sliders_form&who='.$sliderName.'">'._t("Edit","sliders").'</a>&nbsp;|&nbsp;<a href="'.SR.ADMIN.'/sliders_delete&who='.$sliderName.'">'._t("Delete","sliders").'</a></th></tr>';
		} else $ret.='<tr><td colspan="3" align="center">'._t("No sliders were found","sliders").'</td></tr>';
		$ret.='</tbody></table>';
		return $ret;
	}
	public function ActionAdmin_sliders_delete(){
		$this->setCrumbs(array(SR=>_t("Home","sliders"),SR.ADMIN.'/dash'=>_t("Admin","sliders"),SR.ADMIN.'/sliders'=>_t("Manage Sliders","sliders"),"#"=>_t("Slider Delete Confirmation","sliders")));
		if (isset($_GET['who'])&&$_GET['who']) $who=$_GET['who']; else $this->redir("sliders");
		if (!isset($this->sliders[$who])) return '<div class="error">'._t('This slider does not exist',"sliders").'</div>';
		if (isset($_POST['noDelete'])) $this->redir("sliders");		
		if (isset($_POST['yesDelete'])) { 
			if (unlink(__DIR__."/".$who.".madd")) {
				return '<div class="success">'._t('Succesfully deleted:',"sliders").' '.ucfirst($who).'</div>';
			} else return '<div class="error">'._t('Cannot delete:',"sliders").' '.$who.'</div>';
		}
		$ret='<div class="form"><form action="" method="post">
			<div class="row error">'._t("Are you sure you want to delete this slider:","sliders").' '.ucfirst($who).'</div>
			<div class="row"><input type="submit" name="yesDelete" value="'._t('Yes',"sliders").'" /> <input type="submit" name="noDelete" value="'._t('No',"sliders").'" /></div>
			</form>
		</div>';		
		return $ret;
	}
	public function ActionAdmin_sliders_form(){
		$new=(isset($_GET["who"])&&$_GET["who"]?false:true);
		$this->setCrumbs(array(SR=>_t("Home","sliders"),SR.ADMIN.'/dash'=>_t("Admin","sliders"),SR.ADMIN.'/sliders'=>_t("Manage Sliders","sliders"),"#"=>$new?_t("Create Slider","sliders"):_t("Edit Slider","sliders")));		
		$ret='<style>#slidesContainer {} .slide { position:relative; vertical-align:top; display:inline-block; width:100px; height:128px; border:solid 1px #ccc; padding:4px; margin:10px 10px 0px 0; text-align:center; background:#fff;  } .slide img { max-width:100px; max-height:100px; } .slide input[type="text"] {width:96px; margin:4px 0 0 0; padding:0;} .slide .del {display:block;width:16px;height:16px;font-size:16px;line-height:16px;text-align:center;padding:4px;border:solid 1px #ccc; border-radius:3px;position:absolute;right:-8px;top:-8px;background:#ccc;}#uploadWrapper { position:relative; height:34px;} #file_upload  { position:absolute; right:5px; top:0px; z-index:99; } #sliderOptions { width:226px; height:60px; }
</style>';
		if ($new) {
			$who="";$f="[]";$so='data-cycle-speed="1500" data-cycle-timeout="2000" data-cycle-fx="scrollHorz"';
		} else {
			if (isset($_GET['who'])&&$_GET['who']) { $who=$_GET['who']; } else $this->redir("sliders");
			if (isset($this->sliders[$who])) { 
				$j=explode('|*|',$this->sliders[$who]);
				$f=$j[1];
				$so=$j[0];
			} else $this->redir("sliders");
		}
		if (isset($_POST['sent'])&&$_POST['sent']=="yEs") {
			if (!$_POST['sliderName']) $ret.='<div class="error">'._t('Slider name cannot be blank',"sliders").'</div>';
			else {
				$newName=$this->slugify($_POST['sliderName']);
				$oktowrite="";
				if ($newName==$who) {
					$oktowrite=$who;
				} else {
					if ($who) unlink(__DIR__."/".$who.".madd");
					$oktowrite=$newName;
				}
				if ($oktowrite) {
					if (file_put_contents(__DIR__."/".$oktowrite.".madd",$_POST['sliderOptions'].'|*|'.$_POST['sliderContent'])) {
						$_SESSION['infoMessage']=_t("The slider was saved","sliders");
						$this->redir("sliders_form&who=".$oktowrite);
					}
				}
			}
		}
		$timestamp = time();
		$ret.='<div class="form">
			<form action="" method="post"><input type="hidden" name="sent" value="yEs"/><textarea id="sliderContent" name="sliderContent" style="display:none;width:650px;height:120px;">'.(isset($_POST['sliderContent'])?$_POST['sliderContent']:$f).'</textarea>
			<div class="row"><label>'._t("Slider name","sliders").' *:</label> <input type="text" name="sliderName" value="'.(isset($_POST['sliderName'])?$_POST['sliderName']:$who).'" /> <small>'._t("Slider Name can only contain lowercase, alphanumeric characters","sliders").'</small></div>
			<div class="row"><label>'._t("Slider extra options","sliders").':</label> <textarea name="sliderOptions" id="sliderOptions">'.(isset($_POST['sliderOptions'])?$_POST['sliderOptions']:$so).'</textarea> <small>'._t("See http://jquery.malsup.com/cycle2/api/ for more options","sliders").'</small>
			</div>
			<div class="row">
			<div id="uploadWrapper"><div id="queue"></div><input id="file_upload" name="file_upload" type="file" multiple></div>
			</div>
			<div class="row" id="slidesContainer">			
			</div>
			<div class="row"><input type="submit" name="send" value="'.($new?_t("Create Slider","sliders"):_t("Save Slider","sliders")).'" /></div>
			</form>
		</div>';		
		$ret.='<script type="text/javascript">
			$(function() {
				$("#file_upload").uploadify({
					"formData"     : {"timestamp" : "'.$timestamp.'","token"     : "'.md5('uBQzQDBHYgEb+Fw09T(c39'.$timestamp).'"},
					"swf"      : "'.SR.'a55ets/static/up10d1fy/uploadify.swf",
					"uploader" : "'.SR.ADMIN.'/slides_up1oad",
					"buttonText":"'._t("Select image(s)","sliders").'",
					"fileTypeDesc": "Image Files (*.jpg,*.jpeg,*.png,*.gif)",
					"fileTypeExts": "*.jpg;*.jpeg;*.png;*.gif",							
					"onUploadSuccess": function(file, data, response) {
						if (data.indexOf("error:")!=-1) alert(data);
						else {
							var j=$.parseJSON($("#sliderContent").val());
							var newo={"u":"",i:data};
							j.push(newo);
							$("#sliderContent").val(JSON.stringify(j));
							showImages();							
						}
					}
				});
				showImages();
			});
			function showImages(){
				$("#slidesContainer").html("");
				var s="";				
				var j=$.parseJSON($("#sliderContent").val());				
				$.each(j,function(i,e){
					var im=\''.SR.'themes/'.THEME_FOLDER.'/images/slides/\'+e.i;
					s=s+\'<div class="slide" id="slide_\'+i+\'"><a href="#" class="del" data-id="\'+i+\'">X</a><a href="\'+im+\'" target="_blank"><img src="\'+im+\'" /></a><input type="text" data-id="\'+i+\'" value="\'+e.u+\'" id="uslide_\'+i+\'" placeholder="'._t("URL","sliders").'" /></div>\';
				});
				$("#slidesContainer").html(s).fadeIn();
				onChanges();
			}
			function onChanges(){
				$(".del").click(function(e){e.preventDefault();
					var id=$(this).attr("data-id");
					var j=$.parseJSON($("#sliderContent").val());
					j.splice(id,1);
					$("#sliderContent").val(JSON.stringify(j));
					$("#slide_"+id).hide("explode");
					showImages();
					
				});	
				$(".slide input").change(function(){
					var id=$(this).attr("data-id");
					var j=$.parseJSON($("#sliderContent").val());
					j[id].u=$(this).val();
					$("#sliderContent").val(JSON.stringify(j));
					showImages();
				});
			}
		</script>';
		return $ret;
	}
	public function ActionAdmin_sliders_clean(){
		$this->setCrumbs(array(SR=>_t("Home","sliders"),SR.ADMIN.'/dash'=>_t("Admin","sliders"),SR.ADMIN.'/sliders'=>_t("Manage Sliders","sliders"),"#"=>_t("Clean Unused Images","sliders")));
		$ondisk=array();$inpages=array();
		if (glob("themes/".THEME_FOLDER."/images/slides/*")) foreach (glob("themes/".THEME_FOLDER."/images/slides/*") as $f) $ondisk[]=str_replace("themes/".THEME_FOLDER."/images/slides/","",$f);
		if ($this->sliders&&!empty($this->sliders)) foreach ($this->sliders as $k=>$slider) {
			$t=explode('|*|',$slider);
			if (isset($t[1])) {
				$d=json_decode($t[1]);
				if ($d) foreach ($d as $_d)	$inpages[]=$_d->i;
			}			
		}
		$dif=array_diff($ondisk,$inpages);
		//p($ondisk);p($inpages);p($dif);
		if (empty($dif)) return '<div class="info">'._t("Slides folder is clean","sliders").'</div>';
		if (isset($_POST['noDelete'])) $this->redir("sliders");		
		if (isset($_POST['yesDelete'])&&!empty($dif)) { 
			foreach ($dif as $d) {
				unlink("themes/".THEME_FOLDER."/images/slides/".$d);
			}
			$_SESSION['infoMessage']=_t("Slide Images cleaned up succesfully","sliders");
			$this->redir("sliders");			
		}
		$ret='<div class="form"><form action="" method="post">
			<div class="row error">'._t("The following images will be deleted. Are you sure you want to continue?","sliders").'</div>
			<div style="border:solid 1px #ccc; padding:10px; margin:10px 0;background:#fff;">
			<div style="height:220px; overflow:auto;">';
			foreach ($dif as $d) $ret.='<img src="'.SR.'themes/'.THEME_FOLDER.'/images/slides/'.$d.'" width="90" style="float:left; margin:0 4px 4px 0;" />';
		$ret.='</div>
			</div>
			<div class="row"><input type="submit" name="yesDelete" value="'._t('Yes',"sliders").'" /> <input type="submit" name="noDelete" value="'._t('No',"sliders").'" /></div>
			</form>
		</div>';
			
		return $ret;		
	}
	public function ActionAdmin_slides_up1oad(){
		$targetFolder = 'themes/'.THEME_FOLDER.'/images/slides'; // Relative to the root
		if (!is_dir($targetFolder)) @mkdir($targetFolder);
		$verifyToken = md5('uBQzQDBHYgEb+Fw09T(c39'.(isset($_POST['timestamp'])?$_POST['timestamp']:''));
		if (!empty($_FILES) && $_POST['token'] == $verifyToken) {
			if (!is_dir($targetFolder)) @mkdir($targetFolder);			
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
	function __construct(){
		$sliders=glob(__DIR__."/*.madd");
		if ($sliders) foreach ($sliders as $slider) {
			$f=$this->getFile($slider);
			$this->sliders[str_replace(array(__DIR__."/",".madd"),array("",""),$slider)]=$f;
		}
	}
}
?>