<?php

namespace GlpiPlugin\Intranet;

use CommonGLPI;
use Html;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Classe principal do plugin Intranet
 */
class Intranet extends CommonGLPI
{
    static $rightname = 'ticket';

    /**
     * Nome do tipo
     */
    static function getTypeName($nb = 0)
    {
        return __('Intranet', 'intranet');
    }

    /**
     * Nome do menu
     */
    static function getMenuName($nb = 0)
    {
        return self::getTypeName($nb);
    }

    /**
     * URL do formulário
     */
    static function getFormURL($full = true)
    {
        return ($full ? PLUGIN_INTRANET_WEBDIR : '') . '/front/intranet.php';
    }

    /**
     * Conteúdo do menu
     */
    static function getMenuContent()
    {
        $menu = [];

        $menu['title'] = self::getMenuName();
        $menu['page'] = self::getFormURL(false);
        $menu['icon'] = 'ti ti-world';

        $menu['options'] = [
            'intranet' => [
                'title' => self::getTypeName(),
                'page' => self::getFormURL(false),
                'icon' => 'ti ti-world',
                'links' => [
                    'search' => self::getFormURL(false),
                ]
            ]
        ];

        return $menu;
    }

    /**
     * Mostrar página principal
     */
    function showPage()
    {
        // Detectar se é interface simplificada ou completa
        $is_helpdesk = ($_SESSION['glpiactiveprofile']['interface'] == 'helpdesk');
        
        if ($is_helpdesk) {
            // Cabeçalho para interface simplificada
            Html::nullHeader(self::getTypeName(), $_SERVER['PHP_SELF']);
        } else {
            // Cabeçalho para interface completa
            Html::header(
                self::getTypeName(),
                $_SERVER['PHP_SELF'],
                "plugins",
                self::class,
                "intranet"
            );
        }

        echo "<div class='center' style='padding: 20px;'>";
        echo "<h2>" . __('Bem-vindo à Intranet', 'intranet') . "</h2>";
        
        if ($is_helpdesk) {
            echo "<p>" . __('Versão simplificada da Intranet', 'intranet') . "</p>";
        } else {
            echo "<p>" . __('Esta é a página inicial do plugin de Intranet.', 'intranet') . "</p>";
        }

        // Conteúdo da intranet
        echo "<div class='card mt-3'>";
        echo "<div class='card-header'>";
        echo "<h3 class='card-title'>Conteúdo da Intranet</h3>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        if ($is_helpdesk) {
            echo "<div class='row'>";
            echo "<div class='col-md-4'>";
            echo "<h4><i class='ti ti-news'></i> Comunicados</h4>";
            echo "<p>Últimas notícias e comunicados internos.</p>";
            echo "</div>";
            echo "<div class='col-md-4'>";
            echo "<h4><i class='ti ti-file-text'></i> Documentos</h4>";
            echo "<p>Acesso rápido aos documentos importantes.</p>";
            echo "</div>";
            echo "<div class='col-md-4'>";
            echo "<h4><i class='ti ti-link'></i> Links Úteis</h4>";
            echo "<p>Links para sistemas e ferramentas.</p>";
            echo "</div>";
            echo "</div>";
        } else {
            echo "<p>Versão completa com mais recursos e funcionalidades administrativas.</p>";
            echo "<hr>";
            echo "<div class='alert alert-info'>";
            echo "<i class='ti ti-info-circle'></i> ";
            echo "Você está visualizando a versão administrativa da Intranet.";
            echo "</div>";
        }
        
        echo "</div>";
        echo "</div>";

        // Informações do usuário logado
        echo "<div class='card mt-3'>";
        echo "<div class='card-body'>";
        echo "<p><strong>Usuário logado:</strong> " . $_SESSION['glpiname'] . "</p>";
        echo "<p><strong>Perfil:</strong> " . $_SESSION['glpiactiveprofile']['name'] . "</p>";
        echo "<p><strong>Tipo de Interface:</strong> " . ($is_helpdesk ? 'Simplificada (Self-Service)' : 'Completa (Central)') . "</p>";
        echo "</div>";
        echo "</div>";

        echo "</div>";

        if ($is_helpdesk) {
            Html::nullFooter();
        } else {
            Html::footer();
        }
    }

    /**
     * Verificar direitos
     */
    static function canView()
    {
        // Permite acesso para qualquer usuário autenticado
        return Session::getLoginUserID() !== false;
    }
}