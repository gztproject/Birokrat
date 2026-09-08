import { Controller } from '@hotwired/stimulus';

function daysBetween(fromValue, toValue) {
    if (!fromValue || !toValue) {
        return null;
    }
    const from = new Date(`${fromValue}T00:00:00`);
    const to = new Date(`${toValue}T00:00:00`);
    return Math.round((to - from) / 86400000);
}

function addDays(dateValue, days) {
    const date = new Date(`${dateValue}T00:00:00`);
    date.setDate(date.getDate() + Number(days || 0));
    return date.toISOString().slice(0, 10);
}

function parseAmount(value) {
    if (value === '' || value == null) {
        return 0;
    }
    return Number(String(value).replace(',', '.').replace(/\s/g, '').replace('€', '')) || 0;
}

function formatPrice(value) {
    const number = Number(value);
    const [intPart, frac = '00'] = number.toFixed(2).split('.');
    const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return `${grouped},${frac}`;
}

export default class extends Controller {
    static targets = [
        'issuer',
        'number',
        'dateOfIssue',
        'dueDate',
        'dueInDays',
        'from',
        'to',
        'items',
        'discount',
        'total',
        'addButton',
    ];

    connect() {
        this.syncLinkedDates();
        this.recalculate();
        this.refreshNumber();
        this.refreshDueInDays();
        if (this.hasItemsTarget && this.itemsTarget.querySelectorAll('tr.invoice-item').length === 0) {
            this.addItem();
        }
    }

    syncLinkedDates() {
        if (this.hasFromTarget && this.hasToTarget && this.fromTarget.value) {
            this.toTarget.min = this.fromTarget.value;
        }
    }

    onFromChange() {
        if (this.hasToTarget) {
            this.toTarget.min = this.fromTarget.value;
        }
        this.onIssueDateChange();
    }

    onIssueDateChange() {
        if (!this.hasDateOfIssueTarget || !this.dateOfIssueTarget.value) {
            return;
        }
        if (this.hasToTarget) {
            this.toTarget.value = this.dateOfIssueTarget.value;
        }
        if (this.hasDueInDaysTarget && this.hasDueDateTarget) {
            this.dueDateTarget.value = addDays(this.dateOfIssueTarget.value, this.dueInDaysTarget.value);
        }
        this.refreshNumber();
    }

    onDueDateChange() {
        if (!this.hasDueDateTarget || !this.hasDateOfIssueTarget || !this.hasDueInDaysTarget) {
            return;
        }
        const days = daysBetween(this.dateOfIssueTarget.value, this.dueDateTarget.value);
        if (days !== null) {
            this.dueInDaysTarget.value = days;
        }
    }

    onDueInDaysChange() {
        if (!this.hasDateOfIssueTarget || !this.hasDueDateTarget || !this.hasDueInDaysTarget) {
            return;
        }
        if (this.dateOfIssueTarget.value) {
            this.dueDateTarget.value = addDays(this.dateOfIssueTarget.value, this.dueInDaysTarget.value);
        }
    }

    async refreshNumber() {
        if (!this.hasIssuerTarget || !this.hasNumberTarget || !this.hasDateOfIssueTarget) {
            return;
        }
        const body = new URLSearchParams({
            issuerId: this.issuerTarget.value,
            dateOfIssue: this.dateOfIssueTarget.value,
        });
        const response = await fetch('/dashboard/invoice/getNewNumber', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });
        const data = await response.json();
        if (data?.[0]?.status === 'ok') {
            this.numberTarget.value = data[0].data[0];
        }
    }

    async refreshDueInDays() {
        if (!this.hasIssuerTarget || !this.hasDueInDaysTarget) {
            return;
        }
        const body = new URLSearchParams({ issuerId: this.issuerTarget.value });
        const response = await fetch('/dashboard/invoice/getDefaultDueInDays', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });
        const data = await response.json();
        if (data?.[0]?.status === 'ok') {
            this.dueInDaysTarget.value = data[0].data[0];
            this.onDueInDaysChange();
        }
    }

    addItem() {
        const prototype = this.itemsTarget.dataset.prototype;
        const index = Number(this.itemsTarget.dataset.index || this.itemsTarget.querySelectorAll('tr.invoice-item').length);
        const html = prototype.replace(/__name__/g, String(index));
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = `<tr class="invoice-item invoice-item-tr-${index}">
            <td class="codeInputRow">${this.extractField(html, `invoice_invoiceItemCommands_${index}_code`)}</td>
            <td class="nameInput">${this.extractField(html, `invoice_invoiceItemCommands_${index}_name`)}</td>
            <td class="quantityInput">${this.extractField(html, `invoice_invoiceItemCommands_${index}_quantity`)}</td>
            <td class="unitInput">${this.extractField(html, `invoice_invoiceItemCommands_${index}_unit`)}</td>
            <td class="priceInput">${this.extractField(html, `invoice_invoiceItemCommands_${index}_price`)}</td>
            <td class="discountInput">${this.extractField(html, `invoice_invoiceItemCommands_${index}_discount`)}</td>
            <td class="valueInput"><input id="iiValue_${index}" class="valueInput form-control" type="text" placeholder="0,00" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger" data-action="invoice-form#removeItem">−</button></td>
        </tr>`;
        const row = wrapper.firstElementChild;
        this.itemsTarget.dataset.index = String(index + 1);
        this.itemsTarget.append(row);
        row.querySelector(`#invoice_invoiceItemCommands_${index}_code`).value = index + 1;
        row.querySelector(`#invoice_invoiceItemCommands_${index}_quantity`).value = 1;
        row.querySelector(`#invoice_invoiceItemCommands_${index}_unit`).value = 'x';
        row.querySelector(`#invoice_invoiceItemCommands_${index}_discount`).value = 0;
        this.recalculate();
    }

    extractField(html, id) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const field = doc.getElementById(id);
        return field ? field.outerHTML : '';
    }

    removeItem(event) {
        const rows = this.itemsTarget.querySelectorAll('tr.invoice-item');
        if (rows.length <= 1) {
            window.alert("Can't delete last item!");
            return;
        }
        event.currentTarget.closest('tr').remove();
        this.recalculate();
    }

    recalculate() {
        if (!this.hasItemsTarget) {
            return;
        }
        let subtotal = 0;
        this.itemsTarget.querySelectorAll('tr.invoice-item').forEach((row, index) => {
            const quantity = parseAmount(row.querySelector('[id$="_quantity"]')?.value);
            const price = parseAmount(row.querySelector('[id$="_price"]')?.value);
            const discount = parseAmount(row.querySelector('[id$="_discount"]')?.value);
            const value = (quantity || 1) * price * (1 - discount / 100);
            const valueInput = row.querySelector('.valueInput input, input.valueInput');
            if (valueInput) {
                valueInput.value = `${formatPrice(value)} €`;
            }
            const code = row.querySelector('[id$="_code"]');
            if (code) {
                code.value = index + 1;
            }
            subtotal += value;
        });
        const invoiceDiscount = parseAmount(this.hasDiscountTarget ? this.discountTarget.value : 0);
        if (this.hasTotalTarget) {
            this.totalTarget.value = formatPrice(subtotal * (1 - invoiceDiscount / 100));
        }
    }
}
