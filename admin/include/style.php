<?php
declare(strict_types=1);

/**
 * Admin CP Style Override File
 * 
 * This file allows overriding default layout generation classes
 * to customize Admin CP appearance beyond CSS styling.
 */

// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

/**
 * Custom Page class overriding breadcrumb generation
 */
#[AllowDynamicProperties]
class Page extends DefaultPage
{
    /**
     * Generate breadcrumb navigation trail
     * 
     * @return string|false Breadcrumb HTML or false on error
     */
    public function _generate_breadcrumb(): string|false
    {
        if (!is_array($this->_breadcrumb_trail) || empty($this->_breadcrumb_trail)) {
            return false;
        }

        $trailParts = [];
        $totalItems = count($this->_breadcrumb_trail);

        foreach ($this->_breadcrumb_trail as $index => $crumb) {
            $isLastItem = ($index === $totalItems - 1);
            $crumbName = htmlspecialchars((string)($crumb['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            
            if (!$isLastItem) {
                $crumbUrl = htmlspecialchars((string)($crumb['url'] ?? ''), ENT_QUOTES, 'UTF-8');
                $trailParts[] = sprintf('<a href="%s">%s</a>', $crumbUrl, $crumbName);
            } else {
                $trailParts[] = sprintf('<span class="active">%s</span>', $crumbName);
            }
        }

        return implode(' &raquo; ', $trailParts);
    }
}

/**
 * Sidebar item class
 */
//#[AllowDynamicProperties]
//class SidebarItem extends DefaultSidebarItem {}

/**
 * Popup menu class
 */
//#[AllowDynamicProperties]
//class PopupMenu extends DefaultPopupMenu {}

/**
 * Table class
 */
#[AllowDynamicProperties]
class Table extends DefaultTable {}

/**
 * Form class
 */
#[AllowDynamicProperties]
class Form extends DefaultForm {}

/**
 * Form container class
 */
#[AllowDynamicProperties]
class FormContainer extends DefaultFormContainer {}
