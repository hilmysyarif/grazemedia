/*
 * Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
 * Copyright (c) 2010-2013 Exp:resso
 * All rights reserved.
 */

// @codekit-prepend "store.js"
// @codekit-prepend "vendor/jquery.dataTables.js"
// @codekit-prepend "vendor/jquery.flot.js"
// @codekit-prepend "vendor/excanvas.js"

window.ExpressoStore = window.ExpressoStore || {};

window.ExpressoStore.dashboardGraph = function(plotLabel, plotData) {
    var options = {
        legend: { show: true, margin: 10, backgroundOpacity: 0.5 },
        points: { show: true, radius: 3 },
        lines: { show: true },
        grid: { borderWidth: 0, hoverable: true },
        xaxis: { },
        yaxis: { min: 0 }
    };

    var placeholder = $('#store_graph');
    $.plot(placeholder, [{ label: plotLabel, data: plotData }], options);

    var previousPoint = null;
    placeholder.bind('plothover', function (event, pos, item) {
        if (item) {
            if (previousPoint !== item.datapoint[0]) {
                previousPoint = item.datapoint[0];
                $(".store_dashboard_tooltip").remove();
                $('<div class="store_dashboard_tooltip">'+window.ExpressoStore.formatCurrency(item.datapoint[1])+'</div>')
                    .css({ position: "absolute", display: "none", top: item.pageY+15, left: item.pageX+10 })
                    .appendTo("body").fadeIn("fast");
            }
        }
        else {
            previousPoint = null;
            $(".store_dashboard_tooltip").fadeOut("fast", function() { $(this).remove(); });
        }
    });

    placeholder.bind('mouseout', function() {
        previousPoint = null;
        $(".store_dashboard_tooltip").fadeOut("fast", function() { $(this).remove(); });
    });
};

