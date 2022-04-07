"use strict";
/**
 * EGroupware - SmallParT - cognitive load measurement L widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @author Hadi Nategh <hn@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
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
exports.et2_smallpart_cl_measurement_L = void 0;
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
var et2_core_inheritance_1 = require("../../api/js/etemplate/et2_core_inheritance");
var et2_core_baseWidget_1 = require("../../api/js/etemplate/et2_core_baseWidget");
var et2_widget_videobar_1 = require("./et2_widget_videobar");
var et2_smallpart_cl_measurement_L = /** @class */ (function (_super) {
    __extends(et2_smallpart_cl_measurement_L, _super);
    /**
     * Constructor
     */
    function et2_smallpart_cl_measurement_L(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_smallpart_cl_measurement_L._attributes, _child || {})) || this;
        _this.div = null;
        _this.l_button = null;
        _this._mode = 'calibration';
        _this._active = false;
        _this._active_start = 0;
        _this._steps = [];
        _this._stepsDOM = [];
        _this._activeInterval = 0;
        _this._activeInervalCounter = 0;
        _this._content = _this.getInstanceManager().widgetContainer.getArrayMgr('content');
        // Only run this if the course is running in CML mode.
        if ((_this._content.getEntry('course_options') & et2_widget_videobar_1.et2_smallpart_videobar.course_options_cognitive_load_measurement)
            != et2_widget_videobar_1.et2_smallpart_videobar.course_options_cognitive_load_measurement) {
            return _this;
        }
        // widgte div wrapper
        _this.div = document.createElement('div');
        _this.div.classList.add('smallpart-cl-measurement-L');
        _this.l_button = et2_core_widget_1.et2_createWidget('buttononly', { label: egw.lang('L') }, _this);
        // bind keydown event handler
        document.addEventListener('keydown', _this._keyDownHandler.bind(_this));
        _this._steps = _this.options.steps_className.split(',');
        _this.setDOMNode(_this.div);
        return _this;
    }
    et2_smallpart_cl_measurement_L.prototype.set_mode = function (value) {
        this._mode = value;
    };
    et2_smallpart_cl_measurement_L.prototype.set_active = function (value) {
        var _this = this;
        this._active = value;
        if (this._active) {
            this.div.classList.add('active');
            this._active_start = Date.now();
            setTimeout(function (_) {
                _this.set_active(false);
            }, this.options.activation_period);
        }
        else {
            this._active_start = 0;
            this.div.classList.remove('active');
        }
    };
    et2_smallpart_cl_measurement_L.prototype.start = function () {
        var _this = this;
        this._activeInervalCounter = 0;
        clearInterval(this._activeInterval);
        this._steps.forEach(function (className) {
            _this._stepsDOM.push(document.getElementsByClassName(className)[0]);
        });
        switch (this._mode) {
            case 'calibration':
                this._stepsDOM.forEach(function (_node) {
                    _node.style.visibility = 'hidden';
                });
                this._activeInterval = setInterval(function (_) {
                    if (_this._activeInervalCounter <= 3) {
                        _this._stepsDOM[_this._activeInervalCounter].style.visibility = 'visible';
                        _this.set_active(true);
                    }
                    else {
                        clearInterval(_this._activeInterval);
                    }
                    _this._activeInervalCounter++;
                }, (10 + Math.floor(0.9 * 6)) * 1000);
                break;
            case 'running':
                this.set_active(true);
                break;
        }
    };
    et2_smallpart_cl_measurement_L.prototype._keyDownHandler = function (_ev) {
        if (_ev.key === 'Control' && this._active) {
            var end = Date.now() - this._active_start;
            this.egw().json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
                this._content.getEntry('video')['course_id'], this._content.getEntry('video')['video_id'],
                smallpartApp.CLM_TYPE_LEARNING, [end / 1000]
            ]).sendRequest();
        }
    };
    et2_smallpart_cl_measurement_L._attributes = {
        mode: {
            name: 'mode',
            type: 'string',
            description: 'Defines the stage of the process the widget is running in, calibration and running mode.',
            default: 'calibration'
        },
        active: {
            name: 'active',
            type: 'boolean',
            description: 'Activate/deactivate "L" button color mode and functions',
            default: false
        },
        activation_period: {
            name: 'activation period',
            type: 'integer',
            description: 'Defines the duration of active mode, default is 1s (the time is in millisecond).',
            default: 1000
        },
        steps_className: {
            name: 'steps classname',
            type: 'string',
            description: 'comma separated css class name for defining (hide/show) steps. (steps are based on orders)',
            default: ''
        }
    };
    return et2_smallpart_cl_measurement_L;
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_smallpart_cl_measurement_L = et2_smallpart_cl_measurement_L;
et2_core_widget_1.et2_register_widget(et2_smallpart_cl_measurement_L, ["smallpart-cl-measurement-L"]);
//# sourceMappingURL=et2_widget_cl_measurement_L.js.map