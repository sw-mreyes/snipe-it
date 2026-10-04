# Security Policy

We take security issues very seriously, and will always attempt to address any 
vulnerabilities as quickly as possible. 

## Supported Versions

We try to make a reasonable effort to support older versions of Snipe-IT, 
however there are times when library dependencies and/or PHP/MySQL dependencies 
make it impossible to backport security fixes on older versions. 

| Version | Supported          |
|---------|--------------------|
| 8.x     | :white_check_mark: |
| 7.x     | :x:                |
| 6.x     | :x:                |
| 5.1.x   | :x:                |
| 5.0.x   | :x:                |
| 4.0.x   | :x:                |
| < 4.0   | :x:                |

## Reporting a Vulnerability

__Security vulnerabilities should be reported [via a new GHSA](https://github.com/grokability/snipe-it/security) or
via email security@snipeitapp.com.__

If we find that your security report is valid but has been reported in a previous GHSA that is currently still
in draft mode (therefore invisiable to you), we will add your Github username to the GHSA so that you can be
credited for your discovery.

You can typically expect a response within two business days, and we typically have fixes out in under a week
from the initial disclosure.

This obviously varies based on the severity of the security issue and the difficulty in remediation, but those have
historically been the timelines we work around.

__We do ask that you do not disclose the vulnerability publicly until we have had a chance to address it and tag a
release__ so that we can protect our users, and we will work with you to coordinate a public disclosure once we have a
fix out.

We will also work with you to ensure that you receive appropriate credit for the discovery of the vulnerability,
if you would like to be credited. (Please provide a GitHub username or other information if you would like to be
credited, and please let us know if you would like to remain
anonymous.)

For responsible disclosure, we ask that you give us at least __90 days__ to address the issue before disclosing it
publicly,
but we will work with you if you need to disclose it sooner than that.

For a full breakdown of our security policies, please see https://snipeitapp.com/security.
