/**
 * WPEnhance AI — Editor Top-Toolbar Quick Translate
 *
 * Injects a translate icon button directly into the Gutenberg editor's top
 * toolbar — works in both the post/page block editor and the full site /
 * template editor (FSE), even when the WP admin bar is completely hidden.
 *
 * ── Why DOM injection instead of registerPlugin / SlotFill ───────────────────
 * The SlotFill API (PluginMoreMenuItem, PluginSidebar, etc.) has no reliable
 * slot for the top toolbar in the site editor across all WP 6.x versions.
 * PluginMoreMenuItem only targets the "⋮" overflow dropdown, and its scope
 * handling differs between WP versions.  DOM injection targets the actual
 * header element directly — the same technique used when SlotFill falls short.
 *
 * ── Strategy ─────────────────────────────────────────────────────────────────
 * 1. Build the translate popover once and append it to <body>.
 * 2. Use MutationObserver to detect when the editor header renders (React
 *    renders it asynchronously — it is not present on DOMContentLoaded).
 * 3. Insert a <button class="components-button has-icon"> into the header's
 *    right-side toolbar area so it looks native alongside Save / Settings.
 * 4. Clicking the button positions and toggles the popover (same pattern as
 *    the admin-bar toolbar-translate.js popover).
 *
 * CSS class selectors are tried in priority order and cover multiple WP
 * versions.  New versions can be appended to HEADER_SELECTORS without touching
 * the rest of the code.
 *
 * Globals (WPEnhanceAIEditor, injected via wp_localize_script):
 *   .restUrl   — https://…/wp-json/wpenhance-ai/v1
 *   .nonce     — wp_rest nonce
 *   .languages — { code: "Label", … }
 */

