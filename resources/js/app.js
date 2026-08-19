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

Alpine.data('emailMonitoring', () => ({
    resendModalOpen: false,
    resendUrl: '',
    recipient: '',
    originalRecipient: '',
    subject: '',
    logId: '',
    isSubmitting: false,

    viewBodyOpen: false,
    bodyContent: '',
    viewSubject: '',
    isLoadingBody: false,

    openResendModal(url, currentRecipient, currentSubject, id) {
        this.resendUrl = url;
        this.recipient = currentRecipient;
        this.originalRecipient = currentRecipient;
        this.subject = currentSubject;
        this.logId = id;
        this.resendModalOpen = true;
    },

    async submitResend() {
        if (!this.resendUrl || this.isSubmitting) return;
        this.isSubmitting = true;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        try {
            const formData = new FormData();
            formData.append('_token', csrfToken);
            if (this.recipient) {
                formData.append('recipient', this.recipient);
            }

            const res = await fetch(this.resendUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData
            });

            const data = await res.json();
            if (res.ok && data.success) {
                window.dispatchEvent(new CustomEvent('paperflow-toast', {
                    detail: { message: data.message || 'Email successfully re-queued!', type: 'success' }
                }));
                this.resendModalOpen = false;
            } else {
                const err = data.message || 'Failed to re-send email.';
                window.dispatchEvent(new CustomEvent('paperflow-toast', {
                    detail: { message: err, type: 'error' }
                }));
            }
        } catch (e) {
            window.dispatchEvent(new CustomEvent('paperflow-toast', {
                detail: { message: 'Network or server error while re-sending email.', type: 'error' }
            }));
        } finally {
            this.isSubmitting = false;
        }
    },

    async openBodyModal(logId, subject) {
        this.viewSubject = subject;
        this.bodyContent = '<div style="padding: 40px; text-align: center; font-family: sans-serif; color: #64748b; font-weight: bold;">⏳ Loading email content preview...</div>';
        this.isLoadingBody = true;
        this.viewBodyOpen = true;

        try {
            const res = await fetch('/email-monitoring/' + logId + '/body', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (data.body) {
                this.bodyContent = data.body;
            } else {
                this.bodyContent = '<div style="padding: 40px; text-align: center; font-family: sans-serif; color: #ef4444; font-weight: bold;">No body content stored for this email.</div>';
            }
        } catch (e) {
            this.bodyContent = '<div style="padding: 40px; text-align: center; font-family: sans-serif; color: #ef4444; font-weight: bold;">Failed to load email preview.</div>';
        } finally {
            this.isLoadingBody = false;
        }
    },

    copyEmail(email) {
        navigator.clipboard.writeText(email);
        window.dispatchEvent(new CustomEvent('paperflow-toast', {
            detail: { message: 'Copied recipient email address: ' + email, type: 'success' }
        }));
    }
}));

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
