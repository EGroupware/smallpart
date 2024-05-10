# EGroupware REST API for SmallParT / ViDoTeach

Authentication is via Basic Auth with username and a password, or a token valid for:
- either just the given user or all users
- CalDAV/CardDAV Sync (REST API)
- SmallParT / ViDoTeach application

All URLs used in this document are relative to EGroupware's REST API URL:
`https://egw.example.org/egroupware/groupdav.php/`

That means instead of `/smallpart/` you have to use the full URL `https://egw.example.org/egroupware/groupdav.php/smallpart/` replacing `https://egw.example.org/egroupware/` with whatever URL your EGroupware installation uses. 

### Hierarchy

`/smallpart`  application collection (`POST` request to create a new course)
  + `/<course-id>` course object (`GET`, `PATCH`, `PUT` or `DELETE`, `POST` to create a new course-part/material)
    + `/participants` participants objects (`GET`, `POST` to subscribe)
      + `/<account-id>` object of single participant (`GET`, `PATCH` or `DELETE` to unsubscribe)
    + `/cats` _not yet implemented_
    + `/materials` list of available course-parts/materials, object with <material-id> <material-name> pairs
    + `/<material-id>` material / course-part (`GET`, `PATCH`, `DELETE`)
      + `/attachments` attachments collection (`POST` to create/upload)
        + `/<filename>` attachment (`GET`, `PUT` or `DELETE`)
      + `/comments` _not yet implemented_ 
      + `/questions` _not yet implemented_

### Course

Courses are created via a `POST` request to the SmallParT collection: `/smallpart/`

Every course is a sub-collection in the above collection named by its ID.
With sufficient privileges coursed can be edited with `PUT` or `PATCH` requests 
and be removed with `DELETE` requests.

Following schema is used for JSON encoding of courses
* `@type`: `course`
* `id`: integer (readonly) ID
* `name`: string
* `info`: string (multiple lines, html)
* `disclaimer`: string (multiple lines, html)
* `password`: string, _NOT returned regular, used when creating or updating a course_
* `owner`: string (readonly) email, if set, or account-name of owner
* `org`: string account-name of group to limit visibility of course to it's members
* `closed`: bool flag if course is closed, default false
* `options`: object with following boolean attributes
  * `recordWatched`: boolean, record start-, end-time and position of watched videos  
  * `videoWatermark`: boolean, show a watermark on all videos
  * `cognitiveLoadMeasurement`: boolean, true to enable CognitiveLoadMeasurement
  * `allowNeutralLFcategories`: boolean
* `participants`: (readonly) object of participant-objects indexed by their numerical ID
  * `id`: integer (readonly) ID
  * `alias`: name to show for students to other students
  * `name`: string (readonly) full-name, only available to staff (non-student roles)
  * `role`: one of: `admin`, `teacher`, `tutor` or `student`
  * `group`: int ID if students are in subgroups
  * `subscribed`: DateTime-object (readonly) timestamp when student was subscribed
  * `unsubscribed`: DateTime-object (readonly) timestamp when student was unsubscribed
* `materials`: (readonly) object with ID and name pairs of the existing materials / course-parts

The response to the initial `POST` request to create a course contains a `Location` header to its collection 
`/smallpart/<course-id>/`, which can be used to further modify the course with a `PUT` or `PATCH` request, 
read it's current state with `GET` requests or `DELETE` it.

### Participants

Participants are subscribed by sending a POST request to `/smallpart/<course-id>/participants/<account-id>` with either:
* an empty body to create a new regular student
* or an object with the above (non-readonly) attributes, setting more than the `alias` requires a course-admin!
> `<account-id>` is EGroupware's numerical user-ID of an existing user created by other means! 

Participants can change their `alias` via a `PATCH` request with an object with just the `alias` attribute containing the new alias.
Course-admins can use `PUT` or `PATCH` requests to grant a higher role to participants or change the other attributes.

Participants are unsubscribed via a `DELETE` request to `/smallpart/<course-id>/participants/<account-id>`.
> `DELETE` requests never remove the former participant, but just set the `unsubscribed` attribute to the current time.

### Materials or course-parts
Each course-collection `/smallpart/<course-id>/` containing course-parts as sub-collections, with a main document for the students to work on. The main document is either
* a video (mp4 or WebM) or
* a PDF document

