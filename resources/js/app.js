// Fetch pagination in place so the other article section and the active scroll area remain untouched.
const paginationRequests = new Map();

async function updateArticleSection(sectionKey, destination, updateHistory = true) {
    const section = document.querySelector(`[data-article-section="${sectionKey}"]`);
    if (!section) {
        return false;
    }

    paginationRequests.get(sectionKey)?.abort();
    const controller = new AbortController();
    paginationRequests.set(sectionKey, controller);

    try {
        const response = await fetch(destination, {
            headers: {
                Accept: 'text/html',
                'X-Pagination-Section': sectionKey,
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        });

        if (!response.ok) {
            return false;
        }

        const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
        const replacement = documentFragment.querySelector(`[data-article-section="${sectionKey}"]`);

        if (!replacement || paginationRequests.get(sectionKey) !== controller) {
            return false;
        }

        section.replaceWith(replacement);
        if (updateHistory) {
            window.history.pushState({}, '', destination);
        }
        return true;
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('Could not load article page.', error);
        }

        return false;
    } finally {
        if (paginationRequests.get(sectionKey) === controller) {
            paginationRequests.delete(sectionKey);
        }
    }
}

document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('a[data-pagination-section]') : null;

    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const destination = new URL(link.href, window.location.href);
    if (destination.origin !== window.location.origin) {
        return;
    }

    event.preventDefault();
    updateArticleSection(link.dataset.paginationSection, destination.href);
});

window.addEventListener('popstate', () => {
    const currentUrl = new URL(window.location.href);

    document.querySelectorAll('[data-article-section]').forEach((section) => {
        const pageName = section.dataset.pageName;
        const pageInUrl = currentUrl.searchParams.get(pageName) || '1';

        if (pageInUrl !== section.dataset.currentPage) {
            updateArticleSection(section.dataset.articleSection, currentUrl.href, false);
        }
    });
});

function showArticleDeleteNotice(message, isError = false) {
    const notice = document.querySelector('[data-article-delete-notice]');
    if (!notice) {
        return;
    }

    notice.hidden = false;
    notice.textContent = message;
    notice.setAttribute('role', isError ? 'alert' : 'status');
    notice.setAttribute('aria-live', isError ? 'assertive' : 'polite');
    notice.className = isError
        ? 'fixed top-4 right-4 left-4 z-50 mx-auto max-w-md rounded-lg border border-studio-bad/40 bg-studio-bad-bg p-3 text-sm text-studio-bad shadow-xl'
        : 'fixed top-4 right-4 left-4 z-50 mx-auto max-w-md rounded-lg border border-studio-ok/30 bg-studio-live p-3 text-sm text-studio-ok shadow-xl';
}

document.addEventListener('submit', async (event) => {
    const form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!form?.matches('[data-article-delete-form]')) {
        return;
    }

    event.preventDefault();

    try {
        const csrfToken = form.querySelector('input[name="_token"]')?.value;
        const response = await fetch(form.action, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
        });
        const result = await response.json().catch(() => null);

        if (!response.ok || !result?.sectionKey || !Number.isInteger(Number(result.currentPage))) {
            showArticleDeleteNotice('Artikeln kunde inte tas bort. Försök igen.', true);
            return;
        }

        try {
            localStorage.setItem('superblog-article-deleted', JSON.stringify({
                superblogId: result.superblogId,
                nonce: `${Date.now()}-${Math.random()}`,
            }));
        } catch (error) {
            console.warn('Could not notify open previews about the deleted article.', error);
        }

        const section = document.querySelector(`[data-article-section="${result.sectionKey}"]`);
        if (!section) {
            showArticleDeleteNotice('Artikeln har tagits bort permanent. Ladda om sidan för att uppdatera listan.');
            form.closest('dialog')?.close();
            return;
        }

        const destination = new URL(window.location.href);
        destination.searchParams.set(section.dataset.pageName, String(result.currentPage));
        window.history.replaceState({}, '', destination);
        form.closest('dialog')?.close();

        const sectionUpdated = await updateArticleSection(result.sectionKey, destination.href, false);
        showArticleDeleteNotice(sectionUpdated
            ? result.message
            : 'Artikeln har tagits bort, men listan kunde inte uppdateras. Ladda om sidan.', !sectionUpdated);
    } catch {
        showArticleDeleteNotice('Artikeln kunde inte tas bort. Kontrollera anslutningen och försök igen.', true);
    }
});

