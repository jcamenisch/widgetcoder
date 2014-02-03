<?php

/**
 * @package Widgetcoder
 * @version 0.1
 */
/*
Plugin Name: Widgetcoder
Plugin URI: http://wordpress.org/plugins/widgetcoder/
Description: Create custom shortcodes with *zero* boilerplate. Just one <shortcodename>.phtml per shortcode widget.
Author: Jonathan Camenisch
Version: 0.1
Author URI: https://github.com/jcamenisch
*/

class Widgetcoder {
  public function __construct($name, $arguments) {
    $this->name = $name;
    $this->arguments = is_array($arguments[0]) ? $arguments[0] : array();
  }

  public static $shortcode_dir, $shortcode_url, $short_code_runner;

  public static function init() {
    self::$shortcode_dir     = ABSPATH . '/shortcodes/';
    self::$shortcode_url     = site_url() . '/shortcodes/';
    self::$short_code_runner = new Widgetcoder_Runner;

     /**
     * For every .phtml file in the shortcode directory, add a shortcode that runs
     * Widgetcoder::[filename without the extension].
     *
     * When the shortcode is called, the Widgetcoder::__callStatic function will be run
     * with the name of the shortcode, and any attributes.
     */
    foreach (scandir(self::$shortcode_dir) as $shortcode_file) {
      if (preg_match("/(.+)\.phtml/", $shortcode_file, $matches)) {
        $shortcode_name = $matches[1];
        add_shortcode($shortcode_name, array(self::$short_code_runner, $shortcode_name));
      }
    }
  }

  public function template() {
    return $this->_template ? $this->_template : $this->_template = $this->name . '.phtml';
  }

  public function post_url() {
    return '';
  }

  private function template_locals($name = null) {
    $ret = array();

    foreach($this->arguments as $key => $value) {
      if ((string)(int)$value == $value) {
        $value = (int)$value;
      }
      if (is_int($key)) {
        /* First, transform nameless attributes: */
        if (!isset($ret['id']) && is_int($value)) {
          $key = 'id';
        } elseif (preg_match('/^no_/', $value)) {
          $key   = preg_replace('/^no_/', '', $value);
          $value = false;
        } else {
          $key = $value;
          $value = true;
        }
      } else {
        /* Then named attributes: */
        if (preg_match('/^(on|yes|true)$/i', $value)) {
          $value = true;
        } elseif (preg_match('/^(off|no|false)$/i', $value)) {
          $value = false;
        }
      }

      $ret[$key] = $value;
    }

    if (!is_null($name)) return isset($ret[$name]) ? $ret[$name] : null;
    else                 return $ret;
  }

  private function before_render() {
    $before_render_file = self::$shortcode_dir . str_replace('.phtml', '_before_render.php', $this->template());

    if (file_exists($before_render_file)) {
      include($before_render_file);
      unset($before_render_file);
      return get_defined_vars();
    } else {
      return array();
    }
  }

  public function maybe_enqueue_js($for_file, $deps = array('baddger-baddger'), $ver = false, $in_footer = true) {
    if (is_admin()) $in_footer = false;
    $js_file = str_replace('.phtml', '.js', $for_file);

    if (file_exists(self::$shortcode_dir . $js_file)) {
      $url = self::$shortcode_url . $js_file;
      $label = preg_replace('/\.js/', '', str_replace('/', '-', $js_file));

      wp_register_script($label, $url, $deps, $ver, $in_footer);
      wp_enqueue_script($label);
    }
  }

  public function render() {
    try {
      $this->maybe_enqueue_js($this->template());

      extract($this->template_locals());
      extract($this->before_render());

      require (self::$shortcode_dir . $this->template());
    } catch (Widgetcoder_RenderingException $e) {
      ?>
        <div class="error">
          <h2>Failed to render shortcode <?php echo $this->name; ?></h2>
          <p><?php echo $e->getMessage(); ?></p>
        </div>
      <?php
    }

    if ($this->template_locals('debug')) echo to_pre(get_defined_vars(), 'Variables local to template:');
  }
}

class Widgetcoder_RenderingException extends Exception {}

class Widgetcoder_Runner {
  public function __call($name, $arguments) {
    $widgetcoder = new Widgetcoder($name, $arguments);

    ob_start();
    $widgetcoder->render();
    return ob_get_clean();
  }
}

Widgetcoder::init();
