<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductbadgesBadgesModuleFrontController extends ModuleFrontController
{
    public $ajax = true;
    public $ssl  = true;

    public function initContent()
    {
        parent::initContent();

        header('Content-Type: application/json; charset=utf-8');

        $ids = Tools::getValue('ids');
        $ids = is_array($ids) ? array_filter(array_map('intval', $ids)) : [];

        if (empty($ids)) {
            die('[]');
        }

        $max    = (int) Configuration::get('PRODUCTBADGES_MAX_BADGES');
        $id_lang = (int) $this->context->language->id;
        $result = [];

        foreach ($ids as $id) {
            $badges = ProductBadge::getByProduct($id, $id_lang, $max);
            if (!empty($badges)) {
                $result[$id] = $badges;
            }
        }

        die(json_encode($result));
    }
}
