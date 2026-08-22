<?php
/**
 * EGroupware - SmallParT (ViDoTeach) - REST API tests
 *
 * @link https://www.egroupware.org
 * @package smallpart
 * @subpackage tests
 * @license https://spdx.org/licenses/AGPL-3.0-or-later.html GNU Affero General Public License v3.0 or later
 */

namespace EGroupware\SmallParT;

use EGroupware\Api;
use EGroupware\Api\RestBase;
use GuzzleHttp\RequestOptions;

require_once __DIR__.'/../../../api/tests/RestBase.php';

/**
 * REST API tests for /smallpart/ (course/participant/material CRUD, ACL).
 *
 * A course needs an explicit `org` (course_org) pointing to a group every acting user is a member
 * of - So::aclFilter() only makes a course visible to non-owners via `course_org IN (:acl)`, there
 * is no "public/unrestricted" fallback for a course with no org set (see BoTest.php's
 * $defaultGroupId docblock for the same finding at the Bo level).
 *
 * @covers \EGroupware\SmallParT\ApiHandler::get
 * @covers \EGroupware\SmallParT\ApiHandler::put
 * @covers \EGroupware\SmallParT\ApiHandler::delete
 */
class SmallpartRestCreateReadDeleteTest extends RestBase
{
	protected static $users = [
		'smallpart_rest_teacher'  => ['primary_group' => 'Default'],
		'smallpart_rest_student1' => ['primary_group' => 'Default'],
		'smallpart_rest_student2' => ['primary_group' => 'Default'],
	];

