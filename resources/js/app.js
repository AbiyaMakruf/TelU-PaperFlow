import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);
window.Alpine = Alpine;

window.submitPaperflowForm = async function(event) {
    if (!event) return;
    event.preventDefault();
    const form = event.target;
    if (!form) return;

    const submitBtn = event.submitter || form.querySelector('button[type="submit"]') || form.querySelector('button');
    const originalHtml = submitBtn ? submitBtn.innerHTML : '';

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="inline-flex items-center gap-1.5"><svg class="animate-spin size-4 text-current" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...</span>';
    }

    try {
        const formData = new FormData(form);
        if (event.submitter && event.submitter.name && event.submitter.value) {
            formData.set(event.submitter.name, event.submitter.value);
        }

        const targetUrl = form.getAttribute('action') || (typeof form.action === 'string' ? form.action : '');
        const response = await fetch(targetUrl, {
            method: (form.getAttribute('method') || 'POST').toUpperCase(),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: formData
        });

        let data = {};
        try {
            data = await response.json();
        } catch (e) {
            data = {};
        }

        if (response.ok && data.success !== false) {
            const msg = data.message || 'Action completed successfully.';
            window.dispatchEvent(new CustomEvent('paperflow-toast', {
                detail: { message: msg, type: 'success' }
            }));
        } else {
            const errorMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Request failed.');
            window.dispatchEvent(new CustomEvent('paperflow-toast', {
                detail: { message: errorMsg, type: 'error' }
            }));
        }
    } catch (err) {
        window.dispatchEvent(new CustomEvent('paperflow-toast', {
            detail: { message: 'An unexpected error occurred. Please try again.', type: 'error' }
        }));
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    }
};

Alpine.start();

document.addEventListener('click', (event) => {
    const removeButton = event.target.closest('[data-builder-remove]');
    if (removeButton) {
        removeButton.closest('[data-builder-item]')?.remove();
        return;
    }

    const addButton = event.target.closest('[data-builder-add]');
    if (!addButton) return;

    const builder = addButton.closest('[data-builder]');
    const template = builder?.querySelector('[data-builder-template]');
    const list = builder?.querySelector('[data-builder-list]');
    if (!template || !list) return;

    const index = `new_${Date.now()}_${list.children.length}`;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', index).trim();
    list.append(wrapper.firstElementChild);
});
