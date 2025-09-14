<?
/***********************************************/
/*                                             */
/*    E-mail          : mrdecoder@hotmail.com  */
/*                                             */
/*              FearlesS-Releases              */
/*             One Name, One Legend            */
/*                                             */
/***********************************************/


if (!defined("IN_TRACKER")) 
{
    exit("<font face='verdana' size='2' color='darkred'><b>Error!</b> Direct initialization of this file is not allowed.</font>");
}
function ts_category_list($selectname = "type", $selected = 0, $extra = "", $style = "")
{
    global $usergroups;
    global $cache;
    global $_categoriesS;
    global $_categoriesC;
    global $CURUSER;
    $subcategoriesss = array ();
    if (!is_array($_categoriesS) || count($_categoriesS) == 0 || !is_array($_categoriesC) || count($_categoriesC) == 0) 
	{
        require TSDIR . "/cache/categories.php";
    }
    if (is_array($_categoriesS) && 0 < count($_categoriesS)) 
	{
        foreach ($_categoriesS as $scquery) 
		{
            
			
                $subcategoriesss[$scquery["pid"]] = (isset($subcategoriesss[$scquery["pid"]]) ? $subcategoriesss[$scquery["pid"]] : "") . "<option value=\"" . $scquery["id"] . "\"" . ($scquery["id"] == $selected ? " selected=\"selected\"" : "") . ">&nbsp;&nbsp;|-- " . $scquery["name"] . "</option>";
           
        }
    }
    $showcategories = '
	
	
	<label>
	<select class="form-select form-select-sm border pe-5 w-auto" id="'.$style.'" name="'.$selectname.'">
	
	
	
	' . $extra;
    if (is_array($_categoriesC) && 0 < count($_categoriesC)) 
	{
        foreach ($_categoriesC as $mcquery) 
		{
            
                $showcategories .= "
				
				<option value=\"" . $mcquery["id"] . "\"" . ($mcquery["id"] == $selected ? " selected=\"selected\"" : "") . ">" . $mcquery["name"] . "</option>
				
				" . (isset($subcategoriesss[$mcquery["id"]]) ? $subcategoriesss[$mcquery["id"]] : "") . "";
          
        }
    }
    $showcategories .= "</select></label>";
    return $showcategories;
}

?>