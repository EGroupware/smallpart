"use strict";
/**
 * EGroupware SmallPART - timer
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Hadi Nategh<hn@egroupware.org>
 */
var __extends = (this && this.__extends) || (function () {
    var extendStatics = function (d, b) {
        extendStatics = Object.setPrototypeOf ||
            ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
            function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
        return extendStatics(d, b);
    };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
exports.et2_widget_timer = void 0;
require("/api/js/etemplate/et2_core_common");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_valueWidget_1 = require("../../api/js/etemplate/et2_core_valueWidget");
var et2_core_widget_2 = require("../../api/js/etemplate/et2_core_widget");
/**
 * Class which implements the "achelper-timer" XET-Tag
 *
 */
var et2_widget_timer = /** @class */ (function (_super) {
    __extends(et2_widget_timer, _super);
    /**
     * Constructor
     */
    function et2_widget_timer(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_widget_timer._attributes, _child || {})) || this;
        _this.timerInterval = null;
        _this.timer = null;
        _this.container = null;
        _this.days = null;
        _this.hours = null;
        _this.minutes = null;
        _this.seconds = null;
        _this.resetBtn = null;
        _this.resumeBtn = null;
        _this.pauseBtn = null;
        _this._state = null;
        _this._state_unique_id = '';
        _this._appname = '';
        // Build countdown dom container
        _this.container = jQuery(document.createElement("div"))
            .addClass("et2_timer");
        _this.days = jQuery(document.createElement("span"))
            .addClass("et2_timer_days").appendTo(_this.container);
        _this.hours = jQuery(document.createElement("span"))
            .addClass("et2_timer_hours").appendTo(_this.container);
        _this.minutes = jQuery(document.createElement("span"))
            .addClass("et2_timer_minutes").appendTo(_this.container);
        _this.seconds = jQuery(document.createElement("span"))
            .addClass("et2_timer_seconds").appendTo(_this.container);
        _this.timerInterval = setInterval(function (_) {
            _this._updateTimer();
        }, 1000);
        _this.resumeBtn = et2_core_widget_2.et2_createWidget('button', {
            statustext: _this.egw().lang('Start livefeedback session'),
            label: _this.egw().lang('Start'),
            onclick: _this._resumeClicked.bind(_this),
            class: 'btn_resume'
        }, _this);
        _this.pauseBtn = et2_core_widget_2.et2_createWidget('button', {
            statustext: _this.egw().lang('Stop livefeedback session'),
            label: _this.egw().lang('Stop'),
            onclick: _this._pauseClicked.bind(_this),
            class: 'btn_pause'
        }, _this);
        _this.resetBtn = et2_core_widget_2.et2_createWidget('button', {
            statustext: _this.egw().lang('Reset'),
            onclick: _this._resetClicked.bind(_this),
            class: 'btn_clear_timer' + _this.options.hideReset ? ' hideme' : ''
        }, _this);
        _this._state_unique_id = _this.id + '-' + _this.options.uniqueid;
        _this._appname = _this.getInstanceManager().app;
        var state = JSON.parse(_this.egw().getSessionItem(_this._appname, _this._state_unique_id));
        if (state) {
            _this.set_value(state.timer);
            _this.__displayTimer(state.timer);
        }
        _this._set_state(state !== null && state !== void 0 ? state : { id: _this._state_unique_id, paused: !_this.options.autoStart });
        _this.setDOMNode(_this.container[0]);
        return _this;
    }
    /**
     * Clean up interval
     */
    et2_widget_timer.prototype.destroy = function () {
        clearInterval(this.timerInterval);
    };
    et2_widget_timer.prototype.get_value = function () {
        return this.timer;
    };
    et2_widget_timer.prototype.set_value = function (_time) {
        if (isNaN(_time))
            return;
        this.timer = _time;
    };
    et2_widget_timer.prototype._resetClicked = function () {
        this.egw().message('Timer cleared', 'warning');
        this._set_state({ id: this._state_unique_id });
        this.egw().removeSessionItem(this._appname, this._get_state('id'));
        this.set_value(0);
        if (typeof this.options.onReset === 'function')
            this.options.onReset();
    };
    et2_widget_timer.prototype._resumeClicked = function () {
        // don't go further if the timer is not paused
        if (!this._get_state('paused'))
            return;
        var sessionState = JSON.parse(this.egw().getSessionItem(this._appname, this._get_state('id')));
        this._set_state(sessionState);
        this.egw().removeSessionItem(this._appname, this._get_state('id'));
        this.set_value(this._get_state('timer'));
        // unpause the timer state
        this._set_state(null, 'paused', false);
        if (typeof this.options.onResume === 'function')
            this.options.onResume(this._get_state());
    };
    et2_widget_timer.prototype._pauseClicked = function () {
        // don't go further if the timer is already paused
        if (this._get_state('paused'))
            return;
        this._set_state(null, 'paused', true);
        this.egw().setSessionItem(this._appname, this._get_state().id, JSON.stringify(this._get_state()));
        if (typeof this.options.onPause === 'function')
            this.options.onPause(this._get_state());
    };
    /**
     * get state
     *
     * @param key providing key would return the state value of the given key
     * @private
     * @return return whole state object or particular state key value
     */
    et2_widget_timer.prototype._get_state = function (key) {
        return key ? this._state[key] : this._state;
    };
    /**
     * set state
     *
     * @param state set the whole state object or null if desire to set/modify only a key value
     * @param key state key
     * @param value value
     * @private
     */
    et2_widget_timer.prototype._set_state = function (state, key, value) {
        if (state) {
            this._state = state;
        }
        else if (key) {
            this._state[key] = value;
        }
        this._showHideButtons(this._state);
        this.egw().setSessionItem(this._appname, this._state.id, JSON.stringify(this._state));
    };
    et2_widget_timer.prototype._showHideButtons = function (state) {
        if (state && state.paused) {
            jQuery(this.pauseBtn.getDOMNode()).hide();
            jQuery(this.resumeBtn.getDOMNode()).show();
        }
        else {
            jQuery(this.pauseBtn.getDOMNode()).show();
            jQuery(this.resumeBtn.getDOMNode()).hide();
        }
    };
    et2_widget_timer.prototype._updateTimer = function () {
        if (this._get_state('paused'))
            return;
        this.timer++;
        this._set_state(null, 'timer', this.timer);
        this.__displayTimer(this.timer);
    };
    et2_widget_timer.prototype.__displayTimer = function (timer) {
        if (timer < 0)
            return 0;
        if (this.options.alarm > 0 && this.options.alarm == timer && typeof this.options.onAlarm == 'function') {
            this.options.onAlarm();
        }
        var values = {
            days: Math.floor(timer / (60 * 60 * 24)),
            hours: Math.floor((timer % (60 * 60 * 24)) / (60 * 60)),
            minutes: Math.floor((timer % (60 * 60)) / 60),
            secounds: Math.floor((timer % 60))
        };
        this.days.text(values.days + this._getIndicator("days"));
        this.hours.text(values.hours + this._getIndicator("hours"));
        this.minutes.text(values.minutes + this._getIndicator("minutes"));
        this.seconds.text(values.secounds + this._getIndicator("seconds"));
        if (this.options.hideEmpties) {
            if (values.days == 0) {
                this.days.hide();
                if (values.hours == 0) {
                    this.hours.hide();
                    if (values.minutes == 0) {
                        this.minutes.hide();
                        if (values.secounds == 0)
                            this.seconds.hide();
                    }
                    else {
                        this.minutes.show();
                    }
                }
                else {
                    this.hours.show();
                }
            }
            else {
                this.days.show();
            }
        }
        if (this.options.precision) {
            var units = ['days', 'hours', 'minutes', 'seconds'];
            for (var u = 0; u < 4; ++u) {
                if (values[units[u]]) {
                    for (var n = u + this.options.precision; n < 4; n++) {
                        this[units[n]].hide();
                    }
                    break;
                }
                else {
                    this[units[u]].hide();
                }
            }
        }
    };
    et2_widget_timer.prototype._getIndicator = function (_v) {
        return this.options.format == 's' ? this.egw().lang(_v).substr(0, 1) : this.egw().lang(_v);
    };
    et2_widget_timer._attributes = {
        uniqueid: {
            name: "uniqueid",
            type: "string",
            default: "",
            description: "Set a unique id for timer session item incase we have many timers of in a same app"
        },
        format: {
            name: "display format",
            type: "string",
            default: "s",
            description: "Defines display format; s (Initial letter) or l (Complete word) display, default is s."
        },
        onPause: {
            name: "on pause timer",
            type: "js",
            default: et2_no_init,
            description: "Callback function to call when the timer is paused."
        },
        onReset: {
            name: "on reset timer",
            type: "js",
            default: et2_no_init,
            description: "Callback function to call when the timer is reset."
        },
        onResume: {
            name: "on resume timer",
            type: "js",
            default: et2_no_init,
            description: "Callback function to call when the timer is resumed."
        },
        hideEmpties: {
            name: "hide empties",
            type: "string",
            default: true,
            description: "Only displays none empty values."
        },
        precision: {
            name: "how many counters to show",
            type: "integer",
            default: 0,
            description: "Limit number of counters, eg. 2 does not show minutes and seconds, if days are displayed"
        },
        alarm: {
            name: "alarm",
            type: "any",
            default: "",
            description: "Defines an alarm set when timer is reached to the alarm time, it should be in seconds"
        },
        onAlarm: {
            name: "alarm callback",
            type: "js",
            default: "",
            description: "Defines a callback to gets called at alarm - timer. This only will work if there's an alarm set."
        },
        autoStart: {
            name: "auto start",
            type: "string",
            default: false,
            description: "Starts the time immediately."
        },
        hideReset: {
            name: "hide reset button",
            type: "string",
            default: false,
            description: "Hide reset button."
        }
    };
    return et2_widget_timer;
}(et2_core_valueWidget_1.et2_valueWidget));
exports.et2_widget_timer = et2_widget_timer;
et2_core_widget_1.et2_register_widget(et2_widget_timer, ["timer"]);
//# sourceMappingURL=et2_widget_timer.js.map