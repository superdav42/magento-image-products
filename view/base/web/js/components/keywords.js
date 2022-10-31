
define([
    'Magento_Ui/js/form/element/ui-select',
    'Magento_Ui/js/lib/key-codes',
    'underscore',
    'mage/translate'
], function (Select, keyCodes, _, $t) {
    'use strict';

    return Select.extend({
        defaults: {
            keywordInput: '',
            maxInput: 0
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
                if (!this.validateCount()) {
                    return false;
                }
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

        validateCount: function () {
            if (this.maxInput > 0 && this.value().size() >= this.maxInput) {
                this.error($t('Can\'t add more than %s keywords!').replace('%s', this.maxInput));
                return false;
            }
            return true
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
