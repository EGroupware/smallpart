#### **smallPART** - selfdirected media assisted learning lectures & Process Analysis Reflection Tool

#### Questions:

#### ToDo:

##### Security:
- [ ] move ACL check to server-side eg. editing comments could be done by anyone from JS console
- [ ] not sending full names to client-side, as they are visible to everyone in JS console
- [x] ContentSecurityPolicy / no more inline JavaScript

##### Other:
- [ ] Store videos in EGroupware VFS and read via WebDAV URL
- [x] Convert Admin role to EGroupware ACL
- [x] Use EGroupware account management and session

#### You need to make thes videos available via the webserver
```
# SmallParT videos
location /egroupware/smallpart/Resources/Videos {
    alias /var/lib/egroupware/default/files/smallpart;
}
```
