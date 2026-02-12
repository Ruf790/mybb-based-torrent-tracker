<?php
declare(strict_types=1);



class DefaultPage
{
    /**
     * The current style in use.
     */
    public string $style = '';

    /**
     * The primary menu items.
     */
    public array $menu = [];

    /**
     * The side bar menu items.
     */
    public string $submenu = '';

    /**
     * The module we're currently in.
     */
    public ?string $active_module = null;

    /**
     * The action we're currently performing.
     */
    public ?string $active_action = null;

    /**
     * Content for the side bar of the page if we have one.
     */
    public ?string $sidebar = null;

    /**
     * The breadcrumb trail leading up to this page.
     */
    protected array $_breadcrumb_trail = [];

    /**
     * Any additional information to add between the <head> tags.
     */
    public string $extra_header = '';

    /**
     * Any additional messages to add after the flash messages are shown.
     */
    public array $extra_messages = [];

    /**
     * Show a post verify error.
     */
    public string $show_post_verify_error = '';

    /**
     * Menu HTML
     */
    public ?string $_menu = null;

    /**
     * Add an item to the page breadcrumb trail.
     */
    public function add_breadcrumb_item(string $name, string $url = ''): void
    {
        $this->_breadcrumb_trail[] = [
            'name' => $name,
            'url'  => $url
        ];
    }

    /**
     * Generate a breadcrumb trail.
     */
    public function _generate_breadcrumb(): string|false
    {
        if (!is_array($this->_breadcrumb_trail)) {
            return false;
        }

        $trail = '';

        foreach ($this->_breadcrumb_trail as $key => $crumb) {
            if (isset($this->_breadcrumb_trail[$key + 1])) {
                $trail .= '<a href="' . $crumb['url'] . '">' . $crumb['name'] . '</a>';

                if (isset($this->_breadcrumb_trail[$key + 2])) {
                    $trail .= ' &raquo; ';
                }
            } else {
                $trail .= '<span class="active">' . $crumb['name'] . '</span>';
            }
        }

        return $trail;
    }

    /**
     * Output a Javascript based tab control on to the page.
     */
    public function output_tab_control(
        array $tabs = [],
        bool $observe_onload = true,
        string $id = 'tabs'
    ): void {
        global $plugins;

        $tabs = $plugins->run_hooks(
            'admin_page_output_tab_control_start',
            $tabs
        );

        echo '<div class="container mt-3"><ul class="tabs" id="' . $id . '">' . PHP_EOL;

        $tab_count = count($tabs);
        $done = 1;

        foreach ($tabs as $anchor => $title) {
            $class = '';

            if ($tab_count === $done) {
                $class .= ' last';
            }

            if ($done === 1) {
                $class .= ' first';
            }

            $done++;

            echo '<li class="' . trim($class) . '">';
            echo '<a href="#tab_' . $anchor . '">' . $title . '</a>';
            echo '</li>' . PHP_EOL;
        }

        echo '</ul></div>' . PHP_EOL;

        $plugins->run_hooks(
            'admin_page_output_tab_control_end',
            $tabs
        );
    }

    /**
     * Output a page asking if a user wishes to continue performing a specific action.
     */
    public function output_confirm_action(
        string $url,
        string $message = '',
        string $title = ''
    ): void {
        global $lang, $plugins;

        $args = [
            'this'    => $this,
            'url'     => $url,
            'message' => $message,
            'title'   => $title,
        ];

        $plugins->run_hooks('admin_page_output_confirm_action', $args);

        if ($message === '') {
            $message = $lang->confirm_action;
        }

        //$this->output_header($title);

        $form = new Form($url, 'post');

        echo '<div class="confirm_action">';
        echo '<p>' . $message . '</p>';
        echo '<br />';
        echo '<p class="buttons">';
        echo $form->generate_submit_button($lang->yes, ['class' => 'button_yes']);
        echo $form->generate_submit_button($lang->no, [
            'name'  => 'no',
            'class' => 'button_no'
        ]);
        echo '</p>';
        echo '</div>';

        $form->end();
    }
}
