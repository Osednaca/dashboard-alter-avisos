# Login and business contact email

After successful login, the dashboard consumes the previous intended URL and
checks its origin, area, and route permissions against the authenticated user.
An old administrator link falls back to the business dashboard for a business
user. Staff without the destination permission fall back to the admin dashboard.
Unknown, external, or model-specific links also fall back: model ownership and
controller policies cannot be established solely from route middleware after an
identity change. Direct forbidden requests still return 403.
The error page's recovery link also uses the current account's home: business
dashboard for a business user, admin dashboard for staff, and login for guests.
This avoids a forbidden-link loop after following a previous account's URL.

Successful login clears the selected business from the preceding session and
requests that Inertia clear its history on the next page. Logout invalidates the
session and also requests history clearing. The login password uses the shared
show/hide input with a keyboard-accessible button, an accessible Spanish label,
and current-password autocomplete. Its value is kept when toggling visibility.

Authenticated Inertia page history is encrypted. Clearing history during login
or logout removes the previous encryption key so a browser Back navigation
cannot decrypt an earlier account's page props. Public login history is not
encrypted. This requires browser Web Crypto on HTTPS, or localhost during local
verification; the installed Inertia client falls back to plaintext when Web
Crypto is unavailable. Clearing history is not a replacement for server-side
authorization or the PWA's exclusion of private responses from its cache.
Authenticated dashboard responses also send `Cache-Control: no-store, private`
for both the HTML shell and Inertia requests, preventing browsers and shared
caches from retaining an earlier account's response. This web middleware does
not change the device API's caching behavior.

The contact email belongs to the business record and can also be used by that
business's login account. The current code does not impose uniqueness across
these two tables. The real HTTP regression creates the business, creates and
updates its account with the same email, and logs in successfully. The reported
cross-table rejection was not reproduced locally; the user confirmed that the
email had already belonged to an existing login account. A second account using
an existing account email is still rejected, preserving unambiguous login.
No production data or deployed code was inspected for this issue.

Checks: `LoginDestinationTest`, `AuthenticationTest`,
`UserBusinessAssignmentTest`, `BusinessManagementTest`, and the frontend
`web-branding.test.mjs` assertions cover the behavior, including error recovery
for business, staff and guest identities. Isolated browser verification observed
keyboard reveal/hide with Enter and Space, successful login, and admin logout
followed by a business login from an old admin URL. Back navigation to an earlier
encrypted admin page resulted in server authorization rejecting it with 403.
