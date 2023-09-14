/*
 * SmallPart LiveFeedback Button
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {css} from "lit";
import {Et2Button} from "../../api/js/etemplate/Et2Button/Et2Button";
import shoelace from "../../api/js/etemplate/Styles/shoelace";

/**
 *
 */
export class SmallPartLiveFeedbackButton extends Et2Button
{
	static get styles()
	{
		return [
			super.styles,
			shoelace, css`
			:host {
			  width: 100%;
			  display: inherit;
			}
			:host::part(base) {
			  border: 1px solid var(--smallpart-cat-color);
			}
		`];
	}

	static get properties()
	{
		return {
			...super.properties,
			color: {type: String}
		}
	}

	constructor(...args : any[])
	{
		super(...args);

		// Property default values
		this.color = '';
		this.noSubmit = true;
	}

	set color(_color : string)
	{
		this.updateComplete.then(_=>{
			this.shadowRoot.querySelector('.button').style.setProperty('--smallpart-cat-color',_color)
		});
	}
}

customElements.define("smallpart-lf-button", SmallPartLiveFeedbackButton);