<?php
/**
 * ProductBadge model
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductBadge extends ObjectModel
{
    /** @var string */
    public $bg_color = '#e84b4b';

    /** @var string */
    public $text_color = '#ffffff';

    /** @var string top-left|top-right */
    public $position = 'top-left';

    /** @var bool */
    public $active = true;

    /** @var string Multilang label */
    public $label = '';

    /** @var string */
    public $date_add;

    /** @var string */
    public $date_upd;

    public static $definition = [
        'table'     => 'productbadge',
        'primary'   => 'id_productbadge',
        'multilang' => true,
        'fields'    => [
            'bg_color'   => ['type' => self::TYPE_STRING, 'validate' => 'isColor',   'size' => 7],
            'text_color' => ['type' => self::TYPE_STRING, 'validate' => 'isColor',   'size' => 7],
            'position'   => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName'],
            'active'     => ['type' => self::TYPE_BOOL,   'validate' => 'isBool'],
            'date_add'   => ['type' => self::TYPE_DATE,   'validate' => 'isDateFormat'],
            'date_upd'   => ['type' => self::TYPE_DATE,   'validate' => 'isDateFormat'],
            // Multilang
            'label'      => [
                'type'     => self::TYPE_STRING,
                'lang'     => true,
                'validate' => 'isGenericName',
                'required' => false,
                'size'     => 64,
            ],
        ],
    ];

    /* ------------------------------------------------------------------ */
    /*  STATIC QUERIES                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Return all badges (for admin list).
     */
    public static function getAll($id_lang)
    {
        $sql = new DbQuery();
        $sql->select('b.*, bl.label');
        $sql->from('productbadge', 'b');
        $sql->leftJoin(
            'productbadge_lang',
            'bl',
            'b.id_productbadge = bl.id_productbadge AND bl.id_lang = ' . (int) $id_lang
        );
        $sql->orderBy('b.id_productbadge ASC');

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Return only active badges (for admin assign tab).
     */
    public static function getAllActive($id_lang)
    {
        $sql = new DbQuery();
        $sql->select('b.*, bl.label');
        $sql->from('productbadge', 'b');
        $sql->leftJoin(
            'productbadge_lang',
            'bl',
            'b.id_productbadge = bl.id_productbadge AND bl.id_lang = ' . (int) $id_lang
        );
        $sql->where('b.active = 1');
        $sql->orderBy('b.id_productbadge ASC');

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Return badges assigned to a product (frontend).
     */
    public static function getByProduct($id_product, $id_lang, $limit = 0)
    {
        $sql = new DbQuery();
        $sql->select('b.bg_color, b.text_color, b.position, bl.label');
        $sql->from('productbadge', 'b');
        $sql->innerJoin(
            'productbadge_product',
            'bp',
            'b.id_productbadge = bp.id_productbadge AND bp.id_product = ' . (int) $id_product
        );
        $sql->leftJoin(
            'productbadge_lang',
            'bl',
            'b.id_productbadge = bl.id_productbadge AND bl.id_lang = ' . (int) $id_lang
        );
        $sql->where('b.active = 1');
        $sql->orderBy('b.id_productbadge ASC');

        if ($limit > 0) {
            $sql->limit((int) $limit);
        }

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Return badge IDs assigned to a product.
     */
    public static function getProductBadgeIds($id_product)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT id_productbadge FROM `' . _DB_PREFIX_ . 'productbadge_product`
             WHERE id_product = ' . (int) $id_product
        );

        return array_column((array) $rows, 'id_productbadge');
    }

    /**
     * Replace badge assignments for a product.
     */
    public static function setProductBadges($id_product, array $badge_ids)
    {
        $id_product = (int) $id_product;

        // Remove all existing
        Db::getInstance()->delete('productbadge_product', 'id_product = ' . $id_product);

        if (empty($badge_ids)) {
            return true;
        }

        $rows = [];
        foreach ($badge_ids as $id_badge) {
            $id_badge = (int) $id_badge;
            if ($id_badge > 0) {
                $rows[] = [
                    'id_productbadge' => $id_badge,
                    'id_product'      => $id_product,
                ];
            }
        }

        return !empty($rows) ? Db::getInstance()->insert('productbadge_product', $rows) : true;
    }

    /**
     * Delete all product assignments for a badge (called on badge delete).
     */
    public static function deleteProductAssignments($id_badge)
    {
        return Db::getInstance()->delete(
            'productbadge_product',
            'id_productbadge = ' . (int) $id_badge
        );
    }

    /**
     * Count products using a badge.
     */
    public static function countProducts($id_badge)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'productbadge_product`
             WHERE id_productbadge = ' . (int) $id_badge
        );
    }

    /* ------------------------------------------------------------------ */
    /*  OVERRIDE delete() to clean up relations                             */
    /* ------------------------------------------------------------------ */

    public function delete()
    {
        self::deleteProductAssignments($this->id);
        return parent::delete();
    }
}
