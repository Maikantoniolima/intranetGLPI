<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIntranetConfig extends CommonDBTM {

    public static $rightname = 'config';

    /**
     * Obter nome da tabela
     */
    static function getTable($classname = null) {
        return 'glpi_intranet_config';
    }

    /**
     * Carregar configuração atual
     */
    public static function getConfig() {
        global $DB;

        try {
            $result = $DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => ['id' => 1],
                'LIMIT' => 1
            ]);

            if (count($result) === 0) {
                // Retornar configuração padrão se não existe
                return self::getDefaultConfig();
            }

            foreach ($result as $row) {
                return $row;
            }

        } catch (Throwable $e) {
            error_log('Erro ao carregar config da intranet: ' . $e->getMessage());
        }

        return self::getDefaultConfig();
    }

    /**
     * Configuração padrão
     */
    private static function getDefaultConfig() {
        return [
            'id'              => 1,
            'banner'          => '',
            'btn1_label'      => 'Política de Segurança',
            'btn1_link'       => '',
            'btn2_label'      => 'Portal RH',
            'btn2_link'       => '',
            'btn3_label'      => 'Acesso Rápido',
            'btn3_link'       => '',
            'weather_city'    => 'Manaus,BR',
            'weather_api_key' => ''
        ];
    }
}