	/**
	 * course_id's created by tests, closed in tearDown() via the admin client
	 *
	 * @var int[]
	 */
	private array $created_courses = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::createUsersACL(self::$users, 'smallpart');
		foreach (self::$users as $data)
		{
			// _common_get_put_delete()'s generic app-rights gate, same as tracker/invoices
			self::addAcl('smallpart', 'run', $data['id'], 1);
		}
		// course-creation right (Bo::checkTeacher()'s gate)
		self::addAcl('smallpart', 'admin', self::$users['smallpart_rest_teacher']['id'], 1);
	}

	protected function tearDown(): void
	{
		foreach ($this->created_courses as $course_id)
		{
			$this->adminClient()->delete($this->url("/smallpart/$course_id"), [
				RequestOptions::HEADERS => $this->jsonHeaders(),
			]);
		}
		$this->created_courses = [];
	}

	// -------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------

	/**
	 * Client authenticated as EGW_ADMIN_USER - a real superadmin bypasses all smallpart ACL
	 * (Bo::isSuperAdmin()), so it can always create/close a course regardless of the test's role.
	 */
	private function adminClient(): \GuzzleHttp\Client
	{
		return $this->getClient([
			RequestOptions::AUTH => [$GLOBALS['EGW_ADMIN_USER'], $GLOBALS['EGW_ADMIN_PASSWORD']],
		]);
	}

	private function courseFixture(array $overrides=[]): array
	{
		// null overrides (eg. ['org' => null]) drop the key entirely rather than sending a literal
		// JSON null - JsObjects::parseAccount() has no graceful handling for that
		return array_filter(array_merge([
			'@type' => 'course',
			'name' => 'phpunit REST course '.bin2hex(random_bytes(4)),
			'org' => 'Default',
		], $overrides), static fn($value) => $value !== null);
	}

	/**
	 * Create a course as the teacher user, track it for cleanup, return its id.
	 */
	private function createCourse(array $overrides=[]): int
	{
		$response = $this->getClient('smallpart_rest_teacher')->post($this->appUrl('smallpart'), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody($this->courseFixture($overrides)),
		]);
		$this->assertHttpStatus(201, $response, 'course creation');
		$course_id = (int)$this->resourceIdFromResponse($response, 'smallpart');
		$this->assertNotEmpty($course_id);
		$this->created_courses[] = $course_id;

		return $course_id;
	}

	private function courseUrl(int $course_id): string
	{
		return "/smallpart/$course_id";
	}

	// -------------------------------------------------------------------
	// Course create / read
	// -------------------------------------------------------------------

	public function testCreateCourseMinimalBody()
	{
		$response = $this->getClient('smallpart_rest_teacher')->post($this->appUrl('smallpart'), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody($this->courseFixture(['name' => 'Minimal course'])),
		]);
		$this->assertHttpStatus(201, $response);
		$this->assertNotEmpty($this->locationPath($response));
		$course_id = (int)$this->resourceIdFromResponse($response, 'smallpart');
		$this->created_courses[] = $course_id;
	}

	public function testCreateCourseWithoutNameFails()
	{
		$response = $this->getClient('smallpart_rest_teacher')->post($this->appUrl('smallpart'), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody(['@type' => 'course', 'org' => 'Default']),
		]);
		$this->assertHttpStatus([400, 422], $response, 'course without required name');
	}

	public function testCreateCourseAsNonTeacherFails()
	{
		$response = $this->getClient('smallpart_rest_student1')->post($this->appUrl('smallpart'), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody($this->courseFixture()),
		]);
		$this->assertHttpStatus(403, $response);
	}

	public function testGetCourseRoundTripsName()
	{
		$course_id = $this->createCourse(['name' => 'Round trip course']);

		$response = $this->getClient('smallpart_rest_teacher')->get($this->url($this->courseUrl($course_id)), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus(200, $response);
		$this->assertJsonFields(['name' => 'Round trip course'], $response);
	}

	public function testCourseOwnerIsCreatingUser()
	{
		$course_id = $this->createCourse();

		$response = $this->getClient('smallpart_rest_teacher')->get($this->url($this->courseUrl($course_id)), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$body = $this->jsonDecode($response);
		// regression test for the "course_onwer" typo: without the fix, 'owner' is empty/wrong
		$this->assertNotEmpty($body['owner'] ?? null);
	}

	public function testGetNonExistentCourseReturns404()
	{
		$response = $this->getClient('smallpart_rest_teacher')->get($this->url('/smallpart/999999999'), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		// regression test: ApiHandler::read() must distinguish "does not exist" (404) from
		// "exists but no access" (403) - before the fix this always returned 403
		$this->assertHttpStatus(404, $response);
	}

	public function testGetCourseAsNonParticipantReturns403()
	{
		// Bo::read($id, false)'s ACL-filtered SQL query (course_owner OR course_org) is the only
		// gate for a bare GET (Api\CalDAV\Handler::_common_get_put_delete() never calls
		// check_access() for READ) - a course with NO org set is thus only ever visible to its
		// owner, unlike our other fixtures which deliberately set org='Default' so any test user
		// can browse/find it before subscribing (matches doc/REST-API.md's collection-listing
		// example: "courses a given user has access to, could or already has subscribed").
		$course_id = $this->createCourse(['org' => null]);

		$response = $this->getClient('smallpart_rest_student1')->get($this->url($this->courseUrl($course_id)), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus(403, $response);
	}

	// -------------------------------------------------------------------
	// Course update
	// -------------------------------------------------------------------

	public function testUpdateCourseAsTeacherSucceeds()
	{
		$course_id = $this->createCourse();

		// regression test for two bugs at once:
		// 1) check_access() previously required ROLE_ADMIN for EDIT too, so even the course's own
		//    teacher (not admin/owner) got 403 here
		// 2) Bo::save()'s update path crashed with a fatal TypeError (missing 'participants' key,
		//    never populated by JsObjects::parseJsCourse()/ApiHandler for a REST update)
		$response = $this->getClient('smallpart_rest_teacher')->patch($this->url($this->courseUrl($course_id)), [
			RequestOptions::HEADERS => $this->jsonHeaders(['Prefer' => 'return=representation']),
			RequestOptions::BODY => $this->jsonBody(['name' => 'Renamed via PATCH']),
		]);
		$this->assertHttpStatus([200, 204], $response);
		if ($response->getStatusCode() === 200)
		{
			$this->assertJsonFields(['name' => 'Renamed via PATCH'], $response);
		}
	}

	public function testUpdateCourseAsStudentForbidden()
	{
		$course_id = $this->createCourse();
		$this->subscribeSelf('smallpart_rest_student1', $course_id);

		$response = $this->getClient('smallpart_rest_student1')->patch($this->url($this->courseUrl($course_id)), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody(['name' => 'Hijacked']),
		]);
		$this->assertHttpStatus(403, $response);
	}

	// -------------------------------------------------------------------
	// Participants
	// -------------------------------------------------------------------

	private function subscribeSelf(string $user, int $course_id, array $body=null): \Psr\Http\Message\ResponseInterface
	{
		return $this->getClient($user)->post($this->url($this->courseUrl($course_id).'/participants/'), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => isset($body) ? $this->jsonBody($body) : '',
		]);
	}

	public function testSelfSubscribeEmptyBodyBecomesStudent()
	{
		$course_id = $this->createCourse();

		$response = $this->subscribeSelf('smallpart_rest_student1', $course_id);
		$this->assertHttpStatus(201, $response);

		$course = $this->jsonDecode($this->getClient('smallpart_rest_teacher')->get(
			$this->url($this->courseUrl($course_id)), [RequestOptions::HEADERS => $this->jsonHeaders()]));
		$participant = current(array_filter($course['participants'], function($p)
		{
			return $p['role'] === 'student';
		}));
		$this->assertNotFalse($participant, 'newly self-subscribed student must be listed with role student');
	}

	public function testSelfSubscribeCannotRequestAdminRole()
	{
		$course_id = $this->createCourse();

		// regression test for the self-subscribe privilege-escalation bug: a plain participant
		// must NEVER be able to grant themselves a role above student, even via an explicit body
		$response = $this->subscribeSelf('smallpart_rest_student1', $course_id, [
			'@type' => 'participant', 'role' => 'admin',
		]);
		$this->assertHttpStatus(201, $response, 'self-subscribe request itself still succeeds...');

		$course = $this->jsonDecode($this->getClient('smallpart_rest_teacher')->get(
			$this->url($this->courseUrl($course_id)), [RequestOptions::HEADERS => $this->jsonHeaders()]));
		$student_id = self::$users['smallpart_rest_student1']['id'];
		$this->assertSame('student', $course['participants'][$student_id]['role'] ?? null,
			'...but must silently end up as role student, not admin');
	}

	public function testRegisteringOthersAsStudentRequiresTeacher()
	{
		$course_id = $this->createCourse();
		$this->subscribeSelf('smallpart_rest_student1', $course_id);

		// student1 (a plain student) tries to register student2 - must be rejected
		$response = $this->getClient('smallpart_rest_student1')->post(
			$this->url($this->courseUrl($course_id).'/participants/'), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody([
				'@type' => 'participant',
				'account' => 'smallpart_rest_student2',
			]),
		]);
		$this->assertHttpStatus(403, $response);
	}

	public function testRegisteringOthersAsTeacherRequiresAdminOrOwner()
	{
		$course_id = $this->createCourse();

		// the course's teacher (owner) may register student2 directly as a teacher
		$response = $this->getClient('smallpart_rest_teacher')->post(
			$this->url($this->courseUrl($course_id).'/participants/'), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody([
				'@type' => 'participant',
				'account' => 'smallpart_rest_student2',
				'role' => 'teacher',
			]),
		]);
		$this->assertHttpStatus(201, $response);

		$course = $this->jsonDecode($this->getClient('smallpart_rest_teacher')->get(
			$this->url($this->courseUrl($course_id)), [RequestOptions::HEADERS => $this->jsonHeaders()]));
		$student2_id = self::$users['smallpart_rest_student2']['id'];
		$this->assertSame('teacher', $course['participants'][$student2_id]['role'] ?? null);
	}

	public function testPatchOwnAliasDoesNotDemoteStaff()
	{
		$course_id = $this->createCourse();

		// promote student1 to teacher first
		$this->getClient('smallpart_rest_teacher')->post($this->url($this->courseUrl($course_id).'/participants/'), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody([
				'@type' => 'participant', 'account' => 'smallpart_rest_student1', 'role' => 'teacher',
			]),
		]);

		// regression test: an alias-only PATCH (no 'role' in the body) must not reset the
		// participant's role back to student (JsObjects::parseParticipant()'s old_role fallback)
		$student1_id = self::$users['smallpart_rest_student1']['id'];
		$response = $this->getClient('smallpart_rest_student1')->patch(
			$this->url($this->courseUrl($course_id)."/participants/$student1_id"), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody(['@type' => 'participant', 'alias' => 'Teach1']),
		]);
		$this->assertHttpStatus([200, 204], $response);

		$course = $this->jsonDecode($this->getClient('smallpart_rest_teacher')->get(
			$this->url($this->courseUrl($course_id)), [RequestOptions::HEADERS => $this->jsonHeaders()]));
		$this->assertSame('teacher', $course['participants'][$student1_id]['role'] ?? null,
			'alias-only update must not demote an existing teacher back to student');
	}

	public function testUnsubscribeIsSoftDelete()
	{
		$course_id = $this->createCourse();
		$this->subscribeSelf('smallpart_rest_student1', $course_id);
		$student1_id = self::$users['smallpart_rest_student1']['id'];

		$response = $this->getClient('smallpart_rest_student1')->delete(
			$this->url($this->courseUrl($course_id)."/participants/$student1_id"), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus([200, 204], $response);

		// course-teacher can still see the (unsubscribed) participant row
		$course = $this->jsonDecode($this->getClient('smallpart_rest_teacher')->get(
			$this->url($this->courseUrl($course_id)), [RequestOptions::HEADERS => $this->jsonHeaders()]));
		$this->assertArrayHasKey($student1_id, $course['participants'] ?? [],
			'unsubscribe must be soft - the participant row must still exist');
		$this->assertNotEmpty($course['participants'][$student1_id]['unsubscribed'] ?? null);
	}

	// -------------------------------------------------------------------
	// Materials (JSON metadata only, no binary upload)
	// -------------------------------------------------------------------

	public function testCreateMaterialMetadataOnly()
	{
		$course_id = $this->createCourse();

		$response = $this->getClient('smallpart_rest_teacher')->post($this->url($this->courseUrl($course_id).'/'), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody(['@type' => 'material', 'name' => 'Lesson 1']),
		]);
		$this->assertHttpStatus(201, $response);
		$location = $this->locationPath($response);
		$this->assertNotEmpty($location);

		preg_match('#/smallpart/\d+/(\d+)#', $location, $matches);
		$material_id = (int)($matches[1] ?? 0);
		$this->assertNotEmpty($material_id);

		$get = $this->getClient('smallpart_rest_teacher')->get(
			$this->url($this->courseUrl($course_id)."/$material_id"), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus(200, $get);
		$this->assertJsonFields(['name' => 'Lesson 1'], $get);
	}

	public function testUpdateAndDeleteMaterial()
	{
		$course_id = $this->createCourse();
		$create = $this->getClient('smallpart_rest_teacher')->post($this->url($this->courseUrl($course_id).'/'), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody(['@type' => 'material', 'name' => 'Lesson 2']),
		]);
		$this->assertHttpStatus(201, $create, 'material creation');
		preg_match('#/smallpart/\d+/(\d+)#', $this->locationPath($create), $matches);
		$material_id = (int)($matches[1] ?? 0);
		$this->assertNotEmpty($material_id, 'Location header: '.$this->locationPath($create));

		$patch = $this->getClient('smallpart_rest_teacher')->patch(
			$this->url($this->courseUrl($course_id)."/$material_id"), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody(['name' => 'Lesson 2 renamed']),
		]);
		$this->assertHttpStatus([200, 204], $patch);

		$delete = $this->getClient('smallpart_rest_teacher')->delete(
			$this->url($this->courseUrl($course_id)."/$material_id"), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus([200, 204], $delete);

		$get = $this->getClient('smallpart_rest_teacher')->get(
			$this->url($this->courseUrl($course_id)."/$material_id"), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus(404, $get);
	}

	// -------------------------------------------------------------------
	// Course close (DELETE)
	// -------------------------------------------------------------------

	public function testDeleteCourseAsTeacherForbidden()
	{
		$course_id = $this->createCourse();

		// the course's CREATOR is auto-subscribed as course-admin (not just teacher), so use a
		// SEPARATE teacher (student1, explicitly registered with role=teacher, not owner/admin) to
		// actually exercise "closing a course requires a course-admin, plain teacher is not enough"
		// per doc/REST-API.md
		$this->getClient('smallpart_rest_teacher')->post($this->url($this->courseUrl($course_id).'/participants/'), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody([
				'@type' => 'participant', 'account' => 'smallpart_rest_student1', 'role' => 'teacher',
			]),
		]);

		$response = $this->getClient('smallpart_rest_student1')->delete($this->url($this->courseUrl($course_id)), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus(403, $response);
	}

	public function testDeleteCourseAsAdminCloses()
	{
		$course_id = $this->createCourse();

		$response = $this->adminClient()->delete($this->url($this->courseUrl($course_id)), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus([200, 204], $response);

		$get = $this->adminClient()->get($this->url($this->courseUrl($course_id)), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus(200, $get);
		$this->assertJsonFields(['closed' => true], $get);
	}
}
