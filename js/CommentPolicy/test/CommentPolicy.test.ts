import {assert} from "@open-wc/testing";
import * as policy from "../CommentPolicy";

/**
 * The client's commenting rules, as pure functions (CommentPolicy.ts), checked in two ways:
 *
 * 1. against hand-written value tables, with the values typed the way the server sends them ("0", "6", 2, null, ...)
 * 2. against what the server really sends: the golden files smallpart/tests/fixtures/comment-contract-*.json are written
 *    by the PHP test CommentContractTest from the payload of Student\Ui::index() for every combination of viewer and
 *    course/video option (see smallpart/tests/CommentOptionsMatrix.md).  Each record is fed to the functions and what
 *    they derive (may comment, add buttons, test mode, may seek, the list's context menu, ...) is compared with the
 *    RULES below.
 *
 * The RULES are written down independently here, as tables from the option definitions - not by calling the functions
 * under test - so a function and its rule have to be wrong in the same way to pass.
 *
 * Pass criterion: no mismatch.  The golden tests collect every mismatch of all records and report them together as
 * "<case>: <what>", so a failing run names the combinations, not just the first one.
 *
 * Environment: none; the golden files are fetched from the dev server's root (/smallpart/tests/fixtures/).
 */

// ------------------------------------------------------------------------------------------------ rules

const STAFF = ["admin", "teacher", "tutor"];

/** RULE: can the viewer add comments, by comment option; staff column = tutor, teacher, admin */
const COMMENT_ALLOWED_BY_MODE : Record<number, {student : boolean, staff : boolean}> = {
	0: {student: true, staff: true},	// all comments
	1: {student: true, staff: true},	// hide other students
	2: {student: true, staff: true},	// hide teachers
	3: {student: true, staff: true},	// own only
	4: {student: false, staff: true},	// forbidden by students: only staff comments
	5: {student: false, staff: false},	// disabled
	6: {student: true, staff: true},	// group
	7: {student: true, staff: true},	// group, hide teachers
};

/** RULE: may the viewer comment: the mode's rule, and never in a simulated live session that is not over yet */
function ruleCommentAllowed(staff : boolean, mode : number, simulated : boolean, session : string | null) : boolean
{
	const allowed = staff ? COMMENT_ALLOWED_BY_MODE[mode].staff : COMMENT_ALLOWED_BY_MODE[mode].student;
	return allowed && !(simulated && session !== "ended");
}

/** RULE: what the server's `enableClass` makes of the actions: edit and delete are for the comment's owner */
function ruleMenuEnabled(action : string, viewerIsStaff : boolean, viewerIsAuthor : boolean) : boolean
{
	return ["edit", "delete"].includes(action) ? viewerIsStaff || viewerIsAuthor : true;
}

// ------------------------------------------------------------------------------------------------ golden files

interface GoldenComment
{
	author : string; account_id : string | number; comment_is_staff : boolean; class : string; replies : string[];
}
interface GoldenRecord
{
	case : string;
	viewer : string;
	viewer_kind : string;
	user_id : string;
	options : Record<string, any>;
	payload : {
		is_staff : string | null; course_options : number | null;
		video : Record<string, any> | null;
		comments : GoldenComment[] | null;
		actions : string[] | null; enable_class : Record<string, string | null> | null;
		timer : string | null; clm : boolean; questions : boolean;
	};
}

const GROUPS = ["visibility", "state", "test", "simulated", "livefeedback", "course", "categories"];

async function loadJson(name : string)
{
	const response = await fetch(`/smallpart/tests/fixtures/comment-contract-${name}.json`);
	if(!response.ok)
	{
		throw new Error(`${name}: ${response.status} - run the PHP test with UPDATE_GOLDEN=1 to create the golden files`);
	}
	return response.json();
}

const records : GoldenRecord[] = (await Promise.all(GROUPS.map(loadJson))).flat();
const constants : Record<string, number> = (await loadJson("constants"))[0].payload;

/** What the server's timer fact stands for: seconds left, as a number like the countdown gets it */
const timerOf = (record : GoldenRecord) => record.payload.timer === "positive-int" ? 300 : record.payload.timer;

