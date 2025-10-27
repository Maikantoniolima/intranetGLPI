<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginIntranetDashboard {

    /**
     * Obter informações do clima com cache
     */
    static function getWeather(array $config) {
        $city = $config['weather_city'] ?? 'Manaus,BR';
        $key  = $config['weather_api_key'] ?? '';
        
        if (empty($key)) {
            return null;
        }

        // Cache de 10 minutos
        $cache_file = GLPI_PLUGIN_DOC_DIR . '/intranet/weather_cache.json';
        $cache_ttl  = 600;

        // Verificar cache
        if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_ttl)) {
            $cached_data = @json_decode(@file_get_contents($cache_file), true);
            if ($cached_data) {
                return $cached_data;
            }
        }

        // Buscar dados da API
        $url = sprintf(
            'http://api.openweathermap.org/data/2.5/weather?q=%s&units=metric&lang=pt_br&appid=%s',
            urlencode($city),
            $key
        );

        $context = stream_context_create([
            'http' => [
                'timeout' => 5
            ]
        ]);

        $json = @file_get_contents($url, false, $context);
        
        if ($json) {
            $data = json_decode($json, true);
            if ($data && isset($data['main']['temp'])) {
                // Salvar no cache
                @file_put_contents($cache_file, json_encode($data));
                return $data;
            }
        }

        return null;
    }

    /**
     * Upload de banner
     */
    static function uploadBanner(array $file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }

        $upload_dir = GLPI_PLUGIN_DOC_DIR . '/intranet/banners/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0775, true);
        }

        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
        $name = 'banner_' . date('Ymd_His') . '.' . $ext;
        
        $dest = $upload_dir . $name;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return $name;
        }

        return null;
    }
}