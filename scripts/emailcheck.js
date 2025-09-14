function checkEmail(myForm){if(!isValidEmail(myForm.email.value))
{alert(l_wrongemail)
return false;}else{return true;}}
function isValidEmail(email,required){if(required==undefined){required=true;}
if(email==null){if(required){return false;}
return true;}
if(email.length==0){if(required){return false;}
return true;}
if(!allValidChars(email)){return false;}
if(email.indexOf("@")<1){return false;}else if(email.lastIndexOf(".")<=email.indexOf("@")){return false;}else if(email.indexOf("@")==email.length){return false;}else if(email.indexOf("..")>=0){return false;}else if(email.indexOf(".")==email.length){return false;}
return true;}
function allValidChars(email){var parsed=true;var validchars="abcdefghijklmnopqrstuvwxyz0123456789@.-_";for(var i=0;i<email.length;i++){var letter=email.charAt(i).toLowerCase();if(validchars.indexOf(letter)!=-1)
continue;parsed=false;break;}
return parsed;}