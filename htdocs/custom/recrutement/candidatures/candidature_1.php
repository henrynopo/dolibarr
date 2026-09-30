<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/recrutement/class/postes.class.php');
dol_include_once('/recrutement/class/candidatures.class.php');
dol_include_once('/recrutement/class/etiquettes.class.php');
dol_include_once('/recrutement/class/etapescandidature.class.php');
dol_include_once('/recrutement/lib/recrutement.lib.php');
dol_include_once('/core/class/html.form.class.php');

  	// print '<link rel="stylesheet" href="https://www.riccardotartaglia.it/jkanban/dist/jkanban.min.css" />';
   
    print '<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>';
    print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.0/spectrum.min.css" />';
    print '<script src="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.0/spectrum.min.js"></script>';
   
    

$langs->load('recrutement@recrutement');

$modname = $langs->trans("candidatures");


$candidatures = new candidatures($db);
$etiquettes = new etiquettes($db);
$etapes = new etapescandidature($db);
$poste = new postes($db);

$id_poste=GETPOST('id_poste');

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

print_fiche_titre($modname);
// $head = recrutementAdminPrepareHead($id);

// dol_fiche_head(
//         $head,
//         'postes',
//         '', 
//         0,
//         "recrutement@recrutement"
//     );

// print '<div style="float: right; margin: 8px;">';
// 	print '<a href="card.php?action=add" class="butAction" >'.$langs->trans("Add").'</a>';
// print '</div>';

$filter='';
if($id_poste){
	$filter=' and poste = '.$id_poste;
}
$candidatures->fetchAll('','',0,0,$filter);
$nb=count($candidatures->rows);

$etapes->fetchAll();
$nb_etap = count($etapes->rows);

print '<div style="float: left; margin-bottom: 8px; width:100%;">';
  print '<div >';
    print '<a class="icon_list" data-type="list" onclick="cl();" > <img  src="'.dol_buildpath('/recrutement/img/list.png',2).'" style="height:30px" id="list" ></a>';
    print '<a class="icon_list" data-type="grid"> <img src="'.dol_buildpath('/recrutement/img/grip.png',2).'" style="height:30px" id="grid" ></a> ';
  print '<a href="card.php?action=add" class="butAction" style="float:right;">'.$langs->trans("Add").'</a>';
  print '</div>';
print '</div>';

// <!-- Simple MDL Progress Bar -->
// print '<div id="p1" class="mdl-progress mdl-js-progress"></div>';
?>
<script>
  document.querySelector('#p1').addEventListener('mdl-componentupgraded', function() {
    this.MaterialProgress.setProgress(44);
  });
</script>
<?php

print '<div class="dd">';
    $etapes->fetchAll();
    $nb=count($etapes->rows);
    for ($i=0; $i < $nb; $i++) { 

        $etape=$etapes->rows[$i];
        $candidatures->fetchAll('','',0,0,'AND etape ='.$etape->rowid);
        $nb_c=count($candidatures->rows);
            print '<ol class="kanban To-do" id="'.$etape->rowid.'">';
                print '<div class="kanban__title">';
                    print '<h2>'. $etape->label.' <span style="float:right">'.$nb_c.'</span></h2>';
                    print '<span align="right"></span>';
                print '</div>';

                if($nb_c>0){
                    for ($j=0; $j < $nb_c; $j++) { 
                        $item=$candidatures->rows[$j];
                        print '<li class="dd-item " id="'.$item->rowid.'" data-id="3" style="border-left:5px solid '.$etape->color.'">';
                            print '<span class="title " >';
                                print '<span style="width:90% !important; float:right;">';
                                print $item->nom;
                                print '</span>';
                                print '<span style="width:10% !important; float:right;">';
                                    print '<a href="./card.php?id='.$item->id.'&action=edit" align="right">';
                                        print '<img align="right" src="'.DOL_MAIN_URL_ROOT.'/theme/md/img/edit.png">';
                                    print '</a>';
                                print '</span>';
                            print '</span>';
                        print '<div class="dd-handle">';
                              print '<br>';
                            print '<div class="text" >';
                                print '<div style=padding-bottom:5px;>'.$item->sujet.'</div>';
                                print '<div style=padding-bottom:5px;>'.$item->email.'</div>';
                                print '<div style=padding-bottom:5px;>'.$item->tel.'</div>';
                            print '</div>';

                            print '<div>';
                                $etiquette=json_decode($item->etiquettes);
                                foreach ($etiquette as $key => $value) {
                                    $etiquettes->fetch($value);
                                    print '<span style="color:'.$etiquettes->color.';font-size:50px;line-height: 0px;">.</span><span style="font-size:14px;">'.$etiquettes->label.'</span>&nbsp;';
                                }
                            print '</div>';
                            // print $item->appreciation;
                            print '<div class="actions">';
                                print '<div class="rating">';
                                    $rating ='<input type="radio" id="star3_'.$item->rowid.'" name="appreciation" class="appreciation" value="3" /><label for="star3_'.$item->rowid.'"></label>';
                                    $rating.='<input type="radio" id="star2_'.$item->rowid.'" name="appreciation" class="appreciation" value="2" /><label for="star2_'.$item->rowid.'"></label>';
                                    $rating.='<input type="radio" id="star1_'.$item->rowid.'" name="appreciation" class="appreciation" value="1" /><label for="star1_'.$item->rowid.'"></label>';

                                    $rating = str_replace('value="'.$item->appreciation.'"', 'value="'.$item->appreciation.'" checked', $rating);
                                    print $rating;
                                print '</div>'; 
                            print '</div>';
                        
                        print '</li>';
                    }
                }
            print '</ol>';
    }

    // print '<ol class="kanban To-do">';
    //     print '<div class="kanban__title">';
    //         print '<h2><i class="material-icons">report_problem</i> To do</h2>';
    //     print '</div>';

    //     print '<li class="dd-item" data-id="3">';
    //         print '<h3 class="title dd-handle"><i class=" material-icons ">filter_none</i>UX design</h3>';
    //         print '<div class="text" contenteditable="true">';
    //            print ' Paul Rand once said, “The public is more familiar with bad fucking design than good design. It is, in effect, conditioned to prefer bad design,';
    //         print '</div>';
    //         print '<div class="actions">';
    //             print '<i class="material-icons">palette</i><i class="material-icons">edit</i><i class="material-icons">insert_link</i><i class="material-icons">attach_file</i>';
    //         print '</div>';
        
    //     print '</li>';
    //     print '<div class="actions">';
    //         print '<button class="addbutt"><i class="material-icons">control_point</i> Add new</button>';
    //     print '</div>';
    // print '</ol>';



