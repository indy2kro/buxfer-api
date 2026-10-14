# Security policy

## Supported versions

Security fixes are released for the latest major version.

## Reporting a vulnerability

Please do not open a public issue for a security problem. Report it privately through
[GitHub security advisories](https://github.com/indy2kro/buxfer-api/security/advisories/new)
or by email to indy2kro@gmail.com. Expect a first answer within a few days.

## What to know when using the library

- Buxfer takes the session token as a URL query parameter, so request URLs are sensitive. Do not log them, and do not log the requests of your own HTTP client.
- `login()` sends the credentials as POST parameters, and the password is marked with `#[\SensitiveParameter]`, so it is redacted from stack traces on PHP 8.2 and newer.
- Guzzle exceptions are not passed on, because their messages contain the request URL. Errors are thrown as `BuxferApi\Exception`.
- Redirects are not followed, so the token is never sent to another scheme or host.
- `var_dump()` and `print_r()` of the client do not show the token.
