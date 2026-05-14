# Product Badges — PrestaShop 1.7 Module

Visual reusable badge/label manager for your product catalog.

## Requirements

| Requirement      | Value                                        |
|-----------------|----------------------------------------------|
| PrestaShop      | 1.7.0 – 1.7.8.x                             |
| PHP             | 7.4 (tested) / 8.1 (compatible)             |
| MySQL           | 5.7+ / MariaDB 10.2+                        |
| Composer        | **Not required**                             |
| External JS/CSS | None (jQuery bundled by PS)                 |

> **Tested on:** PrestaShop 1.7.8.11 · PHP 7.4 · XAMPP on Windows 11

---

## Features

- **Badge CRUD** — Create, edit, activate/deactivate and delete badges from Back Office → Catalog → Product Badges.
- **Multilanguage** — Badge label is translatable per active language (Spanish and English included out of the box).
- **Many-to-many assignment** — Assign multiple badges to a product via a dedicated tab inside the product edit form.
- **Frontend display** — Badges appear overlaid on product images in category listings, search results, home page widgets, and the product page.
- **Global configuration** — Enable/disable the module, toggle listing and product-page display separately, set a maximum number of visible badges per product.
- **Multistore safe** — Does not break in a multistore setup. Badge data is shared across stores (no store-specific isolation in v1.0.0).

---

## Installation

1. Copy the `productbadges/` folder to `<prestashop_root>/modules/`.
2. Log in to Back Office → **Modules → Module Manager**.
3. Search for **Product Badges** and click **Install**.
4. Configure via **Modules → Product Badges → Configure** or manage badges at **Catalog → Product Badges**.

> If you are reinstalling after a previous version, use **Uninstall** first to drop the old tables before installing again.

---

## Usage

### Creating a badge

1. Go to **Catalog → Product Badges → Add new badge**.
2. Fill in the **label** for each active language.
3. Choose background and text colors with the color picker.
4. Select position: *Top Left* or *Top Right*.
5. Set **Active = Yes** and save.

### Assigning to products

1. Open any product in **Catalog → Products**.
2. Scroll to the **Product Badges** panel at the bottom of the main tab.
3. Check the badges you want and click **Save badges**.

---

## Technical decisions

### Frontend injection via JavaScript, not hooks

The natural PrestaShop hook for product card badges is `displayProductFlags`. In practice many themes — including common child themes of Classic — do not call this hook inside their product card template, so the hook fires for zero products on the listing page.

The chosen approach: `hookDisplayBeforeBodyClosingTag` serializes badge data for every product visible on the current page into a `window.pbData` JSON object. A small vanilla-JS script (`views/js/productbadges.js`) reads that object on `DOMContentLoaded` and injects badge markup into the image container of each card (`[data-id-product] .thumbnail-container`). For home-page widgets whose products are not in the main Smarty context, a lightweight AJAX fallback reads `[data-id-product]` elements from the DOM at runtime and fetches missing badge data from `controllers/front/badges.php`.

`displayProductFlags` is also registered so that themes which do support it get a server-side `<li>` render without any JS dependency.

### PHP HTML generation instead of Smarty templates for per-product output

PrestaShop's `Module::getCurrentSubTemplate()` clones `$this->context->smarty` on first call and caches that clone. Subsequent `assign()` calls on the original `$this->context->smarty` do not propagate to the cached clone, so every product after the first would render with the same data. Generating the badge HTML directly in PHP (`renderBadgesForProduct()`) avoids this entirely with no loss of maintainability given the small output.

### Tab `class_name` without "Controller" suffix

PrestaShop's dispatcher automatically appends `Controller` to the `class_name` stored in the `tab` table when resolving the admin controller. Setting `class_name = 'AdminProductBadgesController'` causes PS to look for a class named `AdminProductBadgesControllerController`, which does not exist and results in a blank page. The stored value must be `'AdminProductBadges'`; the file and class are still named `AdminProductBadgesController`.

### Admin template loaded via `$this->context->smarty->fetch()` with absolute path

`Module::display(__FILE__, 'template.tpl')` only resolves paths relative to `views/templates/hook/`. The product-edit assign panel lives in `views/templates/admin/assign/` and is loaded from an admin hook, where the Smarty security policy does not restrict absolute paths. Using `$this->context->smarty->fetch($this->local_path . 'views/templates/admin/assign/assign_tab.tpl')` works reliably here; the same call on a frontend hook would fail silently.

### Product page image injection target

The product page does not put a `[data-id-product]` attribute on the image container element, unlike listing cards. The product ID is read from the hidden form input `input[name="id_product"]` that PrestaShop renders in the add-to-cart form, and the badge wrapper is injected into `.images-container` (with a fallback to `.product-cover`).

---

## File structure

```
productbadges/
├── productbadges.php                           ← Module entry point, hooks, install/uninstall
├── classes/
│   └── ProductBadge.php                        ← ObjectModel + static query helpers
├── controllers/
│   ├── admin/
│   │   └── AdminProductBadgesController.php    ← Back-office CRUD list + AJAX assign endpoint
│   └── front/
│       └── badges.php                          ← AJAX endpoint for JS fallback badge fetch
├── views/
│   ├── css/
│   │   └── productbadges.css                   ← Frontend badge overlay styles
│   ├── js/
│   │   └── productbadges.js                    ← Two-phase badge injection (pbData + AJAX)
│   └── templates/
│       ├── admin/assign/assign_tab.tpl         ← Badge assignment panel in product edit form
│       └── hook/badges.tpl                     ← Unused fallback template (kept for reference)
├── translations/
│   ├── es.php                                  ← Spanish strings
│   └── en.php                                  ← English strings
├── upgrade/
│   └── upgrade-1.0.0.php                       ← Schema migration hook (empty placeholder)
└── index.php                                   ← Directory listing protection (all dirs)
```

---

## Uninstall

Uninstalling via Back Office removes all three database tables (`productbadge`, `productbadge_lang`, `productbadge_product`), all configuration values, and the admin menu tab. No orphan data is left behind.

---
