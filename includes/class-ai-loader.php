<?php

class AI_Loader {

    public function run() {
        require_once plugin_dir_path(__FILE__) . 'class-ai-admin.php';
        require_once plugin_dir_path(__FILE__) . 'class-ai-frontend.php';
        require_once plugin_dir_path(__FILE__) . 'class-ai-api.php';
        require_once plugin_dir_path(__FILE__) . 'class-ai-database.php';
        require_once plugin_dir_path(__FILE__) . 'class-ai-pdf.php';

        new AI_Admin();
        new AI_Frontend();
        new AI_Api();
        new AI_Database();
        new AI_PDF();
    }
}
