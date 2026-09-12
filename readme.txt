=== OfferWeave ===
Contributors: Convati
Tags: quote calculator, request a quote, price calculator, service catalog, quote form
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.35.8
License: GPL-3.0-only
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create service cards, calculate prices and collect quote requests with custom forms, email notifications and a request inbox.

== Description ==

Developed and published by Convati UG (haftungsbeschränkt).

= Service cards, live prices and quote requests =

OfferWeave helps visitors choose your services, see a price breakdown and send a quote request from your WordPress website.

Create a catalogue with prices, descriptions and images. Visitors select services, adjust quantities and send their details through your enquiry form. Review the saved request and calculation in WordPress.

Use it for creative, design, event or other services that need a quote before work begins. OfferWeave does not collect payments, issue invoices or reserve appointments.

= What you can do with OfferWeave Free =

* **Create service cards.** Add a name, description, image, category, feature list, custom short-info icons and fixed price to each offer.
* **Calculate prices from quantities.** Show one-time or monthly amounts, including the configured term total for monthly offers. Customise the quantity label for your service.
* **Build a shared selection.** Let visitors collect offers and request them together, across different pages.
* **Place views with shortcodes.** Embed the complete builder, a catalogue, a single offer, a selection button or a request form.
* **Show a tax breakdown.** Set default and per-offer VAT rates and choose net or gross display. Tax rates are entered manually. Choose from all supported global currencies, without exchange-rate conversion.
* **Customise your form.** Add text, email, telephone, textarea and select fields. Edit labels and required fields.
* **Keep requests in WordPress.** Open OfferWeave > Requests to inspect saved items and totals, change the status, export records and resend emails.
* **Send standard emails.** Use manual sending or automatic request notifications and customer emails through WordPress mail.
* **Match your basic appearance.** Set the accent colour, card background and corner radius.
* **Work in German and English.** Use WordPress language packs for the interface and the content translation tools for your own texts. Missing interface translations display English source text. Optional Polylang integration is supported.
* **Move your configuration.** Import and export the plugin configuration as JSON, then review imported settings in the editor before saving. Import and editing follow the features available in your edition.
* **Find help in the editor.** Open the bundled German/English handbook and contextual help from the plugin settings.

Free supports 150 offers and 30 items per request.

= From your first offer to a quote request =

1. Open **OfferWeave** in the WordPress dashboard and create your first offers.
2. Adjust the form fields, email settings and tax display for your business.
3. Add `[offerweave]` to a page using a Shortcode block for the complete quote builder.
4. Publish the page and test a request, including the email delivery, before sharing it with customers.

For separate pages, use `[offerweave_catalog]` for your service catalogue and `[offerweave_request]` for the request page. Set the request page as the selection destination in the plugin settings. Add `[offerweave_selection]` where you want a selection button.

= Optional: OfferWeave Pro =

The separate paid Pro edition adds more detailed pricing and presentation tools:

* Tiered pricing, group calculations and bundles with independent prices.
* Configurable option groups and price adjustments, including shared delivery format.
* Freely positioned quantity/variant inputs with connected shortcodes (`external` or `external-shared`).
* Reusable custom surcharges and additional costs with per-offer conditions.
* Scheduled promotions, promotional prices and separately placed promotion banners.
* Advanced card, image, input-area and banner design with a live preview, including individual styles.
* Custom email templates, email design and an SMTP transport for OfferWeave messages.

