# Gravity Forms: Auto-prefix `https://` on Website fields

A one-function snippet that stops Gravity Forms from rejecting a Website field just because somebody typed `example.com` instead of `https://example.com`.

**Tested with:** Gravity Forms 3.2.1 · **Requires:** Gravity Forms 1.9+, PHP 7.4+ (Gravity Forms' own minimum) · **Built by:** [CRFTD](https://crftd.dev)

## The problem

Gravity Forms' Website field only accepts values that start with `http://` or `https://`. Anything else fails server-side validation with *"Please enter a valid Website URL (e.g. https://gravityforms.com)."* That check lives in `GFCommon::is_valid_url()` (`common.php`) and is still enforced in 3.2.1.

Real people don't type the scheme. They type `mycompany.com`, hit submit, get an error, and some of them bounce. On a lead-gen form that's a lost lead over a formality.

## What it does

Hooks `gform_pre_validation` and, for every Website field on the form:

| Submitted value | Stored value |
|---|---|
| `example.com` | `https://example.com` |
| `www.example.com/path` | `https://www.example.com/path` |
| `  example.com  ` (stray whitespace) | `https://example.com` |
| `//example.com` (protocol-relative) | `https://example.com` |
| `example.com:8080` | `https://example.com:8080` |
| `http://example.com` | unchanged |
| `https://example.com` | unchanged |
| `ftp://example.com` | unchanged (Gravity Forms rejects it, which is correct) |
| *(empty)* | unchanged (Gravity Forms' required check still applies) |

The fixed value is written back to `$_POST` *before* validation runs, so it's the value Gravity Forms validates and saves. No frontend changes, no JavaScript, nothing visible to the user except that their submission goes through.

Works on standard, AJAX, multi-page, and GFAPI / REST API v2 submissions. All of them run through `GFFormDisplay::validate()`, which is where `gform_pre_validation` fires.

## Install

Pick one. The first option is the most durable because it survives theme switches.

**mu-plugin (recommended)**
Drop `snippet.php` into `wp-content/mu-plugins/` (create the folder if it doesn't exist). Done. No activation step.

**Code Snippets plugin**
New snippet → paste everything *below* the opening `<?php` line → set to run on the frontend (or everywhere) → activate.

**Theme `functions.php`**
Paste everything below the opening `<?php` line at the end of your (child) theme's `functions.php`. You'll lose it if you change themes, so prefer one of the options above.

## How it works

```php
add_filter( 'gform_pre_validation', 'crftd_gf_prefix_website_scheme' );
```

For each field where `$field->type === 'website'`:

1. Read the submitted value with `rgpost( 'input_{id}' )` and trim it.
2. Skip it if it's empty or already has a scheme (`^[a-z][a-z0-9+.-]*://`, case-insensitive).
3. Strip any leading `//`, prepend `https://`, and write it back to `$_POST['input_{id}']`.

That's the whole thing. Gravity Forms takes it from there: `GF_Field_Website::validate()` runs `is_valid_url()` on the corrected value, and `GF_Field_Website::get_value_save_input()` runs it through `filter_var( FILTER_VALIDATE_URL )` on save (added in GF 3.0.0).

### Why `https://` and not `http://`?

Because it's 2026. Most sites redirect `http` to `https` anyway, and if a site truly only answers on `http` the user can type the scheme and the snippet leaves it alone.

### Why not just loosen validation with `gform_is_valid_url`?

You could return `true` from that filter for bare domains, but then you'd be storing `example.com` in the entry, and anything downstream (notification links, CRM pushes, merge tags in an `href`) now has a half-URL to deal with. Normalizing the value on the way in keeps the entry data clean.

## What changed from the original snippet

The original version of this repo carried two functions. Checked against the Gravity Forms 3.2.1 source:

**1. `my_novalidate()` was removed.** It added `novalidate` to the `<form>` tag via a regex on `method='post'`. Gravity Forms has shipped `novalidate` on the form tag natively since **2.5 (2021-04-27)**. The changelog entry: *"Added the 'novalidate' attribute to the form container to block default HTML5 validation."* On any current GF install the old function only produced a duplicate `novalidate` attribute. The repo name is a leftover from that era.

**2. `my_protocol()` was rewritten as `crftd_gf_prefix_website_scheme()`:**

- `$field['type']` and `$field['id']` (array access on `GF_Field`) → `$field->type` and `$field->id`. Array access has been deprecated since GF 2.0 and fires a `_deprecated_function` notice with `WP_DEBUG` on.
- `RGFormsModel::get_field_value()` → `rgpost()`. `RGFormsModel` still exists as a legacy alias of `GFFormsModel`, but `rgpost()` is what Gravity Forms' own hook docs use, and a Website field is a single input so there's nothing to resolve.
- Scheme detection changed from `(f|ht)tps?://` to any RFC 3986 scheme followed by `://`, so `example.com:8080` gets prefixed (it didn't before) and genuinely schemed values are left alone.
- Added `trim()`, protocol-relative (`//`) handling, an `instanceof GF_Field` guard, a non-string guard (a tampered `$_POST` array no longer hits `preg_match()`), and an `ABSPATH` guard.
- Unique function prefix (`crftd_`) so it can't collide with another `my_protocol()` on the site.

## Credits

Original approach by Phil on the (legacy) Gravity Forms support forum: https://legacy.forums.gravityhelp.com/topic/website-field

Updated, tested, and maintained by [CRFTD Web Development & Consulting](https://crftd.dev). More free Gravity Forms and WordPress tools at [crftdtools.dev](https://crftdtools.dev).

## License

GPL-2.0-or-later, same as WordPress and Gravity Forms.
