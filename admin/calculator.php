<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  if (!defined ('IN_ADMIN_PANEL'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  stdhead ('Calculator');
  //_form_header_open_ ('Calculator');
  
  
  
   echo '
  
   <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Calculator
	</div>
	 </div>
		</div>';
  
  
  
  
  
  
  print '' . '
<script type="text/javascript">
// Script © By Martijns Web Hosting \\
// www.martijnswebhosting.tk \\
// Made for Anouksweb.nl \\
function calc(from) {
gb = document.sizes.gb.value; mb = document.sizes.mb.value; kb = document.sizes.kb.value; b = document.sizes.byte.value;
if(from==\'gb\') { document.sizes.mb.value=""+gb+""; document.sizes.mb.value*="1024"; document.sizes.kb.value=""+gb+""; document.sizes.kb.value*="1024"; document.sizes.kb.value*="1024"; document.sizes.byte.value=""+gb+""; document.sizes.byte.value*="1024"; document.sizes.byte.value*="1024"; document.sizes.byte.value*="1024"; }
else if(from==\'mb\') { document.sizes.gb.value=""+mb+""; document.sizes.gb.value/="1024"; document.sizes.kb.value=""+mb+""; document.sizes.kb.value*="1024"; document.sizes.byte.value=""+mb+""; document.sizes.byte.value*="1024"; document.sizes.byte.value*="1024"; }
else if(from==\'kb\') { document.sizes.gb.value=""+kb+""; document.sizes.gb.value/="1024"; document.sizes.gb.value/="1024"; document.sizes.mb.value=""+kb+""; document.sizes.mb.value/="1024"; document.sizes.byte.value=""+kb+""; document.sizes.byte.value*="1024"; }
else if(from==\'byte\') { document.sizes.gb.value=""+b+""; document.sizes.gb.value/="1024"; document.sizes.gb.value/="1024"; document.sizes.gb.value/="1024"; document.sizes.mb.value=""+b+""; document.sizes.mb.value/="1024"; document.sizes.mb.value/="1024"; document.sizes.kb.value=""+b+""; document.sizes.kb.value/="1024"; }
}
</script>

<form name="sizes">


<div class="container mt-3">
<div class="card">
<div class="card-body">


<tr>
<td width="6%" class=none align=right>GB&nbsp;</td>
<td width="20%" class=none>&nbsp<label><input type="text" class="form-control" name="gb"></label></td>
<td width="74%" class=none>&nbsp<input onclick="javascript:calc(\'gb\')" type="button" class="btn btn-primary" value="Calculate From GB "></td>
</tr>
</br>
</br>
<tr>
<td width="6%" class=none align=right>MB&nbsp;</td>
<td width="20%" class=none>&nbsp;<label><input type="text" class="form-control" name="mb"></label></td>
<td width="74%" class=none>&nbsp;<input onclick="javascript:calc(\'mb\')" type="button" class="btn btn-primary" value="Calculate From MB "></td>
</tr>
</br>
</br>
<tr>
<td width="6%" class=none align=right>KB&nbsp;</td>
<td width="20%" class=none>&nbsp;<label><input type="text" class="form-control" name="kb"></label></td>
<td width="74%" class=none>&nbsp;<input onclick="javascript:calc(\'kb\')" type="button" class="btn btn-primary" value="Calculate From KB "></td>
</tr>
</br>
</br>
<tr>
<td width="6%" class=none align=right>Byte&nbsp;</td>
<td width="20%" class=none>&nbsp;<label><input type="text" class="form-control" name="byte"></label></td>
<td width="74%" class=none>&nbsp;<input onclick="javascript:calc(\'byte\')" type="button" class="btn btn-primary" value="Calculate From Byte"></td>
</tr>

</div>
</div>
</div>


</form>';
 
  stdfoot ();
?>
