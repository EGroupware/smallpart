# Comment options matrix

The student view (`Student\Ui::index()` + `js/app.ts`) behaves differently for every combination of viewer, comment
option, publishing state, test option, live-feedback state and course option. Each option travels from the database,
through PHP, to JavaScript - and on that path a value changes type (`"0"` is truthy, `is_staff` is a role name, the test
timer is a number of seconds). Reading the code cannot show whether each combination arrives in a usable form, so this
matrix runs it.

**Pass criteria:** the PAYLOAD is right (the right comments and replies arrive, flags have the right values *and types*)
and the UI STATE the client derives from it is right (may comment, add buttons, may seek, which context-menu actions are
enabled, what an action really does). No pixels, no rendering.

## Layers

| Layer | File | What it proves |
|---|---|---|
| Client policy | `js/CommentPolicy/CommentPolicy.ts` | The client's decisions as pure functions. `app.ts`, the video overlay, the comment store and the comment list call them instead of testing raw values. |
| Client tests | `js/CommentPolicy/test/CommentPolicy.test.ts` | (1) value tables fed with values typed as the server sends them; (2) every record of the golden files fed through the functions and compared with the client RULES below; (3) `app.ts` really calls the functions. |
| Server tests | `tests/CommentContractTest.php` | Drives the real `Ui::index()` as real course members and compares what it sends with the server RULES below; writes the golden files. |
| Golden files | `tests/fixtures/comment-contract-*.json` | One record per case: viewer, the options the case was set up with, and the payload with its types. They are the bridge: the PHP test regenerates and compares them, the JS test consumes them. |

The two rule tables are written independently of the code they test (from the option definitions in `Bo.php`), and each
is checked against the real payload, so a rule and the code have to be wrong in the same way to pass.

## Dimensions

| Dimension | Values | Where it acts |
|---|---|---|
| Viewer | admin (owner), teacher, tutor, student with group 1 / group 2 / no group, non-member | `is_staff` (`"admin"`, `"teacher"`, `"tutor"` or `null`), `Bo::listComments()`, actions |
| `video_options` (comment visibility) | 0 all, 1 hide other students, 2 hide teachers, 3 own only, 4 forbidden by students, 5 disabled, 6 group, 7 group hide teachers, 8 legacy "simulated" | which comments and replies are sent, the comment actions, the add button |
| `video_published` | draft, published (open / starts later / ended / in window), unavailable, read-only, prerequisite not met, target | `accessible`, `locked`/`countdown`, whether the video is delivered, the actions |
| `video_livefeedback_simulated` | `"0"` / `"1"` (strings), not yet watched / watched through | `livefeedback_session` (`simulated` / `ended`), `isCommentAllowed`, comments, `seekable` |
| Live feedback session | not started, running, hosting (the host), ended | `livefeedback_session`, the categories for the live buttons |
| Test mode | `video_test_duration` > 0: not started, running, stopped | `accessible`, `locked`, `timer`, comments |
| `video_test_options` bits | 1 allow pause, 2 forbid seek, 4 free comment only, 8 read-only after test, 16 teacher comments free, 32 hide teacher text | `seekable`, `free_comment_only`, the `pause` readonly flag, masks on staff comments, what a stopped test becomes |
| `video_test_display` | 0 - 3 | `questions` list only for 3 |
| Course | `course_options` (cognitive load = both bits of 5), config `lf_cats_for_everyone`, `no_free_comment`, categories incl. the special type `sc` | `clm`, `lf_full_cats`, `catsOptions`, the special-category actions |

## How the combinations were chosen

A full product (7 viewers x 9 comment options x 9 publishing states x 3 test states x 64 option masks x ...) is millions
of cases and most of them test the same line twice. The dimensions that interact get a full product, the others are varied
one at a time against a baseline:

