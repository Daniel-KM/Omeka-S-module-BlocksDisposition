$(document).ready(function () {

    var site_settings_blocks = [];
    var blocks_title = $.parseJSON($('.blocks_title').val());

    $.each(blocks_title, function (key, val) {
        site_settings_blocks.push({
            'name': key,
            'title': val,
            'block_settings': '',
        });
    });

    $('.blocksdisposition_modules_from_config').parent().addClass('blocksdisposition-module-group-fields');

    var available_modules = $.parseJSON($('.blocksdisposition_modules_from_config').val());
    // Make html block for available modules.
    var available_modules_html = '<div>';

    $.each(available_modules, function (key, val) {
        available_modules_html += '<div class="field js-module-' + val + '"><a class="button">' + val + '</a><div class="js-module-position">0</div></div>';
    })
    available_modules_html += '</div>';

    $.each(site_settings_blocks, function (key, val) {
        var block_settings = [];
        if ($('.' + val.name).val().length > 0) {
            block_settings = $.parseJSON($('.' + val.name).val());
        }

        site_settings_blocks[key].block_settings = $('.' + val.name).val();

        var site_settings_blocks_html = '<div class="block-for js-' + val.name + '" attr-block-name="' + val.name + '">';
        site_settings_blocks_html += '<div class="block_title">' + val.title + '</div>';
        site_settings_blocks_html += '<div class="block_buttons"></div>';
        site_settings_blocks_html += '</div>';

        $('.blocksdisposition-module-group-fields').append(site_settings_blocks_html);
        $('.js-' + val.name + ' .block_buttons').append(available_modules_html);

        // Set active buttons for block settings.
        var block_position = 0;
        $.each(block_settings, function (key, block_name) {
            ++block_position;
            $('.js-' + val.name + ' .block_buttons .js-module-' + block_name + ' a').addClass('active');
            $('.js-' + val.name + ' .block_buttons .js-module-' + block_name + ' .js-module-position').addClass('active').html(block_position);
        });
        $('.js-' + val.name).attr('attr-count-selected', block_position);
    });

    $('.blocksdisposition-module-group-fields .button').click(function () {
        $(this).toggleClass('active');
        $(this).parent().find('.js-module-position').toggleClass('active');
        var attr_block_name = $(this).parent().parent().parent().parent().attr('attr-block-name');
        rerange_modules($(this).html(), attr_block_name);
    });

    function rerange_modules(clicked_module, attr_block_name) {
        var current_element = $('.js-' + attr_block_name + ' .js-module-' + clicked_module + ' .js-module-position');
        var all_element_in_block = $('.js-' + attr_block_name + ' .js-module-position');
        var attr_count_selected = $('.js-' + attr_block_name).attr('attr-count-selected');
        var new_block_value = [];

        if (current_element.hasClass('active')) {
            ++attr_count_selected;
            current_element.html(attr_count_selected);

            $.each(all_element_in_block, function (key, val) {
                var get_element_count = parseInt($(this).html());
                if (get_element_count > 0) {
                    new_block_value[get_element_count] = $(this).parent().find('a.button').html();
                }
            });
        } else {
            --attr_count_selected;
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

        new_block_value.shift();
        var json_string = JSON.stringify(new_block_value);
        $('.'+attr_block_name).val(json_string);
        $('.js-' + attr_block_name).attr('attr-count-selected', attr_count_selected);
    }

});
