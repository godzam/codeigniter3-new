<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Renders a view inside one of the layouts in application/views/layouts/
 * (public, auth, admin), so pages only contain their own content:
 *
 *   $this->template->render('index', ['items' => $items], 'admin');
 *
 * Inside a module, 'index' resolves to that module's view first. Other
 * options, set before render():
 *
 *   $this->template->set_title('Users');
 *   $this->template->set_breadcrumbs(['Admin' => 'admin', 'Users' => null]);
 *   $this->template->add_head('<link rel="stylesheet" href="...">');
 *   $this->template->add_foot('<script src="..."></script>');
 */
class Template
{
    protected $CI;

    protected $layout = 'public';

    protected $vars = [
        'page_title' => '',
        'breadcrumbs' => [],
        'extra_head' => '',
        'extra_foot' => '',
    ];

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function set_layout($layout)
    {
        $this->layout = $layout;

        return $this;
    }

    public function set_title($title)
    {
        $this->vars['page_title'] = (string) $title;

        return $this;
    }

    /**
     * @param array<string, string|null> $crumbs label => url (null for the current page)
     */
    public function set_breadcrumbs(array $crumbs)
    {
        $this->vars['breadcrumbs'] = $crumbs;

        return $this;
    }

    public function add_head($html)
    {
        $this->vars['extra_head'] .= $html."\n";

        return $this;
    }

    public function add_foot($html)
    {
        $this->vars['extra_foot'] .= $html."\n";

        return $this;
    }

    /**
     * Renders $view into the layout and sends it to the browser.
     *
     * @param array<string, mixed> $data
     */
    public function render($view, array $data = [], $layout = null)
    {
        $this->CI->load->view('layouts/'.($layout ?: $this->layout), $this->build($view, $data));
    }

    /**
     * Same as render() but returns the HTML instead of sending it.
     *
     * @param array<string, mixed> $data
     */
    public function fetch($view, array $data = [], $layout = null)
    {
        return $this->CI->load->view('layouts/'.($layout ?: $this->layout), $this->build($view, $data), true);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function build($view, array $data)
    {
        $module = isset($this->CI->router) && method_exists($this->CI->router, 'fetch_module')
            ? $this->CI->router->fetch_module()
            : '';

        if ($module && strpos($view, '/') === false) {
            $view = $module.'/'.$view;
        }

        return $this->vars + ['content' => $this->CI->load->view($view, $data, true)];
    }
}