| Test (golden group) | Combinations | Why this selection |
|---|---|---|
| `testCommentVisibilityByOption` (`visibility`) | full product: viewers x 8 comment options in two courses (one with groups, one without) + legacy 8 + non-member | The comment filter depends on viewer role, group membership and option together; each combination is cheap. |
| `testVideoStateByPublishing` (`state`) | 9 publishing states x admin / tutor / student | State acts through `accessible` and the viewer's role only; the comment options are held at "all". |
| `testTestModeByStateAndOption` (`test`) | 3 test states x 8 option sets (none, each bit alone, all) for a student, + staff on a not-started test, + display 0-3 | Each option bit acts on its own flag; "all" catches interactions between bits. Not the 64 masks. |
| `testSimulatedLiveSession` (`simulated`) | plain / running / finished x student / admin | The flag is a string; the states are the three the client distinguishes. |
| `testLivefeedbackSessionStates` (`livefeedback`) | 3 stored states (+ hosting) x admin / teacher / student | |
| `testCourseOptions` (`course`) | `course_options` 0, 1, 4, 5, 7 x `lf_cats_for_everyone` x admin / student | Both CLM bits alone and together. |
| `testCategoryOptions` (`categories`) | `no_free_comment` on / off x admin / student | |
| `testSharedConstants` (`constants`) | - | The numbers the client repeats from `Bo.php`. |

## Rules

### Server: which comments a viewer receives (`video_options`)

Authors in a video: admin, staff (tutor or teacher), student 1, student 2; each comment has replies from other authors.
A reply is shown exactly if its author would be shown as a comment author.

| `video_options` | student receives | staff receives |
|---|---|---|
| 0 all | everybody | everybody |
| 1 hide other students | own + staff | everybody |
| 2 hide teachers | all students, no staff | everybody |
| 3 own only | own | everybody |
| 4 forbidden by students | staff only | everybody |
| 5 disabled | no `comments` key at all | no `comments` key at all (`index()` drops it for everybody) |
| 6 group | own group + staff | everybody |
| 7 group hide teachers | own group | everybody |

Students without a group are one group (they see each other in modes 6 and 7). Comment `class`: `commentOwner` for the
author, and for admin and teacher on every comment; **not** for a tutor on other people's comments.

### Server: actions and add button

- Actions on the list: `open, add, edit, retweet, delete`; only `open` for a student when comments are forbidden (4) or the
  video is read-only. `edit` and `delete` carry `enableClass: commentOwner`.
- Initial `hidden` of the add-comment button (`showCommentButton()`): hidden for tutors and students in modes 4 and 5, never
  for teacher/admin. The client overwrites it in `et2_ready()` (next table), so this only decides the first frame.

### Server: video state (student, unless stated)

| State | accessible | what arrives |
|---|---|---|
| draft, ended, unavailable, prerequisite not met | `false` | no video (only a layout key remains in `content.video`), no comments |
| starts later | `false` | video, `locked`, `countdown` (comments: see gap 1) |
| published, in window, target | `true` | video, comments, all actions |
| read-only | `"readonly"` | video, comments, only `open` |
| staff, any state except a prerequisite | `true` | everything |
| prerequisite not met, staff | `true`, `locked` | an unmet prerequisite locks staff as well |

Test mode (student, duration 10 min): not started -> `accessible: null`, `locked`, no timer; running -> `accessible: true`,
`timer` = seconds left (positive integer), `pause` readonly unless bit 1; stopped -> no video, or with bit 8 `"readonly"` with
`video_options` forced to 1 and the state to read-only. While running: bit 32 empties staff text *and replies*, bit 16 sets
staff comments' category to `free`. Bit 4: `free_comment_only` true and every comment's `comment_cat_type` is `free`.
`seekable` is `false` for a student with bit 2, `true` for staff. `questions` only for display 3.

Simulated live session, not yet watched: no comments, `isCommentAllowed: false`, add button hidden, `livefeedback_session:
simulated`, `video_test_options` forced to 2, `seekable` only for staff. Watched through: `ended`, comments back.

### Client: what the policy functions derive (`CommentPolicy.ts`)

| Decision | Rule |
|---|---|
| `isSimulated(video)` | the flag read as a number: `"0"` is **false** |
| `isCommentAllowed(is_staff, video)` | not allowed when: option 5 (everybody); option 4 and the viewer is not staff; simulated and the session is not `ended`. Everything else allowed |
| add-comment buttons | `hidden = !isCommentAllowed` |
| `isInTestMode(video, timer)` | duration > 0 **and** timer (seconds left) > 0 |
| `isSeekable(is_staff, video)` | staff, or no forbid-seek bit - equals the server's `video.seekable` |
| `isOpenIgnored('open', ...)` | a student, forbid-seek bit set |
| `effectiveAction(action, accessible, fromList, texts)` | read-only turns every action into `open`; the list's `edit` of a comment with replies is `open` |
| `isCommentOwner` / `canEditComment` | staff or author (ids compared as text); editing also needs a video that is not read-only |
| `isActionEnabled(enableClass, classes)` | enabled without `enableClass`, else the comment needs one of the classes |
| `isCognitiveLoadMeasurement`, `isTestDisplayList` | both bits of 5; display 3 - equal to the server's `clm` / `questions` |

