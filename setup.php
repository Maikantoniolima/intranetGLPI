<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

define('PLUGIN_INTRANET_VERSION', '1.0.2');
define('PLUGIN_INTRANET_MIN_GLPI_VERSION', '10.0.0');
define('PLUGIN_INTRANET_MAX_GLPI_VERSION', '10.0.21');

/**
 * Informações da versão do plugin
 */
function plugin_version_intranet() {
    return [
        'name'         => 'Intranet',
        'version'      => PLUGIN_INTRANET_VERSION,
        'author'       => 'Maik / GLPI Intranet',
        'license'      => 'GPLv2+',
        'homepage'     => 'https://maiklima.dev.br',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_INTRANET_MIN_GLPI_VERSION,
                'max' => PLUGIN_INTRANET_MAX_GLPI_VERSION
            ]
        ]
    ];
}

/**
 * Inicialização do plugin
 */
function plugin_init_intranet() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['intranet'] = true;

    // Página de configuração (ícone de chave)
    $PLUGIN_HOOKS['config_page']['intranet'] = 'front/config.php';

    // inclui manualmente o arquivo do menu do gestor
    $mgr = GLPI_ROOT . '/plugins/intranet/inc/manager.class.php';
    if (file_exists($mgr)) {
        include_once $mgr;
    }

    // Menu Assistência → Intranet (abaixo de Dashboard)
    $PLUGIN_HOOKS['menu_toadd']['intranet'] = [
        'helpdesk' => 'PluginIntranetMenu',
        'tools'    => 'PluginIntranetManagerMenu'
    ];

    // Fallback para garantir acesso
    $PLUGIN_HOOKS['menu_entry']['intranet'] = 'front/dashboard.php';

    // Para interface simplificada (self-service)
    if (Session::haveRight('ticket', CREATE)) {
        $PLUGIN_HOOKS['helpdesk_menu_entry']['intranet'] = 'front/dashboard.php';
        $PLUGIN_HOOKS['helpdesk_menu_entry_icon']['intranet'] = 'ti ti-world';
    }
}

/**
 * Hook de instalação
 */
function plugin_intranet_install() {
    global $DB;

    // Criar pasta de uploads
    $upload_dir = GLPI_PLUGIN_DOC_DIR . '/intranet/banners';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0775, true);
    }

    // Criar pasta de logs
    $log_dir = GLPI_PLUGIN_DOC_DIR . '/intranet/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0775, true);
    }

    // 1) Executar SQL de instalação das tabelas do plugin
    $sql_file = GLPI_ROOT . '/plugins/intranet/sql/mysql/update-1.0.0.sql';
    if (file_exists($sql_file)) {
        if (!$DB->runFile($sql_file)) {
            return false;
        }
    }

    // 2) Garantir a coluna 'birthday' em glpi_users via Migration (compatível MySQL 5.7/8)
    $migration = new \Migration(102); // aumente quando versionar novas migrações
    if (!$DB->fieldExists('glpi_users', 'birthday')) {
        $migration->addField('glpi_users', 'birthday', 'date', [
            'nullable' => true,
            'default'  => null,
            'after'    => 'begin_date'
        ]);
    }
    $migration->executeMigration();

    return true;
}

/**
 * Hook de atualização
 */
function plugin_intranet_update($from_version) {
    // Reaproveita a lógica de instalação para garantir migrações idempotentes
    return plugin_intranet_install();
}

/**
 * Alias para compatibilidade
 */
function plugin_intranet_upgrade($from_version) {
    return plugin_intranet_install();
}

/**
 * Hook de desinstalação
 */
function plugin_intranet_uninstall() {
    return true;
}

/**
 * Verificar pré-requisitos
 */
function plugin_intranet_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_INTRANET_MIN_GLPI_VERSION, 'lt')) {
        echo "Este plugin requer GLPI >= " . PLUGIN_INTRANET_MIN_GLPI_VERSION;
        return false;
    }
    return true;
}

/**
 * Verificar configuração
 */
function plugin_intranet_check_config($verbose = false) {
    return true;
}
