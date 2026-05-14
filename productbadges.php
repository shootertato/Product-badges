<?php
/**
 * Product Badges - Visual label manager for PrestaShop 1.7
 *
 * @author    Chema Ferrandez
 * @license   MIT
 * @version   1.0.0
 * @tested    PHP 7.4 / PrestaShop 1.7.8.11
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/ProductBadge.php';

class Productbadges extends Module
{
    // Config keys
    const CFG_ENABLED      = 'PRODUCTBADGES_ENABLED';
    const CFG_SHOW_LIST    = 'PRODUCTBADGES_SHOW_LIST';
    const CFG_SHOW_PRODUCT = 'PRODUCTBADGES_SHOW_PRODUCT';
    const CFG_MAX_BADGES   = 'PRODUCTBADGES_MAX_BADGES';

    public function __construct()
    {
        $this->name          = 'productbadges';
        $this->tab           = 'front_office_features';
        $this->version       = '1.0.0';
        $this->author        = 'Chema Ferrandez';
        $this->need_instance = 0;
        $this->bootstrap     = true;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '1.7.99.99',
        ];

        parent::__construct();

        $this->displayName = $this->l('Product Badges');
        $this->description = $this->l('Visual reusable badges for your product catalog.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall? All badge data will be lost.');
    }

    /* ------------------------------------------------------------------ */
    /*  INSTALL / UNINSTALL                                                 */
    /* ------------------------------------------------------------------ */

    public function install()
    {
        return parent::install()
            && $this->installDb()
            && $this->installTab()
            && $this->installHooks()
            && $this->installDefaultConfig();
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallDb()
            && $this->uninstallTab()
            && $this->uninstallConfig();
    }

    private function installDb()
    {
        $sql = [];

        // Badges table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'productbadge` (
            `id_productbadge` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `bg_color`        VARCHAR(7)       NOT NULL DEFAULT \'#e84b4b\',
            `text_color`      VARCHAR(7)       NOT NULL DEFAULT \'#ffffff\',
            `position`        ENUM(\'top-left\',\'top-right\') NOT NULL DEFAULT \'top-left\',
            `active`          TINYINT(1)       NOT NULL DEFAULT 1,
            `date_add`        DATETIME         NOT NULL,
            `date_upd`        DATETIME         NOT NULL,
            PRIMARY KEY (`id_productbadge`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        // Badge lang table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'productbadge_lang` (
            `id_productbadge` INT(10) UNSIGNED NOT NULL,
            `id_lang`         INT(10) UNSIGNED NOT NULL,
            `label`           VARCHAR(64)      NOT NULL DEFAULT \'\',
            PRIMARY KEY (`id_productbadge`, `id_lang`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        // Badge ↔ Product relation (many-to-many)
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'productbadge_product` (
            `id_productbadge` INT(10) UNSIGNED NOT NULL,
            `id_product`      INT(10) UNSIGNED NOT NULL,
            PRIMARY KEY (`id_productbadge`, `id_product`),
            KEY `id_product` (`id_product`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallDb()
    {
        $tables = [
            _DB_PREFIX_ . 'productbadge_product',
            _DB_PREFIX_ . 'productbadge_lang',
            _DB_PREFIX_ . 'productbadge',
        ];

        foreach ($tables as $table) {
            if (!Db::getInstance()->execute('DROP TABLE IF EXISTS `' . $table . '`')) {
                return false;
            }
        }

        return true;
    }

    private function installTab()
    {
        // Clean up any existing tabs regardless of naming convention used
        foreach (['AdminProductBadges', 'AdminProductBadgesController'] as $old_class) {
            $old_id = (int) Tab::getIdFromClassName($old_class);
            if ($old_id) {
                (new Tab($old_id))->delete();
            }
        }

        $tab = new Tab();
        $tab->active      = 1;
        $tab->class_name  = 'AdminProductBadges';
        $tab->module      = $this->name;
        $tab->id_parent   = (int) Tab::getIdFromClassName('AdminCatalog');
        $tab->icon        = 'label';

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = 'Product Badges';
        }

        return $tab->add();
    }

    private function uninstallTab()
    {
        $id_tab = (int) Tab::getIdFromClassName('AdminProductBadges');
        if (!$id_tab) {
            $id_tab = (int) Tab::getIdFromClassName('AdminProductBadgesController');
        }
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }

        return true;
    }

    private function installHooks()
    {
        return $this->registerHook('displayProductFlags')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayBeforeBodyClosingTag')
            && $this->registerHook('displayAdminProductsMainStepLeftColumnBottom');
    }

    private function installDefaultConfig()
    {
        Configuration::updateValue(self::CFG_ENABLED, 1);
        Configuration::updateValue(self::CFG_SHOW_LIST, 1);
        Configuration::updateValue(self::CFG_SHOW_PRODUCT, 1);
        Configuration::updateValue(self::CFG_MAX_BADGES, 3);

        return true;
    }

    private function uninstallConfig()
    {
        Configuration::deleteByName(self::CFG_ENABLED);
        Configuration::deleteByName(self::CFG_SHOW_LIST);
        Configuration::deleteByName(self::CFG_SHOW_PRODUCT);
        Configuration::deleteByName(self::CFG_MAX_BADGES);

        return true;
    }

    /* ------------------------------------------------------------------ */
    /*  MODULE CONFIGURATION PAGE                                           */
    /* ------------------------------------------------------------------ */

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit_productbadges')) {
            $enabled     = (int) Tools::getValue(self::CFG_ENABLED);
            $showList    = (int) Tools::getValue(self::CFG_SHOW_LIST);
            $showProduct = (int) Tools::getValue(self::CFG_SHOW_PRODUCT);
            $maxBadges   = max(1, (int) Tools::getValue(self::CFG_MAX_BADGES));

            Configuration::updateValue(self::CFG_ENABLED, $enabled);
            Configuration::updateValue(self::CFG_SHOW_LIST, $showList);
            Configuration::updateValue(self::CFG_SHOW_PRODUCT, $showProduct);
            Configuration::updateValue(self::CFG_MAX_BADGES, $maxBadges);

            $output .= $this->displayConfirmation($this->l('Settings saved.'));
        }

        $output .= $this->renderConfigForm();
        $output .= '<a href="' . $this->context->link->getAdminLink('AdminProductBadges') . '"
            class="btn btn-default btn-lg" style="margin-top:10px">
            <i class="icon-tag"></i> ' . $this->l('Manage Badges') . '
        </a>';

        return $output;
    }

    private function renderConfigForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('General Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Enable module'),
                        'name'    => self::CFG_ENABLED,
                        'values'  => $this->getSwitchValues(),
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Show badges in product listings'),
                        'name'    => self::CFG_SHOW_LIST,
                        'values'  => $this->getSwitchValues(),
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Show badges on product page'),
                        'name'    => self::CFG_SHOW_PRODUCT,
                        'values'  => $this->getSwitchValues(),
                    ],
                    [
                        'type'    => 'text',
                        'label'   => $this->l('Max badges per product'),
                        'name'    => self::CFG_MAX_BADGES,
                        'class'   => 'fixed-width-xs',
                        'suffix'  => $this->l('badges'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar       = false;
        $helper->table              = $this->table;
        $helper->module             = $this;
        $helper->default_form_language    = $this->context->language->id;
        $helper->identifier         = $this->identifier;
        $helper->submit_action      = 'submit_productbadges';
        $helper->currentIndex       = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name;
        $helper->token              = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value[self::CFG_ENABLED]      = Configuration::get(self::CFG_ENABLED);
        $helper->fields_value[self::CFG_SHOW_LIST]    = Configuration::get(self::CFG_SHOW_LIST);
        $helper->fields_value[self::CFG_SHOW_PRODUCT] = Configuration::get(self::CFG_SHOW_PRODUCT);
        $helper->fields_value[self::CFG_MAX_BADGES]   = Configuration::get(self::CFG_MAX_BADGES);

        return $helper->generateForm([$fields_form]);
    }

    private function getSwitchValues()
    {
        return [
            ['id' => 'on', 'value' => 1, 'label' => $this->l('Yes')],
            ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  HOOKS — FRONTEND                                                    */
    /* ------------------------------------------------------------------ */

    public function hookDisplayHeader($params)
    {
        if (!Configuration::get(self::CFG_ENABLED)) {
            return;
        }
        $this->context->controller->addCSS($this->_path . 'views/css/productbadges.css');
        $this->context->controller->addJS($this->_path . 'views/js/productbadges.js');

        return '<script>window.pbConfig=' . json_encode([
            'ajaxUrl' => $this->context->link->getModuleLink('productbadges', 'badges'),
        ]) . ';</script>';
    }



    public function hookDisplayProductFlags($params)
    {
        if (!Configuration::get(self::CFG_ENABLED)) {
            return '';
        }

        $php_self = isset($this->context->controller->php_self) ? $this->context->controller->php_self : '';
        $isProductPage = ($php_self === 'product');

        if ($isProductPage && !Configuration::get(self::CFG_SHOW_PRODUCT)) {
            return '';
        }
        if (!$isProductPage && !Configuration::get(self::CFG_SHOW_LIST)) {
            return '';
        }

        $product = isset($params['product']) ? $params['product'] : null;
        if (!$product) {
            return '';
        }

        if (is_array($product)) {
            $id_product = (int) ($product['id_product'] ?? $product['id'] ?? 0);
        } else {
            $id_product = (int) $product->id;
        }

        if (!$id_product) {
            return '';
        }

        return $this->renderBadgesForProduct($id_product);
    }

    public function hookDisplayBeforeBodyClosingTag($params)
    {
        if (!Configuration::get(self::CFG_ENABLED)) {
            return '';
        }

        $idLang = $this->context->language->id;
        $max    = (int) Configuration::get(self::CFG_MAX_BADGES);
        $allBadgeData = [];

        $smartyVars = [
            'listing'  => true,
            'products' => false,
            'product'  => false,
        ];

        foreach ($smartyVars as $varName => $isListing) {
            $data = $this->context->smarty->getTemplateVars($varName);
            if (empty($data)) {
                continue;
            }

            if ($isListing) {
                $products = isset($data['products']) ? $data['products'] : [];
            } elseif ($varName === 'product') {
                $products = is_array($data) ? [$data] : [];
            } else {
                $products = is_array($data) ? $data : [];
            }

            foreach ($products as $product) {
                if (is_array($product)) {
                    $id_product = (int) ($product['id_product'] ?? $product['id'] ?? 0);
                } elseif (is_object($product)) {
                    $id_product = (int) $product->id;
                } else {
                    continue;
                }

                if (!$id_product || isset($allBadgeData[$id_product])) {
                    continue;
                }

                $badges = ProductBadge::getByProduct($id_product, $idLang, $max);
                if (!empty($badges)) {
                    $allBadgeData[$id_product] = $badges;
                }
            }
        }

        if (empty($allBadgeData)) {
            return '';
        }

        return '<script>window.pbData=' . json_encode($allBadgeData) . ';</script>';
    }

    /**
     * Tab inside the product edit form (Back Office).
     */
    public function hookDisplayAdminProductsMainStepLeftColumnBottom($params)
    {
        $id_product = (int) Tools::getValue('id_product');
        if (!$id_product && isset($params['id_product'])) {
            $id_product = (int) $params['id_product'];
        }
        if (!$id_product) {
            return '';
        }

        $badges    = ProductBadge::getAllActive($this->context->language->id);
        $assigned  = ProductBadge::getProductBadgeIds($id_product);

        $this->context->smarty->assign([
            'pb_badges'         => $badges,
            'pb_assigned'       => $assigned,
            'pb_id_product'     => $id_product,
            'pb_assign_url'     => $this->context->link->getAdminLink('AdminProductBadges'),
            'pb_token'          => Tools::getAdminTokenLite('AdminProductBadges'),
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/assign/assign_tab.tpl');
    }

    /* ------------------------------------------------------------------ */
    /*  RENDER HELPER                                                       */
    /* ------------------------------------------------------------------ */

    private function renderBadgesForProduct($id_product)
    {
        $max    = (int) Configuration::get(self::CFG_MAX_BADGES);
        $badges = ProductBadge::getByProduct(
            $id_product,
            $this->context->language->id,
            $max
        );

        if (empty($badges)) {
            return '';
        }

        $html = '';
        foreach ($badges as $badge) {
            $bg    = htmlspecialchars($badge['bg_color'],   ENT_QUOTES, 'UTF-8');
            $fg    = htmlspecialchars($badge['text_color'], ENT_QUOTES, 'UTF-8');
            $pos   = htmlspecialchars($badge['position'],   ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($badge['label'],      ENT_QUOTES, 'UTF-8');

            $html .= '<li class="product-flag pb-badge pb-pos-' . $pos . '"'
                   . ' style="background-color:' . $bg . ';color:' . $fg . ';">'
                   . $label . '</li>';
        }

        return $html;
    }
}