( function () {
    'use strict';

    if ( typeof WPEnhanceAIEditor === 'undefined' ) return;

    /* ── Header selectors (priority order, multiple WP version variants) ───── */
    // These target the RIGHT-HAND side of the editor header toolbar where
    // native buttons (Save, Settings, Preview…) already live.

    const HEADER_SELECTORS = [
        // Pinned items bar — present in BOTH post editor and site editor
        // across all WP 6.x versions.  This is where "Create Block Theme",
        // "Settings", and other plugin icon buttons live.
        '.interface-pinned-items',
        // Fallbacks for edge cases / future WP restructuring
        '.edit-site-header-edit-mode__end',
        '.edit-post-header__settings',
        '.editor-header__end',
        '.editor-header__settings',
    ];

    const BTN_CLASS   = 'wpenhance-ai-editor-btn';
    const POPOVER_ID  = 'wpenhance-ai-editor-popover';

    /* ── Language data ─────────────────────────────────────────────────────── */

    const LANG_ENTRIES = Object.entries( WPEnhanceAIEditor.languages || {} );
    const DEFAULT_LANG = LANG_ENTRIES.length ? LANG_ENTRIES[ 0 ][ 0 ] : 'en';

    /* ── Boot ──────────────────────────────────────────────────────────────── */

    function init() {

        const popover = buildPopover();
        document.body.appendChild( popover );
        wirePopoverEvents( popover );
        watchAndInject( popover );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        // DOMContentLoaded already fired — init immediately (footer script).
        init();
    }

    /* ── MutationObserver injection ────────────────────────────────────────── */

    function watchAndInject( popover ) {

        // Initial attempt (header may already exist if script loaded late).
        tryInject( popover );

        // Keep watching — the editor renders asynchronously and may re-mount
        // the header when navigating between list / canvas views in FSE.
        const observer = new MutationObserver( () => tryInject( popover ) );
        observer.observe( document.body, { childList: true, subtree: true } );

        // Disconnect after 60 s — editor is definitely stable by then.
        setTimeout( () => observer.disconnect(), 60000 );
    }

    function tryInject( popover ) {

        for ( const sel of HEADER_SELECTORS ) {

            const container = document.querySelector( sel );

            if ( container && !container.querySelector( '.' + BTN_CLASS ) ) {
                container.insertBefore( buildButton( popover ), container.firstChild );
            }
        }
    }

    /* ── Toolbar button ────────────────────────────────────────────────────── */

    function buildButton( popover ) {

        const btn       = document.createElement( 'button' );
        btn.type        = 'button';
        btn.className   = 'components-button is-compact has-icon ' + BTN_CLASS;
        btn.setAttribute( 'aria-label',    'Quick Translate' );
        btn.setAttribute( 'aria-expanded', 'false' );
        btn.title       = 'Quick Translate';
        btn.innerHTML   =
            '<span class="dashicons dashicons-translation" aria-hidden="true"></span>';

        btn.addEventListener( 'click', ( e ) => {
            e.stopPropagation();
            popover.hidden ? openPopover( popover, btn ) : closePopover( popover );
        } );

        return btn;
    }

    /* ── Popover DOM ───────────────────────────────────────────────────────── */

    function buildPopover() {

        const options = LANG_ENTRIES.map(
            ( [ code, label ] ) =>
                `<option value="${ escAttr( code ) }">${ escHtml( label ) }</option>`
        ).join( '' );

        const el    = document.createElement( 'div' );
        el.id       = POPOVER_ID;
        el.className = 'wpenhance-ai-ep';
        el.hidden   = true;
        el.setAttribute( 'role',       'dialog' );
        el.setAttribute( 'aria-label', 'Quick Translate' );

        el.innerHTML = `
            <div class="wpenhance-ai-ep__header">
                <span class="wpenhance-ai-ep__title">
                    <span class="dashicons dashicons-translation" aria-hidden="true"></span>
                    Quick Translate
                </span>
                <button type="button" class="wpenhance-ai-ep__close" aria-label="Close">✕</button>
            </div>

            <div class="wpenhance-ai-ep__body">

                <label class="wpenhance-ai-ep__label" for="wpai-ep-lang">
                    Target Language
                </label>
                <select id="wpai-ep-lang" class="wpenhance-ai-ep__lang">${ options }</select>

                <label class="wpenhance-ai-ep__label" for="wpai-ep-input">
                    Text to translate
                </label>
                <textarea
                    id="wpai-ep-input"
                    class="wpenhance-ai-ep__textarea"
                    rows="5"
                    placeholder="Paste text, or select text in the editor first…"
                ></textarea>

                <button type="button" class="components-button is-primary wpenhance-ai-ep__translate">
                    Translate
                </button>

            </div>

            <div class="wpenhance-ai-ep__result" hidden>
                <div class="wpenhance-ai-ep__result-meta"></div>
                <textarea
                    class="wpenhance-ai-ep__textarea wpenhance-ai-ep__textarea--output"
                    rows="5"
                    readonly
                ></textarea>
                <button type="button" class="components-button is-secondary wpenhance-ai-ep__copy">
                    Copy
                </button>
            </div>`;

        return el;
    }

    /* ── Popover events ────────────────────────────────────────────────────── */

    function wirePopoverEvents( popover ) {

        // Close button
        popover.querySelector( '.wpenhance-ai-ep__close' )
            .addEventListener( 'click', () => closePopover( popover ) );

        // Translate button
        popover.querySelector( '.wpenhance-ai-ep__translate' )
            .addEventListener( 'click', () => runTranslation( popover ) );

        // Copy button
        popover.querySelector( '.wpenhance-ai-ep__copy' )
            .addEventListener( 'click', async ( e ) => {
                const output = popover.querySelector( '.wpenhance-ai-ep__textarea--output' );
                if ( !output ) return;
                try {
                    await navigator.clipboard.writeText( output.value );
                } catch ( _ ) {
                    output.select();
                    document.execCommand( 'copy' );
                }
                const btn = e.currentTarget;
                btn.textContent = 'Copied ✓';
                setTimeout( () => { btn.textContent = 'Copy'; }, 2000 );
            } );

        // Close on outside click
        document.addEventListener( 'click', ( e ) => {
            if ( popover.hidden ) return;
            const isInsidePopover = popover.contains( e.target );
            const isToolbarBtn    = !!e.target.closest( '.' + BTN_CLASS );
            if ( !isInsidePopover && !isToolbarBtn ) closePopover( popover );
        } );

        // Close on Escape
        document.addEventListener( 'keydown', ( e ) => {
            if ( e.key === 'Escape' && !popover.hidden ) closePopover( popover );
        } );
    }

    /* ── Open / close helpers ──────────────────────────────────────────────── */

    function openPopover( popover, anchorBtn ) {

        // Pre-fill with any selected text.
        const selection = window.getSelection ? window.getSelection().toString().trim() : '';
        const textarea  = popover.querySelector( '.wpenhance-ai-ep__textarea:not(.wpenhance-ai-ep__textarea--output)' );
        if ( selection && textarea && !textarea.value ) {
            textarea.value = selection;
        }

        positionPopover( popover, anchorBtn );
        popover.hidden = false;

        document.querySelectorAll( '.' + BTN_CLASS ).forEach(
            ( b ) => b.setAttribute( 'aria-expanded', 'true' )
        );
    }

    function closePopover( popover ) {

        popover.hidden = true;
        document.querySelectorAll( '.' + BTN_CLASS ).forEach(
            ( b ) => b.setAttribute( 'aria-expanded', 'false' )
        );
    }

    function positionPopover( popover, anchorBtn ) {

        const rect    = anchorBtn.getBoundingClientRect();
        const popW    = 360;
        const margin  = 8;
        const vpWidth = window.innerWidth;

        // Align right edge of popover with right edge of button; clamp to viewport.
        let left = rect.right - popW;
        if ( left < margin )                  left = margin;
        if ( left + popW + margin > vpWidth ) left = vpWidth - popW - margin;

        popover.style.top  = ( rect.bottom + 4 ) + 'px';
        popover.style.left = left + 'px';
    }

    /* ── Translation fetch ─────────────────────────────────────────────────── */

    async function runTranslation( popover ) {

        const langEl       = popover.querySelector( '.wpenhance-ai-ep__lang' );
        const inputEl      = popover.querySelector( '#wpai-ep-input' );
        const resultEl     = popover.querySelector( '.wpenhance-ai-ep__result' );
        const metaEl       = popover.querySelector( '.wpenhance-ai-ep__result-meta' );
        const outputEl     = popover.querySelector( '.wpenhance-ai-ep__textarea--output' );
        const translateBtn = popover.querySelector( '.wpenhance-ai-ep__translate' );

        const text = inputEl.value.trim();
        if ( !text ) { inputEl.focus(); return; }

        resultEl.hidden        = false;
        metaEl.innerHTML       = '<em style="color:#646970">Translating…</em>';
        outputEl.value         = '';
        translateBtn.disabled  = true;
        translateBtn.textContent = 'Translating…';

        try {

            const res  = await fetch(
                `${ WPEnhanceAIEditor.restUrl }/translate-chunk`,
                {
                    method:  'POST',
                    headers: {
                        'X-WP-Nonce':   WPEnhanceAIEditor.nonce,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify( {
                        target_language: langEl.value,
                        chunk_text:      text,
                    } ),
                }
            );

            const data = await res.json();

            if ( data.success && data.output ) {
                metaEl.innerHTML = `Translated to: <strong>${ escHtml( data.language || '' ) }</strong>`;
                outputEl.value   = data.output;
            } else {
                metaEl.innerHTML =
                    `<span style="color:#d63638">${ escHtml( data.error || 'Translation failed.' ) }</span>`;
            }

        } catch ( _ ) {

            metaEl.innerHTML = '<span style="color:#d63638">Request failed. Check your connection.</span>';
        }

        translateBtn.disabled    = false;
        translateBtn.textContent = 'Translate';
    }

    /* ── Utilities ─────────────────────────────────────────────────────────── */

    function escHtml( v ) {
        return String( v )
            .replace( /&/g,  '&amp;' )
            .replace( /</g,  '&lt;'  )
            .replace( />/g,  '&gt;'  )
            .replace( /"/g,  '&quot;')
            .replace( /'/g,  '&#39;' );
    }

    function escAttr( v ) {
        return String( v )
            .replace( /&/g,  '&amp;' )
            .replace( /"/g,  '&quot;')
            .replace( /'/g,  '&#39;' )
            .replace( /</g,  '&lt;'  )
            .replace( />/g,  '&gt;'  );
    }

} )();
