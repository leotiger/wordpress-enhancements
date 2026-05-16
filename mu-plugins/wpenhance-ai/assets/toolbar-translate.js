/**
 * WPEnhance AI — Admin Toolbar Quick Translate popover
 *
 * Completely independent from admin.js / the editor meta-box feature.
 * Works on every page where the WordPress Admin Bar is shown (admin + front-end).
 *
 * Globals injected by wp_localize_script:
 *   WPEnhanceAIToolbar.restUrl   — base REST URL, e.g. https://…/wp-json/wpenhance-ai/v1
 *   WPEnhanceAIToolbar.nonce     — wp_rest nonce
 *   WPEnhanceAIToolbar.languages — { code: label, … }
 */

/* ─────────────────────────────────────────────────────────────────────────────
   Bootstrap on DOM ready
   ───────────────────────────────────────────────────────────────────────────── */

document.addEventListener('DOMContentLoaded', () => {

    const toolbarItem = document.getElementById('wp-admin-bar-wpenhance-ai-translate');

    if (!toolbarItem || typeof WPEnhanceAIToolbar === 'undefined') {
        return;
    }

    const popover = buildPopover();
    document.body.appendChild(popover);

    const toolbarLink = toolbarItem.querySelector('a');

    // ── Open / close ──────────────────────────────────────────────────────────

    toolbarLink.addEventListener('click', (e) => {

        e.preventDefault();
        e.stopPropagation();

        if (popover.hidden) {
            openPopover(popover, toolbarItem);
        } else {
            closePopover(popover);
        }
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!popover.hidden && !popover.contains(e.target) && !toolbarItem.contains(e.target)) {
            closePopover(popover);
        }
    });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !popover.hidden) {
            closePopover(popover);
            toolbarLink.focus();
        }
    });

    // ── Translate button ──────────────────────────────────────────────────────

    popover.querySelector('.wpenhance-ai-tp__btn-translate').addEventListener('click', async () => {
        await runTranslation(popover);
    });

    // ── Copy button (delegated — result area rebuilt on each translation) ─────

    popover.addEventListener('click', async (e) => {

        const btn = e.target.closest('.wpenhance-ai-tp__btn-copy');
        if (!btn) return;

        const output = popover.querySelector('.wpenhance-ai-tp__output');
        if (!output) return;

        try {
            await navigator.clipboard.writeText(output.value);
        } catch (_) {
            output.select();
            document.execCommand('copy');
        }

        btn.textContent = 'Copied ✓';
        setTimeout(() => { btn.textContent = 'Copy'; }, 2000);
    });

    // ── Clear input button ────────────────────────────────────────────────────

    popover.querySelector('.wpenhance-ai-tp__btn-clear-input').addEventListener('click', () => {
        const inputArea = popover.querySelector('.wpenhance-ai-tp__input');
        inputArea.value = '';
        inputArea.focus();
    });

    // ── Clear all button (input + output) ──────────────────────────────────────

    popover.querySelector('.wpenhance-ai-tp__btn-clear-all').addEventListener('click', () => {
        const inputArea = popover.querySelector('.wpenhance-ai-tp__input');
        const outputArea = popover.querySelector('.wpenhance-ai-tp__output');
        const resultPanel = popover.querySelector('.wpenhance-ai-tp__result');
        inputArea.value = '';
        outputArea.value = '';
        resultPanel.hidden = true;
        inputArea.focus();
    });

    // ── Close button ──────────────────────────────────────────────────────────

    popover.querySelector('.wpenhance-ai-tp__close').addEventListener('click', () => {
        closePopover(popover);
        toolbarLink.focus();
    });
});

/* ─────────────────────────────────────────────────────────────────────────────
   Build popover DOM
   ───────────────────────────────────────────────────────────────────────────── */