function mismatches(each : (record : GoldenRecord, report : (what : string) => void) => void) : string[]
{
	const found : string[] = [];
	for(const record of records)
	{
		each(record, what => found.push(`${record.case}: ${what}`));
	}
	return found;
}

// ------------------------------------------------------------------------------------------------ value tables

describe("CommentPolicy: values typed like the server sends them", () =>
{
	/**
	 * The bug this suite was written for: "0" is truthy.  Pass: "0" and 0 are not simulated, "1", 1 and true are.
	 */
	it("isSimulated reads the flag as a number", () =>
	{
		for(const [flag, expected] of <[any, boolean][]>[["0", false], ["1", true], [0, false], [1, true], [null, false],
			[undefined, false], ["", false], [true, true], [false, false]])
		{
			assert.equal(policy.isSimulated({video_livefeedback_simulated: flag}), expected, `flag ${JSON.stringify(flag)}`);
		}
		assert.isFalse(policy.isSimulated(undefined), "no video");
		assert.isFalse(policy.isSimulated(null), "no video");
	});

	/**
	 * Every comment option, as string, as integer and missing, for every kind of staff and student, simulated or not.
	 * Pass: allowed exactly as the rule table says, with each value spelled either way.
	 */
	for(let mode = 0; mode <= 7; ++mode)
	{
		it(`isCommentAllowed with video_options ${mode}`, () =>
		{
			const wrong : string[] = [];
			for(const isStaff of <(string | null)[]>[null, "tutor", "teacher", "admin"])
			{
				for(const options of [String(mode), mode])
				{
					for(const simulated of ["0", "1", 0, 1])
					{
						for(const session of [undefined, null, "simulated", "ended"])
						{
							const video = {video_options: options, video_livefeedback_simulated: simulated, livefeedback_session: session};
							const expected = ruleCommentAllowed(!!isStaff, mode, !!Number(simulated), session ?? null);
							if(policy.isCommentAllowed(isStaff, video) !== expected)
							{
								wrong.push(`is_staff ${isStaff}, ${JSON.stringify(video)}: expected ${expected}`);
							}
						}
					}
				}
			}
			assert.deepEqual(wrong, []);
		});
	}

	/**
	 * Pass: a video without (or with unknown or empty) video_options is a plain one - students comment, and nothing
	 * is read as "forbidden" or "disabled".
	 */
	it("isCommentAllowed without a comment option", () =>
	{
		for(const options of [undefined, null, "", "x", "0"])
		{
			assert.isTrue(policy.isCommentAllowed(null, {video_options: options}), `student, ${JSON.stringify(options)}`);
		}
		assert.isTrue(policy.isCommentAllowed(null, undefined), "no video at all");
	});

	/**
	 * A timed test is on while the video has a duration AND seconds are left.  Pass: only that combination, whatever
	 * type the duration arrives in.
	 */
	it("isInTestMode needs a duration and a running timer", () =>
	{
		for(const [duration, timer, expected] of <[any, any, boolean][]>[
			["10", 300, true], [10, 300, true], ["10", "300", true], ["10", 0, false], ["10", null, false], ["10", undefined, false],
			[null, 300, false], ["0", 300, false], [0, 300, false], ["", 300, false],
		])
		{
			assert.equal(policy.isInTestMode({video_test_duration: duration}, timer), expected,
				`duration ${JSON.stringify(duration)}, timer ${JSON.stringify(timer)}`);
		}
		assert.isFalse(policy.isInTestMode(undefined, 300));
	});

	/**
	 * Seeking: staff always, a student unless the test forbids it (bit 2).  Pass: the bit is found in strings and
	 * integers, with other bits set, and a missing video is not seekable.
	 */
	it("isSeekable and isOpenIgnored follow the forbid-seek bit", () =>
	{
		for(const [options, forbids] of <[any, boolean][]>[["0", false], [0, false], [null, false], ["2", true], [2, true],
			["3", true], ["63", true], ["61", false], ["1", false], [32, false]])
		{
			const video = {video_test_options: options};
			assert.equal(policy.isSeekable(null, video), !forbids, `student, options ${JSON.stringify(options)}`);
			assert.isTrue(policy.isSeekable("tutor", video), `staff, options ${JSON.stringify(options)}`);
			assert.equal(policy.isOpenIgnored("open", null, video), forbids, `open, options ${JSON.stringify(options)}`);
			assert.isFalse(policy.isOpenIgnored("edit", null, video), "only a plain open is ignored");
			assert.isFalse(policy.isOpenIgnored("open", "teacher", video), "staff may open");
		}
		assert.isFalse(policy.isSeekable(null, null), "no video, no seeking");
		assert.isTrue(policy.isSeekable("admin", null));
	});

	/**
	 * Cognitive load measurement needs both bits (4 and 1).  Pass: only 5 and 7 switch it on.
	 */
	it("isCognitiveLoadMeasurement needs both bits", () =>
	{
		for(let options = 0; options <= 7; ++options)
		{
			assert.equal(policy.isCognitiveLoadMeasurement(options), options === 5 || options === 7, `options ${options}`);
			assert.equal(policy.isCognitiveLoadMeasurement(String(options)), options === 5 || options === 7, `options "${options}"`);
		}
		assert.isFalse(policy.isCognitiveLoadMeasurement(null));
	});

	/**
	 * Pass: the test questions are a list for display 3 only, given as string or number.
	 */
	it("isTestDisplayList is display 3", () =>
	{
		for(let display = 0; display <= 3; ++display)
		{
			assert.equal(policy.isTestDisplayList({video_test_display: String(display)}), display === 3);
			assert.equal(policy.isTestDisplayList({video_test_display: display}), display === 3);
		}
		assert.isFalse(policy.isTestDisplayList(null));
	});

	/**
	 * What a comment action does.  Pass: a read-only video only opens; a list action on a comment with replies opens
	 * instead of editing; everything else is what was asked for.
	 */
	it("effectiveAction", () =>
	{
		for(const [action, accessible, fromList, count, expected] of <[string, any, boolean, number, string][]>[
			["edit", true, true, 1, "edit"], ["edit", true, true, 3, "open"], ["edit", true, false, 3, "edit"],
			["edit", "readonly", true, 1, "open"], ["retweet", "readonly", false, 1, "open"], ["retweet", true, true, 3, "retweet"],
			["open", true, true, 3, "open"], ["edit", null, true, 1, "edit"], ["edit", false, true, 1, "edit"],
		])
		{
			assert.equal(policy.effectiveAction(action, accessible, fromList, count), expected,
				`${action}, accessible ${JSON.stringify(accessible)}, fromList ${fromList}, ${count} texts`);
		}
	});

	/**
	 * Pass: staff owns every comment, others only their own; ids compare as text so "5" is 5; a missing id owns
	 * nothing (not even a missing user); a read-only video cannot be edited by anybody.
	 */
	it("isCommentOwner and canEditComment", () =>
	{
		for(const [staff, author, user, owner] of <[any, any, any, boolean][]>[
			[null, "5", 5, true], [null, 5, "5", true], [null, "5", "6", false], [null, null, 5, false], [null, 5, null, false],
			[null, undefined, undefined, false], ["tutor", "5", "6", true], ["admin", null, null, true], [false, 7, 7, true],
		])
		{
			const what = `staff ${JSON.stringify(staff)}, author ${JSON.stringify(author)}, user ${JSON.stringify(user)}`;
			assert.equal(policy.isCommentOwner(staff, author, user), owner, what);
			assert.equal(policy.canEditComment(staff, author, user, true), owner, what);
			assert.isFalse(policy.canEditComment(staff, author, user, "readonly"), `readonly, ${what}`);
		}
	});

	/**
	 * enableClass is a class name, a list or missing; the comment's classes a string or a list.  Pass: no class
	 * required = enabled; otherwise one of the classes has to be there.
	 */
	it("isActionEnabled", () =>
	{
		assert.isTrue(policy.isActionEnabled(undefined, ""));
		assert.isTrue(policy.isActionEnabled(null, "commentOwner"));
		assert.isTrue(policy.isActionEnabled("commentOwner", "commentMarked commentOwner cat-3"));
		assert.isFalse(policy.isActionEnabled("commentOwner", "commentMarked cat-3"));
		assert.isFalse(policy.isActionEnabled("commentOwner", ""), "no classes");
		assert.isFalse(policy.isActionEnabled("commentOwner", undefined), "no classes");
		assert.isTrue(policy.isActionEnabled(["a", "commentOwner"], ["x", "commentOwner"]));
		assert.isFalse(policy.isActionEnabled(["a", "b"], "commentOwner"));
	});

	/**
	 * The numbers the module repeats from Bo.php.  Pass: each equals the golden file written from the PHP constants.
	 */
	it("constants equal the server's", () =>
	{
		const here : Record<string, number> = {
			COMMENTS_SHOW_ALL: policy.COMMENTS_SHOW_ALL, COMMENTS_HIDE_OTHER_STUDENTS: policy.COMMENTS_HIDE_OTHER_STUDENTS,
			COMMENTS_HIDE_TEACHERS: policy.COMMENTS_HIDE_TEACHERS, COMMENTS_SHOW_OWN: policy.COMMENTS_SHOW_OWN,
			COMMENTS_FORBIDDEN_BY_STUDENTS: policy.COMMENTS_FORBIDDEN_BY_STUDENTS, COMMENTS_DISABLED: policy.COMMENTS_DISABLED,
			COMMENTS_GROUP: policy.COMMENTS_GROUP, COMMENTS_GROUP_HIDE_TEACHERS: policy.COMMENTS_GROUP_HIDE_TEACHERS,
			TEST_OPTION_ALLOW_PAUSE: policy.TEST_OPTION_ALLOW_PAUSE, TEST_OPTION_FORBID_SEEK: policy.TEST_OPTION_FORBID_SEEK,
			TEST_OPTION_FREE_COMMENT_ONLY: policy.TEST_OPTION_FREE_COMMENT_ONLY,
			TEST_OPTION_VIDEO_READONLY_AFTER_TEST: policy.TEST_OPTION_VIDEO_READONLY_AFTER_TEST,
			TEST_OPTION_TEACHER_FREE_COMMENT: policy.TEST_OPTION_TEACHER_FREE_COMMENT,
			TEST_OPTION_HIDE_TEACHER_COMMENT_TEXT: policy.TEST_OPTION_HIDE_TEACHER_COMMENT_TEXT,
			TEST_DISPLAY_LIST: policy.TEST_DISPLAY_LIST, OPTION_CL_MEASUREMENT: policy.OPTION_CL_MEASUREMENT,
		};
		assert.deepEqual(here, constants);
	});
});

