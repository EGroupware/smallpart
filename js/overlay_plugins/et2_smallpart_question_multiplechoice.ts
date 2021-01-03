/**
 * EGroupware SmallPART - Videooverlay multiple-choice question plugin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package smallpart
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {et2_IOverlayElement, et2_IOverlayElementEditor} from "../et2_videooverlay_interface";
import {et2_smallpart_question_text, et2_smallpart_question_text_editor} from "./et2_smallpart_question_text";

/**
 * Overlay element to show a multiple-choice question
 */
export class et2_smallpart_question_multiplechoice extends et2_smallpart_question_text implements et2_IOverlayElement
{

}

/**
 * Editor widget for multiple-choice question
 */
export class et2_smallpart_question_multiplechoice_editor extends et2_smallpart_question_text_editor implements et2_IOverlayElementEditor
{

}
