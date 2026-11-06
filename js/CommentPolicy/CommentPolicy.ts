/**
 * EGroupware - SmallParT - what the client decides about commenting, from the values the server sent
 *
 * Pure functions, no DOM and no et2: `app.ts`, the comment list and the video overlay call them instead of testing
 * the raw values themselves.  That matters because the server does not send what the code reads like it does:
 * columns arrive as strings ("0", "6", "2"), `is_staff` is the role name or null, `accessible` is
 * true / false / "readonly" / null and `timer` is the number of seconds left.  Testing such a value for truthiness
 * ("0" is truthy) or comparing it with `===` against a number silently gives the wrong answer.
 *
 * The rules, the options they depend on and the tests that pin them are described in
 * smallpart/tests/CommentOptionsMatrix.md
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage Ui
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/** A value as it arrives from the server: database columns are strings, flags may be bool, missing values null */
export type Loose = string | number | boolean | null | undefined;

// Comment visibility, video_options (Bo::COMMENTS_*)
export const COMMENTS_SHOW_ALL = 0;
export const COMMENTS_HIDE_OTHER_STUDENTS = 1;
export const COMMENTS_HIDE_TEACHERS = 2;
export const COMMENTS_SHOW_OWN = 3;
export const COMMENTS_FORBIDDEN_BY_STUDENTS = 4;
export const COMMENTS_DISABLED = 5;
export const COMMENTS_GROUP = 6;
export const COMMENTS_GROUP_HIDE_TEACHERS = 7;

// Bits of video_test_options (Bo::TEST_OPTION_*)
export const TEST_OPTION_ALLOW_PAUSE = 1;
export const TEST_OPTION_FORBID_SEEK = 2;
export const TEST_OPTION_FREE_COMMENT_ONLY = 4;
export const TEST_OPTION_VIDEO_READONLY_AFTER_TEST = 8;
export const TEST_OPTION_TEACHER_FREE_COMMENT = 16;
export const TEST_OPTION_HIDE_TEACHER_COMMENT_TEXT = 32;

// video_test_display (Bo::TEST_DISPLAY_*)
export const TEST_DISPLAY_LIST = 3;

/** Cognitive load measurement is on, if all of these bits of course_options are set (Bo::OPTION_CL_MEASUREMENT) */
export const OPTION_CL_MEASUREMENT = 5;

/** The video as the server sends it in content['video'], only what the rules read */
export interface PolicyVideo
{
	video_options? : Loose;
	video_livefeedback_simulated? : Loose;
	video_test_duration? : Loose;
	video_test_options? : Loose;
	video_test_display? : Loose;
	/** true: full access, false: none, "readonly": look but do not change, null: test can be started */
	accessible? : boolean | string | null;
	/** "not-started", "running", "hosting", "ended" or "simulated" for a live feedback video */
	livefeedback_session? : string | null;
}

/** An integer from a server value, 0 for null, "" or anything that is not a number */
export function toInt(value : Loose) : number
{
	const number = parseInt(<string>value, 10);
	return isNaN(number) ? 0 : number;
}

/**
 * Is this video a simulated live session?
 *
 * The flag arrives as the string "0" or "1", and "0" is truthy in JavaScript: testing the value itself made every
 * video look simulated, which hid the add-comment buttons.
 */
export function isSimulated(video : PolicyVideo | null | undefined) : boolean
{
	return !!Number(video?.video_livefeedback_simulated);
}

/**
 * Is a timed test running for this student?
 *
 * `timer` is the seconds left, set by the server only while a test with a duration is running.
 */
export function isInTestMode(video : PolicyVideo | null | undefined, timer : Loose) : boolean
{
	return toInt(video?.video_test_duration) > 0 && Number(timer) > 0;
}

/** Is a bit of video_test_options set? */
export function hasTestOption(video : PolicyVideo | null | undefined, option : number) : boolean
{
	return !!(toInt(video?.video_test_options) & option);
}

