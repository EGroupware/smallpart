#### **smallPART** - selfdirected media assisted learning lectures & Process Analysis Reflection Tool

#### Questions:

#### ToDo:
- [ ] LTI support as tool to embed into other learning platforms
- [ ] LTI support as platform to embed other tools into EGroupware (probable added to our OpenID Connect App)
- [ ] Schiboleth / SAML authentication in EGroupware (outside this app)

##### Security:
- [ ] move ACL check to server-side eg. editing comments could be done by anyone from JS console
- [ ] not sending full names to client-side, as they are visible to everyone in JS console
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

#### You need to make thes videos available via the webserver
```
# SmallParT videos
location /egroupware/smallpart/Resources/Videos {
    alias /var/lib/egroupware/default/files/smallpart;
}
```