Materials are created by sending a `POST` request to the course collection with either:
* a video (Content-Type: `video/(mp4|webm)`) or
* a PDF document (Content-Type: `application/pdf`) or
* a JSON document (Content-Type: `application/json`) with metadata / object with the following attributes:
  * `@type`: `material`
  * `id`: integer (readonly) ID
  * `course`: integer (readonly) ID of course
  * `name`: string name of video
  * `date`: DateTime-object (readonly) last updated timestamp
  * `question`: string (multiple lines)
  * `hash`: string (readonly), used to construct video-urls
  * `url`: string URL of the mail-document, which can also be an external video e.g. on YouTube
  * `type`: string (readonly) either `mp4`, `webm`, `youtube` or `pdf`, type of main document
  * `commentType`: string, one of:
    * `show-all` show all comments
    * `show-group` show comments of own group incl. teachers
    * `hide-other-students` hide comment of other students
    * `hide-teachers` hide comments of teachers/staff
    * `show-group-hide-teachers` show comment of own group, but hide teachers
    * `show-own` show students only their own comments
    * `forbid-students` forbid students to comment
    * `disabled` disable comments, e.g. for tests/exams
  * `published`: string of either:
    * `draft`: Only available to course admins
    * `published`: Available to participants during optional begin- and end-date and -time
    * `unavailable`: Only available to course admins, e.g. during scoring of tests
    * `readonly`: Available, but no changes allowed e.g. to let students view their test scores
  * `publishedStart`: optional DateTime-object with start-time for above state `published`
  * `publishedEnd`: optional DateTime-object with end-time for above state `published`
  * `testDisplay` one of `instead-comments`, `dialog` or `video-overlay`
  * `testOptions`: object with following boolean attributes:
    * `allowPause` allow student to pause test
    * `forbidSeek` forbid student to seek in test
  * `testDuration`: integer optional duration in seconds, if this material/course-part is a test/exam
  * `attachments`: object (readonly) with filename and object pairs, with following attributes each:
    * `name`: string filename (also used as attribute-name of the object)
    * `url`: string URL of the file to download or update
    * `mime`: string mime-type of the file
    * `size`: int, size of the file

> Attributes marked as `(readonly)` should never be sent, they are only received in `GET` requests!

The response contains a `Location` header with the newly created material collection `/smallpart/<course-id>/<material-id>/`.

The main document and the JSON meta-data can always be updated by sending a `PUT` request with appropriate `Content-Type` header.

A material or its JSON meta-data can be read via a `GET` request with correct `Accept`-header to distinguish between JSON meta-data and the main document. 
> The server might respond with a redirect / `Location`-header to the `GET` request for the main document, instead of directly sending it!

A material / course-part is removed with a `DELETE` request to its collection URL.

Additional documents can be attached to a material / course-part and are displayed together with its question text by
sending a `POST` request to the materials `attachments` sub-collection: `/smallpart/<course-id>/<material-id>/attachments/`.
Attachments can be listed with a `GET` request to the `attachments` collection and updated or removed with `PUT` or `DELETE` requests to their URL.
> The server might respond to `GET` requests to an attachment-URL with a redirect / `Location` header!

### Supported request methods and examples

* **GET** to collections with an ```Accept: application/json``` header return all timesheets (similar to WebDAV PROPFIND)
<details>
  <summary>Example: Getting all timesheets of a given user</summary>

```
curl https://example.org/egroupware/groupdav.php/<username>/timesheet/ -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/<username>/timesheet/1": {
        "@type": "timesheet",
        "id": 1,
        "title": "Test",
        "start": "2005-12-16T23:00:00Z",
        "duration": 150,
        "quantity": 2.5,
        "unitprice": 50,
        "category": { "other": true },
        "owner": "ralf@example.org",
        "created": "2005-12-16T23:00:00Z",
        "modified": "2011-06-08T10:51:20Z",
        "modifier": "ralf@example.org",
        "status": "genehmigt",
        "etag": "1:1307537480"
    },
    "/<username>/timesheet/140": {
        "@type": "timesheet",
        "id": 140,
        "title": "Test Ralf aus PM",
        "start": "2016-08-22T12:12:00Z",
        "duration": 60,
        "quantity": 1,
        "owner": "ralf@example.org",
        "created": "2016-08-22T12:12:00Z",
        "modified": "2016-08-22T13:13:22Z",
        "modifier": "ralf@example.org",
        "egroupware.org:customfields": {
            "auswahl": {
                "value": [
                    "3"
                ],
                "type": "select",
                "label": "Auswählen",
                "values": {
                    "3": "Three",
                    "2": "Two",
                    "1": "One"
                }
            }
        },
        "etag": "140:1471878802"
    },
...
}
```
</details>

