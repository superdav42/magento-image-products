
define([
    'Magento_Ui/js/form/element/ui-select',
    'Magento_Ui/js/lib/key-codes',
    'underscore'
], function (Select, keyCodes, _) {
    'use strict';

    return Select.extend({
        defaults: {
            keywordInput: ''
        },
        
        initObservable: function () {
            this._super();
            this.observe([
                'keywordInput'
            ]);

            return this;
        },
        
        /**
         * Handler keydown event to filter options input
         *
         * @returns {Boolean} Returned true for emersion events
         */
        inputKeydown: function (data, event) {
            var key = keyCodes[event.keyCode];
            event.stopPropagation();
            
            if (key === 'enterKey' || event.keyCode === 188) {
                event.preventDefault();
                var value = data.keywordInput();
                var option = _.findWhere(this.cacheOptions.plain, {'label': value});
                if (option) {
                    this.value.push(option.value);
                } else {
                    this.cacheOptions.plain.push({'value': value, 'label': value});
                    this.value.push(value);
                }

                this.keywordInput('');
            }

            return true;
        },

        
        /**
         * Parse data and set it to options.
         *
         * @param {Object} data - Response data object.
         * @returns {Object}
         */
        setParsed: function (data) {
            var option = this.parseData(data);

            if (data.error) {
                return this;
            }

            this.options([]);
            this.setOption(option);
            this.set('newOption', option);
        },

        /**
         * Normalize option object.
         *
         * @param {Object} data - Option object.
         * @returns {Object}
         */
        parseData: function (data) {
            return {
                'is_active': data.category['is_active'],
                level: data.category.level,
                value: data.category['entity_id'],
                label: data.category.name,
                parent: data.category.parent
            };
        }
    });
});
