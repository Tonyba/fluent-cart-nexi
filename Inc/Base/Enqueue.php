<?php

namespace Inc\Base;

class Enqueue
{


    private $handle = PLUGIN;

    public function register()
    {
        add_action('admin_enqueue_scripts', array($this, 'load_assets'));
    }

    function load_assets()
    {
        /*  wp_enqueue_style(
              $this->handle,
              PLUGIN_URL . '/assets/css/add-auto.css',
              array(),
              '1.5',
              'all'
          );

          wp_register_script(
              $this->handle,
              PLUGIN_URL . 'assets/js/add-auto.js',
              [],
              '1.5',
              true
          );

          wp_enqueue_script('add-vehicles');

          add_filter('script_loader_tag', [$this, 'add_as_module'], 10, 3);*/

    }

    public function add_as_module($tag, $handle, $src)
    {

        if ($this->handle === $handle) {
            $tag = '<script defer type="module" src="' . esc_url($src) . '"></script>';
        }

        return $tag;
    }

}