<?php
if (!defined('ABSPATH')) {
    exit;
}

class Custom_Feature_Ad_Removal {
    private $access;

    public function __construct() {
        $this->access = new Custom_Orunk_Access();
    }

    public function init() {
        add_action('wp_head', array($this, 'remove_ads'));
    }

    public function remove_ads() {
        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();
        if ($this->access->has_feature_access_by_user_id($user_id, 'ad_removal')) {
            echo '<style>.ad-banner { display: none !important; }</style>';
        }
    }
}