These are Pro features and are not included in the free download. Free works on its own and does not require a paid licence or a Freemius account. Details about the paid edition are available on the [OfferWeave website](https://offerweave.de/en/).

= Documentation and support =

Open **OfferWeave > Documentation** for the bundled handbook, or use the contextual help link in the editor. The handbook includes search, screenshots and printable instructions in German and English.

Use the WordPress.org Support tab for Free support. Include software versions, steps to reproduce the issue and relevant errors. Remove customer data and credentials from attachments.

= Data and external services =

**Your WordPress installation:** Configuration and requests are stored in your WordPress database. Requests include contact details, selected services and form data. You control the fields, retention and deletion settings. Emails use your site's configured mail transport.

**Freemius:** This plugin includes the Freemius SDK for an optional account connection and the separate paid edition's purchase, licence and account services. Depending on the connection and consent, Freemius can process account/contact details, website and installation information, licence and transaction information, and optional environment/usage information described by its opt-in. You may skip the optional opt-in and continue using Free. [Service](https://freemius.com/) | [Privacy](https://freemius.com/privacy/) | [Terms](https://freemius.com/terms/).

**Optional CAPTCHA:** CAPTCHA is disabled by default. If you enable hCaptcha or Cloudflare Turnstile and provide your own keys, the form loads that provider's browser script and the server verifies the token with the provider. Browser and connection information needed for bot detection may be processed by that service. Add the appropriate information to your site's privacy notice before enabling it.

* hCaptcha: [Documentation](https://docs.hcaptcha.com/) | [Privacy](https://www.hcaptcha.com/privacy) | [Terms](https://www.hcaptcha.com/terms).
* Cloudflare Turnstile: [Documentation](https://developers.cloudflare.com/turnstile/) | [Privacy](https://www.cloudflare.com/privacypolicy/) | [Terms](https://www.cloudflare.com/website-terms/).

**Images:** Media Library images are served by your site. External image URLs connect visitors to that image host; use local images to avoid this.

= Source code and build tools =

OfferWeave Free's editable PHP, JavaScript, CSS and complete packaging tools are publicly maintained at https://github.com/Convati-UG-haftungsbeschrankt/offerweave . Clone that repository and run `python3 tools/package.py` to build the Free installation ZIP. The public README documents development, verification and changing Free source files. No private repository, Pro generator or Node.js runtime is needed to build or run Free. Release source tags and checksummed installation ZIPs are available from the repository's Releases page.

The public Free and Pro packages include the unmodified official Freemius WordPress SDK 2.13.4:

* SDK source and release: https://github.com/Freemius/wordpress-sdk/tree/2.13.4
* SDK stylesheets and their source: https://github.com/Freemius/wordpress-sdk/tree/2.13.4/assets/scss
* SDK checkout utilities `jquery.form.js`, `postmessage.js` and `nojquery.ba-postmessage.js`: https://github.com/Freemius/wordpress-sdk/tree/2.13.4/assets/scripts . Check out SDK tag 2.13.4, run `npm ci` and `npm run build` (Gulp, Sass and Terser). This produces the SDK CSS and the three JS utility files. The similarly named jQuery Form AJAX plugin is a different project.
* Pricing UI source (including React components, styles and webpack configuration): https://github.com/Freemius/pricing-page/tree/1.4.1 . Check out this tag, install its package.json dependencies with Node.js/npm (`npm install --legacy-peer-deps`), then run `npm run build`. The result is `dist/freemius-pricing.js`. The upstream tag has no dependency lockfile, so newly resolved dependency versions can affect exact output bytes.

Upstream licence notices remain in `vendor/freemius/LICENSE.txt` and the adjacent asset licence files. See `THIRD-PARTY-NOTICES.txt` for versions and provenance. OfferWeave applies its verified HTTPS policy through WordPress hooks without changing the SDK files.

== Installation ==

1. Install OfferWeave from the WordPress plugin directory, or upload the Free ZIP through **Plugins > Add New > Upload Plugin**.
2. Activate the plugin. You can skip the optional Freemius opt-in and use Free without an account.
3. Open **OfferWeave**, create your offers and configure your form, tax display and email settings.
4. Add `[offerweave]` to a Shortcode block on a page and publish it.
5. Send a test request to confirm the calculation, saved enquiry and email delivery.

Requires WordPress 6.5 or later and PHP 8.1 or later. The WordPress REST API must be reachable. Email delivery needs a working WordPress mail setup. Use HTTPS on your live site. No Node.js or Composer is needed.

== Frequently Asked Questions ==

= Does the calculator require JavaScript? =

Offer cards, images, prices and detail links are rendered by PHP into the original page HTML. JavaScript updates the PHP output without a full page navigation. Without JavaScript, use "Update price" after changing inputs; selection and request submission use standard forms. An optionally enabled external CAPTCHA still requires JavaScript and is never bypassed.

= How is the temporary selection stored? =

A necessary first-party HttpOnly, SameSite cookie (offerweave_selection_ followed by the site ID) holds a random identifier for two hours. The bounded selection is stored in WordPress transients. Contact fields are not stored in this cookie or in page URLs. Submitted requests use the configured retention policy. Exclude OfferWeave shortcode pages and responses for this cookie from shared page/CDN caches; the plugin also sends private no-store response headers.


= Does Free require an account or licence key? =

No. Free works without a paid licence and you can skip the optional Freemius opt-in.

= Can I use OfferWeave without WooCommerce? =

Yes. OfferWeave is a standalone quote-request plugin and does not require WooCommerce or a separate form builder.

= Does it take payments or create bookings? =

No. It collects enquiries and calculates prices. A monthly offer displays its monthly price and term; it does not charge the customer.

= Are all functions shown in the screenshots included in Free? =

Yes. They show the actual Free edition with fictional English services and requests. Example data is not included in a fresh installation. Paid features are listed separately above.

= Can a visitor combine offers from different pages? =

Yes. Offers can share a selection. Add the selection shortcode where needed and configure the destination page containing the request form.

= Can I customise the enquiry fields and emails? =

Free includes editable form fields and standard emails, with manual or automatic sending. Custom email templates, the visual email designer and the integrated OfferWeave SMTP transport are Pro features. Free can use your existing WordPress SMTP setup.

= Does submitting a request create a contract? =

Submitting the enquiry is not a checkout or booking. You can configure outgoing documents as non-binding price summaries or binding offers and edit their notices and validity period. Check the wording and your subsequent acceptance process for your business.

= What does a paid Pro licence include? =

A paid Pro licence includes official updates, downloads and support for the purchased term. Manage your licence and subscription through your Freemius account.

== Screenshots ==

1. Free service catalogue with three offer cards using the same fixed-price-per-project model and 19% VAT. All examples are fictional.
2. The Free offer editor: configure the name, fixed-price model, category and per-offer VAT settings.
3. A visitor's shared selection and quote-request form, with the price breakdown calculated by the plugin.
4. The dedicated OfferWeave > Requests screen with saved requests, statuses and totals. Names and requests are fictional.
5. Custom enquiry fields: set labels, field types, placeholders and required details in Free.
6. Basic Free appearance controls for the accent colour, card background and corner radius.
7. Standard email settings in Free, including sender details and manual or automatic sending.
8. Language tools for the offer and form content, shown in the English interface.
9. JSON import and export for moving or backing up your configuration, with imported settings reviewed in the editor before saving.
10. The bundled English handbook with chapter navigation, search and contextual documentation.
11. Settings grouped into six areas, including separate customer-document and provider settings.

== Changelog ==

= 2.35.8 =
* Removed the redundant feature-summary panel below the Pro pricing cards.

= 2.35.7 =
* Added an OfferWeave Pro plan comparison with German VAT-inclusive annual prices and direct checkout links.
* Preserved the original Freemius checkout and account flows without SDK changes.

= 2.35.6 =
* Selection confirmations are shown once and dismiss automatically; warnings and request references remain readable until dismissed.
* Clarify the separate Pro plugin requirement for retained offers.

= 2.35.5 =
* Cache request lists with immediate invalidation after changes and explicit database error handling.
* Keep ordinary Free editing available when retained settings from another edition reference an offer or form field.
* Remove unreachable Free editor branches and correct offer counters, HTML escaping and WordPress translations.
* Make missing Pro offer assignments visible and repairable; reject packages with missing content.
* Preserve exact large percentage and tax calculations, and isolate frontend updates from other plugins' cards.

= 2.35.4 =
* Remove obsolete short shortcode aliases and the former REST namespace; use the documented offerweave shortcodes.
* Remove exclusively Pro presentation styles and misleading Pro guidance from Free. Free no longer registers placeholder input/banner shortcodes.
* Scope administration dialog styles to OfferWeave. Stored offers, settings and requests remain preserved during upgrades.

= 2.35.3 =
* Publish the complete editable Free source and reproducible packaging tools in the Convati GitHub organisation.
* Replace the private development link with the public Free repository; runtime behaviour is unchanged.

= 2.35.2 =
* Use standard WordPress language loading and locale switching across all editions; Free uses WordPress.org language packs.
* Preserve existing requests during prefixed-table migration with the official SQLite integration, including interrupted upgrades.
* Correct Free editor help and refresh the WordPress.org listing materials.


= 2.35.1 =
* Fixed creating offers in an empty Free catalog and saving all supported translations.
* Removed remaining unsupported Free editor fields and unused Pro language catalogs.
* Prefixed public form parameters, preserving previously open signed forms.
* Independently rechecked edition boundaries and minimum WordPress/PHP compatibility.

= 2.35.0 =
* Separate Free runtime and editor from the advanced Pro implementations.
* All supported currencies are available in Free, Pro and Owner.
* Use unique OfferWeave prefixes with migration of settings, requests and schedules.
* Switch editions explicitly through Plugins; preserve shared data.
* Honor WordPress language packs and regional translations with DE/EN offline fallbacks.


= 2.34.0 =
* Render complete offer cards, images, prices and detail links in the original PHP HTML.
* Support quantity updates, selection editing and request submission through native forms without JavaScript.
* Progressively enhance the same PHP output, preserving focus and form drafts and restoring native forms after connection errors.
* Use the shared PHP renderer for the isolated design preview, including selected card states and category layouts.
* Preserve independent, mixed and externally placed controls, package components, variants, surcharges and existing pricing rules.
* Add bounded first-party selection sessions, signed actions and repeat-submission protection; retain optional external CAPTCHA requirements.
* Update the German and English handbook with the native Update price flow and cache guidance.

= 2.33.1 =
* Refined surcharge assignment layout with aligned controls, compact remove actions, readable empty states and responsive variant fields. Pricing remains unchanged.

= 2.33.0 =
* Pro: centrally maintain named surcharges, assign them to offers and apply optional delivery or variant conditions. Additional quantity inputs, currencies, VAT, previews and saved request details use the same calculation. Existing travel rules remain compatible.

= 2.32.0 =
* Choose EUR or USD globally in Free, or additional currencies in Pro, with matching symbols and decimal places throughout prices, editor previews, requests and emails. Existing requests retain their original currency; no exchange-rate conversion.


= 2.31.1 =
* Smaller download: the complete offline handbook now uses lossless WebP screenshots with identical pixels and dimensions.

= 2.31.0 =
* Pro: Choose a responsive two-column detail selection block, with the price on the left and explanations and inputs on the right. The action button stays below both columns.

= 2.30.0 =
* Choose whether monthly offer cards show the full-term total. Customize the term text and total suffix per offer; request and email calculations remain unchanged.

= 2.29.1 =
* Distribute key facts evenly across the available card width while keeping each icon and label together. Preserve card padding and wrap safely on narrow cards.

= 2.29.0 =
* Set the outer section background or transparency separately for categories and individual offers in the Pro designer. Category catalogs and individual shortcodes use their matching settings, with the same result in the preview.

= 2.28.0 =
* Pro: Set desktop, tablet and phone columns per offer category, with category values overriding the global layout.
* Category previews use the same grid width as the website; removing an override restores global inheritance.

= 2.27.0 =
* Give offerweave_add its own global design, independent of catalog and individual offer styles.
* Choose selection block contents with checkboxes; offer titles are hidden by default.

= 2.26.5 =
* Add a Free and Pro setting to show enquiry and provider information below catalogs and selection blocks. Hidden by default; request form and email notices remain unchanged.

= 2.26.4 =
* Preview and style the actual [offerweave_add] detail selection block in the design editor.

= 2.26.3 =
* Show only the configured option name in variant selectors; retain surcharge calculation and price details on the card.

= 2.26.2 =
* Keep the VAT label immediately below the price and its unit in every card layout, including promotions and variants.

= 2.26.1 =
* Place card quantity, delivery and variant inputs below the price in a compact, responsive layout without an enclosing box.

= 2.26.0 =
* Choose shared catalog inputs or independent quantity and delivery controls on every offer card, including catalogs with one price model.

= 2.25.7 =

* Support WordPress-approved SVG media as offer images and key-fact icons, including files without raster previews.

= 2.25.6 =
* Simplify licence status messages and customer documentation.

= 2.25.5 =
* Use the official Freemius uninstall hook for public Pro deployments while preserving shared data and the explicit deletion preference.

= 2.25.4 =
* Handle invalid stored configuration safely in admin REST reads, legacy shortcode loading, migrations and retention without overwriting data.
* Document individually verified exception-output and CAPTCHA-check false positives; preserve plain-text error messages and existing functionality.

= 2.25.3 =
* Require verified HTTPS for Freemius API requests, legacy HTTP retries and redirects without changing the official SDK.
* Prepare request-table identifiers and preserve search, mail locks, retention and all existing offer functions.
* Improve activation-message escaping, IP validation, translator comments and third-party source/build documentation.

= 2.25.2 =
* Remove the outdated input-placement migration notice below the Free & Pro feature comparison.

= 2.25.1 =
* Show the enquiry and business-audience notice once before the submit button; remove its duplicate below the selection totals.

= 2.25.0 =
* Promotions start as drafts, with explicit start, pause, resume, stop and restart controls.
* Lifecycle commands save immediately; expired dates and overlapping promotions are checked before activation.
* Existing actions and historical request prices are preserved; deletion is available in every state.

= 2.24.1 =
* Correct the plugin author and publisher to Convati UG (haftungsbeschränkt) in all editions and language metadata.


= 2.24.0 =
* Explicit Free/Pro feature matrix. Separate input placement is now Pro; Free keeps normal catalogue inputs when using existing connected shortcodes. Promotion banners and full design remain Pro. Stored data is preserved.


= 2.23.0 =
* New external-shared input mode keeps individual quantities and options on offer cards while placing only shared controls separately.
* Empty shared input blocks disappear for mixed models without delivery controls. Existing external mode retains full relocation.
* Updated bilingual shortcode guidance, screenshots and regression checks.

= 2.22.2 =
* Mixed catalogs keep quantities inside each card and share delivery format above all cards or in connected external controls.
* Delivery variants retain each offer’s own options and surcharges; quantities, package rules and existing selections stay independent.
* Updated German/English guidance and screenshots.

= 2.22.1 =
* Replace OfferWeave browser alerts and confirmations with accessible branded dialogs.
* Preserve package assignments, cancelled actions and unsaved-page protection.

= 2.22.0 =
* Open requests directly from a dedicated WordPress submenu under OfferWeave.
* Keep the requests workspace separate from configuration tabs, saving controls and editor assets.
* Updated bilingual navigation instructions and screenshots.

= 2.21.0 =
* Organise settings into six clear sections with preserved drafts and automatic navigation to invalid fields.
* Separate customer document settings from provider details; update bilingual guides and screenshots.

= 2.20.1 =
* Keep offer search and filters above an independently scrollable result list.
* Preserve result scroll position when switching offers; keep narrow and short layouts usable.

= 2.20.0 =
* Place quantity, delivery and variant controls separately using connected shortcodes.
* Keep independent offer inputs in mixed catalogues and fall back safely when a partner is missing.
* Updated bilingual embedding guide and screenshots.

= 2.19.0 =
* Mixed catalogs now place quantity and applicable delivery/variant controls on every card, with independent defaults and validation.
* Unified designer preview, configurable input styling and updated bilingual guidance.

= 2.18.0 =
* Find a design area through live search, grouped by general settings, promotion banners and offer categories, with keyboard navigation and clear result feedback.

= 2.17.0 =
* Design the shared quantity and variant area separately, with a focused live preview and configurable colors, spacing, borders and field typography. Existing installations keep their previous appearance until customization is enabled.

= 2.16.5 =
* Keep designer settings and the single-card live preview side by side whenever the available workspace has enough room.


= 2.16.4 =
* Restore a single edited card in the designer with catalog or full width.
* Add optional selected-card colors independent of highlighted cards, with matching live and preview states.


= 2.16.3 =
* Fix: selecting an offer preserves the configured card design instead of applying highlight colors.
* Fix: catalog previews render actual neighboring cards and calculate current draft prices through the shared pricing engine.
* Fix: initialize selection before price responses; preserve explicit highlights and imported design values.

= 2.16.2 =
* Align key-fact text fields, icon previews, media buttons and row actions.
* Move icon removal to a compact, labelled cross above the preview and preserve keyboard focus.
* Update German and English handbook screenshots and instructions.

= 2.16.1 =
* Key facts now use your own uploaded icons or WordPress media library images in every edition.
* Replace or remove icons per row; preserve assignments when moving, duplicating, exporting and importing.
* Previous built-in key-fact symbols become empty assignments; text and order stay intact.
* Updated German and English handbook with current media-icon screenshots.

= 2.16.0 =
* Choose an icon or no icon for each key fact in every card layout. Edit, reorder and remove facts while keeping their translations together. Existing fact icons remain editable and survive layout changes.

= 2.15.2 =
* Avoid duplicate automatic quantity explanations on fixed-price cards, detail blocks and selections; retain custom text and group conversions.

= 2.15.1 =
* Show mixed pricing-model guidance only in the administrator offer editor; remove the public notice box.

= 2.15.0 =
* Keep imported configuration authoritative and protect it from private preset migrations.
* Preview catalog widths, hover and selection states; refresh layout and icon markup immediately.
* Customize the background of selected and highlighted comparison cards.

= 2.14.2 =
* Keep designer sections open while customizing, resetting and saving; update overrides without rebuilding the editor.
* Preserve each design scope’s open sections during the editor session. Updated German and English handbook.

= 2.14.1 =
* Added promotion banner shortcodes and setup guidance to the dashboard embedding overview in German and English.
* Updated the illustrated handbook.

= 2.14.0 =
* Added non-blocking notices for mixed calculation models in categories and catalogs.
* Moved separate fixed-price quantities before the card price and clarified which offers the shared input controls.
* Added configurable quantity, delivery and help labels in all editions, with translations and automatic defaults.
* Updated the German/English handbook with six actual interface screenshots.

= 2.13.1 =
* Added a dashboard Buy Pro button linking to the single-site EUR Freemius checkout, with translated and accessible labels.
* Kept the purchase action separate from saving and updated the bilingual handbook.

= 2.13.0 =
* Reorganised the offer editor into four searchable, task-based areas with a draft price preview.
* Separated package prices, quantities and options from individually booked offers. Existing links migrate once without changing saved requests.
* Added custom input and billing unit labels, and updated the German/English handbook and screenshots.

= 2.12.0 =
* Added a bundled German/English handbook with 23 chapters, real screenshots, search and printing.
* Added a Documentation submenu and contextual help in the editor.
* Prepared the English WordPress.org listing and screenshots of the Free edition.