function buildPopover() {

    const languages = WPEnhanceAIToolbar.languages || {};

    // Build <option> list
    const options = Object.entries(languages)
        .map(([code, label]) =>
            `<option value="${escAttr(code)}">${escHtml(label)}</option>`
        )
        .join('');

    const el = document.createElement('div');
    el.id = 'wpenhance-ai-tp';
    el.className = 'wpenhance-ai-tp';
    el.hidden = true;
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-label', 'Quick Translate');

    el.innerHTML = `
        <div class="wpenhance-ai-tp__header">
            <span class="wpenhance-ai-tp__title">
                <span class="dashicons dashicons-translation" aria-hidden="true"></span>
                Quick Translate
            </span>
            <button
                type="button"
                class="wpenhance-ai-tp__close"
                aria-label="Close translate popover"
            >✕</button>
        </div>

        <div class="wpenhance-ai-tp__body">

            <label class="wpenhance-ai-tp__label" for="wpenhance-ai-tp-lang">
                Target Language
            </label>
            <select
                id="wpenhance-ai-tp-lang"
                class="wpenhance-ai-tp__lang"
            >${options}</select>
            <span class="wpenhance-ai-tp__lang-hint" hidden></span>

            <label class="wpenhance-ai-tp__label" for="wpenhance-ai-tp-input">
                Text to translate
            </label>
            <textarea
                id="wpenhance-ai-tp-input"
                class="wpenhance-ai-tp__input"
                rows="5"
                placeholder="Paste text, or select text on the page first…"
            ></textarea>

            <div class="wpenhance-ai-tp__input-actions">
                <button
                    type="button"
                    class="button button-primary wpenhance-ai-tp__btn-translate"
                >Translate</button>
                <button
                    type="button"
                    class="button wpenhance-ai-tp__btn-clear-input"
                    aria-label="Clear input"
                >Clear</button>
            </div>

        </div>

        <div class="wpenhance-ai-tp__result" hidden>
            <div class="wpenhance-ai-tp__result-meta"></div>
            <textarea
                class="wpenhance-ai-tp__output"
                rows="5"
                readonly
                placeholder="Translation will appear here…"
            ></textarea>
            <div class="wpenhance-ai-tp__result-actions">
                <button type="button" class="button button-secondary wpenhance-ai-tp__btn-copy">Copy</button>
                <button type="button" class="button wpenhance-ai-tp__btn-clear-all">Clear All</button>
            </div>
        </div>`;

    return el;
}

/* ─────────────────────────────────────────────────────────────────────────────
   Open / close helpers
   ───────────────────────────────────────────────────────────────────────────── */

/**
 * localStorage key used to persist the user's last chosen language across
 * page loads.  Scoped to this plugin to avoid collisions with other scripts.
 */
const LANG_STORAGE_KEY = 'wpenhance_ai_last_lang';

/**
 * Initialise the language <select> on the very first popover open.
 *
 * Priority order:
 *   1. Post language detected by PHP (WPEnhanceAIToolbar.postLanguage) —
 *      most specific: matches the post/page currently being edited or viewed.
 *   2. Last language persisted in localStorage — used when there is no post
 *      context (e.g. a generic admin screen) so the user's habitual target
 *      language is pre-selected without them having to pick it every time.
 *   3. Default <select> value — whatever the first <option> is.
 *
 * A change listener is attached once here to persist every subsequent manual
 * selection to localStorage and to dismiss the auto-detected hint.
 */
function initLanguageSelect(popover) {

    const langSelect = popover.querySelector('.wpenhance-ai-tp__lang');
    const langHint   = popover.querySelector('.wpenhance-ai-tp__lang-hint');

    if (!langSelect) return;

    const hasOption = (code) =>
        !!langSelect.querySelector(`option[value="${escAttr(String(code))}"]`);

    const detectedCode  = WPEnhanceAIToolbar.postLanguage || null;
    const persistedCode = localStorage.getItem(LANG_STORAGE_KEY);

    if (detectedCode && hasOption(detectedCode)) {

        // Post-context wins — show the "detected" hint.
        langSelect.value = detectedCode;

        if (langHint) {
            langHint.textContent = '↑ Detected from current post';
            langHint.hidden      = false;
        }

    } else if (persistedCode && hasOption(persistedCode)) {

        // Fallback to last persisted choice — no hint needed.
        langSelect.value = persistedCode;
    }

    // Persist every manual change and dismiss the detected hint.
    langSelect.addEventListener('change', () => {
        localStorage.setItem(LANG_STORAGE_KEY, langSelect.value);
        if (langHint) langHint.hidden = true;
    });
}

