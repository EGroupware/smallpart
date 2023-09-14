/**
 * EGroupware - SmallParT - videoTime widget
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @author Hadi Nategh <hn@egroupware.org>
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {css} from "lit";
import {Et2Description} from "../../api/js/etemplate/Et2Description/Et2Description";

export class SmallPartVideoTime extends Et2Description
{

	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				right: 10px;
				bottom: 80px;
				font-size: 12pt;
			}`
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Defines the indicator type, time|page. default is video.
			 */
			indicator: {
				type: String
			}
		}
	}

	constructor()
	{
		super();
		this.indicator = 'time';
	}

	set_value(_value)
	{
		let value = _value;
		switch(this.options.indicator)
		{
			case 'time':
				let time = new Date(null);
				time.setSeconds(parseInt(_value));
				value = time.toISOString().substr(11, 8);
				break;
			case 'page':
				value = egw.lang('page %1', Math.floor(parseInt(_value)));
				break;
		}

		this.value = value;
	}
}

customElements.define("et2-smallpart-videotime", SmallPartVideoTime);