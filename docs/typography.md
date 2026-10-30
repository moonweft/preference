# Admin typography

Theme settings expose font scales of 100%, 110%, 125% and 150%. New and upgraded
installations default to 125%. The percentage is relative to the selected theme's
native type scale: Sharp uses 12px small text at 100%, while Orbit uses 14px.

The setting is platform-wide and applies to every administrator and the public
login page. It is intentionally shared across organisations, like the theme and
colour scheme. Writes require the existing IAM system-settings permission and
use SystemSettings validation, stale-edit detection and audit logging.

Each option previews heading, ordinary and auxiliary text for the selected theme.
Restore 125% changes the form only; Save persists it and reloads the page. Other
open pages apply it on refresh. Form inputs, labels, navigation, actions, tables,
notifications, dialogs and pagination consume the common type scale. Fixed sizes
in Sharp badges and Orbit theme components are also scaled. Table padding is
independent; line boxes may grow to fit larger text. This is not browser zoom.

The Preference panel hook resolves scoped settings while rendering each request
and emits a validated numeric --moonweft-font-scale CSS variable. Sharp consumes
it in its own tokens; Preference supplies the default Filament scale and Orbit
fixed-size adaptations. No user CSS is accepted, and changing this setting does
not require rebuilding assets or restarting workers once this feature is deployed.

Deployment requires the add_font_scale settings migration and a frontend build.
Reload existing workers for the initial code deployment. Regression coverage is
in SystemTypographyTest and SystemAppearanceOctaneTest, including both themes,
all four values, permission revocation, invalid input, public login, two authorised
actors in one worker, missing authority and exception recovery.
