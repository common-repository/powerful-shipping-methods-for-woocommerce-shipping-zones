jQuery(document).ready(function ($) {

    var first_display_condition = true;
    var first_display_cost = true;
    var parent = null;
    function updateConditionsAndCosts() {
        var distanceId = parent.find('.distance-id').val();
        var conditionsAndCosts = powerful_shipping_method_settings.if;
        first_display_condition = true;
        conditionsAndCosts = conditionsAndCosts + displayCondition(powerful_shipping_method_settings.order_total, 'order_total', powerful_shipping_method_settings.currencySymbol, false, distanceId, powerful_shipping_method_settings.currencySymbol);
        conditionsAndCosts = conditionsAndCosts + displayCondition(powerful_shipping_method_settings.weight, 'weight', powerful_shipping_method_settings.kg, true, distanceId, powerful_shipping_method_settings.kg);
        conditionsAndCosts = conditionsAndCosts + displayCondition(powerful_shipping_method_settings.volume, 'volume', powerful_shipping_method_settings.cubicCm, true, distanceId, powerful_shipping_method_settings.cubicCm);
        conditionsAndCosts = conditionsAndCosts + displayCondition(powerful_shipping_method_settings.dimensional_weight, 'dimensional_weight', powerful_shipping_method_settings.cubicCm, true, distanceId, powerful_shipping_method_settings.cubicCm + '/' + powerful_shipping_method_settings.kg);
        conditionsAndCosts = conditionsAndCosts + displayCondition(powerful_shipping_method_settings.quantity, 'quantity', powerful_shipping_method_settings.cubicCm, true, distanceId, powerful_shipping_method_settings.products);
        if (conditionsAndCosts == powerful_shipping_method_settings.if)
            conditionsAndCosts = conditionsAndCosts + powerful_shipping_method_settings.noConditions;
        conditionsAndCosts = conditionsAndCosts + powerful_shipping_method_settings.thenCharge;
        first_display_cost = true;
        var costs = '';
        var fee = parent.find('.fee').val();
        if (fee != '' && fee != 0) {
            first_display_cost = false;
            costs = costs + powerful_shipping_method_settings.currencySymbol + fee;
        }
        costs = costs + displayCost(powerful_shipping_method_settings.order_total, 'order_total', powerful_shipping_method_settings.currencySymbol, false, powerful_shipping_method_settings.currencySymbol, distanceId);
        costs = costs + displayCost(powerful_shipping_method_settings.weight, 'weight', powerful_shipping_method_settings.kg, true, powerful_shipping_method_settings.kg, distanceId);
        costs = costs + displayCost(powerful_shipping_method_settings.volume, 'volume', powerful_shipping_method_settings.cubicCm, true, powerful_shipping_method_settings.cubicCm, distanceId);
        costs = costs + displayCost(powerful_shipping_method_settings.dimensional_weight, 'dimensional_weight', powerful_shipping_method_settings.cubicCm + '/' + powerful_shipping_method_settings.kg, true, powerful_shipping_method_settings.cubicCm + '/' + powerful_shipping_method_settings.kg, distanceId);
        costs = costs + displayCost(powerful_shipping_method_settings.quantity, 'quantity', powerful_shipping_method_settings.products, true, powerful_shipping_method_settings.products, distanceId);
        if (costs == '')
            costs = powerful_shipping_method_settings.currencySymbol + '0';
        costs = costs + powerful_shipping_method_settings.forShipping;
        conditionsAndCosts = conditionsAndCosts + costs;
        parent.find('.conditions-and-costs').html(conditionsAndCosts);
    }

    $(document.body).on('click', '.show-advanced-rule-settings', function () {
        var rule = $(this).closest('.distance-row');
        rule.find('.hide-advanced-rule-settings').show();
        rule.find('.advanced-rule-settings').show();
        $(this).hide();
    });

    $(document.body).on('click', '.hide-advanced-rule-settings', function () {
        var rule = $(this).closest('.distance-row');
        rule.find('.show-advanced-rule-settings').show();
        rule.find('.advanced-rule-settings').hide();
        $(this).hide();
    });

    $('.hide-advanced-rule-settings').hide();
    $(document.body).on('wc_backbone_modal_loaded', function () {
        $('.hide-advanced-rule-settings').hide();
        $('.distance-row').each(function () {
            parent = $(this);
            updateConditionsAndCosts();
            validateDeliveryRates();
        });
        updateRuleNumbers();
        $('#woocommerce_distance_rate_shipping_google_api_key').closest('tr').css('word-break', 'break-all');
    });
    $(document.body).on('change', '.distance-row input, .distance-row select', function () {
        parent = $(this).closest('.distance-row');
        updateConditionsAndCosts();
        validateDeliveryRates();
    });

    $('.distance-row').each(function () {
        parent = $(this);
        updateConditionsAndCosts();
        validateDeliveryRates();
    });

    function displayCondition(label, name, unit, unit_after, distance_rate_id, unit_plural) {
        var minimum = parent.find('.minimum_' + name).val();
        var maximum = parent.find('.maximum_' + name).val();
        if (minimum == '' && maximum == '')
            return '';

        var condition = '';
        var after = '';
        var before = '';
        if (unit_after)
            after = ' ' + unit_plural;
        else
            before = unit_plural;
        if (first_display_condition)
            first_display_condition = false;
        else
            condition = powerful_shipping_method_settings.and + condition;

        if (minimum != '' && maximum != '') {
            condition = condition + label + powerful_shipping_method_settings.isBetween + before + minimum + after + powerful_shipping_method_settings.and + before + maximum + after;
        } else if (minimum != '') {
            condition = condition + label + powerful_shipping_method_settings.isAbove + before + minimum + after;
        } else if (maximum != '') {
            condition = condition + label + powerful_shipping_method_settings.isBelow + before + maximum + after;
        }
        return condition;
    }

    function displayCost(label, name, unit, unit_after, plural_unit, distance_id) {
        var fee_per = '';
        var value = parent.find('.fee_per_' + name).val();
        if (value != '' && value != 0) {
            var after = '';
            var before = '';
            if (unit_after)
                after = ' ' + plural_unit;
            else
                before = plural_unit;
            if (first_display_cost)
                first_display_cost = false;
            else
                fee_per = powerful_shipping_method_settings.plus + fee_per;
            var starting_from = 0;
            if (parent.find('.starting_from_' + name).val() == 'minimum')
                starting_from = parent.find('.minimum_' + name).val();
            fee_per = fee_per + powerful_shipping_method_settings.currencySymbol + value + powerful_shipping_method_settings.per + unit.replace('lbs', 'lb');
            if (starting_from > 0)
                fee_per = fee_per + powerful_shipping_method_settings.startingFrom + before + starting_from + after;
        }
        return fee_per;
    }

    function updateRuleNumbers() {
        var rule = 1;
        $('.rule-number').each(function () {
            $(this).html(rule);
            rule = rule + 1;
        });
        $('#woocommerce_distance_rate_shipping_use_distance, #woocommerce_zone_distance_rate_shipping_use_distance').change();
    }
    updateRuleNumbers();

    var maxId = -1;
    $('.distance-rate-shipping-rates-row-id').each(function () {
        var id = parseFloat($(this).val());
        if (id > maxId) {
            maxId = id;
        }
    });
//Add a new line to the delivery rates
    function addNewLine() {
        $('.distance-rate-shipping-rates-row-id').each(function () {
            var id = parseFloat($(this).val());
            if (id > maxId) {
                maxId = id;
            }
        });
        maxId = maxId + 1;
        var lineToAdd = powerful_shipping_method_settings.new_row;
        lineToAdd = lineToAdd.replace(new RegExp("newRatenewRate", "g"), maxId);
        $('#rules').append(lineToAdd);
        $('.distance-row-' + maxId).hide(0);
        $('.distance-row-' + maxId).show(1000);
        parent = $('.distance-row-' + maxId);
        updateConditionsAndCosts();
        updateRuleNumbers();
    }

//Check that the maximum order total is more than the minimum order total etc
    function validateDeliveryRates() {
        var isValid = true;
        $('.distance-rate-error').remove();
        $('.numeric').each(function () {
            if ($(this).val() == '') {
            }
            else if (!$.isNumeric($(this).val())) {
                isValid = false;
                $(this).after('<span class="distance-rate-error">' + powerful_shipping_method_settings.numeric_error + '</span>');
            }
        });
        if (isValid) {
            $('.row-container input.minimum').each(function () {
                var min = parseInt($(this).val());
                var max = parseInt($(this).closest('tr').find('input.maximum').val());
                if (min != '' && max != '' && max < min) {
                    isValid = false;
                    $(this).after('<span class="distance-rate-error">' + powerful_shipping_method_settings.minimum_maximum_error + '</span>');
                }
            });

        }

        if (!isValid) {
            $('input[name=save]').after('<span class="distance-rate-error">' + powerful_shipping_method_settings.correct_errors + '</span>');
        }
        return isValid;
    }

    $(document.body).on('click', 'input[name=save]', function () {
        return validateDeliveryRates();
    });
    //Add a blank line if there are no lines
    if (maxId == -1)
        addNewLine();
    //For validation
    $(document.body).on('change', '#distance-rate-shipping-rates input[type=text]', function () {
        $('.distance-rate-shipping-error').remove();
        if (!$.isNumeric($(this).val())) {
            $(this).after('<span class="distance-rate-shipping-error">' + powerful_shipping_method_settings.numeric_error + '</span>');
        }
    });

    //The add button
    $(document.body).on('click', '.add-distance-rate', function () {
        addNewLine();
        $('.hide-advanced-rule-settings').hide();
        return false;
    });

    //The remove button
    $(document.body).on('click', '.remove-distance-rate', function () {
        var rowToRemove = $(this).closest('.distance-row');
        rowToRemove.addClass('remove-this-row');
        rowToRemove.hide(1000);
        setTimeout(function () {
            $('.remove-this-row').remove();
        }, 1000);
        return false;
    });

    $(document.body).on('wc_backbone_modal_before_update', function () {
        if ($('.distance-row').length > 0) {
            var serializedRules = $('#rules').find(':input[name]').serialize();
            $('#rules').closest('form').append('<input type="hidden" name="distance_rates" value="' + serializedRules + '" />');
        }
    });

});