print '</div>';
?>
<style type="text/css">

    .icon_list{
    cursor: pointer;
    /*text-decoration: none;*/
    display: inline-block;
  }
  .icon_list:hover{
    text-decoration: none;
    /*padding: 0 1px;*/

  }

  .icon_list:hover img{
    background-color: rgba(0, 0, 0, 0.15);
  }
	body{font-family: "Lato"; margin:0; padding: 0;}
	#myKanban{overflow-x: auto; padding:20px 0;}

	.success{background: #00B961; color:#fff}
	.info{background: #2A92BF; color:#fff}
	.warning{background: #F4CE46; color:#fff}
	.error{background: #FB7D44; color:#fff}
	.scroll_div{
		position: relative;
	}
	.one_content.class2 {
		left:0 !important;
		top:auto !important;
		position: absolute;
	}
	.slimScrollDiv, .scroll_div {
	    min-height: 490px;
	}
	.scroll_div.ui-droppable.ui-droppable-active.ui-droppable-hover{
		border: 1px dashed #c8c8c8;
	}
	.todo_content .columns_ .todo_titre{
		padding: 10px 4px 0;
	}
	.badges.comments{
		margin:0;
	}
	.todo_content .columns_ .one_content {
		cursor: all-scroll;
	}

	/*#PPAYEE .contents,
	#RETARD .contents,
	#PPAYEE .contents,
	#PPAYEE .contents,
	#PPAYEE .contents,
	#PPAYEE .contents {
		cursor: no-drop;
	}*/

    Body {
      font-family: Sans-serif;
      font size: 14;
      width: 100%;
      background-color: #E0E0E0;
    }

    h1 {
      position: absolute;
      left: 16px;
      top: 16px;
    }

    menu {
      position: absolute;
      right: 16px;
      top: 16px;
    }

    menu.kanban .viewlist,
    menu.list .viewkanban {
      display: inline;
    }

    menu.kanban .viewkanban,
    menu.list .viewlist {
      display: none;
    }

    .dd {
      max-width: 100%;
      /*top: 88px;*/
      margin: 0 auto;
      display: block;
      vertical-align: top;
    }

    ol {
      transition: border-color 2s ease, all 0.1s ease;
    }

    ol.list {
      padding-top: 2em;
      padding-left: 15px;
      max-width: 650px;
      margin: 0 auto;
    }

    ol.list .text {
      float: right;
      width: 60%;
    }

    ol.list h3,
    ol.list .actions,
    ol.list label {
      float: left;
      width: 30%;
    }

    ol.list > li,
    ol.list > h3 {
      max-width: 600px;
      margin: 0 auto;
    }

    ol.list > h2 {
      padding-bottom: 6px;
    }

   /* ol.list.To-do {
      border-left: 2px solid #FFB300;
    }

    ol.list.Gone {
      border-left: 2px solid #FF3D00;
    }

    ol.list.progress {
      border-left: 2px solid #29B6F6;
    }

    ol.list.Done {
      border-left: 2px solid #8BC34A;
    }*/

    H2,
    h1,
    button {
      margin-left: 5px;
      font-family: 'Arbutus Slab', serif;
    }

    h2 {
      color: black;
    }

    h2 .material-icons {
      color: #B0BEC5;
      line-height: 1.5;
    }

    .dd-handle .material-icons {
      color: #B0BEC5;
      font-size: 14px;
      font-weight: 800;
      line-height: 2rem;
      position: relative;
      right: 0;
      color: #607D8B;
      padding: 5px 16px;
    }

    button>.material-icons {
      line-height: 0.2;
      position:relative;
      top:7px;
    }



    .dd-item:hover,
    button:hover {
      /*color: #00838F;*/
      will-change: box-shadow;
      transition: box-shadow .2s cubic-bezier(.4, 0, 1, 1), background-color .2s cubic-bezier(.4, 0, .2, 1), color .2s cubic-bezier(.4, 0, .2, 1);
      box-shadow: 0 5px 6px 0 rgba(0, 0, 0, .14), 0 3px 1px -6px rgba(0, 0, 0, .2), 2px 5px 3px 0 rgba(0, 0, 0, .12);
    }

    button.addbutt {
      background-color: #EEEEEE;
      color: #607D8B;
      width: 100%;
    }

    .list > button.addbutt {
      max-width: 330px;
    }

    button:active, button:down, button:focus {box-shadow: 0 0 0 0, 0 0 0 0 rgba(0, 0, 0, .2), 0 0 0 0 rgba(0, 0, 0, .12);color:#00838F;}
    button {
      align-items: center;
      background-color: #EEEEEE;
      box-shadow: 0 2px 2px 0 rgba(0, 0, 0, .14), 0 3px 1px -2px rgba(0, 0, 0, .2), 0 1px 5px 0 rgba(0, 0, 0, .12);
      border: 1px solid #ccc;
      border-radius: 2px;
      color: #607D8B;
      position: relative;
      margin: 0;
      min-width: 44px;
      padding: 10px 16px;
      display: inline-block;
      font-size: 14px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1;
      overflow: hidden;
      outline: none;
      cursor: pointer;
      text-decoration: none;
        }
/*
    ol.kanban.To-do {
      border-top: 5px solid #FFB300;
    }

    ol.kanban.Gone {
      border-top: 5px solid #FF3D00;
    }

    ol.kanban.progress {
      border-top: 5px solid #29B6F6;
    }

    ol.kanban.Done {
      border-top: 5px solid #8BC34A;
    }*/

    ol.kanban {
      /*border-top: 5px solid     ;*/
      width: 16%;
      height: auto;
      margin: 1%;
      /*max-width: 250px;*/
      min-width: 120px;
      display: inline-block;
      vertical-align: top;
      box-shadow: 0 2px 2px 0 rgba(0, 0, 0, .14), 0 3px 1px -2px rgba(0, 0, 0, .2), 0 1px 5px 0 rgba(0, 0, 0, .12);
      flex-direction: column;
      min-height: 200px;
      z-index: 1;
      position: relative;
      background: #fff;
      padding: 1em;
      border-radius: 2px;
    }

    .dd-item {
      display: block;
      position: relative;
      list-style: none;
      font-family: "Roboto", "Helvetica", "Arial", sans-serif;
      min-height: 48px;
      display: -webkit-flex;
      display: -ms-flexbox;
      display: flex;
      -webkit-flex-direction: column;
      -ms-flex-direction: column;
      flex-direction: column;
      font-size: 16px;
      min-height: 120px;
      overflow: hidden;
      z-index: 1;
      position: relative;
      background: #fff;
      border-radius: 2px;
      box-sizing: border-box;
    }

    .title {
      align-self: flex-end;
      color: inherit;
      display: block;
      display: -webkit-flex;
      display: -ms-flexbox;
      display: flex;
      font-size: 16px;
      line-height: normal;
      overflow: hidden;
      -webkit-transform-origin: 149px 48px;
      transform-origin: 149px 48px;
      margin: 0;
    }

    .text {
      color: grey;
      border-top: 1px solid font-size: 1rem;
      font-weight: 400;
      line-height: 18px;
      overflow: hidden;
      /*padding: 16px;*/
      width: 90%;
    }

    .actions {
      border-top: 1px solid rgba(0, 0, 0, .1);
      font-size: 8px;
      line-height: normal;
      width: 100%;
      color: #B0BEC5;
      padding: 2px;
      box-sizing: border-box;
    }


    /**
     * Nestable
     */

    .dd {
      /*position: relative;*/
      display: block;
      list-style: none;
    }

    .dd-list {
      display: block;
      position: relative;
      margin: 0;
      padding: 0;
      list-style: none;
    }

    .dd-list .dd-list {
      padding-left: 30px;
    }

    .dd-collapsed .dd-list {
      display: none;
    }

    .dd-item {
      display: block;
      margin: 5px 0;
      padding: 5px 10px;
      color: #333;
      text-decoration: none;
      font-weight: bold;
      border: 1px solid #ccc;
      background: #fafafa;
      -webkit-border-radius: 3px;
      border-radius: 3px;
      box-sizing: border-box;
      -moz-box-sizing: border-box;
    }

    .dd-item:hover {
      background: #fff;
    }

    .dd-item > button {
      display: block;
      position: relative;
      cursor: move;
      float: left;
      width: 25px;
      height: 20px;
      margin: 5px 0;
      padding: 0;
      text-indent: 100%;
      white-space: nowrap;
      overflow: hidden;
      border: 0;
      background: transparent;
      font-size: 12px;
      line-height: 1;
      text-align: center;
      font-weight: bold;
    }

    .dd-item > button:before {
      content: '+';
      display: block;
      position: absolute;
      width: 100%;
      text-align: center;
      text-indent: 0;
    }

    .dd-item > button[data-action="collapse"]:before {
      content: '<i class="material-icons">filter_none</i>';
    }

    .dd-placeholder,
    .dd-empty {
      margin: 5px 0;
      padding: 0;
      min-height: 30px;
      background: #E0E0E0;
      border: 1px dashed #b6bcbf;
      box-sizing: border-box;
      -moz-box-sizing: border-box;
    }

    .dd-empty {
      border: 1px dashed #bbb;
      min-height: 100px;
      background-color: #E0E0E0;
      background-size: 60px 60px;
      background-position: 0 0, 30px 30px;
    }

    .dd-dragel {
      position: absolute;
      pointer-events: none;
      z-index: 9999;
    }

    .dd-dragel > .dd-item .dd-handle {
      margin-top: 0;
      cursor: move;
    }

    .dd-dragel .dd-item {
      -webkit-box-shadow: 2px 4px 6px 0 rgba(0, 0, 0, .5);
      box-shadow: 2px 4px 6px 0 rgba(0, 0, 0, .5);
      cursor: move;
    }
     .rating {
      /*width: 208px;*/
      /*height: 40px;*/
      margin: 0 auto;
      float: left;
     
    }
    .rating label {
      float: right;
      position: relative;
      /*width: 40px;*/
      /*height: 40px;*/
      cursor: pointer;
    }
    .rating label:not(:first-of-type) {
      padding-right: 2px;
    }
    .rating label:before {
      content: "\2605";
      font-size: 25px;
      color: #CCCCCC;
      line-height: 1;
    }
    .rating input {
      display: none;
    }
    .rating input:checked ~ label:before {
      color: #F9DF4A;
    }
    .rating_edit .rating input:checked ~ label:before, .rating_edit .rating:not(:checked) > label:hover:before, .rating_edit .rating:not(:checked) > label:hover ~ label:before {
      color: #F9DF4A;
    }
</style>
<script>

  $(document).ready(function(){
    
          $('#grid').css('background-color','rgba(0, 0, 0, 0.15)');
     $('.icon_list').click(function(){
        $type=$(this).data('type');
        if($type == 'list'){
          $('#grid').css('background-color','white');
          $('#list').css('background-color','rgba(0, 0, 0, 0.15)');
          window.location.href="<?php echo dol_buildpath('/recrutement/candidatures/index.php',2);?>";
         
        }
        if($type == 'grid'){
          $('#list').css('background-color','white');
          $('#grid').css('background-color','rgba(0, 0, 0, 0.15)');
              window.location.href="<?php echo dol_buildpath('/recrutement/candidatures/candidature.php',2);?>";
          $('.board').show();
          $('.list').hide();
        }
      });
  });
</script>
<script>
    // $(function(){
        /*!
        * Nestable jQuery Plugin - Copyright (c) 2012 David Bushell - http://dbushell.com/
     * Dual-licensed under the BSD or MIT licenses
     */
      $(function(){
        // $('.appreciation').attr('disabled','disabled');

      });
        (function($, window, document, undefined)
        {
            var hasTouch = 'ontouchstart' in document;
            /**
             * Detect CSS pointer-events property
             * events are normally disabled on the dragging element to avoid conflicts
             * https://github.com/ausi/Feature-detection-technique-for-pointer-events/blob/master/modernizr-pointerevents.js
             */
            var hasPointerEvents = (function()
            {
                var el    = document.createElement('div'),
                    docEl = document.documentElement;
                if (!('pointerEvents' in el.style)) {
                    return false;
                }
                el.style.pointerEvents = 'auto';
                el.style.pointerEvents = 'x';
                docEl.appendChild(el);
                var supports = window.getComputedStyle && window.getComputedStyle(el, '').pointerEvents === 'auto';
                docEl.removeChild(el);
                return !!supports;
            })();

            var defaults = {
                    listNodeName    : 'ol',
                    itemNodeName    : 'li',
                    rootClass       : 'dd',
                    listClass       : 'dd-list',
                    itemClass       : 'dd-item',
                    dragClass       : 'dd-dragel',
                    handleClass     : 'dd-handle',
                    collapsedClass  : 'dd-collapsed',
                    placeClass      : 'dd-placeholder',
                    noDragClass     : 'dd-nodrag',
                    emptyClass      : 'dd-empty',
                    expandBtnHTML   : '<button data-action="expand" type="button">Expand</button>',
                    collapseBtnHTML : '<button data-action="collapse" type="button">Collapse</button>',
                    group           : 0,
                    maxDepth        : 5,
                    threshold       : 20
                };

            function Plugin(element, options)
            {
                this.w  = $(document);
                this.el = $(element);
                this.options = $.extend({}, defaults, options);
                this.init();
            }

            Plugin.prototype = {

                init: function()
                {
                    var list = this;

                    list.reset();

                    list.el.data('nestable-group', this.options.group);

                    list.placeEl = $('<div class="' + list.options.placeClass + '"/>');

                    $.each(this.el.find(list.options.itemNodeName), function(k, el) {
                        list.setParent($(el));
                    });

                    list.el.on('click', 'button', function(e) {
                        if (list.dragEl) {
                            return;
                        }
                        var target = $(e.currentTarget),
                            action = target.data('action'),
                            item   = target.parent(list.options.itemNodeName);
                        if (action === 'collapse') {
                            list.collapseItem(item);
                        }
                        if (action === 'expand') {
                            list.expandItem(item);
                        }
                    });

                    var onStartEvent = function(e)
                    {
                        var handle = $(e.target);
                        if (!handle.hasClass(list.options.handleClass)) {
                            if (handle.closest('.' + list.options.noDragClass).length) {
                                return;
                            }
                            handle = handle.closest('.' + list.options.handleClass);
                        }

                        if (!handle.length || list.dragEl) {
                            return;
                        }

                        list.isTouch = /^touch/.test(e.type);
                        if (list.isTouch && e.touches.length !== 1) {
                            return;
                        }

                        e.preventDefault();
                        list.dragStart(e.touches ? e.touches[0] : e);
                    };

                    var onMoveEvent = function(e)
                    {
                        if (list.dragEl) {
                            e.preventDefault();
                            list.dragMove(e.touches ? e.touches[0] : e);
                        }
                    };

                    var onEndEvent = function(e)
                    {
                        if (list.dragEl) {
                            e.preventDefault();
                            list.dragStop(e.touches ? e.touches[0] : e);
                        }
                       
                    };
                    if (hasTouch) {
                        list.el[0].addEventListener('touchstart', onStartEvent, false);
                        window.addEventListener('touchmove', onMoveEvent, false);
                        window.addEventListener('touchend', onEndEvent, false);
                        window.addEventListener('touchcancel', onEndEvent, false);
                    }

                    list.el.on('mousedown', onStartEvent);
                    list.w.on('mousemove', onMoveEvent);
                    list.w.on('mouseup', onEndEvent);

                },

                serialize: function()
                {
                    var data,
                        depth = 0,
                        list  = this;
                        step  = function(level, depth)
                        {
                            var array = [ ],
                                items = level.children(list.options.itemNodeName);
                            items.each(function()
                            {
                                var li   = $(this),
                                    item = $.extend({}, li.data()),
                                    sub  = li.children(list.options.listNodeName);
                                if (sub.length) {
                                    item.children = step(sub, depth + 1);
                                }
                                array.push(item);
                            });
                            return array;
                        };
                    data = step(list.el.find(list.options.listNodeName).first(), depth);
                    return data;
                },

                serialise: function()
                {
                    return this.serialize();
                },

                reset: function()
                {
                    this.mouse = {
                        offsetX   : 0,
                        offsetY   : 0,
                        startX    : 0,
                        startY    : 0,
                        lastX     : 0,
                        lastY     : 0,
                        nowX      : 0,
                        nowY      : 0,
                        distX     : 0,
                        distY     : 0,
                        dirAx     : 0,
                        dirX      : 0,
                        dirY      : 0,
                        lastDirX  : 0,
                        lastDirY  : 0,
                        distAxX   : 0,
                        distAxY   : 0
                    };
                    this.isTouch    = false;
                    this.moving     = false;
                    this.dragEl     = null;
                    this.dragRootEl = null;
                    this.dragDepth  = 0;
                    this.hasNewRoot = false;
                    this.pointEl    = null;
                },

                expandItem: function(li)
                {
                    li.removeClass(this.options.collapsedClass);
                    li.children('[data-action="expand"]').hide();
                    li.children('[data-action="collapse"]').show();
                    li.children(this.options.listNodeName).show();
                },

                collapseItem: function(li)
                {
                    var lists = li.children(this.options.listNodeName);
                    if (lists.length) {
                        li.addClass(this.options.collapsedClass);
                        li.children('[data-action="collapse"]').hide();
                        li.children('[data-action="expand"]').show();
                        li.children(this.options.listNodeName).hide();
                    }
                },

                expandAll: function()
                {
                    var list = this;
                    list.el.find(list.options.itemNodeName).each(function() {
                        list.expandItem($(this));
                    });
                },

                collapseAll: function()
                {
                    var list = this;
                    list.el.find(list.options.itemNodeName).each(function() {
                        list.collapseItem($(this));
                    });
                },

                setParent: function(li)
                {
                    if (li.children(this.options.listNodeName).length) {
                        li.prepend($(this.options.expandBtnHTML));
                        li.prepend($(this.options.collapseBtnHTML));
                    }
                    li.children('[data-action="expand"]').hide();
                },

                unsetParent: function(li)
                {
                    li.removeClass(this.options.collapsedClass);
                    li.children('[data-action]').remove();
                    li.children(this.options.listNodeName).remove();
                },

                dragStart: function(e)
                {
                    var mouse    = this.mouse,
                        target   = $(e.target),
                        dragItem = target.closest(this.options.itemNodeName);

                    this.placeEl.css('height', dragItem.height());

                    mouse.offsetX = e.offsetX !== undefined ? e.offsetX : e.pageX - target.offset().left;
                    mouse.offsetY = e.offsetY !== undefined ? e.offsetY : e.pageY - target.offset().top;
                    mouse.startX = mouse.lastX = e.pageX;
                    mouse.startY = mouse.lastY = e.pageY;

                    this.dragRootEl = this.el;

                    this.dragEl = $(document.createElement(this.options.listNodeName)).addClass(this.options.listClass + ' ' + this.options.dragClass);
                    this.dragEl.css('width', dragItem.width());

                    dragItem.after(this.placeEl);
                    dragItem[0].parentNode.removeChild(dragItem[0]);
                    dragItem.appendTo(this.dragEl);

                    $(document.body).append(this.dragEl);
                    this.dragEl.css({
                        'left' : e.pageX - mouse.offsetX,
                        'top'  : e.pageY - mouse.offsetY
                    });
                    // total depth of dragging item
                    var i, depth,
                        items = this.dragEl.find(this.options.itemNodeName);
                        $id_debut=items.attr('id');
                        

                    for (i = 0; i < items.length; i++) {
                        depth = $(items[i]).parents(this.options.listNodeName).length;
                        if (depth > this.dragDepth) {
                            this.dragDepth = depth;

                        }
                    }
                },

                dragStop: function(e)
                {
                    var el = this.dragEl.children(this.options.itemNodeName).first();
                    el[0].parentNode.removeChild(el[0]);
                    this.placeEl.replaceWith(el);

                    this.dragEl.remove();
                    this.el.trigger('change');
                    if (this.hasNewRoot) {
                        this.dragRootEl.trigger('change');
                    }
                    this.reset();
                },

                dragMove: function(e)
                {
                    var list, parent, prev, next, depth,
                        opt   = this.options,
                        mouse = this.mouse;

                    this.dragEl.css({
                        'left' : e.pageX - mouse.offsetX,
                        'top'  : e.pageY - mouse.offsetY
                    });
                    // console.log(opt.listNodeName);

                    // mouse position last events
                    mouse.lastX = mouse.nowX;
                    mouse.lastY = mouse.nowY;
                    // mouse position this events
                    mouse.nowX  = e.pageX;
                    mouse.nowY  = e.pageY;
                    // distance mouse moved between events
                    mouse.distX = mouse.nowX - mouse.lastX;
                    mouse.distY = mouse.nowY - mouse.lastY;
                    // direction mouse was moving
                    mouse.lastDirX = mouse.dirX;
                    mouse.lastDirY = mouse.dirY;
                    // direction mouse is now moving (on both axis)
                    mouse.dirX = mouse.distX === 0 ? 0 : mouse.distX > 0 ? 1 : -1;
                    mouse.dirY = mouse.distY === 0 ? 0 : mouse.distY > 0 ? 1 : -1;
                    // axis mouse is now moving on
                    var newAx   = Math.abs(mouse.distX) > Math.abs(mouse.distY) ? 1 : 0;

                    // do nothing on first move
                    if (!mouse.moving) {
                        mouse.dirAx  = newAx;
                        mouse.moving = true;
                        return;
                    }

                    // calc distance moved on this axis (and direction)
                    if (mouse.dirAx !== newAx) {
                        mouse.distAxX = 0;
                        mouse.distAxY = 0;
                    } else {
                        mouse.distAxX += Math.abs(mouse.distX);
                        if (mouse.dirX !== 0 && mouse.dirX !== mouse.lastDirX) {
                            mouse.distAxX = 0;
                        }
                        mouse.distAxY += Math.abs(mouse.distY);
                        if (mouse.dirY !== 0 && mouse.dirY !== mouse.lastDirY) {
                            mouse.distAxY = 0;
                        }
                    }
                    mouse.dirAx = newAx;

                    /**
                     * move horizontal
                     */

                    if (mouse.dirAx && mouse.distAxX >= opt.threshold) {
                        // reset move distance on x-axis for new phase
                        mouse.distAxX = 0;
                        prev = this.placeEl.prev(opt.itemNodeName);
                        // increase horizontal level if previous sibling exists and is not collapsed
                        if (mouse.distX > 0 && prev.length && !prev.hasClass(opt.collapsedClass)) {
                            // cannot increase level when item above is collapsed
                            list = prev.find(opt.listNodeName).last();
                            // check if depth limit has reached
                            depth = this.placeEl.parents(opt.listNodeName).length;
                            if (depth + this.dragDepth <= opt.maxDepth) {
                                // create new sub-level if one doesn't exist
                                if (!list.length) {
                                    list = $('<' + opt.listNodeName + '/>').addClass(opt.listClass);
                                    list.append(this.placeEl);
                                    prev.append(list);
                                    this.setParent(prev);
                                } else {
                                    // else append to next level up
                                    list = prev.children(opt.listNodeName).last();
                                    list.append(this.placeEl);
                                }
                            }

                        }
                        // decrease horizontal level
                        if (mouse.distX < 0) {
                            // we can't decrease a level if an item preceeds the current one
                            next = this.placeEl.next(opt.itemNodeName);
                            if (!next.length) {
                                parent = this.placeEl.parent();
                                this.placeEl.closest(opt.itemNodeName).after(this.placeEl);
                                if (!parent.children().length) {
                                    this.unsetParent(parent.parent());
                                }
                            }
                        }
                    }

                    var isEmpty = false;

                    // find list item under cursor
                    if (!hasPointerEvents) {
                        this.dragEl[0].style.visibility = 'hidden';
                    }
                    this.pointEl = $(document.elementFromPoint(e.pageX - document.body.scrollLeft, e.pageY - (window.pageYOffset || document.documentElement.scrollTop)));
                    if (!hasPointerEvents) {
                        this.dragEl[0].style.visibility = 'visible';
                    }
                    if (this.pointEl.hasClass(opt.handleClass)) {
                        this.pointEl = this.pointEl.parent(opt.itemNodeName);
                    }
                    if (this.pointEl.hasClass(opt.emptyClass)) {
                        isEmpty = true;
                    }
                    else if (!this.pointEl.length || !this.pointEl.hasClass(opt.itemClass)) {
                        return;
                    }

                    // find parent list of item under cursor
                    var pointElRoot = this.pointEl.closest('.' + opt.rootClass),
                        isNewRoot   = this.dragRootEl.data('nestable-id') !== pointElRoot.data('nestable-id');
                    /**
                     * move vertical
                     */
                    if (!mouse.dirAx || isNewRoot || isEmpty) {
                        // check if groups match if dragging over new root
                        if (isNewRoot && opt.group !== pointElRoot.data('nestable-group')) {
                            return;
                        }
                        // check depth limit
                        depth = this.dragDepth - 1 + this.pointEl.parents(opt.listNodeName).length;
                        if (depth > opt.maxDepth) {
                            return;
                        }
                        var before = e.pageY < (this.pointEl.offset().top + this.pointEl.height() / 2);
                            parent = this.placeEl.parent();
                        // if empty create new list to replace empty placeholder
                        if (isEmpty) {
                            list = $(document.createElement(opt.listNodeName)).addClass(opt.listClass);
                            list.append(this.placeEl);
                            this.pointEl.replaceWith(list);
                        }
                        else if (before) {
                            this.pointEl.before(this.placeEl);
                        }
                        else {
                            this.pointEl.after(this.placeEl);
                        }
                        if (!parent.children().length) {
                            this.unsetParent(parent.parent());
                        }
                        if (!this.dragRootEl.find(opt.itemNodeName).length) {
                            this.dragRootEl.append('<div class="' + opt.emptyClass + '"/>');
                        }
                        // parent root list has changed
                        if (isNewRoot) {
                            this.dragRootEl = pointElRoot;
                            this.hasNewRoot = this.el[0] !== this.dragRootEl[0];
                        }

                        $id_etat=parent.children('.dd-item').parent('.To-do').attr('id');
                        if(!$id_etat){
                            $id_etat = this.pointEl.parent().attr('id');
                        }
                        $.ajax({
                          data:{'id_candidat':$id_debut,'id_etat':$id_etat},
                          url:"<?php echo dol_buildpath('/recrutement/candidatures/info_contact.php?action_=change_etat',2) ?>",
                          type:'POST',
                          dataType: "json",
                          success:function($data){
                              parent.children('#'+$data['id_candidat']).css('border-left','5px solid '+$data['color']);
                          }
                        });

                    }
                        // console.log(this.pointEl);
                        

                }

            };

            $.fn.nestable = function(params)
            {
                var lists  = this,
                    retval = this;

                lists.each(function()
                {
                    var plugin = $(this).data("nestable");

                    if (!plugin) {
                        $(this).data("nestable", new Plugin(this, params));
                        $(this).data("nestable-id", new Date().getTime());
                    } else {
                        if (typeof params === 'string' && typeof plugin[params] === 'function') {
                            retval = plugin[params]();
                        }
                    }
                });

                return retval || lists;
            };

        })(window.jQuery || window.Zepto, window, document);
        /*my scripts*/
        $('.dd').nestable('serialize');
        $('.viewlist').on('click', function() {
            $('ol.kanban').addClass('list')
            $('ol.list').removeClass('kanban')
            $('menu').addClass('list')
            $('menu').removeClass('kanban')
           });
        $('.viewkanban').on('click', function() {
            $('ol.list').addClass('kanban')
             $('ol.kanban').removeClass('list')
               $('menu').addClass('kanban')
             $('menu').removeClass('list')
           });
        /*colors*/
        // $('#color').spectrum({
        //     color: "#f00",
        //     change: function(color) {
        //         $("#label").text("change called: " + color.toHexString());
        //     }
        // });
    // });
</script>

<!-- end -->

<!-- <style>
    body{font-family: "Lato"; margin:0; padding: 0;}
    #myKanban{overflow-x: auto; padding:20px 0;}

    .success{background: #00B961; color:#fff}
    .info{background: #2A92BF; color:#fff}
    .warning{background: #F4CE46; color:#fff}
    .error{background: #FB7D44; color:#fff}
</style>

<script>
    var KanbanTest = new jKanban({
        element : '#myKanban',
        gutter  : '10px',
        click : function(el){
            alert(el.innerHTML);
            alert(el.dataset.eid)
        },
        boards  :[
            {
                'id' : '_todo',
                'title'  : 'To Do (drag me)',
                'class' : 'info',
                'item'  : [
                    {
                       'id':'task-1',
                        'title':'Try drag me',
                    },
                    {
                       'id':'task-2',
                        'title':'Click me!!',
                    }
                ]
            },
            {
                'id' : '_working',
                'title'  : 'Working',
                'class' : 'warning',
                'item'  : [
                    {
                        'title':'Do Something!',
                    },
                    {
                        'title':'Run?',
                    }
                ]
            },
            {
                'id' : '_done',
                'dragTo' : ['_working'],
                'title'  : 'Done (Drag only in Working)',
                'class' : 'success',
                'item'  : [
                    {
                        'title':'All right',
                    },
                    {
                        'title':'Ok!',
                    }
                ]
            }
        ]
    });

    var toDoButton = document.getElementById('addToDo');
    toDoButton.addEventListener('click',function(){
        KanbanTest.addElement(
            '_todo',
            {
                'title':'Test Add',
            }
        );
    });

    var addBoardDefault = document.getElementById('addDefault');
    addBoardDefault.addEventListener('click', function () {
        KanbanTest.addBoards(
            [{
                'id' : '_default',
                'title'  : 'Default (Can\'t drop in Done)',
                'dragTo':['_todo','_working'],
                'class' : 'error',
                'item'  : [
                    {
                        'title':'Default Item',
                    },
                    {
                        'title':'Default Item 2',
                    },
                    {
                        'title':'Default Item 3',
                    }
                ]
            }]
        )
    });

    var removeBoard = document.getElementById('removeBoard');
    removeBoard.addEventListener('click',function(){
        KanbanTest.removeBoard('_done');
    });
</script>
 -->

  	
	





