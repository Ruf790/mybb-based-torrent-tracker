function check(field)
{
	if(checkflag=="false")
	{
		for(i=0;i<field.length;i++)
		{
			field[i].checked=true;
		}
		checkflag="true";
		return l_uncheckall;
	}
	else
	{
		for(i=0;i<field.length;i++)
		{
			field[i].checked=false;
		}
		checkflag="false";
		return l_checkall;
	}
}

function log_out()
{
	ht=document.getElementsByTagName("html");
	ht[0].style.filter="progid:DXImageTransform.Microsoft.BasicImage(grayscale=1)";
	if(confirm(l_logout))
	{
		return true;
	}
	else
	{
		ht[0].style.filter="";
		return false;
	}
}

function jumpto(url,message)
{
	if(typeof message!="undefined" && TSGetID("jumpto"))
	{
		TSGetID("jumpto").style.display="block";
	}
	window.location=url;
}

function highlight(field)
{
	field.focus();
	field.select();
}

function select_deselectAll(formname,elm,group)
{
	var frm=document.forms[formname];
	for(i=0;i<frm.length;i++)
	{
		if(elm.attributes['checkall'] != null && elm.attributes['checkall'].value == group)
		{
			if(frm.elements[i].attributes['checkme'] != null && frm.elements[i].attributes['checkme'].value == group)
			{
				frm.elements[i].checked=elm.checked;
			}
		}
		else if(frm.elements[i].attributes['checkme'] != null && frm.elements[i].attributes['checkme'].value == group)
		{
			if(frm.elements[i].checked == false)
			{
				frm.elements[1].checked = false;
			}
		}
	}
}

function ts_show(where)
{
	TSGetID(where).style.display='block';
}

function ts_hide(where)
{
	TSGetID(where).style.display='none';
}

function ts_open_popup(desktopURL,alternateWidth,alternateHeight,noScrollbars)
{
	if((alternateWidth&&self.screen.availWidth*0.8<alternateWidth)||(alternateHeight&&self.screen.availHeight*0.8<alternateHeight))
	{
		noScrollbars=false;
		alternateWidth=Math.min(alternateWidth,self.screen.availWidth*0.8);
		alternateHeight=Math.min(alternateHeight,self.screen.availHeight*0.8);
	}
	else
	noScrollbars=typeof(noScrollbars)!="undefined"&&noScrollbars==true;window.open(desktopURL,'ts_requested_popup','toolbar=no,location=no,status=no,menubar=no,scrollbars='+(noScrollbars?'no':'yes')+',width='+(alternateWidth?alternateWidth:480)+',height='+(alternateHeight?alternateHeight:220)+',resizable=no');return false;
}

function TSGetID(IDName)
{
	if(document.getElementById)
	{
		return document.getElementById(IDName);
	}
	else if(document.all)
	{
		return document.all[IDName];
	}
	else if(document.layers)
	{
		return document.layers[IDName];
	}
	else
	{
		return null;
	}
}

function TSGoToPage(WhereToGo,ExTra)
{
	if((pagenum=parseInt(TSGetID('Page_Number').value,10))>0)
	{
		window.location=WhereToGo+'page='+pagenum+ExTra;
	} 
	else if((pagenum=parseInt(TSGetID('Page_Number2').value,10))>0)
	{
		window.location=WhereToGo+'page='+pagenum+ExTra;
	}
	return false;
}

var checkflag="false";
window.status="Powered by TS Special Edition v5.6";