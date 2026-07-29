<?php
/**
 * Elementor Library URL handling for WordPress Playground.
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

final class Morrow_House_Playground_Elementor_Library extends \Elementor\Core\Common\Modules\Connect\Apps\Library
{
    public function get_admin_url($action, $params = [])
    {
        $params = [
            'app' => $this->get_slug(),
            'action' => $action,
            'nonce' => wp_create_nonce($this->get_slug() . $action),
        ] + $params;

        $admin_url = get_admin_url();
        $admin_url .= 'admin.php?page=' . \Elementor\Core\Common\Modules\Connect\Admin::PAGE_ID;

        return add_query_arg($params, $admin_url);
    }
}