Following GET parameters are supported to customize the returned properties:
- props[]=<DAV-prop-name> eg. props[]=getetag to return only the ETAG (multiple DAV properties can be specified)
  Default for timesheet collections is to only return address-data (JsContact), other collections return all props.
- sync-token=<token> to only request change since last sync-token, like rfc6578 sync-collection REPORT
- nresults=N limit number of responses (only for sync-collection / given sync-token parameter!)
  this will return a "more-results"=true attribute and a new "sync-token" attribute to query for the next chunk

The GET parameter `filters` allows to filter or search for a pattern in timesheets of a user:
- `filters[search]=<pattern>` searches for `<pattern>` in the whole timesheet like the search in the GUI
- `filters[search][%23<custom-field-name>]=<custom-field-value>` filters by a custom-field value
- `filters[<attribute-name>]=<value>` filters by a DB-column name and value

<details>
   <summary>Example: Getting just ETAGs and displayname of all timesheets of a user</summary>

```
curl -i 'https://example.org/egroupware/groupdav.php/<username>/timesheet/?props[]=getetag&props[]=displayname' -H "Accept: application/pretty+json" --user <username>

{
  "responses": {
    "/ralf/timesheet/1": {"displayname":"Test","getetag":"\"1:1307537480\""},
    "/ralf/timesheet/140": {"displayname":"Test Ralf aus PM","getetag":"\"140:1471878802\""},
  }
}
```
</details>

<details>
   <summary>Example: Start using a sync-token to get only changed entries since last sync</summary>

#### Initial request with empty sync-token and only requesting 10 entries per chunk:
```
curl 'https://example.org/egroupware/groupdav.php/timesheet/?sync-token=&nresults=10&props[]=displayname' -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/timesheet/2050": "Frau Margot Test-Notifikation",
    "/timesheet/2384": "Test Tester",
    "/timesheet/5462": "Margot Testgedöns",
    "/timesheet/2380": "Frau Test Defaulterin",
    "/timesheet/5474": "Noch ein Neuer",
    "/timesheet/5575": "Mr New Name",
    "/timesheet/5461": "Herr Hugo Kurt Müller Senior",
    "/timesheet/5601": "Steve Jobs",
    "/timesheet/5603": "Ralf Becker",
    "/timesheet/1838": "Test Tester"
  },
  "more-results": true,
  "sync-token": "https://example.org/egroupware/groupdav.php/timesheet/1400867824"
}
```
#### Requesting next chunk:
```
curl 'https://example.org/egroupware/groupdav.php/timesheet/?sync-token=https://example.org/egroupware/groupdav.php/timesheet/1400867824&nresults=10&props[]=displayname' -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/timesheet/1833": "Default Tester",
    "/timesheet/5597": "Neuer Testschnuffi",
    "/timesheet/5593": "Muster Max",
    "/timesheet/5628": "2. Test Contact",
    "/timesheet/5629": "Testen Tester",
    "/timesheet/5630": "Testen Tester",
    "/timesheet/5633": "Testen Tester",
    "/timesheet/5635": "Test4 Tester",
    "/timesheet/5638": "Test Kontakt",
    "/timesheet/5636": "Test Default"
  },
  "more-results": true,
  "sync-token": "https://example.org/egroupware/groupdav.php/timesheet/1427103057"
}
```
</details>

<details>
   <summary>Example: Requesting only changes since last sync</summary>

#### ```sync-token``` from last sync need to be specified (note the null for a deleted resource!)
```
curl 'https://example.org/egroupware/groupdav.php/timesheet/?sync-token=https://example.org/egroupware/groupdav.php/timesheet/1400867824' -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/timesheet/5597": null,
    "/timesheet/5593": {
      TODO
....
    }
  },
  "sync-token": "https://example.org/egroupware/groupdav.php/timesheet/1427103057"
}
```
</details>

