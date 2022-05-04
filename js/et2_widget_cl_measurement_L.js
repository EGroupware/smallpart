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
var et2_widget_dialog_1 = require("../../api/js/etemplate/et2_widget_dialog");
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
        _this._stepIndex = 0;
        _this._activeCalibrationInterval = 0;
        _this._calibrationIsDone = false;
        _this.__runningTimeoutId = 0;
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
        _this._steps = _this.options.steps_className.split(',').map(function (_class) { return { class: _class, node: null }; });
        _this.checkCalibration().then(function (_) { _this._calibrationIsDone = true; }, function (_) { _this._calibrationIsDone = false; });
        _this.setDOMNode(_this.div);
        return _this;
    }
    et2_smallpart_cl_measurement_L._randomNumGenerator = function (min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    };
    et2_smallpart_cl_measurement_L.prototype.set_steps_className = function (value) {
        this._steps = value.split(',').map(function (_class) { return { class: _class, node: null }; });
    };
    et2_smallpart_cl_measurement_L.prototype.get_steps_className = function () {
        return this.options.steps_className;
    };
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
                // record measurement with no time set if there was no interaction
                _this._recordMeasurement();
            }, (this._mode == et2_smallpart_cl_measurement_L.MODE_CALIBRATION ?
                (this.options.calibration_activation_period ? parseInt(this.options.calibration_activation_period) : 3)
                : parseInt(this.options.activation_period ? this.options.activation_period : 5)) * 1000);
        }
        else {
            this._active_start = 0;
            this.div.classList.remove('active');
        }
    };
    et2_smallpart_cl_measurement_L.prototype.start = function () {
        var _this = this;
        return new Promise(function (_resolve) {
            var activeInervalCounter = 1;
            clearInterval(_this._activeCalibrationInterval);
            _this._steps.forEach(function (step) {
                step.node = document.getElementsByClassName(step.class)[0];
            });
            if (_this._mode === et2_smallpart_cl_measurement_L.MODE_CALIBRATION && _this._calibrationIsDone) {
                _this._mode = et2_smallpart_cl_measurement_L.MODE_RUNNING;
            }
            switch (_this._mode) {
                case et2_smallpart_cl_measurement_L.MODE_CALIBRATION:
                    _this._steps.forEach(function (_step) {
                        _step.node.style.visibility = 'hidden';
                    });
                    _this._stepIndex = 0;
                    _this._activeCalibrationInterval = setInterval(function (_) {
                        if ((activeInervalCounter / 4) % 1 != 0)
                            _this.set_active(true);
                        if ((activeInervalCounter / 4) % 1 == 0 && _this._steps[_this._stepIndex]) {
                            _this._steps[_this._stepIndex].node.style.visibility = 'visible';
                            _this._stepIndex++;
                        }
                        if (activeInervalCounter >= 4 * (_this._steps.length + 1)) {
                            clearInterval(_this._activeCalibrationInterval);
                            _this._calibrationIsDone = true;
                            et2_widget_dialog_1.et2_dialog.show_dialog(function (_) {
                                _resolve();
                            }, 'Calibration procedure is finished. After pressing "Ok" the actual test will start.', 'Cognitive Measurement Load Learning Calibration', null, et2_widget_dialog_1.et2_dialog.BUTTONS_OK, et2_widget_dialog_1.et2_dialog.INFORMATION_MESSAGE);
                        }
                        activeInervalCounter++;
                    }, (_this.options.calibration_interval ? parseInt(_this.options.calibration_interval) : 6) * 1000);
                    break;
                case et2_smallpart_cl_measurement_L.MODE_RUNNING:
                    _this.__runningTimeoutId = window.setTimeout(function (_) {
                        _this.set_active(true);
                        _this.start();
                    }, ((parseInt(_this.options.running_interval ? _this.options.running_interval : 5) * 60) +
                        ((Math.round(Math.random()) * 2 - 1) * _this._randomNumGenerator(1, parseInt(_this.options.running_interval_range ? _this.options.running_interval_range : 30)))) * 1000);
                    _resolve();
                    break;
            }
        });
    };
    et2_smallpart_cl_measurement_L.prototype.stop = function () {
        clearTimeout(this.__runningTimeoutId);
    };
    /**
     * Check if calibration is done
     * @protected
     */
    et2_smallpart_cl_measurement_L.prototype.checkCalibration = function () {
        var _this = this;
        return new Promise(function (_resolve, _reject) {
            //don't ask server if the calibration is already done.
            if (_this._calibrationIsDone) {
                _resolve();
                return;
            }
            _this.egw().json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_readCLMeasurement', [
                _this._content.getEntry('video')['course_id'], _this._content.getEntry('video')['video_id'],
                'learning', egw.user('account_id')
            ], function (_records) {
                var resolved = false;
                if (_records) {
                    _records.forEach(function (_record) {
                        var data = JSON.parse(_record.cl_data)[0];
                        if (data.mode && data.mode === et2_smallpart_cl_measurement_L.MODE_CALIBRATION
                            && data.step === (_this._steps.length + 1).toString() + '/' + (_this._steps.length + 1).toString())
                            resolved = true;
                    });
                }
                if (resolved) {
                    _resolve();
                }
                else {
                    _reject();
                }
            }).sendRequest();
        });
    };
    et2_smallpart_cl_measurement_L.prototype._keyDownHandler = function (_ev) {
        if (_ev.key === 'Control' && this._active) {
            var end = Date.now() - this._active_start;
            this._recordMeasurement(end);
            this.set_active(false);
        }
    };
    et2_smallpart_cl_measurement_L.prototype._recordMeasurement = function (_time) {
        var data = { mode: this._mode, time: _time ? _time / 1000 : '' };
        if (this._mode === et2_smallpart_cl_measurement_L.MODE_CALIBRATION) {
            data['step'] = (this._stepIndex + 1).toString() + '/' + (this._steps.length + 1).toString();
        }
        this.egw().json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_recordCLMeasurement', [
            this._content.getEntry('video')['course_id'], this._content.getEntry('video')['video_id'],
            'learning', [data]
        ]).sendRequest();
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
            description: 'Defines the duration of active mode, default is 5s.',
            default: 5
        },
        steps_className: {
            name: 'steps classname',
            type: 'string',
            description: 'comma separated css class name for defining (hide/show) steps. (steps are based on orders)',
            default: ''
        },
        running_interval: {
            name: 'running interval',
            type: 'integer',
            description: 'Defines interval time in minutes of active mode display',
            default: 5
        },
        running_interval_range: {
            name: 'running interval range',
            type: 'integer',
            description: 'Defines interval time in seconds of active mode display',
            default: 30
        },
        calibration_interval: {
            name: 'calibration interval',
            type: 'integer',
            description: 'Defines interval time for each step in seconds',
            default: 6
        },
        calibration_activation_period: {
            name: 'calibration activation period',
            type: 'integer',
            description: 'Defines the duration of active mode while calibrating, default is 3s.',
            default: 3
        },
    };
    et2_smallpart_cl_measurement_L.MODE_CALIBRATION = 'calibration';
    et2_smallpart_cl_measurement_L.MODE_RUNNING = 'running';
    return et2_smallpart_cl_measurement_L;
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_smallpart_cl_measurement_L = et2_smallpart_cl_measurement_L;
et2_core_widget_1.et2_register_widget(et2_smallpart_cl_measurement_L, ["smallpart-cl-measurement-L"]);
//# sourceMappingURL=et2_widget_cl_measurement_L.js.map