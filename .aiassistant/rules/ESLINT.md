---
apply: always
---

# KAGG Compatibility JavaScript rules

This repository has no ESLint configuration, `package.json`, JS package manager,
or asset build. Do not copy unrelated ESLint environments or commands into it.
If JavaScript tooling is introduced, its project configuration becomes authoritative;
update this document to match it.

## Existing source
- Admin behavior is in `assets/js/general.js`; the shipped counterpart is
  `assets/js/general.min.js`. Keep both consistent when behavior changes.
- Follow the existing WordPress JavaScript style: tabs, single quotes, semicolons,
  and spaces inside parentheses and array brackets.
- Use descriptive names and preserve the jQuery event-handler context when
  choosing between ordinary functions and arrow functions.
- Declare WordPress-provided globals, including `jQuery` and
  `KAGGCompatibilityGeneralObject`, in the existing file header.
- Pass user-facing strings from PHP localization using the plugin text domain.
- Use the localized AJAX URL and nonce for state-changing requests.

## Verification
Run the configured syntax checks after JavaScript changes:

```sh
node --check assets/js/general.js
node --check assets/js/general.min.js
```

These checks validate syntax; they do not replace behavioral verification in
WordPress. Do not assume `yarn lint`, Jest, React, or TypeScript are configured.
