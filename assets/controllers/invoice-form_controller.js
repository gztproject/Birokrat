import { Controller } from '@hotwired/stimulus';

function daysBetween(fromValue, toValue) {
    if (!fromValue || !toValue) {
        return null;
    }
    const from = new Date(`${fromValue}T00:00:00`);
    const to = new Date(`${toValue}T00:00:00`);
    return Math.round((to - from) / 86400000);
}

function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function addDays(dateValue, days) {
    const date = new Date(`${dateValue}T00:00:00`);
    date.setDate(date.getDate() + Number(days || 0));
    return toIsoDate(date);
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
        if (!prototype) {
            return;
        }
        const index = Number(this.itemsTarget.dataset.index || this.itemsTarget.querySelectorAll('tr.invoice-item').length);
        const html = prototype.replaceAll('__name__', String(index));
        const fields = this.extractItemFields(html, index);
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = `<tr class="invoice-item invoice-item-tr-${index}">
            <td class="codeInput">${fields.code}</td>
            <td class="nameInput">${fields.name}</td>
            <td class="quantityInput">${fields.quantity}</td>
            <td class="unitInput">${fields.unit}</td>
            <td class="priceInput">${fields.price}</td>
            <td class="discountInput">${fields.discount}</td>
            <td class="valueInput"><input id="iiValue_${index}" class="form-control valueInput" type="text" placeholder="0,00" readonly></td>
            <td class="removeBtn"><button type="button" class="btn btn-sm btn-danger" data-action="invoice-form#removeItem"><i class="fa fa-minus" aria-hidden="true"></i></button></td>
        </tr>`;
        const row = wrapper.firstElementChild;
        this.itemsTarget.dataset.index = String(index + 1);
        this.itemsTarget.append(row);
        const code = row.querySelector('[id$="_code"]');
        const quantity = row.querySelector('[id$="_quantity"]');
        const unit = row.querySelector('[id$="_unit"]');
        const discount = row.querySelector('[id$="_discount"]');
        if (code) {
            code.value = String(index + 1);
        }
        if (quantity && !quantity.value) {
            quantity.value = '1';
        }
        if (unit && !unit.value) {
            unit.value = 'x';
        }
        if (discount && !discount.value) {
            discount.value = '0';
        }
        this.recalculate();
    }

    extractItemFields(html, index) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const pick = (suffix) => {
            const field = doc.getElementById(`invoice_invoiceItemCommands_${index}_${suffix}`)
                || doc.querySelector(`[name$="[${index}][${suffix}]"]`);
            if (!field) {
                return '';
            }
            field.classList.add('form-control');
            return field.outerHTML;
        };
        return {
            code: pick('code'),
            name: pick('name'),
            quantity: pick('quantity'),
            unit: pick('unit'),
            price: pick('price'),
            discount: pick('discount'),
        };
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
