var ua=navigator.userAgent.toLowerCase();var is_opera=((ua.indexOf('opera')!=-1)||(typeof(window.opera)!='undefined'));var is_saf=((ua.indexOf('applewebkit')!=-1)||(navigator.vendor=='Apple Computer, Inc.'));var is_webtv=(ua.indexOf('webtv')!=-1);var is_ie=((ua.indexOf('msie')!=-1)&&(!is_opera)&&(!is_saf)&&(!is_webtv));var is_ie4=((is_ie)&&(ua.indexOf('msie 4.')!=-1));var is_moz=((!is_saf)&&(navigator.product=='Gecko'));var is_kon=(ua.indexOf('konqueror')!=-1);var is_ns=((ua.indexOf('compatible')==-1)&&(ua.indexOf('mozilla')!=-1)&&(!is_opera)&&(!is_webtv)&&(!is_saf));var is_ns4=((parseInt(navigator.appVersion)==4)&&(is_ns));var is_mac=(ua.indexOf('mac')!=-1);var pointer_cursor=(is_ie?'hand':'pointer');function fetch_object(idname)
{if(document.getElementById)
{return document.getElementById(idname);}
else if(document.all)
{return document.all[idname];}
else if(document.layers)
{return document.layers[idname];}
else
{return null;}}
function fetch_tags(parentobj,tag)
{if(parentobj==null)
{return new Array();}
else if(typeof parentobj.getElementsByTagName!='undefined')
{return parentobj.getElementsByTagName(tag);}
else if(parentobj.all&&parentobj.all.tags)
{return parentobj.all.tags(tag);}
else
{return new Array();}}
function array_push(a,value)
{a[a.length]=value;return a.length;}
function array_pop(a)
{if(typeof a!='object'||!a.length)
{return null;}
else
{var response=a[a.length-1];a.length--;return response;}}
function do_an_e(eventobj)
{if(!eventobj||is_ie)
{window.event.returnValue=false;window.event.cancelBubble=true;return window.event;}
else
{eventobj.stopPropagation();eventobj.preventDefault();return eventobj;}}
function e_by_gum(eventobj)
{if(!eventobj||is_ie)
{window.event.cancelBubble=true;return window.event;}
else
{if(eventobj.target.type=='submit')
{eventobj.target.form.submit();}
eventobj.stopPropagation();return eventobj;}}
function menu_register(controlid,noimage,datefield)
{if(typeof menu=='object')
{return menu.register(controlid,noimage);}}
function Popup_Handler()
{this.open_steps=10;this.open_fade=false;this.active=false;this.menus=new Array();this.activemenu=null;this.hidden_selects=new Array();};Popup_Handler.prototype.activate=function(active)
{this.active=active;};Popup_Handler.prototype.register=function(controlkey,noimage)
{this.menus[controlkey]=new Popup_Menu(controlkey,noimage);return this.menus[controlkey];};Popup_Handler.prototype.hide=function()
{if(this.activemenu!=null)
{this.menus[this.activemenu].hide();}};var menu=new Popup_Handler();function menu_hide(e)
{if(e&&e.button&&e.button!=1&&e.type=='click')
{return true;}
else
{menu.hide();}};function Popup_Menu(controlkey,noimage)
{this.controlkey=controlkey;this.menuname=this.controlkey.split('.')[0]+'_menu';this.init_control(noimage);if(fetch_object(this.menuname))
{this.init_menu();}
this.slide_open=(is_opera?false:true);this.open_steps=menu.open_steps;};Popup_Menu.prototype.init_control=function(noimage)
{this.controlobj=fetch_object(this.controlkey);this.controlobj.state=false;if(this.controlobj.firstChild&&(this.controlobj.firstChild.tagName=='TEXTAREA'||this.controlobj.firstChild.tagName=='INPUT'))
{}
else
{if(!noimage&&!(is_mac&&is_ie))
{var space=document.createTextNode(' ');this.controlobj.appendChild(space);var img=document.createElement('img');img.src=dimagedir+'menu_open.gif';img.border=0;img.title='';img.alt='';this.controlobj.appendChild(img);}
this.controlobj.unselectable=true;if(!noimage)
{this.controlobj.style.cursor=pointer_cursor;}
this.controlobj.onclick=Popup_Events.prototype.controlobj_onclick;this.controlobj.onmouseover=Popup_Events.prototype.controlobj_onmouseover;}};Popup_Menu.prototype.init_menu=function()
{this.menuobj=fetch_object(this.menuname);if(this.menuobj&&!this.menuobj.initialized)
{this.menuobj.initialized=true;this.menuobj.onclick=e_by_gum;this.menuobj.style.position='absolute';this.menuobj.style.zIndex=50;if(is_ie&&!is_mac)
{this.menuobj.style.filter+="progid:DXImageTransform.Microsoft.alpha(enabled=1,opacity=100)";this.menuobj.style.filter+="progid:DXImageTransform.Microsoft.shadow(direction=135,color=#8E8E8E,strength=3)";}
this.init_menu_contents();}};Popup_Menu.prototype.init_menu_contents=function()
{var tds=fetch_tags(this.menuobj,'td');for(var i=0;i<tds.length;i++)
{if(tds[i].className=='menu_option')
{if(tds[i].title&&tds[i].title=='nohilite')
{tds[i].title='';}
else
{tds[i].controlkey=this.controlkey;tds[i].onmouseover=Popup_Events.prototype.menuoption_onmouseover;tds[i].onmouseout=Popup_Events.prototype.menuoption_onmouseout;var links=fetch_tags(tds[i],'a');if(links.length==1)
{tds[i].className=tds[i].className+' menu_option_alink';tds[i].islink=true;var linkobj=links[0];var remove_link=false;tds[i].target=linkobj.getAttribute('target');if(typeof linkobj.onclick=='function')
{tds[i].ofunc=linkobj.onclick;tds[i].onclick=Popup_Events.prototype.menuoption_onclick_function;remove_link=true;}
else if(typeof tds[i].onclick=='function')
{tds[i].ofunc=tds[i].onclick;tds[i].onclick=Popup_Events.prototype.menuoption_onclick_function;remove_link=true;}
else
{tds[i].href=linkobj.href;tds[i].onclick=Popup_Events.prototype.menuoption_onclick_link;}
if(remove_link)
{var myspan=document.createElement('span');myspan.innerHTML=linkobj.innerHTML;tds[i].insertBefore(myspan,linkobj);tds[i].removeChild(linkobj);}}
else if(typeof tds[i].onclick=='function')
{tds[i].ofunc=tds[i].onclick;tds[i].onclick=Popup_Events.prototype.menuoption_onclick_function;}}}}};Popup_Menu.prototype.show=function(obj,instant)
{if(!menu.active)
{return false;}
else if(!this.menuobj)
{this.init_menu();}
if(!this.menuobj)
{return false;}
if(menu.activemenu!=null)
{menu.menus[menu.activemenu].hide();}
menu.activemenu=this.controlkey;this.menuobj.style.display='';if(this.slide_open)
{this.menuobj.style.clip='rect(auto, 0px, 0px, auto)';}
this.pos=this.fetch_offset(obj);this.leftpx=this.pos['left'];this.toppx=this.pos['top']+obj.offsetHeight;if((this.leftpx+this.menuobj.offsetWidth)>=document.body.clientWidth&&(this.leftpx+obj.offsetWidth-this.menuobj.offsetWidth)>0)
{this.leftpx=this.leftpx+obj.offsetWidth-this.menuobj.offsetWidth;this.direction='right';}
else
{this.direction='left'}
this.menuobj.style.left=this.leftpx+'px';this.menuobj.style.top=this.toppx+'px';if(!instant&&this.slide_open)
{this.intervalX=Math.ceil(this.menuobj.offsetWidth/this.open_steps);this.intervalY=Math.ceil(this.menuobj.offsetHeight/this.open_steps);this.slide((this.direction=='left'?0:this.menuobj.offsetWidth),0,0);}
else if(this.menuobj.style.clip&&this.slide_open)
{this.menuobj.style.clip='rect(auto, auto, auto, auto)';}
this.handle_overlaps(true);if(this.controlobj.editorid)
{this.controlobj.state=true;Editor[this.controlobj.editorid].menu_context(this.controlobj,'mousedown');}};Popup_Menu.prototype.hide=function(e)
{if(e&&e.button&&e.button!=1)
{return true;}
this.stop_slide();this.menuobj.style.display='none';this.handle_overlaps(false);if(this.controlobj.editorid)
{this.controlobj.state=false;Editor[this.controlobj.editorid].menu_context(this.controlobj,'mouseout');}
menu.activemenu=null;};Popup_Menu.prototype.hover=function(obj)
{if(menu.activemenu!=null)
{if(menu.menus[menu.activemenu].controlkey!=this.id)
{this.show(obj,true);}}};Popup_Menu.prototype.slide=function(clipX,clipY,opacity)
{if(this.direction=='left'&&(clipX<this.menuobj.offsetWidth||clipY<this.menuobj.offsetHeight))
{if(menu.open_fade&&is_ie)
{opacity+=10;this.menuobj.filters.item('DXImageTransform.Microsoft.alpha').opacity=opacity;}
clipX+=this.intervalX;clipY+=this.intervalY;this.menuobj.style.clip="rect(auto, "+clipX+"px, "+clipY+"px, auto)";this.slidetimer=setTimeout("menu.menus[menu.activemenu].slide("+clipX+", "+clipY+", "+opacity+");",0);}
else if(this.direction=='right'&&(clipX>0||clipY<this.menuobj.offsetHeight))
{if(menu.open_fade&&is_ie)
{opacity+=10;menuobj.filters.item('DXImageTransform.Microsoft.alpha').opacity=opacity;}
clipX-=this.intervalX;clipY+=this.intervalY;this.menuobj.style.clip="rect(auto, "+this.menuobj.offsetWidth+"px, "+clipY+"px, "+clipX+"px)";this.slidetimer=setTimeout("menu.menus[menu.activemenu].slide("+clipX+", "+clipY+", "+opacity+");",0);}
else
{this.stop_slide();}};Popup_Menu.prototype.stop_slide=function()
{clearTimeout(this.slidetimer);this.menuobj.style.clip='rect(auto, auto, auto, auto)';if(menu.open_fade&&is_ie)
{this.menuobj.filters.item('DXImageTransform.Microsoft.alpha').opacity=100;}};Popup_Menu.prototype.fetch_offset=function(obj)
{var left_offset=obj.offsetLeft;var top_offset=obj.offsetTop;while((obj=obj.offsetParent)!=null)
{left_offset+=obj.offsetLeft;top_offset+=obj.offsetTop;}
return{'left':left_offset,'top':top_offset};};Popup_Menu.prototype.overlaps=function(obj,m)
{var s=new Array();var pos=this.fetch_offset(obj);s['L']=pos['left'];s['T']=pos['top'];s['R']=s['L']+obj.offsetWidth;s['B']=s['T']+obj.offsetHeight;if(s['L']>m['R']||s['R']<m['L']||s['T']>m['B']||s['B']<m['T'])
{return false;}
return true;};Popup_Menu.prototype.handle_overlaps=function(dohide)
{if(is_ie)
{var selects=fetch_tags(document,'select');if(dohide)
{var menuarea=new Array();menuarea={'L':this.leftpx,'R':this.leftpx+this.menuobj.offsetWidth,'T':this.toppx,'B':this.toppx+this.menuobj.offsetHeight};for(var i=0;i<selects.length;i++)
{if(this.overlaps(selects[i],menuarea))
{var hide=true;var s=selects[i];while(s=s.parentNode)
{if(s.className=='menu_popup')
{hide=false;break;}}
if(hide)
{selects[i].style.visibility='hidden';array_push(menu.hidden_selects,i);}}}}
else
{while(true)
{var i=array_pop(menu.hidden_selects);if(typeof i=='undefined'||i==null)
{break;}
else
{selects[i].style.visibility='visible';}}}}};function Popup_Events()
{}
Popup_Events.prototype.controlobj_onclick=function(e)
{if(typeof do_an_e=='function')
{do_an_e(e);if(menu.activemenu==null||menu.menus[menu.activemenu].controlkey!=this.id)
{menu.menus[this.id].show(this);}
else
{menu.menus[this.id].hide();}}};Popup_Events.prototype.controlobj_onmouseover=function(e)
{if(typeof do_an_e=='function')
{do_an_e(e);menu.menus[this.id].hover(this);}};Popup_Events.prototype.menuoption_onclick_function=function(e)
{this.ofunc(e);menu.menus[this.controlkey].hide();};Popup_Events.prototype.menuoption_onclick_link=function(e)
{e=e?e:window.event;if(e.shiftKey||(this.target!=null&&this.target!=''&&this.target.toLowerCase()!='_self'))
{if(this.target!=null&&this.target.charAt(0)!='_')
{window.open(this.href,this.target);}
else
{window.open(this.href);}}
else
{window.location=this.href;}
e.cancelBubble=true;if(e.stopPropagation)e.stopPropagation();if(e.preventDefault)e.preventDefault();menu.menus[this.controlkey].hide();return false;};Popup_Events.prototype.menuoption_onmouseover=function(e)
{this.className='menu_hilite'+(this.islink?' menu_hilite_alink':'');this.style.cursor=pointer_cursor;};Popup_Events.prototype.menuoption_onmouseout=function(e)
{this.className='menu_option'+(this.islink?' menu_option_alink':'');this.style.cursor='default';};if(typeof menu=='object')
{if(window.attachEvent&&!is_saf)
{document.attachEvent('onclick',menu_hide);window.attachEvent('onresize',menu_hide);}
else if(document.addEventListener&&!is_saf)
{document.addEventListener('click',menu_hide,false);window.addEventListener('resize',menu_hide,false);}
else
{window.onclick=menu_hide;window.onresize=menu_hide;}}