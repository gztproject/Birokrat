import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

function today() {
    return new Date().toISOString().slice(0, 10);
}

function notify(message, extra) {
    const body = document.getElementById('notificationBody');
    const modal = document.getElementById('notificationModal');
    if (body) {
        body.replaceChildren();
        const item = document.createElement('li');
        item.textContent = message;
        body.append(item);
        if (extra) {
            body.append(extra);
        }
    }
    if (modal) {
        Modal.getOrCreateInstance(modal).show();
    }
}

async function postForm(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams(payload),
    });
    if (!response.ok) {
        notify(`Error requesting page ${url}`);
        throw new Error(response.statusText);
    }
    return response;
}

export default class extends Controller {
    static values = {
        issueUrl: { type: String, default: '/dashboard/invoice/issue' },
        payUrl: { type: String, default: '/dashboard/invoice/pay' },
        cancelUrl: { type: String, default: '/dashboard/invoice/cancel' },
        sendUrl: { type: String, default: '/dashboard/invoice/send' },
        showPrefix: { type: String, default: 'invoice' },
    };

    issue(event) {
        event.stopPropagation();
        this.openDateModal(this.issueUrlValue, { id: event.currentTarget.value });
    }

    pay(event) {
        event.stopPropagation();
        this.openDateModal(this.payUrlValue, { id: event.currentTarget.value });
    }

    async cancel(event) {
        event.stopPropagation();
        const idInput = document.getElementById('cancelId');
        const reasonModal = document.getElementById('cancelReasonModal');
        if (idInput) {
            idInput.value = event.currentTarget.value;
        }
        if (reasonModal) {
            Modal.getOrCreateInstance(reasonModal).show();
        }
        const submit = document.getElementById('submitCancel');
        submit?.addEventListener('click', async () => {
            const reason = document.getElementById('cancelReason')?.value ?? '';
            if (reason === '') {
                window.alert('You must enter a reason.');
                return;
            }
            await postForm(this.cancelUrlValue, { id: idInput.value, reason });
            Modal.getOrCreateInstance(reasonModal).hide();
            window.location.reload();
        }, { once: true });
    }

    reject(event) {
        event.stopPropagation();
        const idInput = document.getElementById('rejectId');
        const reasonModal = document.getElementById('rejectReasonModal');
        if (idInput) {
            idInput.value = event.currentTarget.value;
        }
        if (reasonModal) {
            Modal.getOrCreateInstance(reasonModal).show();
        }
        document.getElementById('submitReject')?.addEventListener('click', async () => {
            const reason = document.getElementById('rejectReason')?.value ?? '';
            if (reason === '') {
                window.alert('You must enter a reason.');
                return;
            }
            await postForm('/dashboard/incomingInvoice/reject', { id: idInput.value, reason });
            Modal.getOrCreateInstance(reasonModal).hide();
            window.location.reload();
        }, { once: true });
    }

    send(event) {
        event.stopPropagation();
        const invId = event.currentTarget.value || event.currentTarget.id;
        const emailModal = document.getElementById('emailModal');
        if (!emailModal) {
            notify('Email form is missing on this page.');
            return;
        }
        Modal.getOrCreateInstance(emailModal).show();
        document.getElementById('sendEmailBtn')?.addEventListener('click', async () => {
            const to = document.getElementById('emailInput')?.value ?? '';
            const cc = document.getElementById('ccInput')?.value ?? '';
            const response = await postForm(this.sendUrlValue, {
                id: invId,
                email: to,
                cc,
                subject: document.getElementById('subjectInput')?.value ?? '',
                body: document.getElementById('bodyInput')?.value ?? '',
            });
            const data = await response.json();
            if (data?.[0]?.status !== 'ok') {
                notify(`Error sending invoice: ${data?.[0]?.data?.[0] ?? ''}`);
                return;
            }
            Modal.getOrCreateInstance(emailModal).hide();
            const result = data[0];
            let extra = null;
            if (result.partnerNeedsUpdate) {
                extra = document.createElement('div');
                extra.className = 'mt-2';
                const hint = document.createElement('p');
                hint.className = 'mb-2';
                hint.textContent = result.partnerUpdateHint ?? '';
                extra.append(hint);
                const save = document.createElement('button');
                save.type = 'button';
                save.className = 'btn btn-sm btn-success me-2';
                save.textContent = result.saveOnPartner ?? 'Save';
                save.addEventListener('click', async () => {
                    const mergeResponse = await postForm(result.mergeUrl, { to: result.to ?? to, cc: result.cc ?? cc });
                    const mergeData = await mergeResponse.json();
                    if (mergeData.status !== 'ok') {
                        notify(`Error: ${mergeData?.data?.[0] ?? ''}`);
                        return;
                    }
                    Modal.getOrCreateInstance(document.getElementById('notificationModal')).hide();
                });
                extra.append(save);
                const edit = document.createElement('a');
                edit.className = 'btn btn-sm btn-outline-primary';
                edit.href = result.editUrl;
                edit.textContent = result.editPartnerEmails ?? 'Edit';
                extra.append(edit);
            }
            notify(result.data[0], extra);
        }, { once: true });
    }

    openRow(event) {
        const id = event.currentTarget.dataset.id;
        if (!id) {
            return;
        }
        let url = '';
        if (window.location.pathname.endsWith('dashboard')) {
            url += 'dashboard/';
        }
        url += `${this.showPrefixValue}/${id}/show`;
        window.location = url;
    }

    openDateModal(url, extra) {
        const dateInput = document.getElementById('modalDate');
        const idInput = document.getElementById('dateId');
        const modal = document.getElementById('dateModal');
        if (dateInput) {
            dateInput.value = today();
        }
        if (idInput) {
            idInput.value = extra.id;
        }
        if (modal) {
            Modal.getOrCreateInstance(modal).show();
        }
        document.getElementById('submitDate')?.addEventListener('click', async () => {
            const payload = { ...extra, date: dateInput?.value };
            const method = document.getElementById('modalPaymentMethod');
            if (method) {
                payload.mode = method.value;
            }
            const bankCost = document.getElementById('modalBankCost');
            if (bankCost) {
                payload.bankCost = bankCost.value;
            }
            await postForm(url, payload);
            Modal.getOrCreateInstance(modal).hide();
            window.location.reload();
        }, { once: true });
    }
}
