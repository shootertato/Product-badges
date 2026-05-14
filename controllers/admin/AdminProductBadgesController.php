<?php
/**
 * AdminProductBadgesController
 * Handles CRUD for badges and product assignments via AJAX.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/ProductBadge.php';

class AdminProductBadgesController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap   = true;
        $this->table       = 'productbadge';
        $this->className   = 'ProductBadge';
        $this->identifier  = 'id_productbadge';
        $this->lang        = true;
        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->allow_export = false;

        parent::__construct();

        $this->meta_title = $this->l('Product Badges');

        // List columns
        $this->fields_list = [
            'id_productbadge' => [
                'title'  => $this->l('ID'),
                'align'  => 'center',
                'class'  => 'fixed-width-xs',
            ],
            'label' => [
                'title'  => $this->l('Label'),
                'filter_key' => 'bl!label',
            ],
            'preview' => [
                'title'    => $this->l('Preview'),
                'callback' => 'renderBadgePreview',
                'orderby'  => false,
                'filter'   => false,
                'search'   => false,
            ],
            'position' => [
                'title'  => $this->l('Position'),
            ],
            'products_count' => [
                'title'   => $this->l('Products'),
                'align'   => 'center',
                'orderby' => false,
                'filter'  => false,
                'search'  => false,
            ],
            'active' => [
                'title'   => $this->l('Active'),
                'active'  => 'status',
                'type'    => 'bool',
                'align'   => 'center',
                'class'   => 'fixed-width-sm',
                'orderby' => false,
            ],
        ];

        // Default sort
        $this->_defaultOrderBy  = 'id_productbadge';
        $this->_defaultOrderWay = 'ASC';

        // Override query to include lang + products count
        $this->_join = 'LEFT JOIN `' . _DB_PREFIX_ . 'productbadge_lang` bl
            ON (bl.id_productbadge = a.id_productbadge AND bl.id_lang = ' . (int) $this->context->language->id . ')';

        $this->_select = 'bl.label,
            (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'productbadge_product` bp
             WHERE bp.id_productbadge = a.id_productbadge) AS products_count,
            a.bg_color, a.text_color';
    }

    /* ------------------------------------------------------------------ */
    /*  FORM FIELDS                                                         */
    /* ------------------------------------------------------------------ */

    public function renderForm()
    {
        $languages = Language::getLanguages(false);

        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Badge'),
                'icon'  => 'icon-tag',
            ],
            'input' => [
                [
                    'type'     => 'text',
                    'label'    => $this->l('Label'),
                    'name'     => 'label',
                    'lang'     => true,
                    'required' => true,
                    'hint'     => $this->l('Visible text on the badge (e.g. NEW, SALE, EXCLUSIVE)'),
                ],
                [
                    'type'  => 'color',
                    'label' => $this->l('Background color'),
                    'name'  => 'bg_color',
                ],
                [
                    'type'  => 'color',
                    'label' => $this->l('Text color'),
                    'name'  => 'text_color',
                ],
                [
                    'type'    => 'select',
                    'label'   => $this->l('Position'),
                    'name'    => 'position',
                    'options' => [
                        'query' => [
                            ['id' => 'top-left',  'name' => $this->l('Top Left')],
                            ['id' => 'top-right', 'name' => $this->l('Top Right')],
                        ],
                        'id'   => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type'   => 'switch',
                    'label'  => $this->l('Active'),
                    'name'   => 'active',
                    'values' => [
                        ['id' => 'on',  'value' => 1, 'label' => $this->l('Yes')],
                        ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
        ];

        // Populate lang fields when editing
        if ($this->object && $this->object->id) {
            foreach ($languages as $lang) {
                $this->fields_value['label'][$lang['id_lang']] =
                    $this->object->label[$lang['id_lang']] ?? '';
            }
        }

        return parent::renderForm();
    }

    /* ------------------------------------------------------------------ */
    /*  LIST CALLBACK                                                       */
    /* ------------------------------------------------------------------ */

    public function renderBadgePreview($value, $row)
    {
        $bg   = htmlspecialchars($row['bg_color']);
        $fg   = htmlspecialchars($row['text_color']);
        $text = htmlspecialchars($row['label']);

        return sprintf(
            '<span style="display:inline-block;padding:3px 8px;border-radius:3px;
                background:%s;color:%s;font-size:12px;font-weight:bold;">%s</span>',
            $bg, $fg, $text ?: '—'
        );
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX — product badge assignment                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Called via POST from the assign tab in product edit.
     */
    public function ajaxProcessSaveProductBadges()
    {
        $id_product = (int) Tools::getValue('id_product');
        $badge_ids  = Tools::getValue('badge_ids');
        $badge_ids  = is_array($badge_ids) ? array_map('intval', $badge_ids) : [];

        if (!$id_product) {
            $this->ajaxDie(json_encode(['success' => false, 'message' => 'Invalid product']));
        }

        $result = ProductBadge::setProductBadges($id_product, $badge_ids);

        $this->ajaxDie(json_encode([
            'success' => (bool) $result,
            'message' => $result ? $this->l('Badges saved.') : $this->l('Error saving badges.'),
        ]));
    }

    /* ------------------------------------------------------------------ */
    /*  TOGGLE STATUS (AJAX from list)                                      */
    /* ------------------------------------------------------------------ */

    public function ajaxProcessStatusProductbadge()
    {
        $id = (int) Tools::getValue('id_productbadge');
        $badge = new ProductBadge($id);

        if (!Validate::isLoadedObject($badge)) {
            $this->ajaxDie(json_encode(['success' => false]));
        }

        $badge->active = !$badge->active;
        $badge->update();

        $this->ajaxDie(json_encode(['success' => true, 'active' => (int) $badge->active]));
    }

    /* ------------------------------------------------------------------ */
    /*  BREADCRUMB                                                          */
    /* ------------------------------------------------------------------ */

    public function initPageHeaderToolbar()
    {
        if (empty($this->display)) {
            $this->page_header_toolbar_btn['new_badge'] = [
                'href'  => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
                'desc'  => $this->l('Add new badge'),
                'icon'  => 'process-icon-new',
            ];
        }

        parent::initPageHeaderToolbar();
    }
}

