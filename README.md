#### **smallPART** - selfdirected media assisted learning lectures & Process Analysis Reflection Tool

#### Cooperation partners for content-related didactic development:
* Technical University of Kaiserslautern (Prof. Dr. Thyssen & Arash Tolou, M.A.)
* Eberhard Karls University of Tübingen: Until 31.12.2020 under the name "Live Feedback Plus"

#### ToDo:
- [ ] LTI support as platform to embed other tools into EGroupware (probable added to our OpenID Connect App)
- [x] LTI v1.0/1.1 tools support as to embed into platforms like OpenOLAT
- [x] Schiboleth / SAML authentication in EGroupware (outside this app)
- [x] LTI v1.3 tools support as to embed into platforms like Moodle

##### Security:
- [x] move ACL check to server-side eg. editing comments could be done by anyone from JS console
- [x] ContentSecurityPolicy / no more inline JavaScript

##### Other:
- [ ] Store videos in EGroupware VFS and read via WebDAV URL
- [x] Convert Admin role to EGroupware ACL
- [x] Use EGroupware account management and session

#### Resources:
- [LTI Specification v1.3](https://www.imsglobal.org/spec/lti/v1p3)
- [EduAppCenter](https://www.eduappcenter.com/) List of LTI apps
- [LTI Tutorial / Example App](https://acrl.ala.org/techconnect/post/making-a-basic-lti-learning-tools-intoperability-app/)
- [LTI and Moodle](https://docs.moodle.org/38/en/LTI_and_Moodle)
- [LTI v1.3 PHP Library](https://github.com/IMSGlobal/lti-1-3-php-library)
- [LTI v1.0/1.1/2.0 Library](https://github.com/celtic-project/LTI-PHP)

#### You can either use external video URLs or make the videos available via the webserver
```
# SmallParT videos
location /egroupware/smallpart/Resources/Videos {
    alias /var/lib/egroupware/default/files/smallpart;
}
```
