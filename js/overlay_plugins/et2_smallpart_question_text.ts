/**
 * EGroupware SmallPART - Videooverlay text question plugin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {et2_IOverlayElement, et2_IOverlayElementEditor} from "../et2_videooverlay_interface";
import {et2_smallpart_overlay_html, et2_smallpart_overlay_html_editor} from "./et2_smallpart_overlay_html";

/**
 * Overlay element to show a text question: question with ability to answer with some free text
 */
export class et2_smallpart_question_text extends et2_smallpart_overlay_html implements et2_IOverlayElement
{

}

/**
 * Editor widget for text question
 */
export class et2_smallpart_question_text_editor extends et2_smallpart_overlay_html_editor implements et2_IOverlayElementEditor
{

}
