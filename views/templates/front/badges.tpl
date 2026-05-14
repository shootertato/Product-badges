{**
 * Product Badges – frontend render
 * Output: <li> items valid inside <ul class="product-flags">
 *}
{if isset($pb_badges) && $pb_badges|count > 0}
    {foreach from=$pb_badges item=badge}
        <li class="product-flag pb-badge pb-pos-{$badge.position|escape:'html':'UTF-8'}"
            style="background-color:{$badge.bg_color|escape:'html':'UTF-8'};color:{$badge.text_color|escape:'html':'UTF-8'};">
            {$badge.label|escape:'html':'UTF-8'}
        </li>
    {/foreach}
{/if}
