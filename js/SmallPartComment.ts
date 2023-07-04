/*
 * SmallPart Comment Timespan widget
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {css, html, LitElement, render} from "@lion/core";
import {Et2Widget} from "../../api/js/etemplate/Et2Widget/Et2Widget";
import {sprintf} from "../../api/js/egw_action/egw_action_common";
import {et2_IDetachedDOM} from "../../api/js/etemplate/et2_core_interfaces";

export class SmallPartComment extends Et2Widget(LitElement) implements et2_IDetachedDOM
{
	protected _value : Array<string|number> = [];

	protected _time : String = '';

	protected _nicks : any = {};

	/**
	 * @todo
	 */
	static get styles()
	{
		return [
			...super.styles,
			css`
			
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * optional starttime to display before first comment
			 */
			startTime: {
				type: Number,
			},
			/**
			 * optional stoptime to display before first comment
			 */
			stopTime: {
				type: Number,
			},
			/**
			 * videobar this overlay is for
			 */
			value: {
				type: Array,
				noAccessor: true
			}
		}
	}

	constructor(...args : any[])
	{
		super(...args);
		this._value = [];
		this.startTime = 0;
		this.stopTime = 0;
	}

	set startTime(_time : number)
	{
		if (!isNaN(_time))
		{
			this._time = sprintf('%02d:%02d:%02d', ~~(_time/3600), ~~(_time/60), _time%60);
		}
		else
		{
			this._time = '';
		}
	}

	set stopTime(_time : number)
	{
		if (!isNaN(_time))
		{
			this._time += '-'+sprintf('%02d:%02d:%02d', ~~(_time/3600), ~~(_time/60), _time%60);
		}
		else
		{
			this._time += '';
		}
	}

	set_value(_value)
	{
		if (!Array.isArray(_value)) _value = [_value];

		for (let n=1; n < _value.length; n += 2)
		{
			let user = _value[n];
			if (typeof user === "string" && !parseInt(user))
			{
				let match = user.match(/\[(\d+)\]$/);	// old: "first name [account_id]"
				if (match && match.length > 1) user = _value[n] = parseInt(match[1]);
			}
			if (!Object.keys(this._nicks).length)
			{
				const participants = this.getRoot().getArrayMgr('sel_options').getEntry('account_id');
				participants.forEach((participant) =>
				{
					this._nicks[participant.value] = participant.label;
				});
			}

			let temp = document.createElement("div");
			render(this._addCommentTemplate({value:_value[n+1], user:this._nicks[user] || '#' + user}), temp);
			temp.childNodes.forEach((node) => this.appendChild(node));

		}

		super.requestUpdate();
	}

	/**
	 * @todo
	 */
	render()
	{
		return html`
            <slot></slot>`;
	}

	/**
	 * @todo
	 * @param _data
	 */
	_addCommentTemplate(_data)
	{
		return html`
		`;
	}

	getDetachedAttributes(attrs)
	{
		attrs.push("value", "time");
	}

	getDetachedNodes() : HTMLElement[]
	{
		return [<HTMLElement><unknown>this];
	}

	setDetachedAttributes(_nodes : HTMLElement[], _values : object, _data? : any) : void
	{
		for(let attr in _values)
		{
			this[attr] = _values[attr];
		}
	}
}
customElements.define("et2-smallpart-comment", SmallPartComment);