<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIntranetNews extends CommonDBTM {
    
    public static $rightname = 'config';

    /**
     * Obter nome da tabela
     */
    static function getTable($classname = null) {
        return 'glpi_intranet_news';
    }

    /**
     * Nome do tipo
     */
    static function getTypeName($nb = 0) {
        return __('Notícias', 'intranet');
    }

    /**
     * Preparar entrada para adicionar
     */
    function prepareInputForAdd($input) {
        $input['users_id'] = Session::getLoginUserID();
        
        if (isset($input['content'])) {
            $input['content'] = $input['content'];
        }
        
        if (empty($input['date_publication'])) {
            $input['date_publication'] = date('Y-m-d H:i:s');
        }
        
        return $input;
    }

    /**
     * Preparar entrada para atualizar
     */
    function prepareInputForUpdate($input) {
        if (isset($input['content'])) {
            $input['content'] = $input['content'];
        }
        
        return $input;
    }

    /**
     * Mostrar formulário
     */
    function showForm($ID, array $options = []) {
        global $CFG_GLPI;

        if ($ID > 0) {
            $this->getFromDB($ID);
        } else {
            $this->getEmpty();
        }

        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'>";
        echo "<td style='width:20%;'>" . __('Título', 'intranet') . " *</td>";
        echo "<td colspan='3'>";
        echo Html::input('title', [
            'value'       => $this->fields['title'] ?? '',
            'required'    => true,
            'placeholder' => 'Digite o título da notícia'
        ]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Data de Publicação', 'intranet') . "</td>";
        echo "<td>";
        $pub_date = $this->fields['date_publication'] ?? date('Y-m-d\TH:i');
        if (!empty($pub_date) && $pub_date !== '0000-00-00 00:00:00') {
            $pub_date = date('Y-m-d\TH:i', strtotime($pub_date));
        } else {
            $pub_date = date('Y-m-d\TH:i');
        }
        echo Html::input('date_publication', [
            'type'  => 'datetime-local',
            'value' => $pub_date
        ]);
        echo "</td>";
        echo "<td>" . __('Data de Expiração', 'intranet') . "</td>";
        echo "<td>";
        $exp_date = $this->fields['date_expiration'] ?? '';
        if (!empty($exp_date) && $exp_date !== '0000-00-00 00:00:00') {
            $exp_date = date('Y-m-d\TH:i', strtotime($exp_date));
        }
        echo Html::input('date_expiration', [
            'type'  => 'datetime-local',
            'value' => $exp_date
        ]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td style='vertical-align:top;'>" . __('Conteúdo', 'intranet') . " *</td>";
        echo "<td colspan='3'>";
        echo Html::textarea([
            'name'        => 'content',
            'value'       => $this->fields['content'] ?? '',
            'rows'        => 10,
            'cols'        => 80,
            'required'    => true,
            'placeholder' => 'Digite o conteúdo da notícia'
        ]);
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);

        return true;
    }
}