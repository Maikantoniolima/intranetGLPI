<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIntranetMenu extends CommonGLPI {

    static $rightname = 'ticket';

    /**
     * Nome do menu
     */
    static function getMenuName() {
        return __('Intranet', 'intranet');
    }

    /**
     * Conteúdo do menu
     */
static function getMenuContent() {
    global $CFG_GLPI;

    $menu = [
        'title'   => self::getMenuName(),
        'page'    => $CFG_GLPI['root_doc'] . '/plugins/intranet/front/dashboard.php',
        'icon'    => 'ti ti-world',
        'options' => [
            // mantém o item atual (dashboard)
            'dashboard' => [
                'title' => __('Intranet', 'intranet'),
                'page'  => $CFG_GLPI['root_doc'] . '/plugins/intranet/front/dashboard.php',
                'icon'  => 'ti ti-world'
            ],
            // ➕ novo item: Gestor de Notícias
            'news_manager' => [
                'title' => __('Gestor de Notícias', 'intranet'),
                'page'  => $CFG_GLPI['root_doc'] . '/plugins/intranet/front/news_manager.php',
                'icon'  => 'ti ti-news'
            ]
        ]
    ];

    return $menu;
}


    
    
}