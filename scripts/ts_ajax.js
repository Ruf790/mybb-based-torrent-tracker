var http_request=false;function ts_ajax_post(url,parameters)
{ts_setloading('block');http_request=false;if(window.XMLHttpRequest)
{http_request=new XMLHttpRequest();if(http_request.overrideMimeType)
{http_request.overrideMimeType('text/html');}}
else if(window.ActiveXObject)
{try
{http_request=new ActiveXObject("Msxml2.XMLHTTP");}
catch(e)
{try
{http_request=new ActiveXObject("Microsoft.XMLHTTP");}
catch(e)
{}}}
if(!http_request)
{alert(l_ajaxerror2);return false;}
http_request.onreadystatechange=ts_alertContents;http_request.open('POST',url,true);http_request.setRequestHeader("Content-type","application/x-www-form-urlencoded");http_request.setRequestHeader("Content-length",parameters.length);http_request.setRequestHeader("Connection","close");http_request.send(parameters);};function ts_setValue(printzone,loadingzone)
{window.PreviewValue=printzone;window.LoadingValue=loadingzone;};function ts_alertContents()
{if(http_request.readyState==4)
{if(http_request.status==200)
{result=http_request.responseText;ts_setpreview(result);ts_setloading('none');}
else
{alert(l_ajaxerror);}}};function ts_get(obj,Fvalue,filename,printzone,loadingzone)
{ts_setValue(printzone,loadingzone);var postData=document.getElementById(obj).value;var poststr=Fvalue+"="+encodeURIComponent(postData);ts_ajax_post(baseurl+'/'+filename,poststr);};function ts_setloading(what)
{document.getElementById(LoadingValue).style.display=what;};function ts_setpreview(what)
{document.getElementById(PreviewValue).innerHTML=what;};