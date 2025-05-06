<?php
if (!defined('ABSPATH')) {
    exit;
}

class Custom_Feature_Bin_API {
    private $access;

    public function __construct() {
        $this->access = new Custom_Orunk_Access();
    }

    public function init() {
        // No additional initialization needed; access control is handled by bins-api-plugin
    }
}