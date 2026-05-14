/* WPEnhance AI – admin meta box interactions */

// ─── Feature button click ────────────────────────────────────────────────────

document.addEventListener('click', async (event) => {

    const button = event.target.closest('.wpenhance-ai-action');

    if (!button) return;

    const featureKey = button.dataset.feature;
    const postId     = button.dataset.postId;
    const panel      = button.closest('.wpenhance-ai-panel');
    const result     = panel.querySelector('.wpenhance-ai-result');

    // Collect extra UI field values for this feature (e.g. target_language).
    const params = {};

    panel
        .querySelectorAll(
            `.wpenhance-ai-select[data-feature-ref="${featureKey}"]`
        )
        .forEach((field) => {
            params[field.dataset.field] = field.value;
        });

    result.innerHTML = '<p class="wpenhance-ai-status">Generating…</p>';

    button.disabled = true;

    try {

        const response = await fetch(
            `${WPEnhanceAI.restUrl}/feature/${featureKey}/${postId}`,
            {
                method:  'POST',
                headers: {
                    'X-WP-Nonce':   WPEnhanceAI.nonce,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(params),
            }
        );

        const data = await response.json();

        if (!data.success || !data.output) {

            const msg = data.error || 'Generation failed.';
            result.innerHTML = `<p class="wpenhance-ai-error">${escapeHtml(msg)}</p>`;
            return;
        }

        if (data.type === 'content') {

            renderContentResult(result, data, postId);

        } else {

            result.innerHTML = `
                <textarea
                    class="wpenhance-ai-textarea"
                    rows="4"
                >${escapeHtml(data.output)}</textarea>`;
        }

    } catch (error) {

        result.innerHTML =
            '<p class="wpenhance-ai-error">Request failed.</p>';

    } finally {

        button.disabled = false;
    }
});

// ─── "Apply to Editor" button ────────────────────────────────────────────────

document.addEventListener('click', async (event) => {

    const button = event.target.closest('.wpenhance-ai-apply');

    if (!button) return;

    const panel    = button.closest('.wpenhance-ai-panel');
    const result   = button.closest('.wpenhance-ai-content-result');
    const textarea = panel.querySelector('.wpenhance-ai-textarea');

    if (!textarea) return;

    // Apply translated content to the editor.
    if (window.wp?.data) {

        wp.data
            .dispatch('core/editor')
            .editPost({ content: textarea.value });

    } else {

        // Classic editor fallback.
        const classicEditor = document.querySelector('#content');
        if (classicEditor) classicEditor.value = textarea.value;
    }

    // If footnotes were translated, persist them via the REST endpoint.
    const footnotesJson = result?.dataset.footnotes || '';
    const postId        = panel.querySelector('.wpenhance-ai-action')?.dataset.postId;

    if (footnotesJson && postId) {

        try {

            await fetch(
                `${WPEnhanceAI.restUrl}/footnotes/${postId}`,
                {
                    method:  'POST',
                    headers: {
                        'X-WP-Nonce':   WPEnhanceAI.nonce,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ footnotes: footnotesJson }),
                }
            );

        } catch (_) {
            // Content was already applied — footnote failure is non-fatal.
            console.warn('WPEnhance AI: could not save translated footnotes.');
        }
    }

    button.textContent = 'Applied ✓';
    button.disabled    = true;
});

// ─── "Copy" button ───────────────────────────────────────────────────────────

document.addEventListener('click', async (event) => {

    const button = event.target.closest('.wpenhance-ai-copy');

    if (!button) return;

    const panel    = button.closest('.wpenhance-ai-panel');
    const textarea = panel.querySelector('.wpenhance-ai-textarea');

    if (!textarea) return;

    try {

        await navigator.clipboard.writeText(textarea.value);

        button.textContent = 'Copied ✓';

        setTimeout(() => { button.textContent = 'Copy'; }, 2000);

    } catch (_) {

        // Fallback for browsers that restrict clipboard access.
        textarea.select();
        document.execCommand('copy');

        button.textContent = 'Copied ✓';

        setTimeout(() => { button.textContent = 'Copy'; }, 2000);
    }
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Render the result area for full-content outputs (e.g. translations).
 */
function renderContentResult(container, data, postId) {

    const footnotesAttr = data.footnotes
        ? ` data-footnotes="${escapeAttr(data.footnotes)}"`
        : '';

    const footnotesNote = data.footnotes
        ? `<p class="wpenhance-ai-result-meta">
               ${countFootnotes(data.footnotes)} footnote(s) translated — applied together with content.
           </p>`
        : '';

    container.innerHTML = `
        <div class="wpenhance-ai-content-result"${footnotesAttr}>
            <p class="wpenhance-ai-result-meta">
                Translated to: <strong>${escapeHtml(data.language)}</strong>
            </p>
            ${footnotesNote}
            <textarea
                class="wpenhance-ai-textarea wpenhance-ai-textarea--large"
                rows="10"
            >${escapeHtml(data.output)}</textarea>
            <div class="wpenhance-ai-btn-row">
                <button
                    type="button"
                    class="button button-primary wpenhance-ai-apply"
                >Apply to Editor</button>
                <button
                    type="button"
                    class="button button-secondary wpenhance-ai-copy"
                >Copy</button>
            </div>
        </div>`;
}

/**
 * Count entries in a JSON-encoded footnotes array.
 */
function countFootnotes(json) {

    try {
        return JSON.parse(json).length;
    } catch (_) {
        return 0;
    }
}

/**
 * Escape a value for safe use inside an HTML attribute.
 */
function escapeAttr(value) {

    return String(value)
        .replace(/&/g,  '&amp;')
        .replace(/"/g,  '&quot;')
        .replace(/'/g,  '&#39;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;');
}

/**
 * Safely escape a string for use inside innerHTML.
 */
function escapeHtml(value) {

    const div    = document.createElement('div');
    div.innerText = String(value);

    return div.innerHTML;
}
