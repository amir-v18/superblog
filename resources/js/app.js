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
