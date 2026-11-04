// Links with data-open-dialog open that <dialog> when the page has one; otherwise they stay normal links.
document.addEventListener('click', (event) => {
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