* **GET**  requests with an ```Accept: application/json``` header can be used to retrieve single resources / JsTimesheet schema
<details>
   <summary>Example: GET request for a single resource showcasing available fieldes</summary>

```
curl 'https://example.org/egroupware/groupdav.php/timesheet/140' -H "Accept: application/pretty+json" --user <username>
{
    "@type": "timesheet",
    "id": 140,
    "title": "Test Ralf aus PM",
    "start": "2016-08-22T12:12:00Z",
    "duration": 60,
    "quantity": 1,
    "project": "2024-0001: Test Project",
    "pm_id": 123,
    "unitprice": 100.0,
    "pricelist": 123,
    "owner": "ralf@example.org",
    "created": "2016-08-22T12:12:00Z",
    "modified": "2016-08-22T13:13:22Z",
    "modifier": "ralf@example.org",
    "egroupware.org:customfields": {
        "auswahl": {
            "value": [
                "3"
            ],
            "type": "select",
            "label": "Auswählen",
            "values": {
                "3": "Three",
                "2": "Two",
                "1": "One"
            }
        }
    },
    "etag": "140:1471878802"
}
```
</details>

* **POST** requests to collection with a ```Content-Type: application/json``` header add new entries in timesheet collections
  (Location header in response gives URL of new resource)
<details>
   <summary>Example: POST request to create a new resource</summary>

```
cat <<EOF | curl -i -X POST 'https://example.org/egroupware/groupdav.php/<username>/timesheet/' -d @- -H "Content-Type: application/json" -H 'Accept: application/pretty+json' -H 'Prefer: return=representation' --user <username>
{
    "@type": "timesheet",
    "title": "5. Test Ralf",
    "start": "2024-02-06T10:00:00Z",
    "duration": 60
}
EOF

HTTP/1.1 201 Created
Content-Type: application/json
Location: /egroupware/groupdav.php/ralf/timesheet/204
ETag: "204:1707233040"

{
    "@type": "timesheet",
    "id": 204,
    "title": "5. Test Ralf",
    "start": "2024-02-06T10:00:00Z",
    "duration": 60,
    "quantity": 1,
    "owner": "ralf@example.org",
    "created": "2024-02-06T14:24:05Z",
    "modified": "2024-02-06T14:24:00Z",
    "modifier": "ralf@example.org",
    "etag": "204:1707233040"
}
```
</details>

* **PUT**  requests with  a ```Content-Type: application/json``` header allow modifying single resources (requires to specify all attributes!)

<details>
   <summary>Example: PUT request to update a resource</summary>

```
cat <<EOF | curl -i -X PUT 'https://example.org/egroupware/groupdav.php/<username>/timesheet/1234' -d @- -H "Content-Type: application/json" --user <username>
{
    "@type": "timesheet",
    "title": "6. Test Ralf",
    "start": "2024-02-06T10:00:00Z",
    "duration": 60,
    "quantity": 1,
    "owner": "ralf@example.org",
    "created": "2024-02-06T14:24:05Z",
    "modified": "2024-02-06T14:24:00Z",
    "modifier": "ralf@example.org",
}
EOF

HTTP/1.1 204 No Content
```

</details>


* **PATCH** request with a ```Content-Type: application/json``` header allow to modify a single resource by only specifying changed attributes as a [PatchObject](https://www.rfc-editor.org/rfc/rfc8984.html#type-PatchObject)

<details>
   <summary>Example: PATCH request to modify a timesheet with partial data</summary>

```
cat <<EOF | curl -i -X PATCH 'https://example.org/egroupware/groupdav.php/<username>/timesheet/1234' -d @- -H "Content-Type: application/json" --user <username>
{
  "status": "invoiced"
}
EOF

HTTP/1.1 204 No content
```
</details>

* **DELETE** requests delete single resources

<details>
   <summary>Example: DELETE request to delete a timesheet</summary>

```
curl -i -X DELETE 'https://example.org/egroupware/groupdav.php/<username>/timesheet/1234' -H "Accept: application/json" --user <username>

HTTP/1.1 204 No content
```
</details>

> one can use ```Accept: application/pretty+json``` to receive pretty-printed JSON eg. for debugging and exploring the API