// ------------------------------------------------------------------------------------------------ the server's payloads

describe("CommentPolicy: what the client derives from the payload the server sends", () =>
{
	it("has every group of the matrix", () =>
	{
		for(const group of GROUPS)
		{
			assert.isAbove(records.filter(r => r.case.startsWith(group)).length, 0, `${group} is empty`);
		}
		assert.isAtLeast(records.length, 169, "the matrix has shrunk");
	});

	/**
	 * Add-comment buttons: shown exactly if commenting is allowed.  Pass: for each record the verdict of
	 * isCommentAllowed(is_staff, video) equals the rule for the viewer, the comment option and the simulated state the
	 * case was set up with - the server's values arrive as strings.  A video that is not delivered has nothing to comment on.
	 */
	it("may comment: the add buttons follow the rule", () =>
	{
		const wrong = mismatches((record, report) =>
		{
			const video = record.payload.video;
			if(!video) return;
			const legacy = record.options.video_options === 8;
			const mode = legacy ? 0 : (record.options.video_options ?? 0);
			const simulated = legacy || record.options.video_livefeedback_simulated === 1;
			const session = simulated ? (record.options.state === "finished" ? "ended" : "simulated") : null;
			const expected = ruleCommentAllowed(STAFF.includes(record.viewer_kind), mode, simulated, session);
			const actual = policy.isCommentAllowed(record.payload.is_staff, video);
			if(actual !== expected) report(`allowed ${actual}, rule says ${expected} (video_options ${JSON.stringify(video.video_options)}, flag ${JSON.stringify(video.video_livefeedback_simulated)}, session ${JSON.stringify(video.livefeedback_session)})`);
		});
		assert.deepEqual(wrong, []);
	});

	/**
	 * KNOWN GAP, pinned as it is today: a read-only video (the student may look, not change) still shows the add-comment
	 * buttons, although the server removes the "add" action and refuses the save.  Pass: the policy says "allowed" for
	 * these records.  Once the buttons are meant to be hidden, change isCommentForbidden(), the rule above and this test.
	 */
	it("KNOWN GAP: a read-only video still allows adding comments", () =>
	{
		const readonly = records.filter(r => r.payload.video?.accessible === "readonly");
		assert.isAbove(readonly.length, 0, "no read-only record");
		for(const record of readonly)
		{
			assert.isTrue(policy.isCommentAllowed(record.payload.is_staff, record.payload.video), record.case);
			assert.notInclude(record.payload.actions, "add", `${record.case}: the server removed the add action`);
		}
	});

	/**
	 * Simulated live session: Pass: isSimulated is true exactly for the videos set up as simulated (flag 1, or the legacy
	 * option 8), whatever type the flag has, and false for all others - including the "0" strings.
	 */
	it("simulated: isSimulated equals the setup", () =>
	{
		const wrong = mismatches((record, report) =>
		{
			if(!record.payload.video) return;
			const expected = record.options.video_livefeedback_simulated === 1 || record.options.video_options === 8;
			if(policy.isSimulated(record.payload.video) !== expected) report(`isSimulated ${!expected}, set up as ${expected} (flag ${JSON.stringify(record.payload.video.video_livefeedback_simulated)})`);
		});
		assert.deepEqual(wrong, []);
	});

	/**
	 * Test mode: Pass: isInTestMode(video, timer) is true exactly for the student whose test is running (duration set and
	 * seconds left), never while it is not started, stopped, or for a video without duration.
	 */
	it("test mode: only a running test", () =>
	{
		const wrong = mismatches((record, report) =>
		{
			if(!record.payload.video) return;
			const expected = record.case.startsWith("test/") && record.options.state === "running" && record.options.video_test_duration > 0;
			const actual = policy.isInTestMode(record.payload.video, timerOf(record));
			if(actual !== expected) report(`isInTestMode ${actual}, expected ${expected} (duration ${JSON.stringify(record.payload.video.video_test_duration)}, timer ${JSON.stringify(record.payload.timer)})`);
		});
		assert.deepEqual(wrong, []);
	});

	/**
	 * Seeking: Pass: isSeekable(is_staff, video) equals the server's own `video.seekable`, so the client and the server
	 * agree for every viewer, test option and simulated state; and opening a comment is ignored only for a student in a
	 * test that forbids seeking.
	 */
	it("seeking: the client rule equals the server's seekable flag", () =>
	{
		const wrong = mismatches((record, report) =>
		{
			const video = record.payload.video;
			if(!video) return;
			if(policy.isSeekable(record.payload.is_staff, video) !== video.seekable) report(`isSeekable differs from the server's seekable ${JSON.stringify(video.seekable)} (staff ${record.payload.is_staff}, test options ${JSON.stringify(video.video_test_options)})`);
			const forbids = (Number(video.video_test_options) & constants.TEST_OPTION_FORBID_SEEK) !== 0;
			const expected = forbids && !STAFF.includes(record.viewer_kind);
			if(policy.isOpenIgnored("open", record.payload.is_staff, video) !== expected) report(`isOpenIgnored should be ${expected}`);
		});
		assert.deepEqual(wrong, []);
	});

	/**
	 * Cognitive load and test display: Pass: the client's checks give the same answer as the server's decisions to send
	 * the measurement setup (`clm`) and the list of questions.
	 */
	it("course and test display: the client checks match what the server sent", () =>
	{
		const wrong = mismatches((record, report) =>
		{
			if(record.payload.course_options !== null && policy.isCognitiveLoadMeasurement(record.payload.course_options) !== record.payload.clm)
			{
				report(`isCognitiveLoadMeasurement(${record.payload.course_options}) differs from clm ${record.payload.clm}`);
			}
			if(record.payload.video && policy.isTestDisplayList(record.payload.video) !== record.payload.questions)
			{
				report(`isTestDisplayList differs from questions ${record.payload.questions}`);
			}
		});
		assert.deepEqual(wrong, []);
	});

	/**
	 * The list's context menu per comment: the server sends the actions and the class each needs; the store gives staff and
	 * the author's own comments the commentOwner class.  Pass: for each comment and action, enabled equals the rule - edit
	 * and delete for staff and the author only, the others always - and a viewer sees exactly the actions the server kept.
	 */
	it("context menu: edit and delete only for the owner", () =>
	{
		const wrong = mismatches((record, report) =>
		{
			for(const comment of record.payload.comments ?? [])
			{
				const owner = policy.isCommentOwner(record.payload.is_staff, comment.account_id, record.user_id);
				const classes = comment.class + (owner && !comment.class.includes("commentOwner") ? " commentOwner" : "");
				for(const action of record.payload.actions ?? [])
				{
					const enabled = policy.isActionEnabled(record.payload.enable_class[action], classes);
					const expected = ruleMenuEnabled(action, STAFF.includes(record.viewer_kind), comment.author === record.viewer);
					if(enabled !== expected) report(`${action} on the comment of ${comment.author}: enabled ${enabled}, rule says ${expected}`);
				}
				const canEdit = policy.canEditComment(record.payload.is_staff, comment.account_id, record.user_id, record.payload.video?.accessible);
				const expectedEdit = (STAFF.includes(record.viewer_kind) || comment.author === record.viewer) && record.payload.video?.accessible !== "readonly";
				if(canEdit !== expectedEdit) report(`canEditComment on the comment of ${comment.author}: ${canEdit}, rule says ${expectedEdit}`);
			}
		});
		assert.deepEqual(wrong, []);
	});

	/**
	 * What a comment action does: Pass: on a read-only video every action opens; on a comment with replies the list's edit
	 * opens; otherwise edit edits and retweet retweets.
	 */
	it("opening a comment: read-only and replies turn edit into open", () =>
	{
		const wrong = mismatches((record, report) =>
		{
			for(const comment of record.payload.comments ?? [])
			{
				const texts = 1 + 2 * comment.replies.length;
				const readonly = record.payload.video?.accessible === "readonly";
				const edit = policy.effectiveAction("edit", record.payload.video?.accessible, true, texts);
				const retweet = policy.effectiveAction("retweet", record.payload.video?.accessible, true, texts);
				if(edit !== (readonly || comment.replies.length ? "open" : "edit")) report(`edit of the comment of ${comment.author} becomes ${edit}`);
				if(retweet !== (readonly ? "open" : "retweet")) report(`retweet of the comment of ${comment.author} becomes ${retweet}`);
			}
		});
		assert.deepEqual(wrong, []);
	});
});

// ------------------------------------------------------------------------------------------------ wiring

describe("CommentPolicy: app.ts uses it", () =>
{
	/**
	 * The functions only help if app.ts calls them.  The bug behind this suite was an inline test of the raw flag
	 * (`!!video.video_livefeedback_simulated`), which cannot be unit tested.  Pass: the (transpiled) source of app.ts
	 * calls the policy functions and does not read the simulated flag itself.
	 */
	it("calls the policy functions and does not read the simulated flag itself", async() =>
	{
		const source = await (await fetch("/smallpart/js/app.ts")).text();
		assert.notInclude(source, "video_livefeedback_simulated", "app.ts reads the raw flag: use policy.isSimulated()");
		for(const call of ["isSimulated", "isCommentForbidden", "isInTestMode", "isCognitiveLoadMeasurement", "isTestDisplayList",
			"isOpenIgnored", "effectiveAction", "canEditComment", "isCommentOwner"])
		{
			assert.include(source, `policy.${call}(`, `app.ts does not call policy.${call}()`);
		}
	});
});