/**
 * Must the current user NOT add comments to this video?
 *
 * - staff (tutor, teacher, admin: `is_staff` is the role name) may comment while students are forbidden to
 * - nobody comments when comments are disabled
 * - a simulated live session comments through its live feedback categories, not the comment area, until it ended
 */
export function isCommentForbidden(isStaff : Loose, video : PolicyVideo | null | undefined) : boolean
{
	const options = toInt(video?.video_options);
	return (!isStaff && options === COMMENTS_FORBIDDEN_BY_STUDENTS) ||
		options === COMMENTS_DISABLED ||
		(isSimulated(video) && video?.livefeedback_session != 'ended');
}

/** The add-comment buttons are shown exactly if commenting is allowed */
export function isCommentAllowed(isStaff : Loose, video : PolicyVideo | null | undefined) : boolean
{
	return !isCommentForbidden(isStaff, video);
}

/** Is cognitive load measurement switched on for the course? */
export function isCognitiveLoadMeasurement(courseOptions : Loose) : boolean
{
	return (toInt(courseOptions) & OPTION_CL_MEASUREMENT) === OPTION_CL_MEASUREMENT;
}

/** Are the questions of a test shown as one list? */
export function isTestDisplayList(video : PolicyVideo | null | undefined) : boolean
{
	return toInt(video?.video_test_display) === TEST_DISPLAY_LIST;
}

/**
 * May the user seek in the video: staff always, students unless the test forbids it
 *
 * Mirrors the server's `video.seekable`.
 */
export function isSeekable(isStaff : Loose, video : PolicyVideo | null | undefined) : boolean
{
	return !!isStaff || (!!video && !hasTestOption(video, TEST_OPTION_FORBID_SEEK));
}

/**
 * Does opening a comment (which seeks the video to it) have to be ignored?  Only a click that just opens, for a
 * student, in a test that forbids seeking.
 */
export function isOpenIgnored(actionId : string, isStaff : Loose, video : PolicyVideo | null | undefined) : boolean
{
	return actionId === 'open' && !isStaff && hasTestOption(video, TEST_OPTION_FORBID_SEEK);
}

/**
 * What a comment action really does: a read-only video can only open, and a comment with replies is opened, not
 * edited, when the action came from the list (it has a parent) - editing would hide the replies.
 *
 * @param actionId action chosen: open, edit or retweet
 * @param accessible video.accessible
 * @param fromList the action was run on a list row
 * @param textCount length of comment_added: the text plus a user and text per reply
 */
export function effectiveAction(actionId : string, accessible : boolean | string | null | undefined,
	fromList : boolean, textCount : number) : string
{
	let action = actionId;
	if(accessible === 'readonly')
	{
		action = 'open';
	}
	if(action === 'edit' && fromList && textCount > 1)
	{
		action = 'open';
	}
	return action;
}

/** Is the user the author of the comment, or staff (who may act for everybody)? */
export function isCommentOwner(isStaff : Loose, commentAccountId : Loose, userId : Loose) : boolean
{
	if(isStaff)
	{
		return true;
	}
	if(commentAccountId === null || commentAccountId === undefined || userId === null || userId === undefined)
	{
		return false;
	}
	return String(commentAccountId) === String(userId);
}

/** May the comment be changed: its owner can, unless the video is read-only */
export function canEditComment(isStaff : Loose, commentAccountId : Loose, userId : Loose,
	accessible : boolean | string | null | undefined) : boolean
{
	return isCommentOwner(isStaff, commentAccountId, userId) && accessible !== 'readonly';
}

/**
 * Is a context-menu action enabled for a comment: actions without `enableClass` always, the others only for
 * comments having one of the classes (edit and delete: commentOwner)
 */
export function isActionEnabled(enableClass : string | string[] | undefined | null, commentClass : string | string[] | undefined | null) : boolean
{
	if(!enableClass)
	{
		return true;
	}
	const classes = Array.isArray(commentClass) ? commentClass : String(commentClass ?? '').split(/\s+/);
	return ([] as string[]).concat(enableClass).some(name => classes.includes(name));
}
