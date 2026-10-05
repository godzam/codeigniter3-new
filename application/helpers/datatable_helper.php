<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('datatable_assets')) {
    /**
     * Queues jQuery + DataTables (with Bootstrap 5 styling and the Responsive
     * extension) for the page. Called by datatable_table(); safe to call twice.
     */
    function datatable_assets()
    {
        static $queued = false;

        if ($queued) {
            return;
        }
        $queued = true;

        /** @var CI_Controller&object{template: Template} $CI */
        $CI = &get_instance();
        $CI->template
            ->add_head('<link rel="stylesheet" href="'.asset_url('vendor/datatables/datatables.bundle.min.css').'">')
            ->add_foot(
                '<script src="'.asset_url('vendor/jquery/jquery.min.js').'"></script>'."\n\t"
                .'<script src="'.asset_url('vendor/datatables/datatables.bundle.min.js').'"></script>'."\n\t"
                .'<script src="'.asset_url('js/datatables-init.js').'"></script>'
            );
    }
}

if (!function_exists('datatable_table')) {
    /**
     * Prints the <table> for a server-side DataTable. Every list in the app
     * uses this, so they all sort, search, page and collapse on phones alike.
     *
     * Each column is [title, options]:
     *   'orderable' => bool   default true (also needs ->column(.., sql) server-side)
     *   'priority'  => int    Responsive: lower stays visible longer on narrow screens (default 10000)
     *   'class'     => string cell classes, e.g. 'text-end text-nowrap'
     *   'width'     => string css width
     *
     * Options:
     *   'order'     => [columnIndex, 'asc'|'desc']  initial sort (default none)
     *   'page_length' => int                         default 10
     *   'empty'     => string                        text for an empty table
     *   'search'    => bool                          show the search box (default true)
     *   'filters'   => string                        CSS selector of a box with filter inputs (<select>, <input>).
     *                                                Their name=value pairs are sent with every request (read them
     *                                                in the data() method with $this->input->get()), and changing
     *                                                one reloads the table.
     *
     * @param string $id      unique DOM id
     * @param string $url     JSON endpoint (a controller method calling Datatable::respond)
     * @param array<int, array{0: string, 1?: array<string, mixed>}> $columns
     * @param array<string, mixed> $options
     *
     * @return string
     */
    function datatable_table($id, $url, array $columns, array $options = [])
    {
        datatable_assets();

        $e = static function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };

        $attrs = [
            'id' => $id,
            'class' => 'table table-hover align-middle w-100 app-datatable',
            'data-datatable' => '1',
            'data-url' => site_url($url),
            'data-page-length' => (int) ($options['page_length'] ?? 10),
            'data-search' => !array_key_exists('search', $options) || $options['search'] ? '1' : '0',
            'data-empty' => (string) ($options['empty'] ?? 'Nothing to show yet.'),
        ];
        if (!empty($options['filters'])) {
            $attrs['data-filters'] = (string) $options['filters'];
        }
        if (isset($options['order'])) {
            $attrs['data-order'] = (int) $options['order'][0].','.(strtolower((string) ($options['order'][1] ?? 'asc')) === 'desc' ? 'desc' : 'asc');
        }

        $html = '<table';
        foreach ($attrs as $k => $v) {
            $html .= ' '.$k.'="'.$e($v).'"';
        }
        $html .= ">\n<thead><tr>";

        foreach ($columns as $col) {
            $o = $col[1] ?? [];
            $html .= '<th'
                .' data-orderable="'.(($o['orderable'] ?? true) ? 'true' : 'false').'"'
                .(isset($o['priority']) ? ' data-priority="'.(int) $o['priority'].'"' : '')
                .(isset($o['class']) ? ' data-class-name="'.$e($o['class']).'"' : '')
                .(isset($o['width']) ? ' data-width="'.$e($o['width']).'"' : '')
                .'>'.$e($col[0]).'</th>';
        }

        return $html."</tr></thead>\n</table>\n";
    }
}
