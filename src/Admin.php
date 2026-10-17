<?php

namespace Xyo\MostRead;

class Admin
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'admin_menu'));
    }

    public function admin_menu()
    {
        #add_submenu_page('plugins.php', 'Most Read Posts :: View', 'Most Read Posts', 'manage_options', 'most_read_options', array($this, 'plugin_options'));
    }

    public function _init()
    {
    }

    public function getStatBy($type = null)
    {
    }
}
