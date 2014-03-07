$(document).ready(function(e) {
	var opts = {cssClass : 'el-rte',height :450,lang:lang,toolbar  : 'maxi',cssfiles : [SR+'a55ets/static/e1rte/css/elrte-inner.css',(ELI?ELI:'')]}
	$('#editor').elrte(opts);
	
	if ($("#Page_url").length>0&&$("#Page_url").attr("readonly")!="readonly") {
		$("#Page_title").keyup(function(){
			$("#Page_url").val(convertToSlug($(this).val()));
		});
		$("#Page_title").change(function(){
			$("#Page_url").val(convertToSlug($(this).val()));
		});
	}
	$(".insertShortcode").click(function(e){e.preventDefault();
		var editor = $("#editor").elrte()[0].elrte;
		editor.selection.insertText($(this).text());
	});
	$('#edMenu ol.sortable').nestedSortable({
		disableNesting: 'no-nest',
		forcePlaceholderSize: true,
		handle: 'div',
		items: 'li',
		opacity: .6,
		placeholder: 'placeholder',
		tabSize: 25,
		tolerance: 'pointer',
		toleranceElement: '> div',
		maxLevels: 2,
		update: saveOrder
	});
	forDel();
	$('#addpagesButton').click(function(){
		var n = $("#addPages input:checked").length;
        if (n>0){
			var allVals = [];
			$('#addPages input:checked').each(function() {
			   var ase=$(this).val().split("|*|");
			   var obj = jQuery.parseJSON($("#menuContent").val());
			   var data=0;$.each(obj,function(i,v){
				   if (v!=null) if (data<parseInt(v[0])) data=parseInt(v[0])
			   });
			   data++;
			   var item = $('<li id="list_'+data+'"><div class="aseListItem"><div class="editable" id="editableId_'+data+'" data-text="'+ase[1]+'" data-url="'+ase[0]+'">'+ase[1]+'</div><a href="#" class="atodel" rel="'+data+'">X</a><a href="#" class="atourl" rel="'+data+'">URL</a></div>').hide();
			   $("ol.sortable").append(item);
			   item.show("explode",500);
			   saveOrder();
			   forDel();

			 });
			 $('#checks :checked').each(function() {
				 $(this).removeAttr("checked");
			 });
		}
	});
	$('#additem').click(function(){
		if ($("#static_nume").val()!=""&&$("#static_url").val()!="") {
			var obj = jQuery.parseJSON($("#menuContent").val());
			var data=0;$.each(obj,function(i,v){
				   if (v!=null) if (data<parseInt(v[0])) data=parseInt(v[0])
			   });
			   data++;
			   
			   var item = $('<li id="list_'+data+'"><div class="aseListItem"><div class="editable" id="editableId_'+data+'" data-text="'+$("#static_nume").val()+'" data-url="'+$("#static_url").val()+'">'+$("#static_nume").val()+'</div><a href="#" class="atodel" rel="'+data+'">X</a><a href="#" class="atourl" rel="'+data+'">URL</a></div>').hide();
			   $("ol.sortable").append(item);
			   item.show("explode",500);
			   saveOrder();
			   forDel();
		}
	});
});
	var saveOrder = function(){
		arraied = $('#edMenu ol.sortable').nestedSortable('toArray', {startDepthCount: 0});
		var obj=new Array();
		for (var i=0;i<arraied.length;i++) {
			if (i>0) {
			obj[i]=new Array();
			obj[i][0]=arraied[i].item_id;
			obj[i][1]=(arraied[i].parent_id=="root"?"0":arraied[i].parent_id);
			obj[i][2]=$("#editableId_"+arraied[i].item_id).attr("data-text");
			obj[i][3]=$("#editableId_"+arraied[i].item_id).attr("data-url");
			}
		}
		$("#menuContent").val(JSON.stringify(obj))
	};
function forDel() {	
	$('.editable').editable(function(value,settings){$(this).attr("data-text",value);saveOrder();return(value);}, {
		 submitdata : {type: "saveItem"},
         indicator : 'Saving. Please wait ...',
		 event     : "dblclick",
         tooltip   : 'Double click to edit'
    });	
	$('.atourl').click(function(e){
		e.preventDefault();
		var buttonsOpts = {}
		buttonsOpts[translations["save"]] = function(){$( this ).dialog( "close" );$("#editableId_"+$(oldThis).attr('rel')).attr("data-url",$("#item_url").val());saveOrder();}
		buttonsOpts[translations["cancel"]] = function(){$( this ).dialog( "close" );}
		oldThis=this; 
		$("#item_url").val($("#editableId_"+$(oldThis).attr('rel')).attr("data-url"));
		$('#url_dialog').dialog({width: 300, modal: true, resizable: false, buttons: buttonsOpts});
	});
	$('.atodel').click(function(e){
		e.preventDefault();
		var buttonsOpts = {}
		buttonsOpts[translations["delete"]] = function(){$('ol.sortable #list_'+$(oldThis).attr('rel')).hide('explode',1000);$('ol.sortable #list_'+$(oldThis).attr('rel')).remove();$( this ).dialog( "close" );saveOrder();}
		buttonsOpts[translations["cancel"]] = function(){$( this ).dialog( "close" );}
		oldThis=this; 
		$('#confirm_delete_dialog').dialog({width: 300, modal: true, resizable: false, show: 'fade', hide: 'fade', buttons: buttonsOpts});
	});
}
function convertToSlug(text){
    return text.toLowerCase().replace(/[^\w ]+/g,'').replace(/ +/g,'-');
}