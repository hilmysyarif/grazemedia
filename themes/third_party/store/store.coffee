###
Exp:resso Store module for ExpressionEngine (support@exp-resso.com)
Copyright (c) 2010-2013 Exp:resso
All rights reserved.
###

$ = window.jQuery
lib = window.ExpressoStore ?= {}
lib.products ?= {}

# format currency according to the current config
lib.formatCurrency = (value) ->
    options = $.extend({
        currencySymbol: '$',
        currencyDecimals: 2,
        currencyThousandsSep: ',',
        currencyDecPoint: '.',
        currencySuffix: ''
    }, lib.currencyConfig)

    parts = value.toFixed(options.currencyDecimals).split('.')
    out = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, options.currencyThousandsSep)
    out += options.currencyDecPoint + parts[1] if parts[1]?
    options.currencySymbol + out + options.currencySuffix

# convert a form into a useful hash (supports radios etc)
lib.serializeForm = (form) ->
    values = {}
    (values[elem.name] = elem.value) for elem in $(form).serializeArray()
    values

# find a sku for the current form state
lib.matchSku = (formdata) ->
    # check we have the necessary product data
    product = lib.products[formdata.entry_id]
    return false if not product

    # if there is only one sku, return it
    return product.stock[0] if product.stock.length == 1

    # loop through modifiers, and match them to skus
    for item in product.stock
        match = true

        # are there any modifiers which don't match this sku?
        for mod_id, opt_id of item.opt_values
            if formdata["modifiers_"+mod_id] != opt_id.toString()
                match = false
                break

        # found the correct sku
        return item if match

    false

# calculate the price for the current form state
lib.calculatePrice = (formdata) ->
    # check we have the necessary product data
    product = lib.products[formdata.entry_id]
    return false if not product

    # add any applicable modifiers
    price = product.price
    for mod_id, modifier of product.modifiers
        opt_value = formdata["modifiers_"+mod_id]
        option = modifier.options[opt_value]
        price += option.opt_price_mod_val if option

    return price;

# update magic product classes
lib.updateSku = () ->
    sku = stock_level = ""
    in_stock = true

    # find the currently selected sku
    formdata = lib.serializeForm(this.form)
    skudata = lib.matchSku(formdata)
    if skudata
        sku = skudata.sku
        if skudata.track_stock == "y"
            stock_level = skudata.stock_level
            in_stock = false if stock_level <= 0

    # update the classes
    form = $(this.form)
    $(".store_product_sku", form).val(sku).text(sku).trigger("change")
    $(".store_product_stock", form).val(stock_level).text(stock_level).trigger("change")
    $(".store_product_in_stock", form).toggle(in_stock)
    $(".store_product_out_of_stock", form).toggle(!in_stock)

    # calculate the current price
    price = lib.calculatePrice(formdata)
    if (price != false)
        price_str = lib.formatCurrency(price)
        price_inc_tax = price * (1 + lib.cart.tax_rate)
        price_inc_tax_str = lib.formatCurrency(price_inc_tax)
        $(".store_product_price_val", form).val(price).text(price).trigger("change")
        $(".store_product_price", form).val(price_str).html(price_str).trigger("change")
        $(".store_product_price_inc_tax_val", form).val(price_inc_tax).text(price_inc_tax).trigger("change")
        $(".store_product_price_inc_tax", form).val(price_inc_tax_str).html(price_inc_tax_str).trigger("change")

# dynamically populate region select menus
lib.changeRegionSelect = (countryCode, regionElem) ->
    regions = lib.countries[countryCode].regions
    regionElem.empty()
    for region_id, region_name of regions
        regionElem.append('<option value="'+region_id+'">'+region_name+'</option>')

    if regionElem.children().size() == 0
        regionElem.append('<option></option>')

    regionElem.trigger('change')

# register change handlers
$ ->
    if lib.products
        $(document).delegate('.store_product_form [name^="modifiers"]:not(:radio)', 'change', lib.updateSku)
            .delegate('.store_product_form [name^="modifiers"]:radio', 'click', lib.updateSku)
        $('.store_product_form input:first').each(lib.updateSku)

    if lib.countries
        $("select[name=billing_country]").change ->
            lib.changeRegionSelect $(this).val(), $("select[name=billing_region]")
        $("select[name=shipping_country]").change ->
            lib.changeRegionSelect $(this).val(), $("select[name=shipping_region]")
