$(document).ready(function () {

    var blocksdisposition = $('#blocksdisposition');
    var site_settings_blocks = [];
    var blocks_title = blocksdisposition.data('block-titles');
    var available_modules_by_view = blocksdisposition.data('modules-by-view');

    $.each(blocks_title, function (key, val) {
        site_settings_blocks.push({
            'name': key,
            'title': val,
            'block_settings': '',
        });
    });

    $.each(site_settings_blocks, function (key, val) {
        if ($('#' + val.name).val().length <= 0) {
            return;
        }

        var block_settings = [];
        block_settings = $.parseJSON($('#' + val.name).val());
        // Remove empty values that may exist.
        block_settings = block_settings.filter(item => item);

        site_settings_blocks[key].block_settings = $('#' + val.name).val();
        var site_settings_blocks_html = '<div class="block-for js-' + val.name + '" data-block-name="' + val.name + '">' + "\n"
            + '  <div class="block_title">' + val.title + '</div>' + "\n"
            + '  <div class="block_buttons"></div>' + "\n"
            + '</div>' + "\n";

        // Make html block for available modules for the view.
        var available_modules_html = '<div>' + "\n";
        var appendFieldButton = function(key, val) {
            available_modules_html += '<div class="field js-module js-module-' + val + '">' + "\n"
                + '  <a class="button">' + val + '</a>' + "\n"
                + '  <div class="js-module-position">0</div>' + "\n"
                + '</div>' + "\n";
        };
        // Display ordered used modules first.
        $.each(block_settings, appendFieldButton);
        $.each(available_modules_by_view[val.name.substring(18)].filter(function(val) {return !block_settings.includes(val)}), appendFieldButton);
        available_modules_html += '</div>';

        blocksdisposition.append(site_settings_blocks_html);
        $('.js-' + val.name + ' .block_buttons').append(available_modules_html);

        // Set active buttons for block settings.
        var block_position = 0;
        $.each(block_settings, function (key, block_name) {
            ++block_position;
            $('.js-' + val.name + ' .block_buttons .js-module-' + block_name + ' a').addClass('active');
            $('.js-' + val.name + ' .block_buttons .js-module-' + block_name + ' .js-module-position').addClass('active').html(block_position);
        });
        $('.js-' + val.name).attr('data-count-selected', block_position);
    });

    blocksdisposition.find('.button').click(function () {
        $(this).toggleClass('active');
        $(this).parent().find('.js-module-position').toggleClass('active');
        var attr_block_name = $(this).parent().parent().parent().parent().attr('data-block-name');
        rerange_modules($(this).html(), attr_block_name);
    });

    function rerange_modules(clicked_module, attr_block_name) {
        var current_element = $('.js-' + attr_block_name + ' .js-module-' + clicked_module + ' .js-module-position');
        var all_element_in_block = $('.js-' + attr_block_name + ' .js-module-position');
        var count_selected = $('.js-' + attr_block_name).attr('data-count-selected');
        var new_block_value = [];

        if (current_element.hasClass('active')) {
            ++count_selected;
            current_element.html(count_selected);

            $.each(all_element_in_block, function (key, val) {
                var get_element_count = parseInt($(this).html());
                if (get_element_count > 0) {
                    new_block_value[get_element_count] = $(this).parent().find('a.button').html();
                }
            });
        } else {
            --count_selected;
            var current_element_value = current_element.html();
            current_element.html(0);

            $.each(all_element_in_block, function (key, val) {
                var get_element_count = parseInt($(this).html());
                if (get_element_count > current_element_value) {
                    --get_element_count;
                    $(this).html(get_element_count);
                }
                if (get_element_count > 0) {
                    new_block_value[get_element_count] = $(this).parent().find('a.button').html();
                }
            });
        }

        new_block_value = new_block_value.filter(item => item);
        $('#' + attr_block_name).val(new_block_value);
        $('.js-' + attr_block_name).attr('data-count-selected', count_selected);

        // Save the data via the sorted hidden checkboxes: simply set new names.
        var inputs = blocksdisposition.find('input[name="blocksdisposition[' + attr_block_name + '][]"]').closest('.field');
        // Set all values to false to avoid some checks.
        inputs.find('input.module-sort')
            .prop('checked', false);
        $.each(new_block_value, function (key, val) {
            inputs.find('input.module-sort')
                .slice(key, key + 1)
                .prop('checked', true).prop('value', val).html(val);
        });
        $.each(available_modules_by_view[attr_block_name.substring(18)], function (key, val) {
            if (new_block_value.indexOf(val) < 0) {
                inputs.find('input.module-sort')
                    .slice(key + new_block_value.length, key + 1 + new_block_value.length)
                    .prop('checked', false).prop('value', val).html(val);
            }
        });
    }

});