function openPopover(popover, anchorEl) {

    // ── Apply language preference (first open only) ───────────────────────────
    if (!popover.dataset.langInitialised) {
        initLanguageSelect(popover);
        popover.dataset.langInitialised = '1';
    }

    // ── Pre-fill with selected text (if any) from the current page ───────────

    const selection = window.getSelection
        ? window.getSelection().toString().trim()
        : '';

    if (selection) {
        const textarea = popover.querySelector('.wpenhance-ai-tp__input');
        if (textarea && textarea.value === '') {
            textarea.value = selection;
        }
    }

    // Position below the admin bar anchor.
    positionPopover(popover, anchorEl);

    popover.hidden = false;

    // Focus the language selector for keyboard users.
    const firstFocusable = popover.querySelector('select, textarea, button');
    if (firstFocusable) firstFocusable.focus();
}

function closePopover(popover) {
    popover.hidden = true;
}

/**
 * Position the popover so it appears directly below the toolbar node
 * and stays within the viewport horizontally.
 */
function positionPopover(popover, anchorEl) {

    const rect      = anchorEl.getBoundingClientRect();
    const popWidth  = 360; // matches CSS min-width
    const margin    = 8;
    const vpWidth   = window.innerWidth;

    let left = rect.left;

    // Keep within right edge of viewport.
    if (left + popWidth + margin > vpWidth) {
        left = vpWidth - popWidth - margin;
    }
    // Keep within left edge.
    if (left < margin) {
        left = margin;
    }

    popover.style.top  = (rect.bottom + 2) + 'px';
    popover.style.left = left + 'px';
}

/* ─────────────────────────────────────────────────────────────────────────────
   Translation fetch
   ───────────────────────────────────────────────────────────────────────────── */

async function runTranslation(popover) {

    const langSelect  = popover.querySelector('.wpenhance-ai-tp__lang');
    const inputArea   = popover.querySelector('.wpenhance-ai-tp__input');
    const resultPanel = popover.querySelector('.wpenhance-ai-tp__result');
    const resultMeta  = popover.querySelector('.wpenhance-ai-tp__result-meta');
    const outputArea  = popover.querySelector('.wpenhance-ai-tp__output');
    const translateBtn = popover.querySelector('.wpenhance-ai-tp__btn-translate');

    const chunkText = inputArea.value.trim();

    if (!chunkText) {
        inputArea.focus();
        inputArea.placeholder = 'Please enter some text first…';
        return;
    }

    // ── Loading state ─────────────────────────────────────────────────────────

    resultPanel.hidden      = false;
    resultMeta.textContent  = '';
    outputArea.value        = '';
    translateBtn.disabled   = true;
    translateBtn.textContent = 'Translating…';

    resultMeta.innerHTML = '<span class="wpenhance-ai-tp__status">Translating…</span>';

    // ── Fetch ─────────────────────────────────────────────────────────────────

    try {

        const response = await fetch(
            `${WPEnhanceAIToolbar.restUrl}/translate-chunk`,
            {
                method:  'POST',
                headers: {
                    'X-WP-Nonce':   WPEnhanceAIToolbar.nonce,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    target_language: langSelect.value,
                    chunk_text:      chunkText,
                }),
            }
        );

        const data = await response.json();

        if (!data.success || !data.output) {
            const msg = data.error || 'Translation failed. Please try again.';
            resultMeta.innerHTML = `<span class="wpenhance-ai-tp__error">${escHtml(msg)}</span>`;
            outputArea.value     = '';
        } else {
            const langLabel = data.language
                ? `Translated to: <strong>${escHtml(data.language)}</strong>`
                : 'Translation complete';
            resultMeta.innerHTML = `<span class="wpenhance-ai-tp__success">${langLabel}</span>`;
            outputArea.value     = data.output;
        }

    } catch (_) {

        resultMeta.innerHTML = '<span class="wpenhance-ai-tp__error">Request failed. Check your connection.</span>';
        outputArea.value     = '';
    }

    // ── Restore button ────────────────────────────────────────────────────────

    translateBtn.disabled    = false;
    translateBtn.textContent = 'Translate';
}

/* ─────────────────────────────────────────────────────────────────────────────
   Utilities
   ───────────────────────────────────────────────────────────────────────────── */

function escHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function escAttr(value) {
    return String(value)
        .replace(/&/g,  '&amp;')
        .replace(/"/g,  '&quot;')
        .replace(/'/g,  '&#39;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;');
}