window.addEventListener('storage', (event) => {
    if (event.key !== 'superblog-article-deleted' || !event.newValue) {
        return;
    }

    const previewSuperblogId = document.body.dataset.adminPreviewSuperblogId;
    if (!previewSuperblogId) {
        return;
    }

    try {
        const change = JSON.parse(event.newValue);
        if (String(change.superblogId) === previewSuperblogId) {
            window.location.reload();
        }
    } catch {
        // Ignore malformed storage events; they do not affect the current page.
    }
});

// Links with data-open-dialog open that <dialog> when the page has one; otherwise they stay normal links.
document.addEventListener('click', (event) => {
    const closer = event.target instanceof Element ? event.target.closest('[data-close-dialog]') : null;
    if (closer) {
        event.preventDefault();
        closer.closest('dialog')?.close();
        return;
    }

    const opener = event.target.closest('[data-open-dialog]');
    const dialog = opener && document.getElementById(opener.dataset.openDialog);

    if (dialog instanceof HTMLDialogElement) {
        event.preventDefault();
        dialog.showModal();
    }
});

// A slow submit (e.g. fetching a feed) shows data-pending-text and blocks a second click until the page reloads.
document.addEventListener('submit', (event) => {
    const button = event.submitter;

    if (button?.dataset.pendingText) {
        button.disabled = true;
        button.textContent = button.dataset.pendingText;
    }
});

document.querySelectorAll('dialog').forEach((dialog) => {
    // The content sits in an inner wrapper, so a click whose target is the dialog itself landed on the backdrop.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog || event.target.closest('[data-close-dialog]')) {
            dialog.close();
        }
    });

    // Reopens a modal after a failed submit so its validation errors are visible.
    if (dialog.hasAttribute('data-open-on-load')) {
        dialog.showModal();
    }
});

// Suggests a domain from the name ("Dagens Budbärare" -> dagensbudbarare.se) until the domain is edited by hand.
document.querySelectorAll('form').forEach((form) => {
    const source = form.querySelector('[data-domain-source]');
    const target = form.querySelector('[data-domain-target]');

    if (!source || !target) {
        return;
    }

    // A prefilled value (e.g. old input after an error) counts as the user's own choice.
    let edited = target.value !== '';

    target.addEventListener('input', () => {
        edited = true;
    });

    source.addEventListener('input', () => {
        if (edited) {
            return;
        }

        // NFD splits å/ä/ö into a base letter plus a mark, so removing the marks leaves a/a/o.
        const slug = source.value
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]/g, '');

        target.value = slug ? `${slug}.se` : '';
    });
});

// Dator/Mobil in the builder preview: mobile is 390 px, desktop is drawn 1240 px wide and scaled to fit (never under 30 %).
const previewPane = document.querySelector('[data-preview-pane]');
const previewDevices = document.querySelector('[data-preview-devices]');
const previewFrame = document.querySelector('[data-preview-frame]');
const previewIframe = previewFrame?.querySelector('iframe');

// fit: desktop is scaled down to the pane; mobile is always shown at full size.
const DEVICES = {
    desktop: { width: 1240, label: 'Dator', fit: true },
    mobile: { width: 390, label: '390 px', fit: false },
};

if (previewPane && previewDevices) {
    const label = document.querySelector('[data-preview-label]');
    const buttons = previewDevices.querySelectorAll('[data-preview-device]');

    // sessionStorage keeps the choice across the reload on save and tab change; it can throw (e.g. blocked storage), so desktop is the fallback.
    let device = 'desktop';
    try {
        device = sessionStorage.getItem('preview-device') === 'mobile' ? 'mobile' : 'desktop';
    } catch {}

    const render = () => {
        const current = DEVICES[device];
        // clientWidth includes the pane's 2 × 20 px padding, so 40 px is subtracted.
        const scale = current.fit ? Math.min(1, Math.max(0.3, (previewPane.clientWidth - 40) / current.width)) : 1;

        // transform shrinks only the look, so the iframe keeps a 1240 px viewport; the frame gets the scaled size and clips the rest.
        previewFrame.style.width = `${current.width * scale}px`;
        previewIframe.style.width = `${current.width}px`;
        previewIframe.style.height = `${previewFrame.clientHeight / scale}px`;
        previewIframe.style.transform = `scale(${scale})`;

        label.textContent = current.label;
        buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.previewDevice === device)));
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            device = button.dataset.previewDevice;
            try {
                sessionStorage.setItem('preview-device', device);
            } catch {}
            render();
        });
    });

    new ResizeObserver(render).observe(previewPane);
    previewDevices.hidden = false;
}

