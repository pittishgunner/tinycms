<?php
class upcomingevents extends THelpers {
	function runPlugin(){
		return array(
			'needs'=>array(array(),array('jquery','jqueryui',(LANG<>"en"?'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/i18n/jquery-ui-i18n.min.js':''),'jquery-ui-timepicker-addon.js')),
			'contentSearch'=>$this->search(),
			'contentReplace'=>$this->replace(),
			'adminMenu'=>array(
				_t("Upcoming Events","upcomingevents")."|upcomingevents"),
		);
	}
	private function search(){
		$r=array();
		//if (isset($this->upcomingevents)&&!empty($this->upcomingevents)) {
			$r[]="[[UPCOMINGEVENTS]]";
			$r[]="[[UPCOMINGEVENTS-5]]";
		//}		
		return $r;
	}
	private function replace(){global $languages;
		$r=array();$r1=$r2='';
		if ((MULTI&&$this->getCurlang())) {
			setlocale(LC_TIME, $this->getCurlang()."_".strtoupper($this->getCurlang().'.UTF8'));
		}			
		$r1.='<div id="upcomingPlugin"><table><tbody>';
		$r2.='<div id="upcomingPluginNext"><ul>';			
		if (isset($this->upcomingevents)&&!empty($this->upcomingevents)) { $i=0;
			$d = get_object_vars($this->upcomingevents);
			$d=array_reverse($d);
			foreach ($d as $k=>$v) {$i++;
				$t=explode(" ",$k);
				$date=$t[0];$stime=$t[1];$ftime=$v[0];
				$stt=strtotime($date);
				$r1.='<tr><td class="upt1">'.strftime("%x",$stt).'</td><td class="upt2">'.ucfirst(strftime("%A",$stt)).'</td><td class="upt3">'.$stime.'-'.$ftime.'</td><td class="upt4">'.$v[1].'<br><strong>'.$v[2].'</strong></td></tr>';
				if ($i<6) $r2.='<li>'.ucfirst(strftime("%A",$stt)).' '.strftime("%x",$stt).' '.$stime.' - <strong>'.$v[1].'</strong></li>';
			}
		}
		$r1.='</tbody></table></div>';
		$r2.='</ul></div>';			
		$r[]=$r1;
		$r[]=$r2;
		return $r;		
	}
	public function ActionAdmin_upcomingevents(){ global $languages;
		$this->setCrumbs(array(SR=>_e("Home"),SR.ADMIN.'/dash'=>_e("Admin"),'#'=>_t("Manage Upcoming Events","upcomingevents")));
		if(MULTI) if (isset($_GET['lang'])&&in_array($_GET['lang'],$languages)) $lang=$_GET['lang']; else $lang=$languages[0];
		//p($this->upcomingevents);
		if (isset($_POST['sent'])&&$_POST['sent']=="yEs") {
			if (file_put_contents(__DIR__."/upcomingevents".(isset($lang)&&$lang<>$languages[0]?"_".$lang:'').".madd",$_POST['upcomingevents'])) {
				$_SESSION['infoMessage']=_t("The Event List was saved","upcomingevents");
				$this->redir("upcomingevents".(isset($lang)&&$lang<>$languages[0]?'&lang='.$lang:''));
			}
		}
		$ret='<style>#upcomineventsContainer {} .item {position:relative; vertical-align:top; display:block; min-height:22px; line-height:22px; border:solid 1px #ccc; padding:4px; margin:4px 0 0px 0; background:#fff;  } .item input[type="text"] {width:180px; margin:4px 0 0 4px; padding:0; display:inline-block;} .item .del {display:block;width:16px;height:16px;font-size:16px;line-height:16px;text-align:center;padding:4px;border:solid 1px #ccc; border-radius:3px;position:absolute;right:2px;top:2px;background:#ccc;}.item div {display:inline-block;vertical-align:top; margin:0 16px 0 0;}.item div.r1{width:80px;}.item div.r2,.item div.r3{width:36px;}.item div.r4{width:240px;}.item div.r5{width:330px;}.item div.r6{width:120px;} .item input{max-width:100%;}/**/.ui-timepicker-div .ui-widget-header { margin-bottom: 8px; }.ui-timepicker-div dl { text-align: left; }.ui-timepicker-div dl dt { float: left; clear:left; padding: 0 0 0 5px; }.ui-timepicker-div dl dd { margin: 0 10px 10px 45%; }.ui-timepicker-div td { font-size: 90%; }.ui-tpicker-grid-label { background: none; border: none; margin: 0; padding: 0; }.ui-timepicker-rtl{ direction: rtl; }.ui-timepicker-rtl dl { text-align: right; padding: 0 5px 0 0; }.ui-timepicker-rtl dl dt{ float: right; clear: right; }.ui-timepicker-rtl dl dd { margin: 0 45% 10px 10px; }</style>';
		$ret.='<div class="form">
			<form action="" method="post"><input type="hidden" name="sent" value="yEs"/><textarea id="upcomingevents" name="upcomingevents" style="display:none;width:850px;height:120px;">'.(isset($_POST['upcomingevents'])?$_POST['upcomingevents']:json_encode($this->upcomingevents)).'</textarea>
			';
		if (MULTI) { 
			$ret.='<div class="row"><label>'._e("You are currently editing:").' <img src="'.SR.'a55ets/static/flags/'.$lang.'.png" /></label>';
			foreach ($languages as $k=>$l) if ($l<>$lang) {
				$curr=$_SERVER['REQUEST_URI'];
				if (strstr($curr,'&lang=')) $curr=str_replace('&lang='.$lang,'',$curr);
				$ret.='<a href="'.$curr.'&lang='.$l.'" class="flag"><img src="'.SR.'a55ets/static/flags/'.$l.'.png" /></a>';
			}
			$ret.='</div>';
		}
		$ret.='<div class="row"><div class="item"><div class="r1"><input type="text" id="date" name="datepicker" readonly="readonly" placeholder="'._t("Date","upcomingevents").'"/></div><div class="r2"><input type="text" id="times" class="time" name="times" readonly="readonly" placeholder="'._t("Start","upcomingevents").'"/></div><div class="r3"><input type="text" id="timef" class="time" name="timef" readonly="readonly" placeholder="'._t("End","upcomingevents").'"/></div><div class="r4"><input type="text" id="title" class="title" name="title" placeholder="'._t("Event Title","upcomingevents").'"/></div><div class="r5"><input type="text" id="description" class="description" name="description" placeholder="'._t("Event Description","upcomingevents").'"/></div><div class="r6"><input type="button" id="add" name="add" value="'._t("Add event","upcomingevents").'"/></div></div></div>';
		$ret.='<div class="row" id="upcomineventsContainer"></div>
			<div class="row"><input type="submit" name="send" value="'._t("Save Events","upcomingevents").'" /></div></form></div>';
		$ret.='<script type="text/javascript">
			$(function() {
				'.(LANG<>"en"?'$.datepicker.setDefaults($.datepicker.regional["'.LANG.'"]);$.timepicker.setDefaults($.timepicker.regional["'.LANG.'"]);':'').'
				$("#date").datepicker({ minDate:0,dateFormat:"yy-mm-dd"});
				$(".time").timepicker();
				showData("no");
			});
			function showData(fade){
				if (fade==undefined) $("#upcomineventsContainer").fadeOut().html("");
				else $("#upcomineventsContainer").html("");
				var s="<div id=\"ueDIVList\">";
				var j=$.parseJSON($("#upcomingevents").val());
				keys = Object.keys(j);
				keys.sort();keys.reverse();var c=0;
				$.each(keys,function(i,e){
					var dandt=e.split(" ");
					s=s+\'<div class="item" data-index="\'+c+\'" data-id="\'+e+\'"><div class="r1">\'+dandt[0]+\'</div><div class="r2">\'+dandt[1]+\'</div><div class="r3">\'+j[e][0]+\'</div><div class="r4">\'+j[e][1]+\'</div><div class="r5">\'+j[e][2]+\'</div><a href="#" class="del">X</a></div>\';
					c++;
				});
				s=s+"</div>";
				$("#upcomineventsContainer").html(s).fadeIn();
				onChanges();
			}
			function onChanges(){
				$(".del").click(function(e){e.preventDefault();
					var id=$(this).parent().attr("data-index");
					var j=$.parseJSON($("#upcomingevents").val());
					keys = Object.keys(j);									
					keys.splice(id,1);
					keys.sort();keys.reverse();
					var newOb=new Object();
					$.each(keys,function(i,e){
						newOb[e]=j[e];
					});
					$("#upcomingevents").val(JSON.stringify(newOb));
					$(this).parent().hide("explode");
					showData("no");					
				});
				$("#add").click(function(e){e.preventDefault();
					if ($("#date").val()!=""&&$("#times").val()!=""&&$("#title").val()!="") {
						var id=$(this).parent().attr("data-index");
						var j=$.parseJSON($("#upcomingevents").val());
						j[$("#date").val()+" "+$("#times").val()]=[$("#timef").val(),$("#title").val(),$("#description").val()];
						keys = Object.keys(j);						
						keys.sort();keys.reverse();
						var newOb=new Object();
						$.each(keys,function(i,e){
							newOb[e]=j[e];
						});
						$("#upcomingevents").val(JSON.stringify(newOb));
						$("#date, #times, #timef, #title, #description").val("");
						showData("no");							
					}
				});
			}
			var saveOrder = function(){
				var arraied = $("#mp3OLlist").nestedSortable("toHierarchy", {startDepthCount: 0});
				var j=$.parseJSON($("#upcomingevents").val());
				var obj=new Array();
				for (var i=0;i<arraied.length;i++) obj[i]=j[arraied[i].id];
				$("#upcomingevents").val(JSON.stringify(obj));
				showData("no");
			};
		</script>';			
		return $ret;
	}
	private function cronjob(){
		$glob=glob(__DIR__."/upcomingevents*.madd"); 
		if ($glob) foreach ($glob as $fn) {
			$f=$this->getFile($fn);
			if ($f) $j=json_decode($f);
			$newj=new stdClass();
			if ($j) foreach ($j as $k=>$v) {
				if ($k>date("Y-m-d H:i")) $newj->$k=$v;
//				echo $k.' - '.date("Y-m-d H:i"); p($v);
			}
			if ($j!=$newj) file_put_contents($fn,json_encode($newj));
//			var_dump($j==$newj);p($j);p($newj);
		}		
	}
	function __construct(){global $languages;
		$this->cronjob();
	/*$this->upcomingevents=array("2014-02-28 02:03"=>array("03:08","Event Title","Event Description"),"2014-02-28 01:03"=>array("03:08","Event Title","Event Description"),"2014-02-22 01:03"=>array("03:08","Event Title","Event Description"),); return;*/
		if ((MULTI&&$this->getCurlang()&&$this->getCurlang()<>$languages[0])||
			(isset($_GET['lang'])&&in_array($_GET['lang'],$languages)&&$_GET['lang']<>$languages[0]))
				$f=$this->getFile(__DIR__."/upcomingevents_".(isset($_GET['lang'])?$_GET['lang']:$this->getCurLang()).".madd");
		else $f=$this->getFile(__DIR__."/upcomingevents.madd");
		if ($f) $this->upcomingevents=json_decode($f);
		else $this->upcomingevents=array();
	}
}
?>