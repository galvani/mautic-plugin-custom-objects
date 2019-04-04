// Init stuff on refresh:
mQuery(function() {
    CustomObjects.onCampaignEventModalLoaded(function() {
        CustomObjects.initCustomItemTypeaheadsOnCampaignEventForm();
    })
});

CustomObjects = {

    onCampaignEventModalLoaded(callback) {
        mQuery(document).ajaxComplete(function(event, request, settings) {
            if (settings.type === 'GET' && settings.url.indexOf('s/campaigns/events') >= 0) {
                callback(event, request, settings);
            }
        })
    },

    initCustomFieldConditions() {
        let form = mQuery('form[name=campaignevent]');
        let fieldSelect = form.find('#campaignevent_properties_field');
        let operatorSelect = form.find('#campaignevent_properties_operator');

        CustomObjects.updateFormFieldOptions(fieldSelect, operatorSelect);

        fieldSelect.on('change', function() {
            CustomObjects.updateFormFieldOptions(fieldSelect, operatorSelect);
        });

        operatorSelect.on('change', function() {
            CustomObjects.updateFormFieldOptions(fieldSelect, operatorSelect);
        });
    },

    updateFormFieldOptions(fieldSelect, operatorSelect) {
        let valueField = mQuery('#campaignevent_properties_value');
        let operators = JSON.parse(fieldSelect.find(':selected').attr('data-operators'));
        let options = JSON.parse(fieldSelect.find(':selected').attr('data-options'));
        let selectedOperator = operatorSelect.find(':selected').attr('value');
        let isEmptyOperator = selectedOperator === 'empty' || selectedOperator === '!empty';
        let valueFieldAttrs = {
            'class': valueField.attr('class'),
            'id': valueField.attr('id'),
            'name': valueField.attr('name'),
            'autocomplete': valueField.attr('autocomplete'),
            'value': valueField.attr('value')
        };

        operatorSelect.empty();

        for (let operatorKey in operators) {
            let option = mQuery('<option/>').attr('value', operatorKey).text(operators[operatorKey]);
            if (operatorKey == selectedOperator) {
                option.attr('selected', true);
            }
            operatorSelect.append(option);
        }

        Mautic.destroyChosen(valueField);

        let newValueField = mQuery('<input/>').attr('type', 'text');

        if (options.length && !isEmptyOperator) {
            newValueField = mQuery('<select/>');
            for (let optionKey in options) {
                let optionData = options[optionKey];
                let option = mQuery("<option></option>")
                    .attr('value', optionData.value)
                    .text(optionData.label);
                if (valueField.attr('value') == optionData.value) {
                    option.attr('selected', true);
                }
                newValueField.append(option);
            };
        }

        if (isEmptyOperator) {
            newValueField.attr('readonly', true);
        } else {
            newValueField.attr('value', valueFieldAttrs['value']);
        }

        newValueField.attr(valueFieldAttrs);
        valueField.replaceWith(newValueField);

        if (valueField.is('select')) {
            // I would love this to work, but Chosen doesn't want to initialize on this select...
            Mautic.activateChosenSelect(valueField);
        }
        operatorSelect.trigger("chosen:updated");
    },

    // Called from tab content HTML:
    initContactTabForCustomObject(customObjectId) {
        let contactId = mQuery('input#leadId').val();
        let selector = CustomObjects.createTabSelector(customObjectId, '[data-toggle="typeahead"]');
        let input = mQuery(selector);
        CustomObjects.initCustomItemTypeahead(input, customObjectId, contactId, function(selectedItem) {
            CustomObjects.linkContactWithCustomItem(contactId, selectedItem.id, function() {
                CustomObjects.reloadItemsTable(customObjectId, contactId);
                input.val('');
            });
        });
        CustomObjects.reloadItemsTable(customObjectId, contactId);
    },

    initCustomItemTypeaheadsOnCampaignEventForm() {
        let typeaheadInputs = mQuery('input[data-toggle="typeahead"]');
        typeaheadInputs.each(function(i, nameInputHtml) {
            let nameInput = mQuery(nameInputHtml);
            let customObjectId = nameInput.attr('data-custom-object-id');
            let idInput = mQuery(nameInput.attr('data-id-input-selector'));
            CustomObjects.initCustomItemTypeahead(nameInput, customObjectId, null, function(selectedItem) {
                idInput.val(selectedItem.id);
                CustomObjects.addIconToInput(nameInput, 'check');
            });
            nameInput.on('blur', function() {
                if (!nameInput.val()) {
                    idInput.val('');
                    CustomObjects.removeIconFromInput(nameInput);
                }
            });
            if (idInput.val()) {
                CustomObjects.addIconToInput(nameInput, 'check');
            }
        })
    },

    addIconToInput(input, icon, spinIt) {
        CustomObjects.removeIconFromInput(input);
        let id = input.attr('id')+'-input-icon';
        let formGroup = input.closest('.form-group');
        let iconEl = mQuery('<span/>').addClass('fa fa-'+icon+' form-control-feedback');
        let ariaEl = mQuery('<span/>').addClass('sr-only').text('('+icon+')').attr('id', id);
        if (spinIt) {
            iconEl.addClass('fa-spin');
        }
        formGroup.addClass('has-feedback');
        input.attr('aria-describedby', id);
        formGroup.append(iconEl);
        formGroup.append(ariaEl);
    },

    removeIconFromInput(input) {
        let formGroup = input.closest('.form-group');
        formGroup.find('.form-control-feedback').remove();
        formGroup.find('.sr-only').remove();
        input.removeAttr('aria-describedby');
        formGroup.removeClass('has-feedback');
    },

    reloadItemsTable(customObjectId, contactId) {
        CustomObjects.getItemsForObject(customObjectId, contactId, function(response) {
            CustomObjects.refreshTabContent(customObjectId, response.newContent);
        });
    },

    initCustomItemTypeahead(input, customObjectId, contactId, onSelectCallback) {
        // Initialize only once
        if (input.attr('data-typeahead-initialized')) {
            return;
        }

        input.attr('data-typeahead-initialized', true);
        let url = input.attr('data-action');
        let customItems = new Bloodhound({
            datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value', 'id'),
            queryTokenizer: Bloodhound.tokenizers.whitespace,
            remote: {
                url: url+'?filter=%QUERY&contactId='+contactId,
                wildcard: '%QUERY',
                ajax: {
                    beforeSend: function() {
                        CustomObjects.addIconToInput(input, 'spinner', true);
                    },
                    complete: function() {
                        CustomObjects.removeIconFromInput(input);
                    }
                },
                filter: function(response) {
                    return response.items;
                },
            }
        });

        customItems.initialize();

        input.typeahead({
            minLength: 0,
            highlight: true,
        }, {
            name: 'custom-items-'+customObjectId+'-'+contactId,
            display: 'value',
            source: customItems.ttAdapter()
        }).bind('typeahead:selected', function(e, selectedItem) {
            if (!selectedItem || !selectedItem.id) return;
            onSelectCallback(selectedItem);
        });
    },

    linkContactWithCustomItem(contactId, customItemId, callback) {
        mQuery.ajax({
            type: 'POST',
            url: mauticBaseUrl+'s/custom/item/'+customItemId+'/link/contact/'+contactId+'.json',
            data: {contactId: contactId},
            success: function (response) {
                callback(response);
            },
        });
    },

    unlinkFromContact(elHtml, event, customObjectId, contactId) {
        event.preventDefault();
        mQuery.ajax({type: 'POST', url: mQuery(elHtml).attr('data-action'), success: function() {
            CustomObjects.reloadItemsTable(customObjectId, contactId);
        }});
    },

    getItemsForObject(customObjectId, contactId, callback) {
        mQuery.ajax({
            type: 'GET',
            url: mauticBaseUrl+'s/custom/object/'+customObjectId+'/item?tmpl=list',
            data: {contactId: contactId},
            success: function (response) {
                callback(response);
            },
        });
    },

    refreshTabContent(customObjectId, content) {
        let selector = CustomObjects.createTabSelector(customObjectId, '.custom-item-list');
        mQuery(selector).html(content);
        Mautic.onPageLoad(selector);
    },

    createTabSelector(customObjectId, suffix) {
        return '#custom-object-'+customObjectId+'-container '+suffix;
    },
};