// Live preview without saving: the typed name and the picked theme are copied into the iframe (same origin).
// Without JS the iframe simply shows the saved values.
if (previewIframe) {
    const nameInput = document.querySelector('[data-live-name-source]');
    const themeRadios = document.querySelectorAll('input[type="radio"][name="theme"]');

    const sync = () => {
        const doc = previewIframe.contentDocument;

        // Before the iframe has loaded this is an empty document; the load listener below runs sync again.
        if (!doc?.documentElement) {
            return;
        }

        if (nameInput) {
            doc.querySelectorAll('[data-live-name]').forEach((element) => {
                // The iframe was rendered with the saved name, so the first text seen is kept as the fallback for an empty field.
                element.dataset.savedName ??= element.textContent;
                element.textContent = nameInput.value.trim() === '' ? element.dataset.savedName : nameInput.value;
            });
        }

        // Only the attribute changes; the colors come from the [data-theme] rules in app.css, which the iframe loads for every theme.
        const checked = [...themeRadios].find((radio) => radio.checked);
        if (checked) {
            doc.documentElement.dataset.theme = checked.value;
        }
    };

    // Also covers unsaved values that are already in the form, e.g. old input after a validation error.
    previewIframe.addEventListener('load', sync);
    nameInput?.addEventListener('input', sync);
    themeRadios.forEach((radio) => radio.addEventListener('change', sync));
    sync();
}

// Admin color mode: switch in place and save in the background, so unsaved fields (e.g. in the builder) survive.
// Only pages rendered by layouts.admin have the switch; the preview iframe and the magazines load this file too
// but have no switch, so nothing here runs there.
const modeForms = document.querySelectorAll('[data-studio-mode-form]');

if (modeForms.length > 0) {
    const MODE_TEXT = {
        light: { label: 'Ljust', aria: 'Byt till ljust läge' },
        dark: { label: 'Mörkt', aria: 'Byt till mörkt läge' },
    };

    // Shows the page in the given mode and makes every switch offer the other one.
    const applyMode = (mode) => {
        if (mode === 'light') {
            document.documentElement.dataset.studioMode = 'light';
        } else {
            delete document.documentElement.dataset.studioMode;
        }

        const next = mode === 'light' ? 'dark' : 'light';
        modeForms.forEach((form) => {
            form.querySelector('[data-studio-mode-next]').value = next;
            form.querySelector('[data-studio-mode-label]').textContent = MODE_TEXT[next].label;
            form.querySelector('button').setAttribute('aria-label', MODE_TEXT[next].aria);
            form.querySelectorAll('[data-studio-mode-icon]').forEach((icon) => {
                icon.toggleAttribute('hidden', icon.dataset.studioModeIcon !== next);
            });
        });
    };

    const showModeError = () => {
        const notice = document.createElement('div');
        notice.setAttribute('role', 'alert');
        notice.className = 'fixed top-4 right-4 left-4 z-50 mx-auto max-w-md rounded-lg border border-studio-bad/40 bg-studio-bad-bg p-3 text-sm text-studio-bad shadow-xl';
        notice.textContent = 'Valet kunde inte sparas. Läget gäller tills sidan laddas om.';
        document.body.append(notice);
        setTimeout(() => notice.remove(), 6000);
    };

    document.addEventListener('submit', async (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!form?.matches('[data-studio-mode-form]')) {
            return;
        }

        event.preventDefault();
        // Taken ahead of applyMode, which rewrites the mode field to the next choice.
        const body = new FormData(form);
        applyMode(body.get('mode'));

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });

            if (!response.ok) {
                showModeError();
            }
        } catch {
            showModeError();
        }
    });
}
