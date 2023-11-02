/**
 * EGroupware SmallPART - video controls
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Hadi Nategh <hn@egroupware.org>
 */

import {et2_smallpart_videobar} from "./et2_widget_videobar";
import {Et2Widget} from "../../api/js/etemplate/Et2Widget/Et2Widget";
import {css, html, LitElement} from "lit";

/**
 * This widget creates a video controller in order to control vidoebar <video>. It offers control buttons such as
 * Play/Pause, Forward/Backward for the givien videobar widget which contains display part for <video>/youtube/pdf.
 */
export class SmallPartVideoControls extends Et2Widget(LitElement)
{
	protected _playState : string = 'paused';
	static get styles()
	{
		return [
			...super.styles,
			css`
				:host{
				  width: 100%;
				}
				et2-button-icon {font-size: 1.5em}
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * videobar this overlay is for
			 */
			videobar: {
				type: String
			},
			/**
			 * callback function on play button
			 */
			onPlayCallback: {
				type: Function
			},
			/**
			 * callback function on pause button
			 */
			onPauseCallback: {
				type: Function
			},
			/**
			 * callback function on forward button
			 */
			onForwardCallback: {
				type: Function
			},
			/**
			 * callback function on backward button
			 */
			onBackwardCallback: {
				type: Function
			}
		}
	}

	/**
	 * find/set videobar widget
	 * @param _widget
	 */
	set videobar(_widget: string | et2_smallpart_videobar)
	{
		if (typeof _widget === 'string') {
			_widget = <et2_smallpart_videobar>this.getRoot().getWidgetById(_widget);
		}
		if (_widget instanceof et2_smallpart_videobar) {
			this._videobar = _widget;
		}
	}

	/**
	 * returns videobar widget
	 */
	get videobar()
	{
		return <et2_smallpart_videobar>this._videobar;
	}

	render()
	{
		const playImage = this._playState === 'played' ? "pause-circle" : "play-circle";
		const playTitle = this._playState === 'played' ? "pause" : "play";
		return html`
			<et2-hbox class="et2_smallpart-video-controls">
				<et2-label value=${this.label} .disabled=${!this.label}></et2-label>
				<et2-button-icon class="backward" image="arrow-counterclockwise" statustext=${this.egw().lang('Backward 10 sec')}
								 @click=${this._onBackwardClickHandler.bind(this)}></et2-button-icon>
				<et2-button-icon class="play" image=${playImage} statustext=${this.egw().lang(playTitle)}
								 @click=${this._onPlayClickHandler.bind(this)}></et2-button-icon>
				<et2-button-icon class="forward" image="arrow-clockwise" statustext=${this.egw().lang('Forward 10 sec')} 
								 @click=${this._onForwardClickHandler.bind(this)}></et2-button-icon>
			</et2-hbox>
		`;
	}

	/**
	 * onPlay click handler
	 * @param _event
	 * @private
	 */
	private _onPlayClickHandler(_event : Event)
	{
		if (this.videobar.paused())
		{
			this.videobar.play();
			this._playState = 'played';
		}
		else
		{
			this.videobar.pause_video();
			this._playState = 'paused';
		}

		if (typeof this.onPlayCallback === 'function')
		{
			this.onPlayCallback.call(this, _event, this);
		}
		this.requestUpdate();
	}

	/**
	 * onForward click handler
	 * @param _event
	 * @private
	 */
	private _onForwardClickHandler(_event : Event)
	{
		if (typeof this.onForwardCallback === 'function') {
			this.options.onForwardCallback.call(this, _event, this);
		}

		if (this.videobar.currentTime() + 10 <= this.videobar.duration()) {
			this.videobar.seek_video(this.videobar.currentTime() + 10);
		}
	}

	/**
	 * onBackward click handler
	 * @param _event
	 * @private
	 */
	private _onBackwardClickHandler(_event : Event)
	{
		if (typeof this.options.onBackwardCallback === 'function')
		{
			this.options.onBackwardCallback.call(this, _event, this);
		}
		if (this.videobar.currentTime() - 10 >= 0) {
			this.videobar.seek_video(this.videobar.currentTime() - 10);
		}
	}
}
customElements.define("smallpart-video-controls", SmallPartVideoControls);