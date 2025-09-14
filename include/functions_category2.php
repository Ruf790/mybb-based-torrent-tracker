<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.2 & 7.3
 * @ Decoder version: 1.0.6
 * @ Release: 10/08/2022
 */

if (!defined("IN_TRACKER")) 
{
    exit("<font face='verdana' size='2' color='darkred'><b>Error!</b> Direct initialization of this file is not allowed.</font>");
}


function ts_category_list2($type = 1, $formname = "usercp")
{
    global $usergroups, $CURUSER, $cache, $_categoriesS, $_categoriesC;

    if (!is_array($_categoriesS) || count($_categoriesS) == 0 || !is_array($_categoriesC) || count($_categoriesC) == 0) 
	{
        require TSDIR . "/cache/categories.php";
    }

    $subcategories = [];

    // Format subcategories
    if (is_array($_categoriesS) && count($_categoriesS) > 0) 
	{
        foreach ($_categoriesS as $scquery) 
		{
            $checked = (strpos($CURUSER["notifs"], "[cat" . $scquery["id"] . "]") !== false) ? "checked" : "";
            $inputName = $type == 1 ? "cat" . $scquery["id"] : "cat[]";
            $inputValue = $type == 1 ? "yes" : $scquery["id"];

            $subcategories[$scquery["pid"]][] = '
                <div class="form-check ms-4">
                    <input class="form-check-input" type="checkbox" value="' . $inputValue . '" name="' . $inputName . '" checkme="group' . $scquery["pid"] . '" ' . $checked . '>
                    <label class="form-check-label" style="font-size: 0.9rem;">' . htmlspecialchars($scquery["name"]) . '</label>
                </div>';
        }
    }

    $showcategories = '
    <div class="container my-3">
        <div class="card">
			<div class="card-header fw-bold text-primary">RSS Feeds Categories to retrieve:</div>
            <div class="card-body">
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">';

    // Format main categories
    if (is_array($_categoriesC) && count($_categoriesC) > 0) 
	{
        foreach ($_categoriesC as $mcquery) 
		{
            $checked = (strpos($CURUSER["notifs"], "[cat" . $mcquery["id"] . "]") !== false) ? "checked" : "";

            $showcategories .= '
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="cat' . $mcquery["id"] . '" value="yes" ' . $checked . ' checkall="group' . $mcquery["id"] . '" onclick="return select_deselectAll(\'' . $formname . '\', this, \'group' . $mcquery["id"] . '\');">
                            <label class="form-check-label fw-bold">' . htmlspecialchars($mcquery["name"]) . '</label>
                        </div>';

            // Add subcategories if available
            if (isset($subcategories[$mcquery["id"]])) 
			{
                foreach ($subcategories[$mcquery["id"]] as $subcatHtml) 
				{
                    $showcategories .= $subcatHtml;
                }
            }

            $showcategories .= '</div>';
        }
    }

    $showcategories .= '
                </div>
            </div>
        </div>
    </div>';

    return $showcategories;
}

	


?>