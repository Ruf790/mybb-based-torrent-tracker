function doBlink(){var blink=document.all.tags("BLINK")
for(var i=0;i<blink.length;i++)
blink[i].style.visibility=blink[i].style.visibility==""?"hidden":""}
function startBlink(){if(document.all)
setInterval("doBlink()",800)}
window.onload=startBlink;function rnd(scale){var dd=new Date();return((Math.round(Math.abs(Math.sin(dd.getTime()))*1000000000)%scale));}