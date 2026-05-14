{**
 * Product Badges – assign tab inside product edit (back office)
 *}
<div class="panel" id="pb-assign-panel">
    <div class="panel-heading">
        <i class="icon-tag"></i>
        {l s='Product Badges' mod='productbadges'}
    </div>

    <div class="panel-body">
        {if $pb_badges|count > 0}
            <p class="help-block">
                {l s='Select the badges to display on this product.' mod='productbadges'}
            </p>

            <div class="pb-badge-grid" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;">
                {foreach from=$pb_badges item=badge}
                    <label class="pb-badge-option"
                           style="cursor:pointer;display:flex;align-items:center;gap:8px;">
                        <input type="checkbox"
                               name="pb_badge_ids[]"
                               value="{$badge.id_productbadge|intval}"
                               {if in_array($badge.id_productbadge, $pb_assigned)}checked{/if}>
                        <span style="display:inline-block;padding:4px 10px;border-radius:3px;
                                     background:{$badge.bg_color|escape:'html':'UTF-8'};
                                     color:{$badge.text_color|escape:'html':'UTF-8'};
                                     font-size:12px;font-weight:bold;">
                            {$badge.label|escape:'html':'UTF-8'}
                        </span>
                        <small class="text-muted">{$badge.position|escape:'html':'UTF-8'}</small>
                    </label>
                {/foreach}
            </div>

            <button type="button" id="pb-save-badges" class="btn btn-primary" style="margin-top:20px;">
                <i class="icon-save"></i>
                {l s='Save badges' mod='productbadges'}
            </button>
            <span id="pb-save-msg" style="margin-left:10px;display:none;"></span>
        {else}
            <div class="alert alert-info">
                {l s='No active badges found.' mod='productbadges'}
                <a href="{$pb_assign_url|escape:'html':'UTF-8'}" target="_blank">
                    {l s='Create badges' mod='productbadges'}
                </a>
            </div>
        {/if}
    </div>
</div>

<script>
(function($) {
    $('#pb-save-badges').on('click', function() {
        var $btn  = $(this);
        var $msg  = $('#pb-save-msg');
        var ids   = [];

        $('input[name="pb_badge_ids[]"]:checked').each(function() {
            ids.push($(this).val());
        });

        $btn.prop('disabled', true);
        $msg.hide();

        $.post(
            '{$pb_assign_url|escape:'javascript':'UTF-8'}',
            {
                ajax       : 1,
                action     : 'SaveProductBadges',
                token      : '{$pb_token|escape:'javascript':'UTF-8'}',
                id_product : {$pb_id_product|intval},
                badge_ids  : ids
            },
            function(data) {
                $btn.prop('disabled', false);
                $msg.text(data.message)
                    .removeClass('text-danger text-success')
                    .addClass(data.success ? 'text-success' : 'text-danger')
                    .show();
            },
            'json'
        ).fail(function() {
            $btn.prop('disabled', false);
            $msg.text('{l s='Request failed.' mod='productbadges' js=1}')
                .addClass('text-danger').show();
        });
    });
}(jQuery));
</script>
