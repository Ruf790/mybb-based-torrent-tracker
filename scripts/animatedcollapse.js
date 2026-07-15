var uniquepageid=window.location.href.replace("http://"+window.location.hostname,"").replace(/^\//,"")
function animatedcollapse(divId,animatetime,persistexpand,initstate){this.divId=divId
this.divObj=document.getElementById(divId)
this.divObj.style.overflow="hidden"
this.timelength=animatetime
this.initstate=(typeof initstate!="undefined"&&initstate=="block")?"block":"contract"
this.isExpanded=animatedcollapse.getCookie(uniquepageid+"-"+divId)
this.contentheight=parseInt(this.divObj.style.height)
var thisobj=this
if(isNaN(this.contentheight)){animatedcollapse.dotask(window,function(){thisobj._getheight(persistexpand)},"load")
if(!persistexpand&&this.initstate=="contract"||persistexpand&&this.isExpanded!="yes")
this.divObj.style.visibility="hidden"}
else if(!persistexpand&&this.initstate=="contract"||persistexpand&&this.isExpanded!="yes")
this.divObj.style.height=0
if(persistexpand)
animatedcollapse.dotask(window,function(){animatedcollapse.setCookie(uniquepageid+"-"+thisobj.divId,thisobj.isExpanded)},"unload")}
animatedcollapse.prototype._getheight=function(persistexpand){this.contentheight=this.divObj.offsetHeight
if(!persistexpand&&this.initstate=="contract"||persistexpand&&this.isExpanded!="yes"){this.divObj.style.height=0
this.divObj.style.visibility="visible"}
else
this.divObj.style.height=this.contentheight+"px"}
animatedcollapse.prototype._slideengine=function(direction){var elapsed=new Date().getTime()-this.startTime
var thisobj=this
if(elapsed<this.timelength){var distancepercent=(direction=="down")?animatedcollapse.curveincrement(elapsed/this.timelength):1-animatedcollapse.curveincrement(elapsed/this.timelength)
this.divObj.style.height=distancepercent*this.contentheight+"px"
this.runtimer=setTimeout(function(){thisobj._slideengine(direction)},10)}
else{this.divObj.style.height=(direction=="down")?this.contentheight+"px":0
this.isExpanded=(direction=="down")?"yes":"no"
this.runtimer=null}}
animatedcollapse.prototype.slidedown=function(){if(typeof this.runtimer=="undefined"||this.runtimer==null){if(isNaN(this.contentheight))
alert("Please wait until document has fully loaded then click again")
else if(parseInt(this.divObj.style.height)==0){this.startTime=new Date().getTime()
this._slideengine("down")}}}
animatedcollapse.prototype.slideup=function(){if(typeof this.runtimer=="undefined"||this.runtimer==null){if(isNaN(this.contentheight))
alert("Please wait until document has fully loaded then click again")
else if(parseInt(this.divObj.style.height)==this.contentheight){this.startTime=new Date().getTime()
this._slideengine("up")}}}
animatedcollapse.prototype.slideit=function(){if(isNaN(this.contentheight))
alert("Please wait until document has fully loaded then click again")
else if(parseInt(this.divObj.style.height)==0)
this.slidedown()
else if(parseInt(this.divObj.style.height)==this.contentheight)
this.slideup()}
animatedcollapse.curveincrement=function(percent){return(1-Math.cos(percent*Math.PI))/2}
animatedcollapse.dotask=function(target,functionref,tasktype){var tasktype=(window.addEventListener)?tasktype:"on"+tasktype
if(target.addEventListener)
target.addEventListener(tasktype,functionref,false)
else if(target.attachEvent)
target.attachEvent(tasktype,functionref)}
animatedcollapse.getCookie=function(Name){var re=new RegExp(Name+"=[^;]+","i");if(document.cookie.match(re))
return document.cookie.match(re)[0].split("=")[1]
return""}
animatedcollapse.setCookie=function(name,value,days){if(typeof days!="undefined"){var expireDate=new Date()
var expstring=expireDate.setDate(expireDate.getDate()+days)
document.cookie=name+"="+value+"; expires="+expireDate.toGMTString()}
else
document.cookie=name+"="+value}