jQuery(function($) {

    /*
     * Orders Page / Tables
     */

    $('#filterform select, #filterform input:checkbox').change(function() {
        $('.store_datatable').dataTable().fnDraw();
    });


    $('#filterform input:text').bind('keyup blur paste', function() {
        $('.store_datatable').dataTable().fnDraw();
    });

    $('#checkall').change(function() {
        $('.mainTable input:checkbox[name="selected[]"]').attr('checked', this.checked)
            .closest('tr').toggleClass('store_selected', this.checked);
    });

    $('.mainTable input:checkbox[name="selected[]"]').live('change', function() {
        $(this).closest('tr').toggleClass('store_selected', this.checked);
    });

    /*
     * Datepicker used on various pages
     */

    // check jQuery Datepicker plugin has been loaded
    if ($('#mainContent input.store_datetimepicker').size() > 0) {
        var date_obj = new Date();
        var date_obj_hours = date_obj.getHours();
        var date_obj_mins = date_obj.getMinutes();
        var date_obj_am_pm = " AM";

        if (date_obj_mins < 10) { date_obj_mins = "0" + date_obj_mins; }

        if (date_obj_hours > 11) {
            date_obj_hours = date_obj_hours - 12;
            date_obj_am_pm = " PM";
        }

        var date_obj_time = " '"+date_obj_hours+":"+date_obj_mins+date_obj_am_pm+"'";

        $('#mainContent input.store_datetimepicker').datepicker({dateFormat: $.datepicker.W3C + date_obj_time});
    }

    if ($('#mainContent input.store_datepicker').size() > 0) {
        $('#mainContent input.store_datepicker').datepicker({dateFormat: $.datepicker.W3C});
    }

    /*
     * Country/Region multiple select boxes used on various pages
     */

    if ($('#mainContent select.store_country_select').size() > 0)
    {
        $('#mainContent select.store_country_select').change(function() {
            var region_select_name = $(this).attr('name').replace('[country_code]', '[region_code]');
            var region_select = $('#mainContent select[name="'+region_select_name+'"]');

            if ($(this).data('oldVal') !== $(this).val()) {
                // remove all but first entry
                region_select.html(region_select.find('option[value="*"]'));

                // populate new regions
                var region_list = window.ExpressoStore.countries[$(this).val()];
                if (typeof(region_list) !== 'undefined') {
                    for (var property in region_list.regions) {
                        region_select.append('<option value="'+property+'">'+region_list.regions[property]+'</option>');
                    }
                }

                // save current value
                $(this).data('oldVal', $(this).val());
            }
        });
    }

    /*
     * Datatables functions
     */

    if ($('#mainContent .store_datatable').size() > 0)
    {
        /* Formatting function for row details */
        var fnFormatDetails = function(oTable, nTr)
        {
            var aData = oTable.fnGetData( nTr );
            var sOut = aData[aData.length-1];
            return sOut;
        };

        var fnToggleHiddenRow = function() {
            var tr = $(this).parent();
            var img = tr.children('td').children('a').children('img');

            if (tr.attr('class').indexOf('expand') >= 0)
            {
                /* This row is already open - close it */
                img.attr('src', window.EE.PATH_CP_GBL_IMG + 'expand.gif');
                tr.removeClass('expand');
                tr.addClass('collapse');
                tr.next('tr').find('.store_datatables_details_row').slideUp('fast', function() {
                    window.oTable.fnClose(tr.get(0));
                });
            }
            else
            {
                /* Open this row */
                img.attr('src', window.EE.PATH_CP_GBL_IMG + 'collapse.gif');
                tr.removeClass('collapse');
                tr.addClass('expand');
                var newTr = window.oTable.fnOpen(this.parentNode, fnFormatDetails(window.oTable, this.parentNode), 'details');
                var newTrCols = $(newTr).children('td').attr('colspan');
                $(newTr).children('td').attr('colspan', newTrCols - 2);
                $(newTr).prepend('<td colspan="2"></td>');
                $(newTr).find('.store_datatables_details_row').slideDown('fast');
            }
            return false;
        };

        $('.store_datatable tbody tr td.clickable').live('click', fnToggleHiddenRow);

        var fnToggleAll = function() {
                var img = $(this).children('img');

                if (img.attr('src').indexOf('expand') >= 0)
                {
                    img.attr('src', window.EE.PATH_CP_GBL_IMG + 'collapse.gif');
                    $('.store_datatable tbody tr.collapse td.clickable:first-child').click();
                }
                else
                {
                    img.attr('src', window.EE.PATH_CP_GBL_IMG + 'expand.gif');
                    $('.store_datatable tbody tr.expand td.clickable:first-child').click();
                }
                return false;
        };

        $('.store_datatable thead tr th a[id=all]').live('click', fnToggleAll);

        /*
         * Insert a 'details' column to the table
         */
        var nCloneTd = document.createElement('td');
        $('.store_datatable tbody tr').each( function () {
            this.insertBefore(nCloneTd.cloneNode(true), this.childNodes[0] );
        } );
    }

    /*
     * Status changing
     */
    $('.cancel_edit_status').click(function() {
        $(this).closest('td').find('.edit_status_info').slideUp('fast');
        $(this).hide();
        $(this).prev('.edit_status').show();
        return false;
    });
    $('.edit_status').click(function() {
        $(this).closest('td').find('.edit_status_info').slideDown('fast');
        $(this).next('.cancel_edit_status').show();
        $(this).hide();
        return false;
    });

    $('input[data-store-confirm]').click(function() {
        return window.confirm($(this).attr('data-store-confirm'));
    });

    /*
     * Reports page
     */
    $('#store_report_list .store_date_range_select').change(function() {
        if ($(this).val() === 'custom_range') {
            $(this).siblings('.custom_date_range').show();
        } else {
            $(this).siblings('.custom_date_range').hide();
        }
    });

    /*
     * Payment Methods settings
     */
    $('#payment_method_class').change(function() {
        var driver_class = $(this).val();
        // hide and disable all driver settings
        $('.payment_driver_settings').hide().find(':input').attr('disabled', true);
        if (driver_class) {
            // show and enable new driver
            $('#'+driver_class+'_settings').show().find(':input').attr('disabled', false);
            // update name and title fields
            $('#payment_method_title').val(window.ExpressoStore.payment_drivers[driver_class].title);
            $('#payment_method_name').val(window.ExpressoStore.payment_drivers[driver_class].name);
        }
    }).change();

    /*
     * Sortable Tables
     */
    if ($('.store_sortable_table').size() > 0)
    {
        $('.store_sortable_table').sortable({ handle: '.store_handle' });

        var reorderDisplayNums = function() {
            var rows = $('.store_sortable_table').children();
            //Iterate through the table rows to reset each row's hidden new display value
            var ordering_data= "";
            for (var i = 0; i < rows.length; i++){
                var d = $(rows[i]);
                d.attr("data-order", i);

                if (i > 0) {
                    ordering_data += ", ";
                }
                ordering_data += d.attr("id")+" => "+d.attr("data-order");
            }

            var URL = window.EE.BASE+"&C=addons_modules&M=show_module_cp&module=store&method=ajax_reorder&table="+$('.store_sortable_table').attr('id');
            $.ajax({
                url: URL,
                data: {order: ordering_data},
                dataType: "json"
            });
        };

        var initialiseTable = function() {
            $(".store_sortable_table").sortable({
                update: function() { reorderDisplayNums(); }
            });
        };

        initialiseTable();
    }

    /*
     * Publish field
     */
    if ($('#store_product_field').size() > 0)
    {
        var modifiersTable = $('#store_product_modifiers_table');

        // toggle field panes
        $('#store_product_field label.store_hide_field').click(function() {
            var img = $(this).children('img');
            if (img.attr('src').indexOf('field_collapse') > 0)
            {
                img.attr('src', img.attr('src').replace('field_collapse', 'field_expand'));
                $(this).next('.store_field_pane').slideDown();
            } else {
                img.attr('src', img.attr('src').replace('field_expand', 'field_collapse'));
                $(this).next('.store_field_pane').slideUp();
            }
        });

        // drag & drop reordering
        var updateModifierOrder = function(el) {
            $('input.store_input_mod_order', el).each(function(index) {
                $(this).val(index);
            });
        };
        var updateOptionOrder = function(el) {
            $('input.store_input_opt_order', el).each(function(index) {
                $(this).val(index);
            });
        };
        modifiersTable.sortable({
            items: '> tbody',
            handle: '.store_modifier_handle',
            placeholder: 'store_ft_placeholder',
            forceHelperSize: true,
            forcePlaceholderSize: true,
            update: function() {
                updateModifierOrder(this);
            }
        });
        var sortableOptions = function(el) {
            el.find('.store_product_options_table > tbody').sortable({
                handle: '.store_option_handle',
                placeholder: 'store_ft_placeholder',
                forceHelperSize: true,
                forcePlaceholderSize: true,
                update: function() {
                    updateOptionOrder(this);
                }
            });
        };
        sortableOptions(modifiersTable);

        // store new option template and handle add option events
        var opt_template = $('#store_product_modifier_template tr.store_product_option_row').clone();
        opt_template.attr('style', 'display: none');

        modifiersTable.delegate('a.store_product_option_add', 'click', function() {
            var mod_key = $(this).attr('data-mod-key');
            var new_opt_key = parseInt($(this).attr('data-new-opt-key'), 10);
            var opt_prefix = '[modifiers]['+mod_key+'][options]['+new_opt_key+']';

            var template = opt_template.clone();
            template.find('[name]').each(function() {
                $(this).attr('name', $(this).attr('name').replace('[modifiers][new][options][1]', opt_prefix));
            });
            var wrapper = $(this).closest('td');
            wrapper.find('.store_product_options_table tbody').append(template);
            template.fadeIn();
            updateOptionOrder(wrapper);
            template.find('input:text:first').focus();

            $(this).attr('data-new-opt-key', new_opt_key+1);
            return false;
        });

        // store product modifier template and handle add modifier events
        var mod_template = $('#store_product_modifier_template').removeAttr('id');
        mod_template.remove();

        $('#store_product_modifiers_add').click(function() {
            var new_mod_key = parseInt($(this).attr('data-new-mod-key'), 10);
            var mod_prefix = '[modifiers]['+new_mod_key+']';
            var template = mod_template.clone();

            template.find('[name]').each(function() {
                $(this).attr('name', $(this).attr('name').replace('[modifiers][new]', mod_prefix));
            });
            template.find('a.store_product_option_add').attr('data-mod-key', new_mod_key);
            $('#store_product_modifier_empty').hide();
            modifiersTable.append(template);
            template.fadeIn();
            updateModifierOrder(modifiersTable);
            sortableOptions(template);
            template.find('input:text:first').focus();

            $(this).attr('data-new-mod-key', new_mod_key+1);
            return false;
        });

        var reloadStockTable = function() {
            $('#store_product_stock_loading').show();
            // don't serialize ACT attribute, otherwise it overrides our URL action
            var act = $('#publishForm [name="ACT"]').attr('disabled', true);
            var data = $('#publishForm').serialize();
            act.attr('disabled', false);
            $('#store_product_stock input').attr('disabled', true);

            $.post(window.ExpressoStore.fieldStockUrl, data).done(function(content) {
                $('#store_product_stock').empty();
                $('#store_product_stock').html(content);
                $('#store_product_stock_loading').hide();
            });
        };

        modifiersTable.delegate('.store_select_mod_type', 'change', function() {
            $(this).closest('tr').find('.store_product_options_wrap').toggle(
                $(this).val() === 'var' || $(this).val() === 'var_single_sku'
            );
            reloadStockTable();
        });

        modifiersTable.delegate('.store_input_mod_name, .store_input_opt_name', 'change', function() {
            reloadStockTable();
        });

        modifiersTable.delegate('a.store_product_modifier_remove', 'click', function() {
            $(this).closest('tbody').fadeOut(function() {
                $(this).remove();
                reloadStockTable();

                if (modifiersTable.find('tbody.store_product_modifier').size() === 0)
                {
                    $('#store_product_modifier_empty').show();
                }
            });
            return false;
        });

        modifiersTable.delegate('a.store_product_option_remove', 'click', function() {
            $(this).closest('tr').fadeOut(function() {
                $(this).remove();
                reloadStockTable();
            });
            return false;
        });

        $('#store_product_field').delegate('td.store_ft_text', 'click', function(ev) {
            // if input element was clicked it will already be focused
            if (ev.target === this) {
                var inputElement = $(this).find('input:text:enabled').first();
                inputElement.focus().val(inputElement.val()); // prevents highlighting text
            }
        });
        $('#store_product_field').delegate('td.store_ft_text', 'keydown', function(ev) {
            if (ev.which === 13) { return false; }
        });
        $('#store_product_field').delegate('td.store_ft_text', 'focusin', function() {
            $(this).addClass('store_ft_focus');
        });
        $('#store_product_field').delegate('td.store_ft_text', 'focusout', function() {
            $(this).removeClass('store_ft_focus');
        });

        //Toggle stock level enable/disable based on stock tracking option
        $('#store_product_stock .store_track_stock input:checkbox').live('change', function() {
            var stock_level_elem = $(this).closest('td').find('input:text');
            stock_level_elem.attr('disabled', !this.checked).toggleClass('disabled', !this.checked);
            if (this.checked) {
                stock_level_elem.focus();
            }
        });

        $('#store_product_stock .checkall_stock_publish').live('change', function() {
            $(this).closest('table').find('.store_track_stock input:checkbox')
                .attr('checked', this.checked).trigger('change');
        });
    }
});