## Known gaps and findings

Found by running the matrix. Each is either pinned as "today's behaviour" in a test that **fails when the gap closes** (so
the entry is removed and the case held to the rule), or listed as an observation. None was changed: they are product
decisions.

1. **Comments of a locked page are sent.** `index()` reads the comments before it decides the page is locked, and never
   drops them. A student whose test is not yet started receives the staff comments in full - also with bit 32 (hide teacher
   text), which only applies while the test runs. (`knownGaps()`: `test/*/not-started/s1`, `state/published-starts-later/s1`)
2. **Any non-zero test option makes a stopped test read-only.** `Bo::videoAccessible()` tests
   `$video['video_test_options'] && self::TEST_OPTION_VIDEO_READONLY_AFTER_TEST` (logical and). A student whose finished test
   has only "allow pause" (or any option except bit 8) gets the video read-only instead of "no longer accessible".
   (`knownGaps()`: `test/*/stopped/s1`)
3. **Read-only video still shows the add-comment buttons** (client): `isCommentForbidden()` ignores `accessible`, while the
   server removes the `add` action and `saveComment()` refuses. Pinned in `CommentPolicy.test.ts`, "KNOWN GAP".
4. A **tutor** gets edit and delete enabled on everybody's comments in the list menu (the store marks staff as owner) while
   the server's comment `class` and `saveComment()` only allow that to teacher and up.
5. **Read-only is never applied to staff**: `videoAccessible()` returns `true` through `videoEditable()` before it reaches
   the read-only branch, whose comment says course admins are included. Recorded as the rule (`state/readonly/admin`).
6. An **unmet prerequisite** locks staff out too (recorded as the rule, `state/prerequisite-not-met/admin`).
7. `et2_ready()` disables the forward/backward/playback buttons for a forbid-seek test (`notSeekable`) for staff as well,
   while the video overlay's slider and the server's `seekable` exempt staff. That place is not behind `isSeekable()` (it
   would change behaviour), only the overlay's two uses are.
8. `_student_setCommentArea()` computes a `forbidTocomment` (with an inverted staff test) that nothing reads.
9. The tutor's add button is hidden by the server in modes 4 and 5 and shown by the client afterwards (the client wins).

Not covered: the prerequisite *satisfied* path (needs a finished test of another video), per-participant `agreed`
disclaimers, the real push path (`CommentTestAdd/Reply`, `BoTest` cover recipients), the viewer's own preferences, mobile
templates, anything visual.

## Running

PHP, in the container, one invocation at a time (about 90 s):

```bash
docker exec -u www-data egroupware bash -c "cd /var/www/egroupware && vendor/bin/phpunit -c doc/phpunit.xml smallpart/tests/CommentContractTest.php"
```

Each failure names the case and the fact: `state/published-ended/s1: has_video: rule false, got true`.

The payload is compared with the golden files. After an intended change of what the server sends, regenerate and review the
diff (the container user needs write access to `tests/fixtures/`):

```bash
chmod o+w smallpart/tests/fixtures
docker exec -u www-data egroupware bash -c "cd /var/www/egroupware && UPDATE_GOLDEN=1 vendor/bin/phpunit -c doc/phpunit.xml smallpart/tests/CommentContractTest.php"
chmod o-w smallpart/tests/fixtures
```

JS, from the repository root:

```bash
npm run jstest -- --group smallpart
JSTEST_BROWSERS=chromium,firefox npx web-test-runner --config web-test-runner.config.mjs --group smallpart
```

The test creates and deletes its own courses; the accounts are the shared ones of `SmallpartTestAccounts`. It writes a
"last video" row for each, as the app does when a user opens a video.

## Adding a case

Add the combination to the matching test (rule next to it), run PHP with `UPDATE_GOLDEN=1`, run the JS tests - they pick
the new record up without changes. A new client decision belongs in `CommentPolicy.ts` (and is called from `app.ts`), with
a value table and, if the server sends what it depends on, a golden-driven check.
