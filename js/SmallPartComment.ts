/*
 * SmallPart Comment Timespan widget
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {css, html, LitElement, nothing} from "lit";
import {Et2Widget} from "../../api/js/etemplate/Et2Widget/Et2Widget";
import {sprintf} from "../../api/js/egw_action/egw_action_common";
import {et2_IDetachedDOM} from "../../api/js/etemplate/et2_core_interfaces";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import {map} from "lit/directives/map.js";

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
				:host {
					position: relative;
				}

				.edit-icon {
					position: absolute;
					right: 0;
				}

				.smallpart-comment__reply {
					position: relative;
					margin-top: var(--sl-spacing-small);
					padding-top: var(--sl-spacing-small);
					padding-left: var(--sl-spacing-large);
					border-top: var(--sl-panel-border-width) solid var(--sl-panel-border-color);
					min-height: var(--sl-input-height-medium);
				}

				.smallpart_comment_reply__participant {
					font-weight: bold;
					padding-right: var(--sl-spacing-medium);
					flex: 0 0 fit-content;
				}

				.smallpart_comment_reply__participant::after {
					content: ":";
				}
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
			 * Comment can be edited
			 */
			editable: {
				type: Boolean,
			},

			/**
			 * Comment value
			 */
			value: {
				type: Object,
				noAccessor: true
			}
		}
	}

	private comment : string = "";
	private replies : { user : string, reply : string }[] = [];

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

	set_value(_value : { course_id : string, video_id : string, comment_id : string, comment : string[] })
	{
		this.value = _value;

		this.comment = (typeof _value.comment != "undefined" ? _value?.comment[0] : "") ?? "";
		this.replies = [];

		for(let n = 1; n < _value?.comment?.length; n += 2)
		{
			let user = _value.comment[n];
			if (typeof user === "string" && !parseInt(user))
			{
				let match = user.match(/\[(\d+)\]$/);	// old: "first name [account_id]"
				if(match && match.length > 1)
				{
					user = _value.comment[n] = String(parseInt(match[1]));
				}
			}
			if (!Object.keys(this._nicks).length)
			{
				const participants = this.getRoot().getArrayMgr('sel_options').getEntry('account_id');
				participants.forEach((participant) =>
				{
					this._nicks[participant.value] = participant.label;
				});
			}
			this.replies.push({user: user, reply: _value.comment[n + 1]});
		}

		this.requestUpdate("value");
	}

	private async handleEditClick(event, data, index)
	{
		const userLabel = this._nicks[data.user] || '#' + data.user
		const editDialog = <Et2Dialog><unknown>document.createElement('et2-dialog');
		editDialog._setApiInstance(this.egw());
		editDialog.transformAttributes({
			title: this.egw().lang("Edit"),
			buttons: Et2Dialog.BUTTONS_OK_CANCEL,
			isModal: true,
			template: "smallpart.student.edit_comment",
			value: {content: {label: userLabel, ...data}}
		});
		// Stop enter from closing dialog
		const stop = (e) =>
		{
			if(e.key == "Enter")
			{
				e.stopImmediatePropagation();
			}
		}
		editDialog.updateComplete.then(() =>
		{
			editDialog.querySelector("#_reply").addEventListener("keyup", stop);
		});

		document.body.appendChild(<LitElement><unknown>editDialog);
		let [button, edit] = await editDialog.getComplete();
		editDialog.querySelector("#_reply").removeEventListener("keyup", stop);

		if(button == Et2Dialog.OK_BUTTON)
		{
			this.replies[index].reply = edit["reply"];

			this.egw().json('smallpart.\\EGroupware\\SmallParT\\Student\\Ui.ajax_saveComment', [
				this.getInstanceManager().etemplate_exec_id,
				{
					course_id: this.value.course_id,
					video_id: this.value.video_id,
					comment_id: this.value.comment_id,
					// send action and text to server-side to be able to do a proper ACL checks
					action: "reply_edit",
					index: 2 + 2 * index,
					reply: edit["reply"]
				}
			]).sendRequest();

			this.requestUpdate("value");
		}
	}

	addCommentTemplate(_data : { reply : string, user : string }, index : number)
	{
		const editable = (this.editable || _data.user == this.egw().user('account_id')) && this.value.course_id && this.value.video_id && this.value.comment_id;
		const userLabel = this._nicks[_data.user] || '#' + _data.user
		return html`
            <et2-hbox class="et2_smallpart_comment_retweet smallpart-comment__reply" data-index="${index}">
                <et2-image class="bi-arrow-right"></et2-image>
                <span class="retweeter smallpart_comment_reply__participant">${userLabel}</span>
                <span class="smallpart-comment-reply__reply">
                ${_data.reply}
				</span>
                ${editable ? html`
                    <et2-button-icon
                            part="edit-icon"
                            class="edit-icon"
                            align="right"
                            image="edit" label="Edit" noSubmit
                            @click=${(event) => this.handleEditClick(event, _data, index)}
                    >
                    </et2-button-icon>` : nothing
                }
            </et2-hbox>
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

	render()
	{
		return html`
            <div class="smallpart-comment smallpart-comment__base">
                <span class="smallpart-comment__time" part="time">${this._time}</span>
                ${this.comment}
                <slot></slot>
                ${map(this.replies, (data, index) => this.addCommentTemplate(data, index))}
            </div>`;
	}
}
customElements.define("et2-smallpart-comment", SmallPartComment);