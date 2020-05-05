## SmallParT
#### Selfdirected media assisted learning lectures & Process analysis reflection Tool

#### Questions:

* nickname vs. account_lid
  - has "nickname" only to be unique or does it need to have some meaning to the users and/or should be changable by the user himself
    - according to Arash no need for a customizable nickname
    - use regular user display setting **forced** for students to eg. "Firstname L." or a new one "First [ID]"
  - EGroupware account_lid is required to be unique and needed to persistent link Shibboleth identities persistent to EGroupware users
* should we use internal.php instead of index.php (maybe show image when no course is select)
* do we want / need the old menu as we now have a sidebox menu

#### ToDo:

##### Security:
- [ ] ContentSecurityPolicy / no more inline JavaScript
- [ ] move ACL check to server-side eg. editing comments could be done by anyone from JS console
- [ ] not sending full names to client-side, as they are visible to everyone in JS console

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