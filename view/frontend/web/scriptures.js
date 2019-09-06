define(['jquery', 'uiComponent', 'ko', 'tabs'], function ($, Component, ko) {
    'use strict';
    var self;
    return Component.extend({
        current: ko.observable(''),
        initialize: function () {
            self = this;
            this._super();
        },
        setCurrent: function () {
            if (self.current() === this) {
                self.current(false);
            } else {
                self.current(this);
            }
        }
    });
});