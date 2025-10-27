<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Ferramentas → Gestor de Notícias
 * Item direto (sem subitens) apontando para front/news_manager.php
 */
class PluginIntranetManagerMenu extends CommonGLPI {

    static $rightname = 'ticket'; // mantém compatível com o que já funciona

    static function getMenuName() {
        return __('Gestor de Notícias', 'intranet');
    }

    static function getMenuContent() {
        global $CFG_GLPI;

        return [
            'title' => self::getMenuName(),
            'page'  => $CFG_GLPI['root_doc'] . '/plugins/intranet/front/news_manager.php',
            'icon'  => 'ti ti-news'
            // sem 'options' → vira um item direto em Ferramentas
        ];
    }
}
